<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/github_client.php';
require_once __DIR__ . '/../lib/api_keys.php';
require_once __DIR__ . '/../lib/llm_client.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

require_login_api();
require_once __DIR__ . '/../lib/csrf.php';
require_csrf();

$userId = current_user_id();
$pdo = db();

$input = json_decode(file_get_contents('php://input'), true);
$convId = (int)($input['conversation_id'] ?? 0);
$message = trim($input['message'] ?? '');
$references = $input['references'] ?? [];

if ($convId <= 0 || $message === '') {
    echo "data: " . json_encode(['error' => 'Missing conversation_id or message']) . "\n\n";
    ob_flush(); flush();
    exit;
}

// Verify conversation ownership
$stmt = $pdo->prepare('SELECT id, active_skill FROM conversations WHERE id = ? AND user_id = ?');
$stmt->execute([$convId, $userId]);
$conv = $stmt->fetch();

if (!$conv) {
    echo "data: " . json_encode(['error' => 'Conversation not found']) . "\n\n";
    ob_flush(); flush();
    exit;
}

/* ── Detect skill command from message ── */
$skillName = $conv['active_skill'];
preg_match('/^\/([a-zA-Z0-9_\-]+)/', $message, $skillMatch);
if (!empty($skillMatch[1])) {
    $detectedSkill = $skillMatch[1];
    if ($detectedSkill !== $skillName) {
        $skillName = $detectedSkill;
        // Persist to conversation for future turns
        $stmtUpd = $pdo->prepare('UPDATE conversations SET active_skill = ? WHERE id = ?');
        $stmtUpd->execute([$skillName, $convId]);
    }
}

/* ── Save user message ── */
$stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
$stmt->execute([$convId, 'user', $message]);

/* ── Auto-title new conversations from the first message (like claude.ai) ── */
$stmt = $pdo->prepare('SELECT title, (SELECT COUNT(*) FROM messages WHERE conversation_id = ?) AS msg_count FROM conversations WHERE id = ?');
$stmt->execute([$convId, $convId]);
$titleRow = $stmt->fetch();
$newTitle = null;
if ($titleRow && (int)$titleRow['msg_count'] <= 1 && in_array($titleRow['title'], ['New Chat', 'New Conversation', ''], true)) {
    $newTitle = preg_replace('/^\/[a-zA-Z0-9_\-]+\s*/', '', $message); // drop leading /command
    $newTitle = trim(preg_replace('/\s+/', ' ', $newTitle)) ?: ($skillName ? '/' . $skillName : 'New Chat');
    if (mb_strlen($newTitle) > 48) {
        $newTitle = mb_substr($newTitle, 0, 48) . '…';
    }
    $stmt = $pdo->prepare('UPDATE conversations SET title = ? WHERE id = ?');
    $stmt->execute([$newTitle, $convId]);
}

/* ── Build system prompt: strict document mode when a skill is active, general assistant otherwise ── */
$skillContent = $skillName ? github_fetch_skill_content($skillName) : null;
$skillMode = $skillName && $skillContent;

if ($skillMode) {
    $systemPrompt = "You are a senior business analyst producing a formal deliverable document.\n"
        . "The skill below is your METHOD and OUTPUT SPECIFICATION — never copy or summarize the skill text itself. "
        . "Apply it to the user's request to produce the deliverable it describes, about the user's subject matter."
        . "\n\n--- ACTIVATED SKILL: /$skillName ---\n" . substr($skillContent, 0, 24000) . "\n--- END SKILL ---"
        . "\n\nFINAL INSTRUCTIONS — follow all of these exactly:\n"
        . "1. The FIRST line of your reply must be exactly: SAVE_AS: {short-kebab-case-name}.{ext} — nothing before it. Use .md unless the user asked for another format (.html, .yaml, .json, .csv, .xml, .txt).\n"
        . "2. After that line, output the complete deliverable document in pure markdown, using the output structure, tables and formats the skill defines, filled with content about the USER'S request (not the skill's own text or examples).\n"
        . "3. Where the skill defines workflow steps or question checklists, work through them applied to the user's input.\n"
        . "4. If information is missing, keep the section and mark gaps explicitly (e.g. 'TBD — needs stakeholder input: owner of X') instead of inventing specifics.\n"
        . "5. No greetings, no preamble, no closing remarks — the document only.";
} else {
    $systemPrompt = 'You are SkillApp, a capable, friendly AI assistant with business-analysis expertise. '
        . 'Respond conversationally and helpfully, like a knowledgeable colleague. Use markdown formatting (headings, lists, tables, code blocks) when it improves clarity, and keep answers direct and well-organized. '
        . 'Only produce a formal deliverable document when the user explicitly asks for one. '
        . 'FILE SAVING RULE: only when the user explicitly asks you to create, save, generate or export a FILE, make the FIRST line of your reply exactly "SAVE_AS: {short-kebab-case-name}.{ext}" (choose the extension they asked for: .html, .yaml, .json, .csv, .xml, .txt or .md) followed by the file content. For any other message — questions, chit-chat, explanations, code help — never emit SAVE_AS and never create files. '
        . 'The user can activate specialized BA skills by typing /commands (e.g. /user-story-standards); if their request clearly matches a skill workflow, you may mention that the skill exists.';
}

// File Reference Context
if (!empty($references)) {
    $attachmentTexts = [];
    foreach ($references as $refName) {
        // Check uploads
        $stmt = $pdo->prepare('SELECT filename, extracted_text as text_content FROM uploads WHERE filename = ? AND conversation_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$refName, $convId, $userId]);
        $row = $stmt->fetch();
        
        // Check artifacts if not found
        if (!$row) {
            $stmt = $pdo->prepare('SELECT filename, content_blob as text_content FROM artifacts WHERE filename = ? AND conversation_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$refName, $convId, $userId]);
            $row = $stmt->fetch();
        }
        
        if ($row && $row['text_content']) {
            $attachmentTexts[] = "--- File: {$row['filename']} ---\n{$row['text_content']}\n--- End File ---";
        }
    }
    if ($attachmentTexts) {
        $systemPrompt .= "\n\nThe user has referenced the following documents in their message:\n\n" . implode("\n\n", $attachmentTexts) . "\n\nYou must read and analyze these documents as part of your context.";
    }
}

/* ── Build message history ── */
$stmt = $pdo->prepare('SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id ASC');
$stmt->execute([$convId]);
$history = $stmt->fetchAll();

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $h) {
    if ($h['role'] === 'system') continue;
    $messages[] = ['role' => $h['role'], 'content' => $h['content']];
}

/* ── Resolve API key: each user must bring their own (config constants only as local-dev fallback) ── */
$activeKey = api_key_get_active($userId);
if ($activeKey) {
    $provider = $activeKey['provider'] ?? 'openai-compatible';
    $apiKey = $activeKey['api_key'];
    $baseUrl = rtrim($activeKey['base_url'], '/');
    $model = $activeKey['model'];
} elseif (defined('DEFAULT_API_KEY') && DEFAULT_API_KEY !== '') {
    // Generic server-wide fallback: works with any provider, not just one vendor
    $provider = defined('DEFAULT_PROVIDER') ? DEFAULT_PROVIDER : 'openai-compatible';
    $apiKey = DEFAULT_API_KEY;
    $baseUrl = rtrim(preg_replace('#/(chat/completions|messages)/?$#', '', DEFAULT_API_URL), '/');
    $model = DEFAULT_MODEL;
} elseif (defined('GROQ_API_KEY') && GROQ_API_KEY !== '') {
    // Legacy config constants, kept for backward compatibility
    $provider = 'openai-compatible';
    $apiKey = GROQ_API_KEY;
    // llm_client appends /chat/completions itself; strip it if present in the config URL
    $baseUrl = rtrim(preg_replace('#/chat/completions/?$#', '', GROQ_API_URL), '/');
    $model = GROQ_MODEL;
} else {
    echo "data: " . json_encode(['error' => 'No API key configured. Open Settings (gear icon) and add your own API key to start chatting.']) . "\n\n";
    ob_flush(); flush();
    exit;
}

/* ── Stream ── */
$fullReply = llm_stream_chat($messages, function ($token) {
    echo "data: " . json_encode(['token' => $token]) . "\n\n";
    ob_flush(); flush();
}, $provider, $apiKey, $baseUrl, $model);

/* ── Save assistant message ── */
$stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
$stmt->execute([$convId, 'assistant', $fullReply]);
$msgId = (int)$pdo->lastInsertId();

/* ── Load skill folder mapping ── */
$skillFolders = require __DIR__ . '/../config/skill_folders.php';
$phaseFolder = $skillName && isset($skillFolders[$skillName]) ? $skillFolders[$skillName] : null;

/* ── Check for SAVE_AS: artifact directive ── */
$saved = [];
if (preg_match('/^SAVE_AS:\s*([^\r\n]+)\s*\r?\n([\s\S]*)$/m', $fullReply, $m)) {
    $relPath = trim($m[1]);
    $content = trim($m[2]);

    if ($content !== '' && $relPath !== '') {
        // Prepend phase folder if not already present and skill has a mapping
        if ($phaseFolder && strpos($relPath, '/') === false && strpos($relPath, '\\') === false) {
            $relPath = $phaseFolder . '/' . $relPath;
        }
        // Security Fix: Prevent Path Traversal by stripping '..' and forcing valid characters
        $safePath = str_replace(['..', "\0"], '', $relPath);
        $safePath = preg_replace('/[^a-zA-Z0-9_\-\.\/ ]/', '', $safePath);
        $safePath = trim($safePath, '/\\');

        $filename = $safePath;
        if ($filename === '') {
            $filename = 'artifact_' . date('Ymd_His') . '.md';
        }
        $fileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($fileType, ['md', 'pdf', 'docx', 'txt', 'html', 'yaml', 'yml', 'json', 'csv', 'xml'], true)) {
            if ($fileType === 'pdf') {
                require_once __DIR__ . '/generate/to_pdf.php';
                $blob = generate_pdf($content);
            } elseif ($fileType === 'docx') {
                require_once __DIR__ . '/generate/to_docx.php';
                $blob = generate_docx($content);
            } else {
                $blob = $content;
            }

            $stmt = $pdo->prepare('INSERT INTO artifacts (conversation_id, user_id, filename, file_type, content_blob, size_bytes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$convId, $userId, $filename, $fileType, $blob, strlen($blob)]);
            $saved[] = ['id' => (int)$pdo->lastInsertId(), 'filename' => $filename, 'type' => $fileType];
        }
    }
}

/* ── Implicit save only in skill mode: normal conversation stays chat ── */
$isError = str_starts_with($fullReply, 'Error: API returned HTTP');
if ($skillMode && !$isError && empty($saved) && strlen($fullReply) > 30) {
    $baseName = ($skillName ? $skillName : 'Response') . '_' . date('Ymd_His') . '.md';
    $fname = $phaseFolder ? $phaseFolder . '/' . $baseName : $baseName;
    $blob = $fullReply;

    $stmt = $pdo->prepare('INSERT INTO artifacts (conversation_id, user_id, filename, file_type, content_blob, size_bytes) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$convId, $userId, $fname, 'md', $blob, strlen($blob)]);
    $saved[] = ['id' => (int)$pdo->lastInsertId(), 'filename' => $fname, 'type' => 'md'];
}

// Modify the message content in DB to just be the acknowledgment, so on refresh it shows the same.
if (!empty($saved)) {
    $ackContent = "✅ Content generated as artifact: **" . $saved[0]['filename'] . "**";
    $stmt = $pdo->prepare('UPDATE messages SET content = ? WHERE id = ?');
    $stmt->execute([$ackContent, $msgId]);
}

echo "data: " . json_encode(['done' => true, 'message_id' => $msgId, 'saved' => $saved, 'title' => $newTitle, 'error' => $isError ? $fullReply : null]) . "\n\n";
ob_flush(); flush();

<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/github_client.php';
require_once __DIR__ . '/../lib/api_keys.php';
require_once __DIR__ . '/../lib/groq_client.php';

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

/* ── Build system prompt ── */
$systemPrompt = 'You are a business analysis assistant that produces deliverable documents. '
    . 'When the user types a /command, use the associated skill content (if provided below) to produce a complete markdown document. '
    . 'Your response should be well-structured with headings, lists, and tables as appropriate. '
    . 'Start directly with the document content — no greetings or explanations.';

if ($skillName) {
    $skillContent = github_fetch_skill_content($skillName);
    if ($skillContent) {
        $systemPrompt .= "\n\n--- ACTIVATED SKILL: /$skillName ---\n" . substr($skillContent, 0, 12000) . "\n--- END SKILL ---";
        $systemPrompt .= "\n\nIMPORTANT: Output a deliverable markdown document. "
            . "To save as an artifact, start with: SAVE_AS: {phase_folder}/{filename}.md";
    }
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

/* ── Resolve active API key ── */
$activeKey = api_key_get_active();
if (!$activeKey) {
    // Fall back to config constants
    $apiKey = GROQ_API_KEY;
    $baseUrl = rtrim(GROQ_API_URL, '/');
    $model = GROQ_MODEL;
} else {
    $apiKey = $activeKey['api_key'];
    $baseUrl = rtrim($activeKey['base_url'], '/');
    $model = $activeKey['model'];
}

/* ── Stream ── */
$fullReply = groq_stream_chat($messages, function ($token) {
    echo "data: " . json_encode(['token' => $token]) . "\n\n";
    ob_flush(); flush();
}, $apiKey, $baseUrl, $model);

/* ── Save assistant message ── */
$stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
$stmt->execute([$convId, 'assistant', $fullReply]);
$msgId = (int)$pdo->lastInsertId();

/* ── Check for SAVE_AS: artifact directive ── */
$saved = [];
if (preg_match('/^SAVE_AS:\s*([^\r\n]+)\s*\r?\n([\s\S]*)$/m', $fullReply, $m)) {
    $relPath = trim($m[1]);
    $content = trim($m[2]);

    if ($content !== '' && $relPath !== '') {
        $filename = basename($relPath);
        $fileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($fileType, ['md', 'pdf', 'docx', 'txt'], true)) {
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

/* ── Also save implicitly for all responses ── */
$isError = str_starts_with($fullReply, 'Error: API returned HTTP');
if (!$isError && empty($saved) && strlen($fullReply) > 30) {
    $fname = ($skillName ? $skillName : 'Response') . '_' . date('Ymd_His') . '.md';
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

echo "data: " . json_encode(['done' => true, 'message_id' => $msgId, 'saved' => $saved, 'error' => $isError ? $fullReply : null]) . "\n\n";
ob_flush(); flush();

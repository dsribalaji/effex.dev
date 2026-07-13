<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');
require_login_api();

$userId = current_user_id();
require_once __DIR__ . '/../lib/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}
$pdo = db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    /* ── List artifacts for a conversation ── */
    case 'list':
        $convId = (int)($_GET['conversation_id'] ?? 0);
        if ($convId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing conversation_id']);
            exit;
        }
        require_conversation_owner_api($convId, $userId);

        $stmt = $pdo->prepare('SELECT id, filename, file_type, size_bytes, created_at FROM artifacts WHERE conversation_id = ? AND user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$convId, $userId]);
        echo json_encode($stmt->fetchAll());
        break;

    /* ── Preview artifact (rendered HTML) ── */
    case 'preview':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { not_found(); }

        $stmt = $pdo->prepare('SELECT filename, file_type, content_blob FROM artifacts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) { not_found(); }

        $content = $row['content_blob'];
        $type = $row['file_type'];
        $name = $row['filename'];

        if ($type === 'md') {
            require_once __DIR__ . '/../lib/markdown.php';
            $html = markdown_to_html($content);
            echo json_encode(['type' => 'html', 'html' => $html, 'name' => $name]);
        } elseif ($type === 'pdf') {
            echo json_encode(['type' => 'pdf', 'url' => 'api/artifacts.php?action=download&id=' . $id, 'name' => $name]);
        } elseif ($type === 'docx') {
            $text = strip_tags($content);
            echo json_encode(['type' => 'text', 'content' => $text, 'name' => $name]);
        } else {
            echo json_encode(['type' => 'text', 'content' => $content, 'name' => $name]);
        }
        break;

    /* ── Download artifact BLOB ── */
    case 'download':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { not_found(); }

        $stmt = $pdo->prepare('SELECT filename, file_type, content_blob, size_bytes FROM artifacts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) { not_found(); }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($row['filename']) . '"');
        header('Content-Length: ' . $row['size_bytes']);
        echo $row['content_blob'];
        exit;

    /* ── Create artifact ── */
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); }

        $input = json_decode(file_get_contents('php://input'), true);
        $convId = (int)($input['conversation_id'] ?? 0);
        require_conversation_owner_api($convId, $userId);
        $filename = trim($input['filename'] ?? '');
        $content = $input['content'] ?? '';
        $fileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($fileType, ['md', 'pdf', 'docx', 'txt'], true)) {
            echo json_encode(['error' => 'Unsupported file type: ' . $fileType]);
            exit;
        }

        // If PDF or DOCX, generate from markdown content
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

        echo json_encode([
            'id' => (int)$pdo->lastInsertId(),
            'filename' => $filename,
            'type' => $fileType,
            'size_bytes' => strlen($blob),
        ]);
        break;

    /* ── Delete artifact ── */
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM artifacts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}

function not_found(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Artifact not found']);
    exit;
}

function method_not_allowed(): void
{
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

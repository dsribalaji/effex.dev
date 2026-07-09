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
    case 'list':
        $convId = (int)($_GET['conversation_id'] ?? 0);
        if ($convId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing conversation_id']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, filename, mime_type, size_bytes, created_at FROM uploads WHERE conversation_id = ? AND user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$convId, $userId]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'preview':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { not_found(); }

        $stmt = $pdo->prepare('SELECT filename, mime_type, extracted_text FROM uploads WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) { not_found(); }

        $mime = $row['mime_type'];
        $url = 'api/uploads.php?action=download&id=' . $id;
        
        if (str_contains($mime, 'pdf')) {
            echo json_encode(['type' => 'pdf', 'url' => $url, 'name' => $row['filename']]);
        } elseif (str_contains($mime, 'image')) {
            echo json_encode(['type' => 'image', 'url' => $url, 'name' => $row['filename']]);
        } else {
            $content = $row['extracted_text'];
            if ($content === null) {
                // If we couldn't extract text, we don't send the raw binary blob in JSON because it will break json_encode.
                // We just send a message or fall back to an iframe/download.
                echo json_encode(['type' => 'text', 'content' => "Preview not available for this file type. Please download to view.", 'name' => $row['filename']]);
            } else {
                echo json_encode(['type' => 'text', 'content' => $content, 'name' => $row['filename']]);
            }
        }
        break;

    case 'download':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { not_found(); }

        $stmt = $pdo->prepare('SELECT filename, mime_type, content_blob, size_bytes FROM uploads WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) { not_found(); }

        header('Content-Type: ' . $row['mime_type']);
        header('Content-Disposition: attachment; filename="' . rawurlencode($row['filename']) . '"');
        header('Content-Length: ' . $row['size_bytes']);
        echo $row['content_blob'];
        exit;

    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); }

        $convId = (int)($_POST['conversation_id'] ?? 0);
        if ($convId <= 0) {
            echo json_encode(['error' => 'Missing conversation_id']);
            exit;
        }

        if (empty($_FILES['file'])) {
            echo json_encode(['error' => 'No file uploaded']);
            exit;
        }

        $file = $_FILES['file'];
        $filename = basename($file['name']);
        $tmpPath = $file['tmp_name'];
        $size = $file['size'];

        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            echo json_encode(['error' => 'File empty or exceeds 10 MB limit']);
            exit;
        }

        $blob = file_get_contents($tmpPath);
        $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';

        // Extract text for LLM context
        $extractedText = extract_text($blob, $filename, $mime);

        $stmt = $pdo->prepare('INSERT INTO uploads (conversation_id, user_id, filename, mime_type, content_blob, extracted_text, size_bytes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$convId, $userId, $filename, $mime, $blob, $extractedText, $size]);

        echo json_encode([
            'id' => (int)$pdo->lastInsertId(),
            'filename' => $filename,
            'size_bytes' => $size,
            'extracted' => $extractedText !== null,
        ]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM uploads WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}

function not_found(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

function method_not_allowed(): void
{
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

function extract_text(string $blob, string $filename, string $mime): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, ['txt', 'md', 'markdown', 'csv', 'json', 'xml', 'html', 'htm'], true)) {
        return $blob;
    }

    if ($ext === 'pdf') {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmp, $blob);
        $text = shell_exec('pdftotext ' . escapeshellarg($tmp) . ' - 2>/dev/null');
        unlink($tmp);
        return $text !== null && $text !== '' ? $text : null;
    }

    if (in_array($ext, ['doc', 'docx'], true)) {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tmp, $blob);
        $text = null;
        $zip = new ZipArchive();
        if ($zip->open($tmp) === true) {
            $content = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($content) {
                $text = strip_tags($content);
            }
        }
        unlink($tmp);
        return $text;
    }

    return null;
}

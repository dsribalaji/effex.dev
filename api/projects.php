<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/projects.php';
require_once __DIR__ . '/../lib/csrf.php';

require_login_api();
$userId = current_user_id();
$pdo = db();

$action = $_GET['action'] ?? '';

/* ── Export project artifacts as zip (before JSON header) ── */
if ($action === 'export') {
    $projectId = (int)($_GET['id'] ?? 0);
    header('Content-Type: application/json'); // for the error paths
    require_project_owner_api($projectId, $userId);

    $stmt = $pdo->prepare(
        'SELECT a.filename, a.content_blob FROM artifacts a
         JOIN conversations c ON c.id = a.conversation_id
         WHERE c.project_id = ? AND a.user_id = ?
         ORDER BY a.created_at ASC'
    );
    $stmt->execute([$projectId, $userId]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        echo json_encode(['error' => 'No artifacts in this project yet']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT name FROM projects WHERE id = ?');
    $stmt->execute([$projectId]);
    $projectName = $stmt->fetchColumn() ?: 'project';

    $tmp = tempnam(sys_get_temp_dir(), 'skillapp_zip_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);

    $seen = [];
    foreach ($rows as $r) {
        // filename column already carries folder structure (e.g. 01_discovery/x.md)
        $path = ltrim($r['filename'], '/');
        if (isset($seen[$path])) {
            $seen[$path]++;
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $base = $ext !== '' ? substr($path, 0, -strlen($ext) - 1) : $path;
            $path = $base . ' (' . $seen[$path] . ')' . ($ext !== '' ? '.' . $ext : '');
        } else {
            $seen[$path] = 1;
        }
        $zip->addFromString($path, $r['content_blob']);
    }
    $zip->close();

    $safeName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $projectName) ?: 'project';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $safeName . '-artifacts.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

header('Content-Type: application/json');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        project_ensure_default($userId);
        $stmt = $pdo->prepare('SELECT id, name, created_at FROM projects WHERE user_id = ? ORDER BY created_at ASC');
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        require_csrf();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = trim($input['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Project name required (max 100 chars)']);
            exit;
        }
        projects_ensure_schema();
        $stmt = $pdo->prepare('INSERT INTO projects (user_id, name) VALUES (?, ?)');
        $stmt->execute([$userId, $name]);
        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'name' => $name]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');
require_login_api();

$userId = current_user_id();
require_once __DIR__ . '/../lib/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_csrf();
}
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $projectId = (int)($_GET['project_id'] ?? 0);
        if ($projectId > 0) {
            require_once __DIR__ . '/../lib/projects.php';
            require_project_owner_api($projectId, $userId);
            $stmt = $pdo->prepare('SELECT id, title, active_skill, created_at FROM conversations WHERE user_id = ? AND project_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId, $projectId]);
        } else {
            $stmt = $pdo->prepare('SELECT id, title, active_skill, created_at FROM conversations WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId]);
        }
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $title = trim($input['title'] ?? 'New Chat');
        $activeSkill = trim($input['active_skill'] ?? '');
        $projectId = (int)($input['project_id'] ?? 0);
        if ($projectId > 0) {
            require_once __DIR__ . '/../lib/projects.php';
            require_project_owner_api($projectId, $userId);
        } else {
            require_once __DIR__ . '/../lib/projects.php';
            $projectId = project_ensure_default($userId);
        }

        $stmt = $pdo->prepare('INSERT INTO conversations (user_id, title, active_skill, project_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $activeSkill ?: null, $projectId]);

        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'title' => $title]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $convId = (int)($input['id'] ?? 0);

        if ($convId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }

        $fields = [];
        $params = [];

        if (isset($input['title']) && trim($input['title']) !== '') {
            $fields[] = 'title = ?';
            $params[] = trim($input['title']);
        }
        if (isset($input['active_skill'])) {
            $fields[] = 'active_skill = ?';
            $params[] = trim($input['active_skill']) ?: null;
        }

        if (empty($fields)) {
            echo json_encode(['success' => true]);
            break;
        }

        $params[] = $convId;
        $params[] = $userId;
        $stmt = $pdo->prepare('UPDATE conversations SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
        $stmt->execute($params);

        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $convId = (int)($_GET['id'] ?? 0);

        if ($convId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM conversations WHERE id = ? AND user_id = ?');
        $stmt->execute([$convId, $userId]);

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

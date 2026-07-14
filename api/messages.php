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
        $convId = (int)($_GET['conversation_id'] ?? 0);

        if ($convId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing conversation_id']);
            exit;
        }
        require_conversation_owner_api($convId, $userId);

        $stmt = $pdo->prepare('SELECT id, role, content, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC');
        $stmt->execute([$convId]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $convId = (int)($input['conversation_id'] ?? 0);
        $role = $input['role'] ?? 'user';
        $content = $input['content'] ?? '';

        if ($convId <= 0 || $content === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing conversation_id or content']);
            exit;
        }
        require_conversation_owner_api($convId, $userId);

        if (!in_array($role, ['user', 'assistant', 'system'], true)) {
            $role = 'user';
        }

        $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
        $stmt->execute([$convId, $role, $content]);

        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'role' => $role]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

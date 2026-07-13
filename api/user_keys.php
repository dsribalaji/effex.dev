<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api_keys.php';
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json');
require_login_api();

$userId = current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf();
}

switch ($method) {
    case 'GET':
        echo json_encode(api_key_get_all($userId));
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $label = trim($input['label'] ?? '');
        $provider = trim($input['provider'] ?? 'openai-compatible');
        $baseUrl = rtrim(trim($input['base_url'] ?? ''), '/');
        $model = trim($input['model'] ?? '');
        $key = $input['api_key'] ?? '';

        if ($label === '' || $baseUrl === '' || $model === '' || $key === '') {
            http_response_code(400);
            echo json_encode(['error' => 'All fields required']);
            exit;
        }

        $id = api_key_create($label, $provider, $baseUrl, $model, $key, $userId);

        // If this is the user's first key, auto-activate it
        if (count(api_key_get_all($userId)) === 1) {
            api_key_set_active($id, $userId);
        }

        echo json_encode(['id' => $id, 'success' => true]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'activate') {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid id']);
                exit;
            }
            $ok = api_key_set_active($id, $userId);
            echo json_encode(['success' => $ok]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
        }
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }
        $ok = api_key_delete($id, $userId);
        echo json_encode(['success' => $ok]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json');
require_login_api();
require_admin();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    require_csrf();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    /* ── List all users with their roles ── */
    case 'users':
        if ($method !== 'GET') { http_response_code(405); exit; }
        $stmt = $pdo->query('
            SELECT u.id, u.username, u.email, u.is_admin,
                   u.role_id, COALESCE(r.name, "(none)") AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            ORDER BY u.id
        ');
        echo json_encode($stmt->fetchAll());
        break;

    /* ── List all roles ── */
    case 'roles':
        if ($method !== 'GET') { http_response_code(405); exit; }
        echo json_encode($pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll());
        break;

    /* ── Update a user's role ── */
    case 'update_role':
        if ($method !== 'POST') { http_response_code(405); exit; }
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        $roleId = isset($input['role_id']) ? (int)$input['role_id'] : null;
        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user_id']);
            exit;
        }
        if ($roleId === null || $roleId <= 0) {
            $stmt = $pdo->prepare('UPDATE users SET role_id = NULL WHERE id = ?');
            $stmt->execute([$userId]);
        } else {
            // Verify role exists
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE id = ?');
            $roleStmt->execute([$roleId]);
            if (!$roleStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Role not found']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?');
            $stmt->execute([$roleId, $userId]);
        }
        log_role_change($userId, current_user_id(), $roleId);
        echo json_encode(['success' => true]);
        break;

    /* ── Toggle admin status ── */
    case 'toggle_admin':
        if ($method !== 'POST') { http_response_code(405); exit; }
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user_id']);
            exit;
        }
        // Cannot demote yourself
        if ($userId === current_user_id()) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot change your own admin status']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE users SET is_admin = NOT is_admin WHERE id = ?');
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
        break;

    /* ── Add a new role ── */
    case 'add_role':
        if ($method !== 'POST') { http_response_code(405); exit; }
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $description = trim($input['description'] ?? '');
        $githubPath = trim($input['github_path'] ?? '.copilot/skills');
        if ($name === '' || $slug === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name and slug are required']);
            exit;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO roles (name, slug, description, github_path) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $slug, $description, $githubPath]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                http_response_code(409);
                echo json_encode(['error' => 'Role name or slug already exists']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        break;

    /* ── Delete a role ── */
    case 'delete_role':
        if ($method !== 'POST') { http_response_code(405); exit; }
        $input = json_decode(file_get_contents('php://input'), true);
        $roleId = (int)($input['id'] ?? 0);
        if ($roleId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role id']);
            exit;
        }
        // Set users with this role to null first
        $pdo->prepare('UPDATE users SET role_id = NULL WHERE role_id = ?')->execute([$roleId]);
        $pdo->prepare('DELETE FROM roles WHERE id = ?')->execute([$roleId]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

function log_role_change(int $userId, int $changedBy, ?int $newRoleId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO role_change_log (user_id, changed_by, new_role_id) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $changedBy, $newRoleId]);
}

<?php
require_once __DIR__ . '/db.php';

function login_user(string $username, string $password): ?int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password_hash, role_id, is_admin FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['username'] = $username;
        _session_set_role($row);
        return (int)$row['id'];
    }
    return null;
}

function create_user(string $username, string $email, string $password, int $roleId = null): ?int
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return null;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($roleId !== null) {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, role_id, password_hash) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $email, $roleId, $hash]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $hash]);
    }
    return (int)$pdo->lastInsertId();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Verify the user still exists in the database
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, role_id, is_admin FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Refresh role info in session every page load
    _session_set_role($row);
}

function require_login_api(): void
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthenticated']);
        exit;
    }
}

function current_user_id(): int
{
    return (int)$_SESSION['user_id'];
}

function user_owns_conversation(int $convId, int $userId): bool
{
    $stmt = db()->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$convId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * API guard: 403 + exit unless the conversation belongs to the user.
 */
function require_conversation_owner_api(int $convId, int $userId): void
{
    if ($convId <= 0 || !user_owns_conversation($convId, $userId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Conversation not found or access denied']);
        exit;
    }
}

function current_username(): string
{
    return $_SESSION['username'] ?? '';
}

/* ── Role & Admin helpers ── */

function _session_set_role(array $row): void
{
    if (isset($row['role_id'])) {
        $_SESSION['role_id'] = (int)$row['role_id'];
        // Fetch github_path from the roles table
        $stmt = db()->prepare('SELECT github_path, slug FROM roles WHERE id = ?');
        $stmt->execute([$row['role_id']]);
        $role = $stmt->fetch();
        if ($role) {
            $_SESSION['github_path'] = $role['github_path'];
            $_SESSION['role_slug'] = $role['slug'];
        }
    }
    $_SESSION['is_admin'] = !empty($row['is_admin']);
}

/**
 * Refresh the session role data for the current user (call after admin changes a user's role).
 */
function session_refresh_role(): void
{
    if (empty($_SESSION['user_id'])) return;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, role_id, is_admin FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row) {
        _session_set_role($row);
    }
}

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: admin access required']);
        exit;
    }
}

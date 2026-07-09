<?php
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_verify(): bool
{
    $token = $_SESSION['_csrf_token'] ?? '';
    if ($token === '') return false;

    // Check JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['_csrf_token']) && hash_equals($token, $input['_csrf_token'])) {
        return true;
    }

    // Check POST field
    if (isset($_POST['_csrf_token']) && hash_equals($token, $_POST['_csrf_token'])) {
        return true;
    }

    // Check header
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($header !== '' && hash_equals($token, $header)) {
        return true;
    }

    return false;
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }
}

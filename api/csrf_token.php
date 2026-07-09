<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

require_once __DIR__ . '/../lib/csrf.php';
echo json_encode(['token' => csrf_token()]);

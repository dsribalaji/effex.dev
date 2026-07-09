<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';

header('Content-Type: application/json');

if (!empty($_SESSION['user_id'])) {
    echo json_encode(['authenticated' => true, 'username' => $_SESSION['username'] ?? '']);
} else {
    echo json_encode(['authenticated' => false]);
}

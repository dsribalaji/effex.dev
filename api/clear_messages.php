<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');
require_login_api();

$userId = current_user_id();
require_once __DIR__ . '/../lib/csrf.php';
require_csrf();

$pdo = db();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$convId = (int)($input['conversation_id'] ?? 0);

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation id']);
    exit;
}

// Verify conversation belongs to user
$stmt = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
$stmt->execute([$convId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Conversation not found or access denied']);
    exit;
}

// Delete all messages in conversation
$stmt = $pdo->prepare('DELETE FROM messages WHERE conversation_id = ?');
$stmt->execute([$convId]);

// Delete all artifacts in conversation
$stmt = $pdo->prepare('DELETE FROM artifacts WHERE conversation_id = ?');
$stmt->execute([$convId]);

echo json_encode(['success' => true]);
<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github_client.php';

header('Content-Type: application/json');
require_login_api();

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing slug']);
    exit;
}

$content = github_fetch_skill_content($slug);

if ($content === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Skill not found']);
    exit;
}

echo json_encode(['slug' => $slug, 'content' => $content]);

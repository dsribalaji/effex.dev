<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github_client.php';

header('Content-Type: application/json');
require_login_api();

$skills = github_list_skills();

$result = [];
foreach ($skills as $s) {
    $result[] = [
        'id' => $s['slug'],
        'name' => $s['name'],
        'description' => $s['description'],
    ];
}

echo json_encode($result);

<?php
require_once __DIR__ . '/db.php';

/**
 * Idempotent schema: projects table + conversations.project_id.
 */
function projects_ensure_schema(): void
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    try {
        $pdo->exec('ALTER TABLE conversations ADD COLUMN project_id INT NULL');
    } catch (PDOException $e) {
        // column already exists
    }
}

/**
 * Get-or-create the user's "Default" project and adopt orphan conversations.
 */
function project_ensure_default(int $userId): int
{
    projects_ensure_schema();
    $pdo = db();

    $stmt = $pdo->prepare("SELECT id FROM projects WHERE user_id = ? AND name = 'Default'");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if ($row) {
        $projectId = (int)$row['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, name) VALUES (?, 'Default')");
        $stmt->execute([$userId]);
        $projectId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('UPDATE conversations SET project_id = ? WHERE user_id = ? AND project_id IS NULL');
    $stmt->execute([$projectId, $userId]);

    return $projectId;
}

function user_owns_project(int $projectId, int $userId): bool
{
    $stmt = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * API guard: 403 + exit unless the project belongs to the user.
 */
function require_project_owner_api(int $projectId, int $userId): void
{
    if ($projectId <= 0 || !user_owns_project($projectId, $userId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Project not found or access denied']);
        exit;
    }
}

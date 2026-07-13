<?php
require_once __DIR__ . '/db.php';

/**
 * Get the user's own active API key. There is no shared key —
 * every user brings their own via the Settings page.
 */
function api_key_get_active(int $userId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, label, provider, base_url, model, api_key FROM api_keys WHERE is_active = 1 AND created_by = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if ($row) {
        $row['api_key'] = decrypt_api_key($row['api_key']);
        return $row;
    }

    return null;
}

function api_key_get_all(int $userId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, label, provider, base_url, model, is_active, created_at FROM api_keys WHERE created_by = ? ORDER BY is_active DESC, created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function api_key_create(string $label, string $provider, string $baseUrl, string $model, string $key, int $createdBy): ?int
{
    $pdo = db();
    $encrypted = encrypt_api_key($key);
    $stmt = $pdo->prepare('INSERT INTO api_keys (label, provider, base_url, model, api_key, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$label, $provider, $baseUrl, $model, $encrypted, $createdBy]);
    return (int)$pdo->lastInsertId();
}

/**
 * Activate a key for a user. Only that user's other keys are deactivated,
 * and the key can only be activated by its owner.
 */
function api_key_set_active(int $id, int $userId): bool
{
    $pdo = db();
    // Deactivate this user's keys only
    $stmt = $pdo->prepare('UPDATE api_keys SET is_active = 0 WHERE created_by = ?');
    $stmt->execute([$userId]);
    // Activate the chosen one, if owned by this user
    $stmt = $pdo->prepare('UPDATE api_keys SET is_active = 1 WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
}

function api_key_delete(int $id, int $userId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM api_keys WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
}

function encrypt_api_key(string $key): string
{
    // Simple reversible obfuscation — not true encryption (no key management),
    // but prevents casual exposure if the DB is dumped
    return base64_encode($key);
}

function decrypt_api_key(string $encrypted): string
{
    return base64_decode($encrypted);
}

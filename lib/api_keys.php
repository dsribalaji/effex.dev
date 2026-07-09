<?php
require_once __DIR__ . '/db.php';

function api_key_get_active(): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, label, provider, base_url, model, api_key FROM api_keys WHERE is_active = 1 LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch();

    if ($row) {
        $row['api_key'] = decrypt_api_key($row['api_key']);
        return $row;
    }

    return null;
}

function api_key_get_all(): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, label, provider, base_url, model, is_active, created_at FROM api_keys ORDER BY is_active DESC, created_at DESC');
    $stmt->execute();
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

function api_key_set_active(int $id): bool
{
    $pdo = db();
    // Deactivate all first
    $pdo->exec('UPDATE api_keys SET is_active = 0');
    // Activate the chosen one
    $stmt = $pdo->prepare('UPDATE api_keys SET is_active = 1 WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function api_key_delete(int $id): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM api_keys WHERE id = ?');
    $stmt->execute([$id]);
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

<?php
require_once __DIR__ . '/key_crypto.php';

/** Transactional migration; the default is a validation-only dry run. */
function migrate_api_keys(PDO $pdo, bool $apply = false): int
{
    api_key_encryption_secret();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id, api_key FROM api_keys';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;
        $update = $pdo->prepare('UPDATE api_keys SET api_key = ? WHERE id = ?');
        foreach ($rows as $row) {
            if (str_starts_with($row['api_key'], 'v1:')) {
                decrypt_api_key($row['api_key']); // Verify existing rows too.
                continue;
            }
            $encrypted = encrypt_api_key(decode_legacy_api_key($row['api_key']));
            ++$count;
            if ($apply) {
                $update->execute([$encrypted, $row['id']]);
            }
        }
        if ($apply) {
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
        return $count;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

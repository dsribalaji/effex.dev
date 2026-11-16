<?php
// Never expose database migration through HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/key_migration.php';
if (count($argv) > 2 || (isset($argv[1]) && !in_array($argv[1], ['--apply', '--dry-run'], true))) {
    fwrite(STDERR, "Usage: php scripts/migrate_api_keys.php [--dry-run|--apply]\n");
    exit(2);
}
$apply = ($argv[1] ?? '') === '--apply';
try {
    $count = migrate_api_keys(db(), $apply);
    echo $apply ? "Migrated $count key rows.\n" : "Dry run: $count key rows need migration; nothing changed.\n";
} catch (Throwable $e) {
    // Do not print database errors, credentials, or key material.
    fwrite(STDERR, "Migration failed; transaction rolled back. Check encryption configuration, key lengths and stored row formats.\n");
    exit(1);
}

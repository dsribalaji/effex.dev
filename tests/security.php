<?php
require_once __DIR__ . '/../lib/markdown.php';
require_once __DIR__ . '/../lib/key_crypto.php';
require_once __DIR__ . '/../lib/key_migration.php';
$count = 0;
function check(bool $ok, string $message): void {
    global $count;
    ++$count;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
}
function fails(callable $fn, string $message): void {
    try { $fn(); } catch (RuntimeException | InvalidArgumentException $e) { check(true, $message); return; }
    check(false, $message);
}

foreach ([
    '<img src=x onerror="window.marker=1">',
    '<script>window.marker=1</script>',
    '[click](javascript:alert(1))',
    '[click](data:text/html,evil)',
    '[click](jav&#x61;script:evil)',
    '[click](https://example.com/"onmouseover="evil)',
    '<svg onload=evil()>',
    "</code><img src=x onerror=evil()>"
] as $payload) {
    $html = markdown_to_html($payload);
    $dom = new DOMDocument();
    @$dom->loadHTML('<div>' . $html . '</div>');
    check($dom->getElementsByTagName('script')->length === 0, 'no script nodes');
    check($dom->getElementsByTagName('img')->length === 0, 'no injected image nodes');
    foreach ($dom->getElementsByTagName('*') as $node) {
        foreach ($node->attributes ?? [] as $attr) {
            check(!str_starts_with(strtolower($attr->name), 'on'), 'no event handlers');
            if ($attr->name === 'href') {
                check((bool)preg_match('~^(https?://|mailto:)~i', $attr->value), 'safe URL scheme');
            }
        }
    }
}
check(str_contains(markdown_to_html('# Title'), '<h1>Title</h1>'), 'heading');
check(str_contains(markdown_to_html('**bold**'), '<strong>bold</strong>'), 'bold');
check(str_contains(markdown_to_html('[site](https://example.com?a=1&b=2)'), 'href="https://example.com?a=1&amp;b=2"'), 'safe link');
check(!str_contains(markdown_to_html('`**literal**`'), '<strong>'), 'inline code protected');
check(!str_contains(markdown_to_html("```html\n<script>evil()</script>\n```"), '<script>'), 'fenced code escaped');
check(str_contains(markdown_to_html("| A | B |\n|---|---|\n| 1 | 2 |"), '<table>'), 'table');
check(str_contains(markdown_to_html("- one\n- two"), '<ul>'), 'list');
check(str_contains(markdown_to_html('- `literal`'), '<li><code>literal</code></li>'), 'inline code in list');
check(str_contains(markdown_to_html('[`label`](https://example.com)'), '<a href="https://example.com"><code>label</code></a>'), 'code link label');
check(!str_contains(markdown_to_html("\0" . '0' . "\0"), "\0"), 'no forged placeholders');

putenv('API_KEY_ENCRYPTION_KEY');
fails(fn() => encrypt_api_key('fixture'), 'missing key fails closed');
putenv('API_KEY_ENCRYPTION_KEY=bad');
fails(fn() => encrypt_api_key('fixture'), 'malformed key fails closed');
// Random local test material only. Nothing is printed or persisted.
putenv('API_KEY_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
foreach (['', 'fixture-key', str_repeat('x', 300), "unicode-ключ"] as $plain) {
    $a = encrypt_api_key($plain);
    $b = encrypt_api_key($plain);
    check($a !== $b, 'random nonces');
    check(strlen($a) <= 500, 'fits existing DB column');
    check(decrypt_api_key($a) === $plain, 'round trip');
    $payload = base64_decode(substr($a, 3));
    $payload[15] = chr(ord($payload[15]) ^ 1);
    fails(fn() => decrypt_api_key('v1:' . base64_encode($payload)), 'tampering rejected');
}
fails(fn() => encrypt_api_key(str_repeat('x', 301)), 'oversized key rejected');
fails(fn() => decrypt_api_key(base64_encode('legacy')), 'legacy runtime fails closed');
fails(fn() => decrypt_api_key('v2:unknown'), 'unknown version rejected');
fails(fn() => decrypt_api_key('v1:%%%'), 'malformed ciphertext rejected');
fails(fn() => decode_legacy_api_key('%%%'), 'bad legacy encoding rejected');
$encrypted = encrypt_api_key('fixture');
putenv('API_KEY_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
fails(fn() => decrypt_api_key($encrypted), 'wrong encryption key rejected');

// Exercise the same migration function used by the CLI, with fixture-only SQLite.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE api_keys (id INTEGER PRIMARY KEY, api_key TEXT NOT NULL)');
$insert = $pdo->prepare('INSERT INTO api_keys VALUES (?, ?)');
$insert->execute([1, base64_encode('legacy-fixture')]);
$insert->execute([2, encrypt_api_key('modern-fixture')]);
$before = $pdo->query('SELECT * FROM api_keys')->fetchAll();
check(migrate_api_keys($pdo) === 1, 'dry run finds legacy');
check($pdo->query('SELECT * FROM api_keys')->fetchAll() === $before, 'dry run makes no changes');
check(migrate_api_keys($pdo, true) === 1, 'apply migrates one row');
check(decrypt_api_key($pdo->query('SELECT api_key FROM api_keys WHERE id=1')->fetchColumn()) === 'legacy-fixture', 'migrated key usable');
check(migrate_api_keys($pdo, true) === 0, 'migration idempotent');
$pdo->exec('DELETE FROM api_keys');
$insert->execute([1, base64_encode('valid-fixture')]);
$insert->execute([2, 'not-base64!']);
$before = $pdo->query('SELECT * FROM api_keys')->fetchAll();
fails(fn() => migrate_api_keys($pdo, true), 'invalid row rejects migration');
check($pdo->query('SELECT * FROM api_keys')->fetchAll() === $before, 'failed migration rolls back all rows');
echo "$count security assertions passed.\n";

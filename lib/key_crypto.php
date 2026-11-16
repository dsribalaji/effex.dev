<?php
/** Authenticated encryption for database API keys. No database side effects. */
function api_key_encryption_secret(): string
{
    $encoded = getenv('API_KEY_ENCRYPTION_KEY');
    if ($encoded === false || $encoded === '') {
        throw new RuntimeException('API_KEY_ENCRYPTION_KEY must be configured');
    }
    $key = base64_decode($encoded, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('API_KEY_ENCRYPTION_KEY must be base64-encoded 32 bytes');
    }
    return $key;
}

function encrypt_api_key(string $key): string
{
    // Keeps the versioned ciphertext inside the existing VARCHAR(500) column.
    if (strlen($key) > 300) {
        throw new InvalidArgumentException('API keys must be at most 300 bytes');
    }
    $secret = api_key_encryption_secret();
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($key, 'aes-256-gcm', $secret,
        OPENSSL_RAW_DATA, $nonce, $tag, 'effex-api-key:v1', 16);
    if ($ciphertext === false) {
        throw new RuntimeException('Could not encrypt API key');
    }
    return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
}

function decrypt_api_key(string $encrypted): string
{
    if (!str_starts_with($encrypted, 'v1:')) {
        // No silent fallback to reversible obfuscation. Migrate before deployment.
        throw new RuntimeException('Legacy API key requires the CLI migration');
    }
    $payload = base64_decode(substr($encrypted, 3), true);
    if ($payload === false || strlen($payload) < 28 || strlen($payload) > 328) {
        throw new RuntimeException('Invalid API key ciphertext');
    }
    $plaintext = openssl_decrypt(substr($payload, 28), 'aes-256-gcm',
        api_key_encryption_secret(), OPENSSL_RAW_DATA, substr($payload, 0, 12),
        substr($payload, 12, 16), 'effex-api-key:v1');
    if ($plaintext === false) {
        throw new RuntimeException('API key authentication failed');
    }
    return $plaintext;
}

/** Strict decoder for the offline migration only, never a runtime fallback. */
function decode_legacy_api_key(string $encoded): string
{
    $plain = base64_decode($encoded, true);
    if ($plain === false || base64_encode($plain) !== $encoded) {
        throw new RuntimeException('Invalid legacy API key encoding');
    }
    return $plain;
}

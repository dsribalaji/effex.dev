# Markdown and API-key security upgrade

## Changes

- Artifact Markdown escapes all raw HTML before introducing its own formatting. Links allow only HTTP, HTTPS or mailto destinations. Code spans and fenced blocks are protected from later substitutions.
- API keys use versioned AES-256-GCM ciphertext, a random 12-byte nonce and a 16-byte authentication tag. They are no longer stored as bare base64. Missing or invalid encryption configuration and unauthenticated ciphertext fail closed.
- The new encrypted payload fits the existing VARCHAR(500) column for API keys of up to 300 bytes. Longer input is rejected, not truncated. Empty keys for local providers remain supported, with encrypted storage.
- API errors do not disclose key material or encryption configuration details. The migration is CLI-only and its directory denies HTTP access under Apache.

## Deployment gate: do not deploy the encryption change without preparation

This code does not configure the live server or migrate any real database. Existing base64 rows are deliberately not readable by the new runtime until migrated. A release without the following steps can interrupt chat for users with saved keys.

1. Back up the database and current application release. Arrange a maintenance window that stops concurrent key writes and application requests.
2. Create a 32-byte random key using your server's secret manager. Supply its base64 representation as `API_KEY_ENCRYPTION_KEY` to both PHP CLI and the web server. Keep it outside the repository and database, restrict access and retain a secure backup. Losing it makes encrypted keys unrecoverable. Never commit it or paste it into an issue or chat.
3. Stage this release outside the served directory. Run the dry run against the intended database:

   ```sh
   php scripts/migrate_api_keys.php --dry-run
   ```

   It validates every existing row and reports a count only. Nothing is stored. Invalid rows, oversized legacy keys, or the wrong encryption key stop the run.
4. With requests stopped, apply:

   ```sh
   php scripts/migrate_api_keys.php --apply
   ```

   All changes are transactional. Failure rolls back all rows. Repeating a completed migration validates the encrypted rows and migrates zero rows. Test on a database copy before production.
5. Activate the prepared application with the same encryption key. Test sign-in, settings, adding an API key, a chat request and Markdown previews before restoring service. The tests below do not replace this deployment check.

Rollback requires restoring the old application **and its matching pre-migration database backup together** while requests remain stopped. Old code cannot interpret `v1:` ciphertext. Do not discard the encryption key or database backup until the release is verified. This migration is not a key-rotation tool; existing encrypted rows must be decryptable with the configured key.

## Offline checks

```sh
php tests/security.php
find . -name '*.php' -exec php -l {} \;
```

Tests use fixture-only SQLite, DOM parsing and local random test keys. They cover encryption/decryption, tampering, wrong/missing keys, storage limits, migration dry runs/idempotency/rollback, and Markdown injection/formatting. PHP needs OpenSSL, DOM and PDO SQLite for tests; deployment uses the existing PDO MySQL backend. MySQL-specific locking and a production deployment have not been tested by the SQLite checks.

Raw embedded HTML in Markdown is now displayed as text, not rendered. Relative and custom-scheme Markdown links are not made clickable. This is intentional. The converter remains a small Markdown subset, not a full CommonMark implementation. Other application surfaces and upload types were not audited by this patch.

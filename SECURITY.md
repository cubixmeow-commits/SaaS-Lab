# Security

## Runtime data

- SQLite databases live under `/data` and `/projects/{slug}/data`, never under `/public`.
- Runtime files (`*.sqlite`, WAL/journal sidecars, logs, uploads, `config.local.php`, `installed.lock`) are gitignored.
- Do not commit password hashes, session data, Hostinger credentials, or API tokens.

## Transport

- Production on Hostinger is expected to use HTTPS.
- Session cookies are HTTP-only, SameSite=Lax, and Secure when HTTPS is active.
- Session cookie path is derived from `base_url` so authentication covers the full installation, including subdirectory deploys.

## Application controls

- Passwords are hashed with `password_hash()` / verified with `password_verify()`.
- All SQL uses prepared PDO statements.
- CSRF tokens are required for state-changing requests.
- Project slugs and page names are validated before filesystem resolution.
- Production error display is disabled; failures are written to `storage/logs/`.

## Reporting

If you discover a vulnerability in a deployment you operate, rotate credentials, inspect `storage/logs/app.log`, and patch from the latest trusted repository revision.

# StudentLogin Portal

A lightweight PHP (MySQLi) student portal featuring:

- User registration & profile editing (with optional profile picture & up to 3 videos)
- Traditional password authentication
- Experimental WebAuthn (biometric) login (enable/disable from profile page)
- Password reset via code OR biometric verification
- Basic group / messaging scaffolding (files present, not fully documented here yet)

> NOTE: The current WebAuthn implementation is intentionally minimal: it does **not** yet verify attestation objects nor assertion signatures or maintain server-side challenge state. It only stores credential IDs and trusts the browser API for user presence. This is fine for learning, but **NOT** production-secure.

## Features Overview

| Area | Status | Notes |
|------|--------|-------|
| Registration | ✔ | Stores user; optional profile image; saves initial WebAuthn credential if provided |
| Login (password) | ✔ | Password hash via `password_hash()` |
| Login (WebAuthn) | ✔ (minimal) | Matches credential_id only (needs cryptographic verification) |
| Profile Editing | ✔ | Update fields, upload/remove picture, manage videos, toggle biometric login |
| Videos | ✔ | Max 3 stored per user (filename only) |
| Password Reset | ✔ | Code-based + biometric bypass if previously verified |
| Biometric Enable/Disable | ✔ | Adds/removes stored credential(s) via profile page |
| Group / Messaging | Partial | Endpoints/files exist; not yet documented |

## Security Gaps (To Improve)

1. WebAuthn: No server-stored challenge or signature/attestation validation.
2. No rate limiting on login / enable/disable endpoints.
3. No CSRF tokens (relies on session + same-origin only).
4. No input normalization/sanitization beyond prepared statements.
5. Plain DB credentials hard-coded in `db_connect.php`.

## Quick Start (Local XAMPP)

1. Clone repo into your XAMPP `htdocs` directory:
   ```
   git clone https://github.com/<your-user>/<your-repo>.git StudentLogin
   ```
2. Create MySQL database (default name: `class`).
3. Import schema:
   ```
   mysql -u root -p class < schema.sql
   ```
4. Ensure `uploads/` is writable.
5. Visit: `http://localhost/StudentLogin/registration.html`

## Database Schema (See `schema.sql`)
Key tables:
- `registration` – master user profile
- `login` – authentication hash mirror (could be merged into `registration` later)
- `webauthn_credentials` – stored credential ids (base64url) + placeholder public_key
- `user_videos` – video filenames per user
- `password_reset_codes` – time-bound reset codes

## WebAuthn (Current Flow)
- On enabling: `navigator.credentials.create()` generates credential; only its `rawId` (base64url) is stored.
- On login: Frontend fetches stored credential ID (`get_webauthn_id.php`), calls `navigator.credentials.get()` with `allowCredentials`.
- On disable: Requires assertion for existing credential ID, then deletes rows.

### Hardening TODO
- Persist challenge server-side and compare in enable/disable/login flows.
- Parse and validate clientDataJSON & authenticatorData.
- Store and verify public key and signature (e.g., using a WebAuthn PHP library).
- Track signCount to detect cloned authenticators.

## File Map (Selected)
- `registration_process.php` – creates user + optional WebAuthn credential
- `login_process.php` – password & biometric login paths
- `edit.php` – profile + biometric enable/disable UI
- `enable_biometric.php` / `disable_biometric.php` / `biometric_status.php`
- `reset_request.html`, `reset_bio_verify.php`, `reset_password*.{html,php}`
- `get_webauthn_id.php` – returns credential_id for a username

## Adding A Real WebAuthn Library
Consider integrating something like:
- https://github.com/web-auth/webauthn-framework
- Or a lighter wrapper for FIDO2 challenge/signature verification

## Environment Configuration
Currently credentials live in `db_connect.php`. For production, refactor to `.env` (not committed) + loader:
```
DB_HOST=localhost
DB_NAME=class
DB_USER=app_user
DB_PASS=strong_password
```

## .gitignore & Uploads
The `uploads/` directory is tracked only with a placeholder. Real uploaded files should not be committed.

## License
MIT – see `LICENSE` file.

## Contributing
PRs welcome. Please add:
- Minimal test (where feasible)
- Clear description of security impact

## Roadmap (Suggested)
1. Full WebAuthn challenge + signature validation
2. CSRF tokens on state-changing POST forms
3. Merge `login` table into `registration`
4. Add pagination & size limits to video handling
5. Structured logging + audit trail

## Deployment: Vercel (Docker) or Alternative

Vercel's legacy `@vercel/docker` builder is now deprecated/unpublished. Two workable options:

### Option A: Use Another Host (Render / Fly.io / Railway)
Already Dockerized (see `Dockerfile`). Provide env vars:
```
DB_HOST=...
DB_PORT=3306
DB_USER=...
DB_PASS=...
DB_NAME=studentlogin
DB_SSL=1        # if managed DB requires TLS
DB_SSL_VERIFY=1 # set 0 to debug cert issues
```

Single-variable alternative (preferred for SkySQL) — set only this (plus DB_SSL / DB_SSL_VERIFY):
```
SKYSQL_CREDS={"host":"serverless-eastus.sysp0000.db3.skysql.com","port":4048,"user":"appuser","password":"YOUR_STRONG_PASS","db":"studentlogin"}
DB_SSL=1
DB_SSL_VERIFY=1
```
If both individual DB_* and SKYSQL_CREDS are present, individual DB_* values override.

To generate SKYSQL_CREDS from already set local env values:
```
php generate_skysql_creds.php
```
Copy the JSON output into the Render environment variable field.

### Option B: Vercel via Build Output API (Advanced)
1. Replace Docker deployment with a build step that outputs a static + serverless structure OR use a community PHP runtime.
2. Because this project expects a long-lived PHP process & file uploads, a traditional VPS / container host is simpler.

### Option C: Vercel + External Container Registry (Future)
Vercel is experimenting with OCI image deploys—if available to your account, push image and configure service to use external image directly.

### Health Check
`/health.php` returns JSON and attempts a `SELECT 1`.

## MariaDB / SkySQL Notes
If using MariaDB SkySQL with TLS enforcing port (e.g. 4047):
```
mariadb -h <host> -P 4047 -u <user> -p
CREATE DATABASE IF NOT EXISTS studentlogin;
USE studentlogin;
SOURCE schema.sql;
```
Privileges example (run as admin-capable account):
```
CREATE USER IF NOT EXISTS 'appuser'@'%' IDENTIFIED BY 'Strong!Pass123';
GRANT ALL ON studentlogin.* TO 'appuser'@'%';
FLUSH PRIVILEGES;
```

If your provider gives a URL (e.g. `mariadb://user:pass@host:4047/studentlogin?ssl-mode=REQUIRED`) you can set:
```
DB_URL=mariadb://user:pass@host:4047/studentlogin?ssl-mode=REQUIRED
DB_SSL=1
```
Explicit `DB_*` vars override parts of `DB_URL` if both are present.

## Troubleshooting DB Connection
Symptom | Likely Cause | Fix
------- | ------------ | ---
`Connection failed` | Wrong credentials / host / port | Verify CLI login first
`Access denied` | Privileges or password mismatch | Reset password, `SHOW GRANTS;`
Hangs on connect | Firewall / network egress blocked | Allow host, open port
SSL verify errors | Missing CA bundle | Set `DB_SSL_VERIFY=0` temporarily or provide `DB_SSL_CA`


## Disclaimer
Educational / prototype quality. Do **not** deploy as-is to production.

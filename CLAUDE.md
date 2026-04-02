# PersonalPortal — CLAUDE.md

## Project Summary

PersonalPortal is a self-hosted personal dashboard built with PHP 8+, MySQL, and vanilla JavaScript.
Designed for Dreamhost shared hosting. No frameworks, no build tools, no npm.

**Current version:** `1.5.1` (defined in `includes/version.php`)  
**GitHub repo:** https://github.com/brianpavane/PersonalPortal  
**Branch:** `main`

### Feature Set

- Bookmarks (grouped by category, auto-favicons)
- Stock ticker (Yahoo Finance v8 public endpoint, file-cached)
- News aggregator (RSS/Atom feeds, file-cached)
- Notes (simple markup: headings, bullets, paragraphs)
- Weather widget (Open-Meteo, no API key required)
- World Clock widget (client-side, up to 6 timezone zones)
- Admin panel (CRUD for all content, CSRF-protected)
- TOTP MFA (RFC 6238, AES-256-CBC encrypted secret at rest)
- Portal users (non-admin accounts with remember-me tokens)

### Stack

| Layer | Technology |
|---|---|
| Server-side | PHP 8.0+ |
| Database | MySQL (PDO prepared statements) |
| Frontend | Vanilla JS, dark-mode CSS |
| Auth | Session + bcrypt + TOTP |
| Hosting target | Dreamhost shared |

---

## Directory Layout

```
PersonalPortal/
├── index.php              ← Main portal (public)
├── portal_login.php       ← Portal user login
├── .htaccess              ← Security headers, HTTPS redirect, PHP hardening
├── config/config.php      ← Generated at install; never commit secrets
├── includes/
│   ├── auth.php           ← Session auth, CSRF helpers
│   ├── db.php             ← PDO connection singleton
│   ├── functions.php      ← Shared utilities
│   ├── portal_auth.php    ← Portal-user auth helpers
│   ├── totp.php           ← TOTP generate/verify
│   └── version.php        ← APP_VERSION constant — bump each release
├── api/                   ← JSON endpoints (bookmarks, notes, stocks, news, weather)
├── admin/                 ← Password-protected CRUD
├── assets/css/            ← Portal (style.css) and admin (admin.css) stylesheets
├── assets/js/portal.js    ← All widget JS
├── cache/                 ← Auto-generated file cache (git-ignored except .gitkeep)
├── install/               ← Installer wizard (delete after install)
└── docs/
    ├── README.md          ← End-user documentation
    ├── UPDATING.md        ← Upgrade guide
    └── migrations/        ← Idempotent SQL migration files per version
```

---

## Versioning

`APP_VERSION` lives in `includes/version.php`. Every release requires:

1. **Bump the version constant** in `includes/version.php`:
   ```php
   define('APP_VERSION',      'X.Y.Z');
   define('APP_VERSION_DATE', 'YYYY-MM-DD');
   ```
   Add a comment line to the history block in that file.

2. **Follow semantic versioning:**
   - `PATCH` (x.y.**Z**) — bug fixes, no schema changes, no new config keys
   - `MINOR` (x.**Y**.0) — new features, new config keys, or new DB columns (needs migration)
   - `MAJOR` (**X**.0.0) — breaking changes, schema redesign

3. **If schema changes exist**, create `docs/migrations/vX.Y.Z.sql` (idempotent: use
   `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE`).

4. **Update migration table in `docs/UPDATING.md`** to include the new version row.

5. **Update version history section** in `docs/UPDATING.md` with a bullet summary of changes.

6. **Update `docs/README.md`** if new widgets, config keys, or admin screens were added.

7. **Commit message format:** `vX.Y.Z: <one-line summary of key changes>`

---

## Documentation Publishing

All user-facing documentation lives in `docs/`. Keep it current with every release.

| File | What to update |
|---|---|
| `docs/README.md` | Feature table, directory structure, config reference, usage guide |
| `docs/UPDATING.md` | Migration table, version history section, new-version upgrade notes |
| `docs/migrations/vX.Y.Z.sql` | New migration file per MINOR/MAJOR release |
| `install/schema.sql` | Keep cumulative (full schema for fresh installs) |

When publishing a new release:
- Verify `install/schema.sql` reflects the complete up-to-date schema.
- Verify all migration files in `docs/migrations/` are present and idempotent.
- Verify the UPDATING.md migration table is correct and exhaustive.
- Verify README feature table matches actual shipped features.

---

## Security Scanning

Run the following checks before every release (or on any security-touching change):

### 1. Input / Output
- All user input goes through PDO prepared statements — no string interpolation in queries.
- All output to HTML must use `htmlspecialchars()` or equivalent — no raw `echo $_POST[...]`.
- CSRF tokens required on every state-changing form (`admin/` pages and portal login).

### 2. Authentication
- Passwords: bcrypt via `password_hash(PASSWORD_BCRYPT)` — never MD5/SHA1/plain.
- TOTP secret: AES-256-CBC encrypted, keyed to `SECRET_KEY` — never stored plain.
- Session cookies: `httponly`, `samesite=Strict`, `secure` (set in `.htaccess`).
- Rate limiter: 5 failed logins → 15-minute IP lockout (applies to both password and TOTP steps).

### 3. HTTP Headers
Verify `.htaccess` enforces all of the following (already configured):
- `Content-Security-Policy` (CSP)
- `Strict-Transport-Security` (HSTS, HTTPS only)
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy`
- `Permissions-Policy`
- `X-Powered-By` unset

### 4. File / Directory Access
- `config/`, `includes/`, `cache/` blocked by `.htaccess` `RewriteRule` and directory-level `.htaccess`.
- `install/installed.lock` blocked from direct access.
- `.sql`, `.log`, `.bak`, `.cache`, `.lock`, `.md` extensions denied by `<FilesMatch>`.
- Directory listing disabled: `Options -Indexes`.

### 5. API Endpoints
- Auth-protected APIs set `Cache-Control: private` (not `public`) to prevent proxy caching.
- News feed proxy validates URLs against an allowlist / scheme check to prevent SSRF.
- Stock proxy caches responses to avoid rate-limit hammering of upstream API.

### 6. Secrets in Config
- `config/config.php` is in `.gitignore` (verify this before every commit).
- `SECRET_KEY` must never be the default `REPLACE_BEFORE_DEPLOY_…` placeholder on a live install.
- Alpha Vantage key (if set) is server-side only — never exposed to the browser.

### 7. Pre-commit Checklist
Before committing any change that touches auth, API, admin, or `.htaccess`:
- [ ] No `var_dump`, `print_r`, `die(...)` debug statements left in code
- [ ] No hardcoded credentials or tokens
- [ ] `config/config.php` is NOT staged (`git status` — it should be gitignored)
- [ ] All new DB columns added via migration file, not only in `install/schema.sql`
- [ ] CSRF token checked on any new form POST handler
- [ ] Output HTML-escaped on any new template output

---

## Release Workflow (Every Commit)

Every commit that changes user-facing behaviour, fixes a bug, or modifies schema **must** be
pushed and published as a GitHub release. Follow these steps in order:

### 1. Bump the version
- Edit `includes/version.php`: increment `APP_VERSION` and `APP_VERSION_DATE`.
- Add a one-line entry to the history comment block in that file.

### 2. Update documentation
- `docs/UPDATING.md` — add row to migration table; add version history section.
- `docs/README.md` — reflect any new features, config keys, or admin screens.
- `docs/migrations/vX.Y.Z.sql` — create if schema changed.
- `install/schema.sql` — keep cumulative (add any new DDL).

### 3. Security scan
Run through the pre-commit checklist in the **Security Scanning** section above before staging.

### 4. Commit
```bash
git add -p                        # stage deliberately; never 'git add .'
# Confirm config/config.php is NOT staged
git commit -m "vX.Y.Z: <summary>"
```

### 5. Push to origin
```bash
git push origin main
```

### 6. Create a GitHub release
```bash
gh release create vX.Y.Z \
  --title "vX.Y.Z — <short title>" \
  --notes "$(cat <<'EOF'
## What's changed
- <bullet list of changes>

## Migration
<!-- omit if no schema changes -->
Run `docs/migrations/vX.Y.Z.sql` against your database before deploying.

## Upgrade
See [UPDATING.md](docs/UPDATING.md) for full instructions.
EOF
)"
```

> **Note:** The release tag must match `APP_VERSION` exactly (e.g. `v1.5.0`).
> The GitHub release is the canonical published artefact — do not push without creating one.

---

## Development Notes

- No build step. Edit PHP/CSS/JS files directly.
- PHP errors are OFF in `.htaccess` for production. Temporarily enable with
  `ini_set('display_errors', 1);` at the top of a file during dev; remove before commit.
- File cache lives in `cache/`. Delete `cache/*.cache` to force a refresh during testing.
- To test MFA locally, any TOTP app (Authy, 1Password, etc.) works; clock drift ±1 window
  (90 seconds) is tolerated.
- `install/` should be absent on live deployments. For local dev it's safe to keep but
  must be removed or the lock file must exist before exposing to the internet.

---

## Common Tasks

### Add a new widget
1. Add DB storage or settings key in a new migration file + `install/schema.sql`
2. Add API endpoint under `api/` if data is fetched server-side
3. Add JS widget class/function in `assets/js/portal.js`
4. Add admin config section in `admin/settings.php`
5. Update `docs/README.md` feature table and usage guide
6. Bump version (MINOR)

### Change admin password (programmatic)
Use `password_hash($newpass, PASSWORD_BCRYPT)` and UPDATE `portal_settings` where
`setting_key = 'admin_password_hash'`.

### Add a migration
Create `docs/migrations/vX.Y.Z.sql`. Use only idempotent DDL:
```sql
CREATE TABLE IF NOT EXISTS ...
ALTER TABLE foo ADD COLUMN IF NOT EXISTS bar VARCHAR(255) DEFAULT '';
INSERT IGNORE INTO portal_settings (setting_key, setting_value) VALUES ('key', 'default');
```
Then run it against `install/schema.sql` to keep the cumulative schema current.

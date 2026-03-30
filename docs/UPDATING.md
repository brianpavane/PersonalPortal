# Updating PersonalPortal

**Repository:** https://github.com/brianpavane/PersonalPortal

How to apply new versions without reinstalling from scratch, from **any** previous version.

---

## Quick Upgrade (TL;DR)

```bash
# 1. Back up
mysqldump -u DB_USER -p DB_NAME > backup_$(date +%Y%m%d).sql

# 2. Pull new code
cd /path/to/personalportal
git remote set-url origin https://github.com/brianpavane/PersonalPortal.git
git pull origin main

# 3. Run ALL migration files that are newer than your current version (see table below)
#    All migrations are idempotent — safe to re-run; running extras causes no harm.

# 4. Clear the cache
rm -f /path/to/personalportal/cache/*.cache

# 5. Verify — open portal, check version in footer
```

---

## Migration File Reference

Every release publishes a migration file in `docs/migrations/`. Each file is **idempotent**
(`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, etc.) — safe to run more than once.

| From version | Run these migration files (in order) |
|---|---|
| Fresh install | `v1.0.0.sql` only (or use `install/`) |
| v1.0.0 → latest | `v1.2.0.sql` → `v1.3.0.sql` → `v1.4.0.sql` |
| v1.1.x → latest | `v1.2.0.sql` → `v1.3.0.sql` → `v1.4.0.sql` |
| v1.2.x → latest | `v1.3.0.sql` → `v1.4.0.sql` |
| v1.3.x → latest | `v1.4.0.sql` |
| v1.4.x → latest | Nothing to run (no DB changes) |
| Already on latest | Nothing to run |

**When in doubt:** run all migrations from `v1.0.0.sql` upward — it's safe.

---

## Detailed Steps

### 1. Back up first

```bash
# Export your database
mysqldump -u DB_USER -p DB_NAME > backup_$(date +%Y%m%d).sql

# (Optional) Archive your current files
tar czf personalportal_backup_$(date +%Y%m%d).tar.gz /path/to/personalportal/
```

### 2. Pull the latest files

If you installed via git:

```bash
cd /path/to/personalportal
# Confirm your remote points to the correct repo
git remote set-url origin https://github.com/brianpavane/PersonalPortal.git
git pull origin main
```

If you installed without git (first time setup):

```bash
cd /path/to/personalportal
git init
git remote add origin https://github.com/brianpavane/PersonalPortal.git
git fetch origin main
git checkout main
```

If you prefer a ZIP download (no git required):
```
https://github.com/brianpavane/PersonalPortal/archive/refs/heads/main.zip
```
Extract and upload the new files via FTP/SFTP, **skipping** `config/config.php`.

**Do not overwrite these files** (they contain your local configuration):
- `config/config.php`

**Safe to delete** (auto-recreated at runtime):
- `cache/` directory contents

### 3. Run migration SQL

Migration files live at:
https://github.com/brianpavane/PersonalPortal/tree/main/docs/migrations

Open phpMyAdmin or your MySQL client, select your database, then run each
migration file that is newer than your installed version (see table above).

**phpMyAdmin**: Database → SQL tab → paste contents → Go

**MySQL CLI**:
```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < docs/migrations/v1.3.0.sql
mysql -h DB_HOST -u DB_USER -p DB_NAME < docs/migrations/v1.4.0.sql
```

### 4. Check config.php for new constants

If a new version adds constants to `config/config.php`, you need to add them to
your installed copy. Diff the example file against yours:

```bash
diff config/config.php.example config/config.php
```

Add any missing `define()` lines. Missing constants will cause PHP warnings.

### 5. Clear the cache

```bash
rm -f /path/to/personalportal/cache/*.cache
```

Or visit **Admin → Settings** and clear relevant caches by re-saving (stock/news
saves wipe their respective caches automatically).

### 6. Verify the upgrade

- Open the portal in your browser
- Check the version number in the footer matches the release you deployed
- Spot-check that bookmarks, notes, stocks, and news all load correctly
- If you added new widgets (weather, world clock), configure them in Admin → Settings

---

## Version History & What Each Release Changes

### v1.4.2
- World Clock now supports up to 10 timezones (was 6).
- Typing a city name in the Label field auto-fills the IANA timezone; typing an IANA timezone auto-fills the label. San Francisco, Mumbai, and Bangalore are included in the city lookup.
- Weather Locate button now uses Nominatim (OpenStreetMap) — supports city names **and** zip/postal codes.
- No database changes.

### v1.4.1
- Bug fix: `admin/settings.php` returned HTTP 500 on every load due to misplaced closing brace that placed the Weather and Timezone POST handlers outside the `if (POST)` block.
- Added `config/config.php.example` for use when diffing config during upgrades.
- No database changes.

### v1.4.0
- **No new tables.** Weather city config and timezone zones stored as JSON in `portal_settings`.
- Stock ticker switched from Yahoo Finance v7 (deprecated, requires auth) to v8 chart endpoint.
- New widgets: Weather (Open-Meteo, no API key) and World Clock (client-side, up to 6 zones).
- Header clock removed; replaced by configurable World Clock widget.
- Configure both in **Admin → Settings** (bottom two sections).
- **After upgrading:** run `docs/migrations/v1.4.0.sql`, then visit Admin → Settings to configure
  weather cities and timezone zones.

### v1.3.1
- Arrow-based (▲/▼) sort ordering for categories, notes, and bookmarks replaces manual number entry.
- `Cache-Control` on auth-protected API endpoints corrected to `private` when portal auth is on.
- No database changes.

### v1.3.0
- **New tables:** `portal_users`, `portal_tokens`.
- New: separate portal user login (non-admin accounts), remember-me tokens (30-day).
- New: 16-colour accent palette picker in admin.
- New: version number displayed in portal footer and admin sidebar.
- **After upgrading:** run `docs/migrations/v1.3.0.sql`, then visit Admin → Portal Users to
  create portal user accounts (if you want to enable access control).

### v1.2.1
- Installer bug fix for Dreamhost shared hosting (`SET time_zone` privilege error).
- Config file injection fix in installer.
- No database changes.

### v1.2.0
- **No new tables.** TOTP secret stored in `portal_settings`.
- New: TOTP-based two-factor authentication for admin login.
- No database changes.

### v1.1.0
- Security hardening: CSRF on login, rate limiting, cache serialization fix, SSRF prevention.
- No database changes.

### v1.0.0
- Initial release. Full schema in `docs/migrations/v1.0.0.sql`.

---

## Rollback

If something goes wrong:

```bash
# Restore database
mysql -h DB_HOST -u DB_USER -p DB_NAME < backup_YYYYMMDD.sql

# Restore files via git
git remote set-url origin https://github.com/brianpavane/PersonalPortal.git
git checkout <previous-commit-hash>   # use 'git log' to find the hash before your upgrade

# Or restore from file archive
tar xzf personalportal_backup_YYYYMMDD.tar.gz -C /path/to/
```

---

## Dreamhost-specific Notes

- Run migrations via phpMyAdmin (use the SQL tab).
- The `install/index.php` installer can also be re-run to apply the latest `install/schema.sql`
  (which is cumulative), but you **must** re-enter your config or it will overwrite `config.php`.
  Prefer running individual migration files instead.
- Clear PHP's opcode cache if you have one enabled: `opcache_reset()` or restart the PHP process.

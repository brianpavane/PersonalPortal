# Upgrading PersonalPortal

**Repository:** https://github.com/brianpavane/PersonalPortal  
**Current release:** v1.5.0

This guide covers upgrading from **any previous version** to the latest release.  
All migration files are idempotent — safe to run more than once.

---

## Before You Start

1. **Note your current version** — shown in the portal footer and admin sidebar.
2. **Back up your database and files** (see Step 1 below).
3. Upgrading takes about 5–10 minutes on Dreamhost shared hosting.

---

## Migration File Reference

Every release that changes the database ships a migration file in `docs/migrations/`.
Files that add no schema changes are no-ops (safe to run but do nothing).

| Your current version | Migration files to run (in order) |
|---|---|
| Fresh install | Use `install/index.php` — do not run migrations manually |
| v1.0.0 or v1.1.x | `v1.2.0.sql` → `v1.3.0.sql` → `v1.4.0.sql` → `v1.4.5.sql` |
| v1.2.x | `v1.3.0.sql` → `v1.4.0.sql` → `v1.4.5.sql` |
| v1.3.0 or v1.3.1 | `v1.4.0.sql` → `v1.4.5.sql` |
| v1.4.0 – v1.4.4 | `v1.4.5.sql` |
| v1.4.5 – v1.5.0 | Nothing to run (no schema changes) |

**When in doubt:** run all migrations in order — it's safe.

---

## Step-by-Step Upgrade

### Step 1 — Back up

**Back up your database** (do this before touching any files):

In **phpMyAdmin** (recommended on Dreamhost):
1. Log into your Dreamhost panel → Databases → phpMyAdmin
2. Select your database in the left panel
3. Click **Export** → Quick → Format: SQL → **Go**
4. Save the downloaded `.sql` file somewhere safe

**Or via SSH/terminal:**
```bash
mysqldump -h DB_HOST -u DB_USER -p DB_NAME > backup_$(date +%Y%m%d).sql
```
Replace `DB_HOST`, `DB_USER`, `DB_NAME` with the values from your `config/config.php`.

---

### Step 2 — Pull the latest code

**Via SSH on Dreamhost:**
```bash
cd /path/to/your/personalportal
git pull origin main
```

If you have never set up git on this installation:
```bash
cd /path/to/your/personalportal
git init
git remote add origin https://github.com/brianpavane/PersonalPortal.git
git fetch origin main
git checkout main
```

**Without git (FTP/SFTP upload):**
1. Download the ZIP: https://github.com/brianpavane/PersonalPortal/archive/refs/heads/main.zip
2. Extract locally
3. Upload **all files except `config/config.php`** to your server via FTP/SFTP
4. **Do not overwrite** `config/config.php` — it contains your database credentials

---

### Step 3 — Run migration SQL

Look up your current version in the migration table above and run the listed files.

**phpMyAdmin (recommended on Dreamhost):**
1. Log in to phpMyAdmin
2. Select your database
3. Click the **SQL** tab
4. Open each migration file from `docs/migrations/` in a text editor, copy its contents, paste into the SQL tab, and click **Go**
5. Repeat for each file in order

**MySQL CLI (SSH):**
```bash
# Replace DB_HOST, DB_USER, DB_NAME with values from your config/config.php

# Example: upgrading from v1.3.x
mysql -h DB_HOST -u DB_USER -p DB_NAME < docs/migrations/v1.4.0.sql
mysql -h DB_HOST -u DB_USER -p DB_NAME < docs/migrations/v1.4.5.sql

# Example: upgrading from v1.4.0–v1.4.4
mysql -h DB_HOST -u DB_USER -p DB_NAME < docs/migrations/v1.4.5.sql
```

---

### Step 4 — Check config.php for new constants

If a release adds new constants, you need to add them to your installed `config/config.php`.
Compare the example file against yours:

```bash
diff config/config.php.example config/config.php
```

Add any `define()` lines that appear in the example but not in your file.
Missing constants cause PHP warnings and may break features.

**No new constants were added in v1.5.0.** This step is only needed when upgrading
across a major feature release (v1.3.0, v1.4.0).

---

### Step 5 — Clear the cache

Delete all cached files so the portal fetches fresh data:

```bash
rm -f /path/to/personalportal/cache/*.cache
```

**In phpMyAdmin / no SSH access:** You can trigger a cache clear by visiting
**Admin → Settings**, making a minor change to Stock Symbols (add and remove a space),
and clicking Save — this wipes the stock and news caches automatically.

---

### Step 6 — Verify

1. Open your portal in the browser
2. Check the version number in the **footer** — it should read **v1.5.0**
3. Confirm each widget loads: Bookmarks, Notes, Stocks/Ticker, Weather, News, World Clock
4. Check Admin → Settings to configure any new features (see What's New below)

---

## What's New in v1.5.0

This is a consolidated release covering all improvements since v1.4.0.

### New features
- **Weather widget** — up to **6 cities** (was 3). Configure in Admin → Settings.
- **Weather Locate button** — type a city name or zip/postal code and click Locate to auto-fill coordinates. Uses server-side geocoding (no browser CORS issues).
- **World Clock** — up to **10 timezone slots** (was 6). Type a city name in the Label field to auto-fill the IANA timezone, or type the timezone to auto-fill the label. Includes San Francisco, Mumbai, and Bangalore presets.
- **Stock display mode** — choose between *Ticker bar only* (default), *Sidebar widget only*, or *Both*, in Admin → Settings → Stock Symbols.
- **Admin Dashboard link** — the admin sidebar now has a Dashboard link at the top.

### Bug fixes
- Stock widget was reporting "Stock data unavailable" — fixed Yahoo Finance v8 crumb/cookie authentication, switched to the `query2` host, and added output buffering to prevent PHP warnings corrupting JSON.
- Settings page returned HTTP 500 — a misplaced closing brace placed the Weather and Timezone save handlers outside the POST block.
- Bookmark favicons showed as gray boxes — Google's `s2/favicons` service redirects to `gstatic.com`, which was blocked by the Content Security Policy. Switched to DuckDuckGo's favicon service (no redirects). CSP `img-src` updated accordingly.
- Bookmarks failed to render when any URL was stored without a `https://` protocol — `new URL()` threw an uncaught error that crashed the entire category. Fixed with a safe `getHostname()` helper.
- Portal widgets did not refresh (browser F5 or widget refresh button served stale data) — `api/bookmarks.php` and `api/notes.php` sent no `Cache-Control` header, allowing browsers to cache responses indefinitely. Both now send `Cache-Control: no-store`.
- PHP 7.4 compatibility — removed `mixed` type hint that required PHP 8.0+.

### Configuration after upgrading
- **Stock display mode**: visit Admin → Settings → Stock Symbols, choose your preferred display, Save.
- **Additional weather cities**: visit Admin → Settings → Weather Cities, fill in up to 6 cities using the Locate button or manual coordinates.
- **Additional timezone slots**: visit Admin → Settings → World Clock Timezones — 10 slots now available.

---

## Version History

| Version | Date | Summary |
|---|---|---|
| **v1.5.0** | 2026-04-01 | Consolidated release: 6 weather cities, 10 timezones, stock display mode, favicon fix, refresh fix, stocks API crumb auth |
| v1.4.7 | 2026-03-30 | Favicon CSP fix — switched to DuckDuckGo favicon service |
| v1.4.6 | 2026-03-30 | Favicon URL crash fix; Cache-Control: no-store on bookmarks/notes |
| v1.4.5 | 2026-03-30 | Weather up to 6 cities; stock display mode setting |
| v1.4.4 | 2026-03-30 | Server-side geocode proxy; Dashboard link in admin sidebar |
| v1.4.3 | 2026-03-30 | Stocks crumb auth (Yahoo Finance v8); PHP 7.4 compat |
| v1.4.2 | 2026-03-30 | Timezones up to 10; city↔TZ auto-fill; zip code geocoding |
| v1.4.1 | 2026-03-30 | settings.php 500 fix; config.php.example |
| v1.4.0 | 2026-03-30 | Weather + World Clock widgets; Yahoo Finance v8; header clock removed |
| v1.3.1 | 2026-03-30 | ▲/▼ sort ordering; Cache-Control security fix |
| v1.3.0 | 2026-03-30 | Portal user accounts; 16-colour palette; version display |
| v1.2.1 | 2026-03-30 | Installer bug fix for Dreamhost |
| v1.2.0 | 2026-03-30 | TOTP two-factor authentication |
| v1.1.0 | 2026-03-30 | Security hardening (CSRF, rate limiting, SSRF prevention) |
| v1.0.0 | 2026-03-30 | Initial release |

---

## Rollback

If something goes wrong after upgrading:

**Restore your database:**

phpMyAdmin: Database → Import → select your backup `.sql` file → Go

SSH:
```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < backup_YYYYMMDD.sql
```

**Restore files via git:**
```bash
# Find the commit hash before your upgrade
git log --oneline

# Roll back to that commit
git checkout <previous-commit-hash>
```

**Restore files from archive:**
```bash
tar xzf personalportal_backup_YYYYMMDD.tar.gz -C /path/to/
```

---

## Dreamhost Notes

- **phpMyAdmin** is the easiest way to run migrations — no SSH required.
  Access it via your Dreamhost panel under Databases.
- **SSH access** is available on all Dreamhost plans — enable it in the panel under Users.
- The `DB_HOST` in your `config/config.php` is your Dreamhost MySQL hostname
  (typically `mysql.yourdomain.com`) — always use this when running `mysql` CLI commands.
- If the portal looks unchanged after upgrading, your browser may have cached the old CSS/JS.
  Hard refresh with **Ctrl+Shift+R** (Windows/Linux) or **Cmd+Shift+R** (Mac).
- PHP opcode cache: if you have OPcache enabled, you may need to restart PHP-FPM or
  touch your `.htaccess` file to clear compiled bytecode.

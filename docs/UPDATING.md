# Updating PersonalPortal

How to apply new versions without reinstalling from scratch.

---

## Standard Update Process

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
git pull origin main
```

If you downloaded a ZIP manually, upload the new files via FTP/SFTP. **Do not overwrite:**
- `config/config.php` — your local configuration
- `cache/` — runtime cache (safe to delete if needed)

### 3. Run any migration SQL

Each version's migration lives in `docs/migrations/`. Run the relevant file(s)
in your database client (phpMyAdmin, MySQL CLI, etc.):

```sql
-- Example: upgrading to v1.3.0
SOURCE /path/to/personalportal/docs/migrations/v1.3.0.sql;
```

Or paste the contents into phpMyAdmin's SQL tab.

Migrations are **idempotent** (`IF NOT EXISTS` / `INSERT IGNORE`) — safe to run
more than once.

### 4. Clear the cache

```bash
rm -f /path/to/personalportal/cache/*.cache
```

Or visit **Admin → Settings** and click **Clear Cache** if available.

### 5. Verify

Open the portal in your browser and confirm the version number in the footer
matches the release you just deployed.

---

## Version Migration Notes

### v1.2.0 → v1.2.1
- No database changes.
- Schema parser fix for Dreamhost shared hosting (removed `SET time_zone`).
- Config generator injection fix (use `var_export`).

### v1.2.1 → v1.3.0
- Run `docs/migrations/v1.3.0.sql` — adds `portal_users` and `portal_tokens` tables.
- New files added: `includes/version.php`, `includes/portal_auth.php`,
  `portal_login.php`, `portal_logout.php`, `admin/portal_users.php`,
  `assets/js/admin.js`.
- Portal access control is **disabled by default** after migration. Enable it in
  **Admin → Portal Users** once you have created at least one portal user.

---

## Config File Changes

If a new version changes `config/config.php.example`, you may need to add new
constants to your existing `config/config.php`. Always diff the example against
yours after a major update:

```bash
diff config/config.php.example config/config.php
```

Add any missing `define()` lines with appropriate values.

---

## Rollback

If something goes wrong:

1. Restore your database backup:
   ```bash
   mysql -u DB_USER -p DB_NAME < backup_YYYYMMDD.sql
   ```
2. Restore your file backup or `git checkout` the previous tag:
   ```bash
   git checkout v1.2.1
   ```

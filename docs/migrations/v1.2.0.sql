-- PersonalPortal v1.2.0 — MFA / TOTP Support
-- No schema changes. TOTP secret and enabled flag are stored in portal_settings
-- using the keys 'totp_secret' and 'totp_enabled' (written by includes/totp.php).
-- Running this file on any v1.0.0+ database is a no-op.

-- (intentionally empty — no DDL changes in this release)
SELECT 'v1.2.0: no schema changes' AS migration_note;

-- PersonalPortal v1.4.5 — Weather 6 cities + Stock display mode setting
-- No new tables. New portal_settings keys:
--   stock_display_mode: 'ticker' | 'widget' | 'both'  (default: ticker)
-- Weather city limit increased from 3 to 6 in admin UI and API (no DB change).
-- Running this file on any v1.0.0+ database is a no-op.

-- Ensure portal_settings table exists
CREATE TABLE IF NOT EXISTS `portal_settings` (
  `setting_key`   VARCHAR(64)   NOT NULL,
  `setting_value` TEXT          NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default stock display mode (ticker-only)
INSERT IGNORE INTO `portal_settings` (`setting_key`, `setting_value`) VALUES
  ('stock_display_mode', 'ticker');

SELECT 'v1.4.5: stock_display_mode setting initialised' AS migration_note;

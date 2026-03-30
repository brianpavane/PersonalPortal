-- PersonalPortal v1.4.0 — Weather & Timezone Widgets
-- No new tables. Weather city config and timezone zones are stored as JSON
-- in portal_settings under the keys 'weather_cities', 'weather_unit',
-- and 'timezone_zones'. These keys are created automatically by Admin > Settings.
--
-- Stock data API was migrated from Yahoo Finance v7 to v8 chart endpoint;
-- no database changes required (clear the stock cache after upgrading).
--
-- Running this file on any v1.0.0+ database is a no-op.

-- Ensure portal_settings table exists (in case upgrading from a very early install)
CREATE TABLE IF NOT EXISTS `portal_settings` (
  `setting_key`   VARCHAR(64)   NOT NULL,
  `setting_value` TEXT          NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-create settings keys with empty values so the admin form works before first save
INSERT IGNORE INTO `portal_settings` (`setting_key`, `setting_value`) VALUES
  ('weather_cities', '[]'),
  ('weather_unit',   'fahrenheit'),
  ('timezone_zones', '[]');

SELECT 'v1.4.0: weather + timezone settings keys initialised' AS migration_note;

-- PersonalPortal v1.3.0 Migration
-- Run this if upgrading from v1.2.x to v1.3.0.
-- Safe to run multiple times (IF NOT EXISTS / INSERT IGNORE).

SET NAMES utf8mb4;

-- ── Portal Users (separate from admin) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `portal_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Portal Remember-Me Tokens ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `portal_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id`    (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_token_user`
    FOREIGN KEY (`user_id`) REFERENCES `portal_users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default portal_auth_enabled setting (off by default) ─────────────────────
INSERT IGNORE INTO `portal_settings` (`setting_key`, `setting_value`)
  VALUES ('portal_auth_enabled', '0');

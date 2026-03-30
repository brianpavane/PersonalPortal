-- PersonalPortal Database Schema
-- Run this during installation via install/index.php

SET NAMES utf8mb4;

-- ── Settings ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `portal_settings` (
  `setting_key`   VARCHAR(64)   NOT NULL,
  `setting_value` TEXT          NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Bookmark Categories ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookmark_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `icon`       VARCHAR(64)  NOT NULL DEFAULT 'folder',
  `color`      VARCHAR(7)   NOT NULL DEFAULT '#58a6ff',
  `sort_order` SMALLINT     NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Bookmarks ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `url`         TEXT         NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `sort_order`  (`sort_order`),
  CONSTRAINT `fk_bookmark_category`
    FOREIGN KEY (`category_id`) REFERENCES `bookmark_categories` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notes ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200) NOT NULL DEFAULT 'Untitled Note',
  `content`    TEXT         NOT NULL,
  `color`      VARCHAR(7)   NOT NULL DEFAULT '#58a6ff',
  `sort_order` SMALLINT     NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Stock Symbols ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_symbols` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol`     VARCHAR(20)  NOT NULL,
  `label`      VARCHAR(100) NOT NULL DEFAULT '',
  `sort_order` SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── News Feeds ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news_feeds` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `url`        TEXT         NOT NULL,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default seed data ────────────────────────────────────────────────────────
INSERT IGNORE INTO `bookmark_categories` (`name`, `icon`, `color`, `sort_order`) VALUES
  ('Work',          'briefcase',  '#58a6ff', 1),
  ('Development',   'code',       '#3fb950', 2),
  ('Finance',       'chart-line', '#f0883e', 3),
  ('News & Media',  'newspaper',  '#bc8cff', 4),
  ('Utilities',     'tools',      '#ff7b72', 5);

INSERT IGNORE INTO `stock_symbols` (`symbol`, `label`, `sort_order`) VALUES
  ('SPY',  'S&P 500 ETF', 1),
  ('QQQ',  'Nasdaq ETF',  2),
  ('AAPL', 'Apple',       3),
  ('MSFT', 'Microsoft',   4);

INSERT IGNORE INTO `notes` (`title`, `content`, `color`, `sort_order`) VALUES
  ('Quick Notes', '## Today\n- [ ] Check emails\n- [ ] Review dashboard\n\n## Ideas\n- Add more bookmarks\n- Configure news feeds', '#58a6ff', 1);

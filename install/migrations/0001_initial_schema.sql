-- OCCI Blogs — Initial Schema
-- Migration 0001

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `migrations` (
  `version`    VARCHAR(80) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`       VARCHAR(60) NOT NULL,
  `email`          VARCHAR(255) NOT NULL,
  `password`       VARCHAR(255) NOT NULL,
  `role`           ENUM('superadmin','blogger') NOT NULL DEFAULT 'blogger',
  `display_name`   VARCHAR(120) DEFAULT NULL,
  `bio`            TEXT DEFAULT NULL,
  `avatar`         VARCHAR(500) DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blogs` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `slug`            VARCHAR(80) NOT NULL,
  `name`            VARCHAR(200) NOT NULL,
  `description`     TEXT DEFAULT NULL,
  `tagline`         VARCHAR(255) DEFAULT NULL,
  `theme`           VARCHAR(80) NOT NULL DEFAULT 'minimal',
  `is_public`       TINYINT(1) NOT NULL DEFAULT 1,
  `ap_enabled`      TINYINT(1) NOT NULL DEFAULT 0,
  `ap_public_key`   TEXT DEFAULT NULL,
  `ap_private_key`  TEXT DEFAULT NULL,
  `followers_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `custom_css`      TEXT DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_blogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`      INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `slug`         VARCHAR(255) NOT NULL,
  `content`      LONGTEXT DEFAULT NULL,
  `excerpt`      TEXT DEFAULT NULL,
  `cover_image`  VARCHAR(500) DEFAULT NULL,
  `status`       ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_slug` (`blog_id`,`slug`),
  KEY `idx_blog_status` (`blog_id`,`status`),
  KEY `idx_published_at` (`published_at`),
  CONSTRAINT `fk_posts_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id` INT UNSIGNED NOT NULL,
  `name`    VARCHAR(100) NOT NULL,
  `slug`    VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_tag` (`blog_id`,`slug`),
  CONSTRAINT `fk_tags_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_tags` (
  `post_id` INT UNSIGNED NOT NULL,
  `tag_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`,`tag_id`),
  CONSTRAINT `fk_pt_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_tag`  FOREIGN KEY (`tag_id`)  REFERENCES `tags`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`       INT UNSIGNED DEFAULT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `filename`      VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type`     VARCHAR(100) NOT NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `width`         SMALLINT UNSIGNED DEFAULT NULL,
  `height`        SMALLINT UNSIGNED DEFAULT NULL,
  `path`          VARCHAR(500) NOT NULL,
  `thumb_path`    VARCHAR(500) DEFAULT NULL,
  `alt_text`      VARCHAR(255) DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog_id` (`blog_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_views` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`   INT UNSIGNED NOT NULL,
  `blog_id`   INT UNSIGNED NOT NULL,
  `viewed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash`   VARCHAR(64) NOT NULL,
  `referer`   VARCHAR(500) DEFAULT NULL,
  `device`    ENUM('desktop','tablet','mobile','bot') NOT NULL DEFAULT 'desktop',
  PRIMARY KEY (`id`),
  KEY `idx_post_id`   (`post_id`),
  KEY `idx_blog_id`   (`blog_id`),
  KEY `idx_viewed_at` (`viewed_at`),
  CONSTRAINT `fk_pv_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pv_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key`      VARCHAR(100) NOT NULL,
  `value`    TEXT DEFAULT NULL,
  `autoload` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('platform_name',    'OCCI Blogs'),
  ('platform_tagline', 'Independent Catholic voices'),
  ('admin_email',      ''),
  ('analytics_enabled','1');

SET foreign_key_checks = 1;

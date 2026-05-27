-- Phase 3: Federation delivery queue and tombstones

CREATE TABLE IF NOT EXISTS `federation_queue` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `blog_id`      INT UNSIGNED NOT NULL,
  `activity`     LONGTEXT NOT NULL,
  `inbox_url`    VARCHAR(500) NOT NULL,
  `key_id`       VARCHAR(500) NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `next_attempt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_error`   TEXT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pending`  (`attempts`, `next_attempt`),
  INDEX `idx_blog_id`  (`blog_id`),
  CONSTRAINT `fk_fq_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tombstones` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`    INT UNSIGNED NOT NULL,
  `blog_id`    INT UNSIGNED NOT NULL,
  `uri`        VARCHAR(500) NOT NULL,
  `deleted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_blog_id` (`blog_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

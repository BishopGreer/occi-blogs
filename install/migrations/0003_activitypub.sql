-- Phase 3: ActivityPub remote actors and blog followers

CREATE TABLE IF NOT EXISTS `remote_actors` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uri`              VARCHAR(500) NOT NULL,
  `inbox_url`        VARCHAR(500) NOT NULL,
  `shared_inbox_url` VARCHAR(500) DEFAULT NULL,
  `public_key_pem`   TEXT DEFAULT NULL,
  `username`         VARCHAR(255) DEFAULT NULL,
  `domain`           VARCHAR(255) DEFAULT NULL,
  `fetched_at`       DATETIME DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_uri` (`uri`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_followers` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `blog_id`            INT UNSIGNED NOT NULL,
  `remote_actor_id`    INT UNSIGNED NOT NULL,
  `follow_activity_id` VARCHAR(500) DEFAULT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_blog_follower` (`blog_id`, `remote_actor_id`),
  FOREIGN KEY (`blog_id`)         REFERENCES `blogs`(`id`)         ON DELETE CASCADE,
  FOREIGN KEY (`remote_actor_id`) REFERENCES `remote_actors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

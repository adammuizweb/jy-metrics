CREATE TABLE IF NOT EXISTS `jy_metrics_content_views_daily` (
    `entity_kind` ENUM('post', 'category') NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `view_date_utc` DATE NOT NULL,
    `entity_type` VARCHAR(16) NOT NULL,
    `view_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`entity_kind`, `entity_id`, `view_date_utc`),
    KEY `popular_period` (`entity_kind`, `entity_type`, `view_date_utc`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

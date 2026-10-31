ALTER TABLE `jy_metrics_content_views_daily`
    ADD COLUMN `representation_locale` VARCHAR(16) NOT NULL DEFAULT '' AFTER `entity_type`,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`entity_kind`, `entity_id`, `view_date_utc`, `representation_locale`),
    DROP KEY `popular_period`,
    ADD KEY `popular_period` (`entity_kind`, `entity_type`, `representation_locale`, `view_date_utc`, `entity_id`);

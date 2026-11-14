<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNewsTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('news_categories', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `news_categories` (
                  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `slug`        VARCHAR(100) NOT NULL,
                  `name_th`     VARCHAR(100) NOT NULL,
                  `name_en`     VARCHAR(100) NULL,
                  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
                  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
                  `created_at`  DATETIME NULL,
                  `updated_at`  DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_news_categories_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        if (! $this->db->tableExists('news', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `news` (
                  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `category_id`     INT UNSIGNED NOT NULL,
                  `slug`            VARCHAR(200) NOT NULL,
                  `title_th`        VARCHAR(255) NOT NULL,
                  `title_en`        VARCHAR(255) NULL,
                  `excerpt_th`      VARCHAR(500) NULL,
                  `excerpt_en`      VARCHAR(500) NULL,
                  `content_th`      MEDIUMTEXT   NOT NULL,
                  `content_en`      MEDIUMTEXT   NULL,
                  `cover_image`     VARCHAR(255) NOT NULL,
                  `cover_thumb`     VARCHAR(255) NULL,
                  `cover_alt_th`    VARCHAR(255) NULL,
                  `cover_alt_en`    VARCHAR(255) NULL,
                  `status`          ENUM('draft','published','hidden') NOT NULL DEFAULT 'draft',
                  `published_at`    DATETIME     NULL,
                  `is_pinned`       TINYINT(1)   NOT NULL DEFAULT 0,
                  `view_count`      INT UNSIGNED NOT NULL DEFAULT 0,
                  `created_by`      BIGINT UNSIGNED NULL,
                  `updated_by`      BIGINT UNSIGNED NULL,
                  `created_at`      DATETIME NULL,
                  `updated_at`      DATETIME NULL,
                  `deleted_at`      DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_news_slug` (`slug`),
                  KEY `idx_news_list` (`status`, `published_at`),
                  KEY `idx_news_category` (`category_id`),
                  FULLTEXT KEY `ft_news_title` (`title_th`, `title_en`),
                  CONSTRAINT `fk_news_category`   FOREIGN KEY (`category_id`) REFERENCES `news_categories` (`id`) ON DELETE RESTRICT,
                  CONSTRAINT `fk_news_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `fk_news_updated_by` FOREIGN KEY (`updated_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `chk_news_published` CHECK (`status` <> 'published' OR `published_at` IS NOT NULL)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        if (! $this->db->tableExists('news_images', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `news_images` (
                  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `news_id`     BIGINT UNSIGNED NOT NULL,
                  `image_path`  VARCHAR(255) NOT NULL,
                  `thumb_path`  VARCHAR(255) NOT NULL,
                  `caption_th`  VARCHAR(255) NULL,
                  `caption_en`  VARCHAR(255) NULL,
                  `width`       SMALLINT UNSIGNED NULL,
                  `height`      SMALLINT UNSIGNED NULL,
                  `file_size`   INT UNSIGNED NULL,
                  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
                  `created_at`  DATETIME NULL,
                  `updated_at`  DATETIME NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_news_images_news` (`news_id`, `sort_order`),
                  CONSTRAINT `fk_news_images_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        if (! $this->db->tableExists('news_attachments', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `news_attachments` (
                  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `news_id`        BIGINT UNSIGNED NOT NULL,
                  `title_th`       VARCHAR(255) NOT NULL,
                  `title_en`       VARCHAR(255) NULL,
                  `file_path`      VARCHAR(255) NOT NULL,
                  `original_name`  VARCHAR(255) NOT NULL,
                  `mime_type`      VARCHAR(100) NOT NULL,
                  `file_size`      INT UNSIGNED NOT NULL,
                  `scan_status`    ENUM('pending','clean','infected','failed') NOT NULL DEFAULT 'pending',
                  `sort_order`     INT UNSIGNED NOT NULL DEFAULT 0,
                  `created_at`     DATETIME NULL,
                  `updated_at`     DATETIME NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_news_att_news` (`news_id`, `sort_order`),
                  CONSTRAINT `fk_news_att_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        $exists = $this->db->query(
            "SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'slides' AND CONSTRAINT_NAME = 'fk_slides_news'",
        )->getRowArray();

        if ((int) ($exists['n'] ?? 0) === 0) {
            $this->db->query(<<<'SQL'
                ALTER TABLE `slides` ADD CONSTRAINT `fk_slides_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE SET NULL
                SQL);
        }
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->query('ALTER TABLE `slides` DROP FOREIGN KEY `fk_slides_news`');
        $this->db->query('DROP TABLE IF EXISTS `news_attachments`');
        $this->db->query('DROP TABLE IF EXISTS `news_images`');
        $this->db->query('DROP TABLE IF EXISTS `news`');
        $this->db->query('DROP TABLE IF EXISTS `news_categories`');
        $this->db->enableForeignKeyChecks();
    }
}

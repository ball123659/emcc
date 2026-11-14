<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDownloadTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('download_categories', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `download_categories` (
                  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `slug`        VARCHAR(100) NOT NULL,
                  `name_th`     VARCHAR(100) NOT NULL,
                  `name_en`     VARCHAR(100) NULL,
                  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
                  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
                  `created_at`  DATETIME NULL,
                  `updated_at`  DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_download_categories_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        if (! $this->db->tableExists('downloads', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `downloads` (
                  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `category_id`     INT UNSIGNED NOT NULL,
                  `title_th`        VARCHAR(255) NOT NULL,
                  `title_en`        VARCHAR(255) NULL,
                  `description_th`  TEXT NULL,
                  `description_en`  TEXT NULL,
                  `type`            ENUM('file','link') NOT NULL DEFAULT 'file',
                  `file_path`       VARCHAR(255) NULL,
                  `original_name`   VARCHAR(255) NULL,
                  `file_extension`  VARCHAR(10)  NULL,
                  `mime_type`       VARCHAR(100) NULL,
                  `file_size`       BIGINT UNSIGNED NULL,
                  `file_hash`       CHAR(64)     NULL,
                  `scan_status`     ENUM('pending','clean','infected','failed') NULL,
                  `scanned_at`      DATETIME NULL,
                  `external_url`    VARCHAR(500) NULL,
                  `download_count`  INT UNSIGNED NOT NULL DEFAULT 0,
                  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
                  `sort_order`      INT UNSIGNED NOT NULL DEFAULT 0,
                  `created_by`      BIGINT UNSIGNED NULL,
                  `updated_by`      BIGINT UNSIGNED NULL,
                  `created_at`      DATETIME NULL,
                  `updated_at`      DATETIME NULL,
                  `deleted_at`      DATETIME NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_downloads_list` (`category_id`, `is_active`, `sort_order`),
                  KEY `idx_downloads_hash` (`file_hash`),
                  CONSTRAINT `fk_downloads_category`   FOREIGN KEY (`category_id`) REFERENCES `download_categories` (`id`) ON DELETE RESTRICT,
                  CONSTRAINT `fk_downloads_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `fk_downloads_updated_by` FOREIGN KEY (`updated_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `chk_downloads_type` CHECK (
                       (`type` = 'file' AND `file_path` IS NOT NULL AND `external_url` IS NULL)
                    OR (`type` = 'link' AND `external_url` IS NOT NULL AND `file_path` IS NULL)
                  )
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->query('DROP TABLE IF EXISTS `downloads`');
        $this->db->query('DROP TABLE IF EXISTS `download_categories`');
        $this->db->enableForeignKeyChecks();
    }
}

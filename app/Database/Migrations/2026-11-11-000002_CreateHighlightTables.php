<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Slide Show / Popup — โครงสร้างต้องตรงกับ database/webemcc.sql (CI ตรวจเทียบทุกครั้ง)
 */
class CreateHighlightTables extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE `slides` (
              `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `title`           VARCHAR(200) NOT NULL COMMENT 'ชื่อเรียกในหลังบ้าน (ไม่แสดงหน้าเว็บ)',
              `image_th`        VARCHAR(255) NOT NULL COMMENT 'path รูปภาษาไทย (บังคับ)',
              `image_en`        VARCHAR(255) NULL     COMMENT 'path รูปภาษาอังกฤษ (ว่าง = ใช้รูปไทย)',
              `image_mobile`    VARCHAR(255) NULL     COMMENT 'รูปสำหรับจอมือถือ (ไม่บังคับ)',
              `alt_th`          VARCHAR(255) NOT NULL COMMENT 'คำอธิบายรูป สำหรับผู้พิการทางสายตา',
              `alt_en`          VARCHAR(255) NULL,
              `link_type`       ENUM('none','news','url') NOT NULL DEFAULT 'none',
              `news_id`         BIGINT UNSIGNED NULL  COMMENT 'ใช้เมื่อ link_type = news',
              `link_url`        VARCHAR(500) NULL     COMMENT 'ใช้เมื่อ link_type = url (http/https เท่านั้น)',
              `open_new_tab`    TINYINT(1)   NOT NULL DEFAULT 1,
              `start_date`      DATE NULL COMMENT 'ว่าง = แสดงทันที',
              `end_date`        DATE NULL COMMENT 'ว่าง = ไม่มีวันหมดอายุ',
              `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
              `sort_order`      INT UNSIGNED NOT NULL DEFAULT 0,
              `created_by`      BIGINT UNSIGNED NULL,
              `updated_by`      BIGINT UNSIGNED NULL,
              `created_at`      DATETIME NULL,
              `updated_at`      DATETIME NULL,
              `deleted_at`      DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `idx_slides_show` (`is_active`, `start_date`, `end_date`, `sort_order`),
              KEY `idx_slides_news` (`news_id`),
              CONSTRAINT `fk_slides_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_slides_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
              CONSTRAINT `chk_slides_dates` CHECK (`end_date` IS NULL OR `start_date` IS NULL OR `end_date` >= `start_date`),
              CONSTRAINT `chk_slides_link`  CHECK (`link_type` <> 'url' OR `link_url` IS NOT NULL)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='สไลด์โชว์หน้าแรก'
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE `popups` (
              `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `title`           VARCHAR(200) NOT NULL COMMENT 'ชื่อเรียกในหลังบ้าน',
              `image_th`        VARCHAR(255) NOT NULL,
              `image_en`        VARCHAR(255) NULL,
              `alt_th`          VARCHAR(255) NOT NULL,
              `alt_en`          VARCHAR(255) NULL,
              `link_url`        VARCHAR(500) NULL COMMENT 'ไม่บังคับ (http/https เท่านั้น)',
              `open_new_tab`    TINYINT(1)   NOT NULL DEFAULT 1,
              `start_date`      DATE NOT NULL,
              `end_date`        DATE NOT NULL,
              `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
              `sort_order`      INT UNSIGNED NOT NULL DEFAULT 0,
              `created_by`      BIGINT UNSIGNED NULL,
              `updated_by`      BIGINT UNSIGNED NULL,
              `created_at`      DATETIME NULL,
              `updated_at`      DATETIME NULL,
              `deleted_at`      DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `idx_popups_show` (`is_active`, `start_date`, `end_date`, `sort_order`),
              CONSTRAINT `fk_popups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_popups_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
              CONSTRAINT `chk_popups_dates` CHECK (`end_date` >= `start_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ป๊อปอัปหน้าเว็บ'
            SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->query('DROP TABLE IF EXISTS `popups`');
        $this->db->query('DROP TABLE IF EXISTS `slides`');
        $this->db->enableForeignKeyChecks();
    }
}

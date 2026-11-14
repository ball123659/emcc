<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ตั้งค่าเว็บไซต์ / บันทึกการใช้งาน — โครงสร้างต้องตรงกับ database/webemcc.sql (CI ตรวจเทียบทุกครั้ง)
 */
class CreateSettingsAndLogTables extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE `site_settings` (
              `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `group`       VARCHAR(50)  NOT NULL DEFAULT 'general',
              `key`         VARCHAR(100) NOT NULL,
              `label_th`    VARCHAR(150) NOT NULL COMMENT 'ชื่อช่องที่แสดงในหลังบ้าน',
              `value_th`    TEXT NULL,
              `value_en`    TEXT NULL,
              `input_type`  ENUM('text','textarea','url','email','phone','image') NOT NULL DEFAULT 'text',
              `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
              `updated_by`  BIGINT UNSIGNED NULL,
              `updated_at`  DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_site_settings_key` (`key`),
              CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตั้งค่าเว็บไซต์'
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE `activity_logs` (
              `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`       BIGINT UNSIGNED NULL COMMENT 'NULL = ไม่ทราบผู้ใช้ เช่น ล็อกอินผิด username',
              `action`        VARCHAR(50)  NOT NULL COMMENT 'login, login_failed, logout, create, update, delete, restore, password_reset, ...',
              `subject_type`  VARCHAR(50)  NULL COMMENT 'ชื่อตาราง เช่น news, users',
              `subject_id`    BIGINT UNSIGNED NULL,
              `description`   VARCHAR(500) NULL,
              `changes`       JSON NULL COMMENT 'ค่าเดิม/ค่าใหม่ (ไม่เก็บรหัสผ่าน)',
              `ip_address`    VARCHAR(45)  NULL,
              `user_agent`    VARCHAR(255) NULL,
              `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_logs_user` (`user_id`, `created_at`),
              KEY `idx_logs_subject` (`subject_type`, `subject_id`),
              KEY `idx_logs_action` (`action`, `created_at`),
              CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='บันทึกการใช้งาน'
            SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->query('DROP TABLE IF EXISTS `activity_logs`');
        $this->db->query('DROP TABLE IF EXISTS `site_settings`');
        $this->db->enableForeignKeyChecks();
    }
}

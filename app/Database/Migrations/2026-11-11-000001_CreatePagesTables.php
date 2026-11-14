<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePagesTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('users', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `users` (
                  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `username`              VARCHAR(50)  NOT NULL,
                  `password`              VARCHAR(255) NOT NULL,
                  `role`                  ENUM('developer','administrator','user') NOT NULL DEFAULT 'user',
                  `first_name`            VARCHAR(100) NOT NULL,
                  `last_name`             VARCHAR(100) NOT NULL,
                  `email`                 VARCHAR(191) NOT NULL,
                  `phone`                 VARCHAR(20)  NULL,
                  `position`              VARCHAR(150) NULL,
                  `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
                  `must_change_password`  TINYINT(1)   NOT NULL DEFAULT 1,
                  `password_changed_at`   DATETIME     NULL,
                  `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                  `locked_until`          DATETIME     NULL,
                  `last_login_at`         DATETIME     NULL,
                  `last_login_ip`         VARCHAR(45)  NULL,
                  `remember_token`        VARCHAR(100) NULL,
                  `created_by`            BIGINT UNSIGNED NULL,
                  `updated_by`            BIGINT UNSIGNED NULL,
                  `created_at`            DATETIME NULL,
                  `updated_at`            DATETIME NULL,
                  `deleted_at`            DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_users_username` (`username`),
                  UNIQUE KEY `uq_users_email` (`email`),
                  KEY `idx_users_role_active` (`role`, `is_active`),
                  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        if (! $this->db->tableExists('pages', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `pages` (
                  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `code`        VARCHAR(50)  NOT NULL,
                  `parent_id`   INT UNSIGNED NULL,
                  `name_th`     VARCHAR(100) NOT NULL,
                  `name_en`     VARCHAR(100) NULL,
                  `icon`        VARCHAR(50)  NULL,
                  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
                  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
                  `admin_only`  TINYINT(1)   NOT NULL DEFAULT 0,
                  `created_at`  DATETIME NULL,
                  `updated_at`  DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_pages_code` (`code`),
                  KEY `idx_pages_parent` (`parent_id`),
                  CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }

        if (! $this->db->tableExists('user_page_permissions', false)) {
            $this->db->query(<<<'SQL'
                CREATE TABLE `user_page_permissions` (
                  `user_id`     BIGINT UNSIGNED NOT NULL,
                  `page_id`     INT UNSIGNED    NOT NULL,
                  `can_view`    TINYINT(1)      NOT NULL DEFAULT 0,
                  `updated_by`  BIGINT UNSIGNED NULL,
                  `updated_at`  DATETIME NULL,
                  PRIMARY KEY (`user_id`, `page_id`),
                  KEY `idx_upp_page` (`page_id`),
                  CONSTRAINT `fk_upp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_upp_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_upp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                SQL);
        }
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->query('DROP TABLE IF EXISTS `user_page_permissions`');
        $this->db->query('DROP TABLE IF EXISTS `pages`');
        $this->db->query('DROP TABLE IF EXISTS `users`');
        $this->db->enableForeignKeyChecks();
    }
}

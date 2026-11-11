-- =====================================================================
--  EMCC Back-office — Database schema
--  Database : webemcc
--  Charset  : utf8mb4 / utf8mb4_general_ci  (รองรับไทย + อังกฤษ + emoji ครบ)
--  Target   : MySQL 8.0.16+ หรือ MariaDB 10.4+  (ต้องการ CHECK constraint และ JSON)
--  Version  : 0.1 (draft)  — 2026-11-11
--
--  หลักการออกแบบ
--  - ไฟล์รูป/เอกสาร "ไม่เก็บในฐานข้อมูล" เก็บบนดิสก์ แล้วเก็บแค่ path ในตาราง
--  - ข้อความที่แสดงบนเว็บมี 2 ภาษา: *_th (บังคับ) และ *_en (ไม่บังคับ — ถ้าว่างใช้ภาษาไทยแทน)
--  - ลบแบบ soft delete (deleted_at) สำหรับข้อมูลหลัก เพื่อกู้คืนได้
--  - created_by / updated_by เก็บว่าใครสร้าง/แก้ล่าสุด
--  - ตารางของระบบแผนที่/จุดตรวจวัด (dev อีกคน) ให้ใช้ prefix  map_  เพื่อไม่ชนกัน
--  - ตารางระบบของ Laravel (sessions, cache, jobs, migrations) จะถูกสร้างโดย
--    `php artisan migrate` ไม่ได้อยู่ในไฟล์นี้
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `webemcc`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE `webemcc`;

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) ผู้ใช้งาน
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`              VARCHAR(50)  NOT NULL COMMENT 'a-z 0-9 . _ - เท่านั้น (ตรวจในแอป)',
  `password`              VARCHAR(255) NOT NULL COMMENT 'bcrypt hash เท่านั้น ห้ามเก็บรหัสจริง',
  `role`                  ENUM('developer','administrator','user') NOT NULL DEFAULT 'user'
                          COMMENT 'developer/administrator เข้าได้ทุกหน้า + จัดการผู้ใช้; user ตามสิทธิ์รายหน้า',
  `first_name`            VARCHAR(100) NOT NULL,
  `last_name`             VARCHAR(100) NOT NULL,
  `email`                 VARCHAR(191) NOT NULL,
  `phone`                 VARCHAR(20)  NULL COMMENT 'เก็บเฉพาะตัวเลข เช่น 0812345678',
  `position`              VARCHAR(150) NULL COMMENT 'ตำแหน่ง',
  `is_active`             TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = ปิดการใช้งาน ล็อกอินไม่ได้',
  `must_change_password`  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = ต้องเปลี่ยนรหัสก่อนใช้งาน',
  `password_changed_at`   DATETIME     NULL,
  `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`          DATETIME     NULL COMMENT 'ล็อกชั่วคราวเมื่อใส่รหัสผิดเกินกำหนด',
  `last_login_at`         DATETIME     NULL,
  `last_login_ip`         VARCHAR(45)  NULL COMMENT 'รองรับ IPv6',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ผู้ใช้งานหลังบ้าน';

-- ---------------------------------------------------------------------
-- 2) หน้า/เมนูหลังบ้าน และสิทธิ์รายหน้า (เห็น / ไม่เห็น)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(50)  NOT NULL COMMENT 'รหัสที่โค้ดใช้อ้างอิง เช่น news, popups',
  `parent_id`   INT UNSIGNED NULL COMMENT 'เมนูแม่ (เช่น Highlight)',
  `name_th`     VARCHAR(100) NOT NULL,
  `name_en`     VARCHAR(100) NULL,
  `icon`        VARCHAR(50)  NULL,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `admin_only`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = เฉพาะ developer/administrator (เช่น จัดการผู้ใช้) ให้สิทธิ์ user ไม่ได้',
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_code` (`code`),
  KEY `idx_pages_parent` (`parent_id`),
  CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='รายการหน้า/เมนูหลังบ้าน';

DROP TABLE IF EXISTS `user_page_permissions`;
CREATE TABLE `user_page_permissions` (
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `page_id`     INT UNSIGNED    NOT NULL,
  `can_view`    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '0 = ไม่เห็น (ค่าเริ่มต้น), 1 = เห็นและใช้งานได้',
  `updated_by`  BIGINT UNSIGNED NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`user_id`, `page_id`),
  KEY `idx_upp_page` (`page_id`),
  CONSTRAINT `fk_upp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_upp_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_upp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='สิทธิ์ผู้ใช้ x หน้า';

-- ---------------------------------------------------------------------
-- 3) Highlight — Slide Show
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `slides`;
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
  -- (link_type = news ต้องมี news_id — ตรวจในแอป เพราะ MySQL ห้าม CHECK บนคอลัมน์ที่มี FK แบบ SET NULL;
  --  ถ้าข่าวถูกลบ news_id จะเป็น NULL และหน้าเว็บจะแสดงสไลด์แบบไม่มีลิงก์)
  CONSTRAINT `chk_slides_link`  CHECK (`link_type` <> 'url' OR `link_url` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='สไลด์โชว์หน้าแรก';

-- ---------------------------------------------------------------------
-- 4) Highlight — Popup
--    หน้าเว็บแสดงเฉพาะ is_active = 1 และ CURDATE() BETWEEN start_date AND end_date
--    เรียงตาม sort_order ปิดอันแรกแล้วอันถัดไปขึ้นต่อ
--    ช่อง "ไม่ต้องแสดงอีกวันนี้" เก็บฝั่งเบราว์เซอร์ (localStorage) ไม่ลงฐานข้อมูล
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `popups`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ป๊อปอัปหน้าเว็บ';

-- ---------------------------------------------------------------------
-- 5) ข่าวประชาสัมพันธ์
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `news_categories`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='หมวดหมู่ข่าว';

DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED NOT NULL,
  `slug`            VARCHAR(200) NOT NULL COMMENT 'ส่วนของ URL เช่น 2026-11-pm25-report',
  `title_th`        VARCHAR(255) NOT NULL,
  `title_en`        VARCHAR(255) NULL,
  `excerpt_th`      VARCHAR(500) NULL COMMENT 'คำโปรยบนการ์ดข่าว',
  `excerpt_en`      VARCHAR(500) NULL,
  `content_th`      MEDIUMTEXT   NOT NULL COMMENT 'HTML ที่ผ่านการกรองแล้ว (กัน XSS)',
  `content_en`      MEDIUMTEXT   NULL,
  `cover_image`     VARCHAR(255) NOT NULL COMMENT 'path รูปปก (ย่อแล้ว)',
  `cover_thumb`     VARCHAR(255) NULL     COMMENT 'path รูปปกขนาดเล็กสำหรับหน้ารายการ',
  `cover_alt_th`    VARCHAR(255) NULL,
  `cover_alt_en`    VARCHAR(255) NULL,
  `status`          ENUM('draft','published','hidden') NOT NULL DEFAULT 'draft',
  `published_at`    DATETIME     NULL COMMENT 'วันเวลาเผยแพร่ (ตั้งล่วงหน้าได้)',
  `is_pinned`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ปักหมุดไว้บนสุด',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ข่าวประชาสัมพันธ์';

DROP TABLE IF EXISTS `news_images`;
CREATE TABLE `news_images` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `news_id`     BIGINT UNSIGNED NOT NULL,
  `image_path`  VARCHAR(255) NOT NULL COMMENT 'ย่อแล้ว (ด้านยาวไม่เกิน 1920px, WebP)',
  `thumb_path`  VARCHAR(255) NOT NULL,
  `caption_th`  VARCHAR(255) NULL,
  `caption_en`  VARCHAR(255) NULL,
  `width`       SMALLINT UNSIGNED NULL,
  `height`      SMALLINT UNSIGNED NULL,
  `file_size`   INT UNSIGNED NULL COMMENT 'ไบต์',
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_news_images_news` (`news_id`, `sort_order`),
  CONSTRAINT `fk_news_images_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='แกลเลอรีรูปของข่าว';

DROP TABLE IF EXISTS `news_attachments`;
CREATE TABLE `news_attachments` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `news_id`        BIGINT UNSIGNED NOT NULL,
  `title_th`       VARCHAR(255) NOT NULL,
  `title_en`       VARCHAR(255) NULL,
  `file_path`      VARCHAR(255) NOT NULL COMMENT 'ชื่อสุ่ม เก็บนอก public',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ไฟล์แนบของข่าว';

-- ---------------------------------------------------------------------
-- 6) ไฟล์ดาวน์โหลด
--    type = file  -> อัปโหลดเข้าระบบ ต้องผ่านการสแกน (scan_status = clean) จึงจะแสดง
--    type = link  -> ลิงก์ภายนอก (Google Drive, YouTube ฯลฯ) ไม่เก็บไฟล์บนเซิร์ฟเวอร์
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `download_categories`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='หมวดหมู่ไฟล์ดาวน์โหลด';

DROP TABLE IF EXISTS `downloads`;
CREATE TABLE `downloads` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED NOT NULL,
  `title_th`        VARCHAR(255) NOT NULL,
  `title_en`        VARCHAR(255) NULL,
  `description_th`  TEXT NULL,
  `description_en`  TEXT NULL,
  `type`            ENUM('file','link') NOT NULL DEFAULT 'file',
  `file_path`       VARCHAR(255) NULL COMMENT 'ชื่อสุ่ม เก็บนอก public',
  `original_name`   VARCHAR(255) NULL COMMENT 'ชื่อไฟล์เดิม ใช้ตอนดาวน์โหลด',
  `file_extension`  VARCHAR(10)  NULL,
  `mime_type`       VARCHAR(100) NULL COMMENT 'ตรวจจากเนื้อไฟล์จริง',
  `file_size`       BIGINT UNSIGNED NULL COMMENT 'ไบต์',
  `file_hash`       CHAR(64)     NULL COMMENT 'SHA-256 ใช้ตรวจไฟล์ซ้ำ/ถูกแก้ไข',
  `scan_status`     ENUM('pending','clean','infected','failed') NULL,
  `scanned_at`      DATETIME NULL,
  `external_url`    VARCHAR(500) NULL COMMENT 'ใช้เมื่อ type = link (https เท่านั้น)',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ไฟล์ดาวน์โหลด';

-- ---------------------------------------------------------------------
-- 7) ตั้งค่าเว็บไซต์ (เบอร์โทร ที่อยู่ โซเชียล ฯลฯ)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `site_settings`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตั้งค่าเว็บไซต์';

-- ---------------------------------------------------------------------
-- 8) Activity Log — ใครทำอะไร เมื่อไร (รวมล็อกอินสำเร็จ/ล้มเหลว)
--    บันทึกอย่างเดียว ไม่มีหน้าแก้ไข/ลบในหลังบ้าน
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='บันทึกการใช้งาน';

-- FK ของ slides -> news (เพิ่มหลังสร้างตาราง news แล้ว)
ALTER TABLE `slides`
  ADD CONSTRAINT `fk_slides_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  Triggers — สร้างสิทธิ์ค่าเริ่มต้น 0 (ไม่เห็น) อัตโนมัติ
--  - เพิ่มหน้าใหม่  -> ผู้ใช้ทุกคนได้แถวสิทธิ์ของหน้านั้น = 0
--  - เพิ่มผู้ใช้ใหม่ -> ได้แถวสิทธิ์ของทุกหน้า = 0
--  หมายเหตุ: ถ้าเซิร์ฟเวอร์เปิด binary log อยู่ ผู้ที่ import อาจต้องมีสิทธิ์
--  SUPER หรือ log_bin_trust_function_creators = 1  (ถ้าสร้างไม่ได้ ระบบ Laravel
--  จะทำแทนในโค้ดได้ — แจ้ง dev)
-- =====================================================================
DROP TRIGGER IF EXISTS `trg_pages_after_insert`;
DROP TRIGGER IF EXISTS `trg_users_after_insert`;

DELIMITER $$
CREATE TRIGGER `trg_pages_after_insert` AFTER INSERT ON `pages`
FOR EACH ROW
BEGIN
  INSERT IGNORE INTO `user_page_permissions` (`user_id`, `page_id`, `can_view`, `updated_at`)
  SELECT u.`id`, NEW.`id`, 0, NOW() FROM `users` u;
END$$

CREATE TRIGGER `trg_users_after_insert` AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
  INSERT IGNORE INTO `user_page_permissions` (`user_id`, `page_id`, `can_view`, `updated_at`)
  SELECT NEW.`id`, p.`id`, 0, NOW() FROM `pages` p;
END$$
DELIMITER ;

-- =====================================================================
--  ข้อมูลเริ่มต้น
-- =====================================================================

-- เมนูหลังบ้าน (ลำดับตาม requirement)
INSERT INTO `pages` (`id`, `code`, `parent_id`, `name_th`, `name_en`, `icon`, `sort_order`, `admin_only`, `created_at`, `updated_at`) VALUES
  (1,  'dashboard',       NULL, 'แดชบอร์ด',            'Dashboard',          'home',      0, 0, NOW(), NOW()),
  (2,  'monitoring',      NULL, 'จุดตรวจวัด',          'Monitoring Points',  'map-pin',  10, 0, NOW(), NOW()),
  (3,  'highlight',       NULL, 'จัดการ Highlight',     'Highlights',         'star',     20, 0, NOW(), NOW()),
  (4,  'slides',          3,    'Slide Show',           'Slide Show',         'photo',    21, 0, NOW(), NOW()),
  (5,  'popups',          3,    'Popup',                'Popup',              'window',   22, 0, NOW(), NOW()),
  (6,  'news',            NULL, 'ข่าวประชาสัมพันธ์',   'News',               'newspaper',30, 0, NOW(), NOW()),
  (7,  'downloads',       NULL, 'ไฟล์ดาวน์โหลด',       'Downloads',          'download', 40, 0, NOW(), NOW()),
  (8,  'site_settings',   NULL, 'ตั้งค่าเว็บไซต์',     'Site Settings',      'cog',      50, 0, NOW(), NOW()),
  (9,  'users',           NULL, 'จัดการผู้ใช้งาน',     'Users',              'users',    60, 1, NOW(), NOW()),
  (10, 'activity_logs',   NULL, 'บันทึกการใช้งาน',     'Activity Log',       'clock',    70, 1, NOW(), NOW());

-- บัญชี Developer เริ่มต้น  (username: developer / password: passwordemcc)
-- must_change_password = 1  -> ล็อกอินครั้งแรกระบบบังคับเปลี่ยนรหัสทันที
-- !! แก้ email ให้เป็นของจริงก่อนใช้งาน !!
INSERT INTO `users` (`id`, `username`, `password`, `role`, `first_name`, `last_name`, `email`, `position`, `is_active`, `must_change_password`, `created_at`, `updated_at`) VALUES
  (1, 'developer', '$2y$12$J.4vJLKkIgbdEsHCtLcn8.zlFBgbqQUMOomq7y9f60QDVBpwMvUze', 'developer',
   'ผู้พัฒนา', 'ระบบ', 'developer@example.com', 'Developer', 1, 1, NOW(), NOW());

-- หมวดหมู่เริ่มต้น (แก้/เพิ่มได้ในหลังบ้าน)
INSERT INTO `news_categories` (`slug`, `name_th`, `name_en`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('news',         'ข่าวประชาสัมพันธ์', 'News',          1, NOW(), NOW()),
  ('activities',   'กิจกรรม',           'Activities',    2, NOW(), NOW()),
  ('announcements','ประกาศ',            'Announcements', 3, NOW(), NOW());

INSERT INTO `download_categories` (`slug`, `name_th`, `name_en`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('documents', 'เอกสารเผยแพร่', 'Documents', 1, NOW(), NOW()),
  ('reports',   'รายงาน',        'Reports',   2, NOW(), NOW()),
  ('media',     'สื่อ',          'Media',     3, NOW(), NOW());

-- ค่าตั้งค่าเว็บไซต์ (ค่าจาก template)
INSERT INTO `site_settings` (`group`, `key`, `label_th`, `value_th`, `value_en`, `input_type`, `sort_order`) VALUES
  ('contact', 'phone',        'เบอร์โทรศัพท์',        '038-683933 / 081-7323485', '038-683933 / 081-7323485', 'phone',    1),
  ('contact', 'email',        'อีเมล',                NULL, NULL, 'email',    2),
  ('contact', 'address',      'ที่อยู่',              NULL, NULL, 'textarea', 3),
  ('contact', 'map_embed_url','ลิงก์แผนที่ (Google Maps)', NULL, NULL, 'url', 4),
  ('social',  'facebook_url', 'Facebook',             NULL, NULL, 'url',      10),
  ('social',  'line_url',     'LINE Official',        NULL, NULL, 'url',      11),
  ('social',  'youtube_url',  'YouTube',              NULL, NULL, 'url',      12),
  ('general', 'site_name',    'ชื่อเว็บไซต์',         'ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', 'Environmental Monitoring & Control Center', 'text', 20),
  ('general', 'footer_text',  'ข้อความท้ายเว็บ',      NULL, NULL, 'textarea', 21);

-- =====================================================================
--  Query ตัวอย่างสำหรับหน้าเว็บ (อ้างอิง)
-- =====================================================================
-- Popup ที่ต้องแสดงวันนี้ (เรียงตามลำดับ):
--   SELECT * FROM popups
--   WHERE is_active = 1 AND deleted_at IS NULL
--     AND CURDATE() BETWEEN start_date AND end_date
--   ORDER BY sort_order, id;
--
-- สไลด์ที่ต้องแสดงวันนี้:
--   SELECT * FROM slides
--   WHERE is_active = 1 AND deleted_at IS NULL
--     AND (start_date IS NULL OR start_date <= CURDATE())
--     AND (end_date   IS NULL OR end_date   >= CURDATE())
--   ORDER BY sort_order, id;
--
-- ข่าวที่เผยแพร่แล้ว (ปักหมุดขึ้นก่อน):
--   SELECT * FROM news
--   WHERE status = 'published' AND published_at <= NOW() AND deleted_at IS NULL
--   ORDER BY is_pinned DESC, published_at DESC;

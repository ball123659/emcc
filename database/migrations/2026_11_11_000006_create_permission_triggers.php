<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Triggers สร้างสิทธิ์ค่าเริ่มต้น 0 (ไม่เห็น) อัตโนมัติ — ตรงกับ webemcc.sql
 *   - เพิ่มหน้าใหม่  -> ผู้ใช้ทุกคนได้แถวสิทธิ์ของหน้านั้น = 0
 *   - เพิ่มผู้ใช้ใหม่ -> ได้แถวสิทธิ์ของทุกหน้า = 0
 *
 * ถ้าสร้าง trigger ไม่ได้ (เช่น เปิด binary log แต่ไม่มีสิทธิ์ SUPER)
 * ระบบยังทำงานได้ เพราะ Model Page/User สร้างแถวสิทธิ์ในโค้ดด้วย (INSERT IGNORE ซ้ำได้)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            DB::unprepared('DROP TRIGGER IF EXISTS `trg_pages_after_insert`');
            DB::unprepared('DROP TRIGGER IF EXISTS `trg_users_after_insert`');

            DB::unprepared('
                CREATE TRIGGER `trg_pages_after_insert` AFTER INSERT ON `pages`
                FOR EACH ROW
                BEGIN
                  INSERT IGNORE INTO `user_page_permissions` (`user_id`, `page_id`, `can_view`, `updated_at`)
                  SELECT u.`id`, NEW.`id`, 0, NOW() FROM `users` u;
                END
            ');

            DB::unprepared('
                CREATE TRIGGER `trg_users_after_insert` AFTER INSERT ON `users`
                FOR EACH ROW
                BEGIN
                  INSERT IGNORE INTO `user_page_permissions` (`user_id`, `page_id`, `can_view`, `updated_at`)
                  SELECT NEW.`id`, p.`id`, 0, NOW() FROM `pages` p;
                END
            ');
        } catch (Throwable $e) {
            Log::warning('สร้าง trigger สิทธิ์รายหน้าไม่ได้ — ใช้การสร้างสิทธิ์ในโค้ดแทน: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `trg_pages_after_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_users_after_insert`');
    }
};

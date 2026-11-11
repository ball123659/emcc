<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ลบ trigger สิทธิ์รายหน้าออกจากฐานที่เคยสร้างไว้ (จาก webemcc.sql รุ่นเก่า หรือ migration 000006 เดิม)
 * เหตุผล: MariaDB 10.4 (XAMPP) crash ตอน INSERT users ใน execute_trigger
 * แถวสิทธิ์ค่าเริ่มต้น 0 สร้างในโค้ดแทน และเติมแถวที่อาจขาดให้ครบในขั้นตอนนี้
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS `trg_pages_after_insert`');
            DB::unprepared('DROP TRIGGER IF EXISTS `trg_users_after_insert`');
        }

        // เติมแถวสิทธิ์ที่อาจขาด (ผู้ใช้ทุกคน x ทุกหน้า = 0) — ไม่แตะแถวที่มีอยู่แล้ว
        DB::table('user_page_permissions')->insertUsing(
            ['user_id', 'page_id', 'can_view', 'updated_at'],
            DB::table('users')
                ->crossJoin('pages')
                ->leftJoin('user_page_permissions as upp', fn ($join) => $join
                    ->on('upp.user_id', '=', 'users.id')
                    ->on('upp.page_id', '=', 'pages.id'))
                ->whereNull('upp.user_id')
                ->select(['users.id', 'pages.id', DB::raw('0'), DB::raw('CURRENT_TIMESTAMP')]),
        );
    }

    public function down(): void
    {
        // ไม่สร้าง trigger กลับ (ตั้งใจเลิกใช้)
    }
};

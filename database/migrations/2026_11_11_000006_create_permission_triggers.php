<?php

use Illuminate\Database\Migrations\Migration;

/**
 * (เดิม) สร้าง trigger สิทธิ์รายหน้า — ยกเลิกแล้ว
 * MariaDB 10.4 (XAMPP) crash ตอน INSERT users ใน execute_trigger -> fill_effective_table_privileges
 * แถวสิทธิ์ค่าเริ่มต้น 0 สร้างในโค้ดแทน (Model User / Page — App\Models\UserPagePermission)
 * ไฟล์นี้คงไว้เพื่อให้ประวัติ migration ของฐานเดิมตรงกัน ฐานที่เคยมี trigger ถูกลบโดย
 * 2026_11_18_000001_drop_permission_triggers
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};

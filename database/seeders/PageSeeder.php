<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * เมนูหลังบ้าน (ลำดับตาม requirement) — pages.code ใช้อ้างอิงสิทธิ์รายหน้าในโค้ด
 */
class PageSeeder extends Seeder
{
    public const PAGES = [
        // id, code, parent_id, name_th, name_en, icon, sort_order, admin_only
        [1, 'dashboard', null, 'แดชบอร์ด', 'Dashboard', 'home', 0, false],
        [2, 'monitoring', null, 'จุดตรวจวัด', 'Monitoring Points', 'map-pin', 10, false],
        [3, 'highlight', null, 'จัดการ Highlight', 'Highlights', 'star', 20, false],
        [4, 'slides', 3, 'Slide Show', 'Slide Show', 'photo', 21, false],
        [5, 'popups', 3, 'Popup', 'Popup', 'window', 22, false],
        [6, 'news', null, 'ข่าวประชาสัมพันธ์', 'News', 'newspaper', 30, false],
        [7, 'downloads', null, 'ไฟล์ดาวน์โหลด', 'Downloads', 'download', 40, false],
        [8, 'site_settings', null, 'ตั้งค่าเว็บไซต์', 'Site Settings', 'cog', 50, false],
        [9, 'users', null, 'จัดการผู้ใช้งาน', 'Users', 'users', 60, true],
        [10, 'activity_logs', null, 'บันทึกการใช้งาน', 'Activity Log', 'clock', 70, true],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::PAGES as [$id, $code, $parentId, $nameTh, $nameEn, $icon, $sortOrder, $adminOnly]) {
            DB::table('pages')->insertOrIgnore([
                'id' => $id,
                'code' => $code,
                'parent_id' => $parentId,
                'name_th' => $nameTh,
                'name_en' => $nameEn,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'admin_only' => $adminOnly,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

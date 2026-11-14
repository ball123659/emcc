<?php

namespace App\Database\Seeds;

use App\Models\UserPagePermissionModel;
use CodeIgniter\Database\Seeder;

/** หน้า/เมนูหลังบ้าน (ตรงกับ database/webemcc.sql) */
class PageSeeder extends Seeder
{
    public const PAGES = [
        [1, 'dashboard', null, 'แดชบอร์ด', 'Dashboard', 'home', 0, 0],
        [2, 'monitoring', null, 'จุดตรวจวัด', 'Monitoring Points', 'map-pin', 10, 0],
        [3, 'highlight', null, 'จัดการ Highlight', 'Highlights', 'star', 20, 0],
        [4, 'slides', 3, 'Slide Show', 'Slide Show', 'photo', 21, 0],
        [5, 'popups', 3, 'Popup', 'Popup', 'window', 22, 0],
        [6, 'news', null, 'ข่าวประชาสัมพันธ์', 'News', 'newspaper', 30, 0],
        [7, 'downloads', null, 'ไฟล์ดาวน์โหลด', 'Downloads', 'download', 40, 0],
        [8, 'site_settings', null, 'ตั้งค่าเว็บไซต์', 'Site Settings', 'cog', 50, 0],
        [9, 'users', null, 'จัดการผู้ใช้งาน', 'Users', 'users', 60, 1],
        [10, 'activity_logs', null, 'บันทึกการใช้งาน', 'Activity Log', 'clock', 70, 1],
    ];

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (self::PAGES as [$id, $code, $parent, $th, $en, $icon, $sort, $adminOnly]) {
            if ($this->db->table('pages')->where('code', $code)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('pages')->insert([
                'id' => $id, 'code' => $code, 'parent_id' => $parent, 'name_th' => $th, 'name_en' => $en, 'icon' => $icon,
                'sort_order' => $sort, 'admin_only' => $adminOnly, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        UserPagePermissionModel::fillMissing();
    }
}

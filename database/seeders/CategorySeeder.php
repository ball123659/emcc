<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * หมวดหมู่เริ่มต้นของข่าวและไฟล์ดาวน์โหลด (แก้/เพิ่มได้ในหลังบ้าน)
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $news = [
            ['news', 'ข่าวประชาสัมพันธ์', 'News', 1],
            ['activities', 'กิจกรรม', 'Activities', 2],
            ['announcements', 'ประกาศ', 'Announcements', 3],
        ];

        $downloads = [
            ['documents', 'เอกสารเผยแพร่', 'Documents', 1],
            ['reports', 'รายงาน', 'Reports', 2],
            ['media', 'สื่อ', 'Media', 3],
        ];

        foreach (['news_categories' => $news, 'download_categories' => $downloads] as $table => $rows) {
            foreach ($rows as [$slug, $nameTh, $nameEn, $sortOrder]) {
                DB::table($table)->insertOrIgnore([
                    'slug' => $slug,
                    'name_th' => $nameTh,
                    'name_en' => $nameEn,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}

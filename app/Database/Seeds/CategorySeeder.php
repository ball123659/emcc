<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** หมวดหมู่เริ่มต้น (แก้/เพิ่มได้ในหลังบ้าน) */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $sets = [
            'news_categories' => [
                ['news', 'ข่าวประชาสัมพันธ์', 'News'],
                ['activities', 'กิจกรรม', 'Activities'],
                ['announcements', 'ประกาศ', 'Announcements'],
            ],
            'download_categories' => [
                ['documents', 'เอกสารเผยแพร่', 'Documents'],
                ['reports', 'รายงาน', 'Reports'],
                ['media', 'สื่อ', 'Media'],
            ],
        ];

        foreach ($sets as $table => $rows) {
            if ($this->db->table($table)->countAllResults() > 0) {
                continue;
            }

            foreach ($rows as $i => [$slug, $th, $en]) {
                $this->db->table($table)->insert([
                    'slug' => $slug, 'name_th' => $th, 'name_en' => $en, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
}

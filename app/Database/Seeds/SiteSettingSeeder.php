<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public const SETTINGS = [
        ['contact', 'phone', 'เบอร์โทรศัพท์', '038-683933 / 081-7323485', '038-683933 / 081-7323485', 'phone', 1],
        ['contact', 'email', 'อีเมล', null, null, 'email', 2],
        ['contact', 'address', 'ที่อยู่', null, null, 'textarea', 3],
        ['contact', 'map_embed_url', 'ลิงก์แผนที่ (Google Maps)', null, null, 'url', 4],
        ['social', 'facebook_url', 'Facebook', null, null, 'url', 10],
        ['social', 'line_url', 'LINE Official', null, null, 'url', 11],
        ['social', 'youtube_url', 'YouTube', null, null, 'url', 12],
        ['general', 'site_name', 'ชื่อเว็บไซต์', 'ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', 'Environmental Monitoring & Control Center', 'text', 20],
        ['general', 'footer_text', 'ข้อความท้ายเว็บ', null, null, 'textarea', 21],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as [$group, $key, $label, $th, $en, $type, $sort]) {
            if ($this->db->table('site_settings')->where('key', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('site_settings')->insert([
                'group' => $group, 'key' => $key, 'label_th' => $label, 'value_th' => $th, 'value_en' => $en,
                'input_type' => $type, 'sort_order' => $sort,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ค่าตั้งค่าเว็บไซต์เริ่มต้น (ค่าจาก template)
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // group, key, label_th, value_th, value_en, input_type, sort_order
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

        foreach ($settings as [$group, $key, $label, $valueTh, $valueEn, $inputType, $sortOrder]) {
            DB::table('site_settings')->insertOrIgnore([
                'group' => $group,
                'key' => $key,
                'label_th' => $label,
                'value_th' => $valueTh,
                'value_en' => $valueEn,
                'input_type' => $inputType,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}

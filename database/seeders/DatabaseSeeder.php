<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ข้อมูลเริ่มต้นให้ตรงกับส่วน "ข้อมูลเริ่มต้น" ใน database/webemcc.sql
 * รันซ้ำได้ (ไม่ทับข้อมูลเดิม) — ใช้ได้ทั้งฐานที่ migrate ใหม่ และฐานที่ import SQL มาแล้ว
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PageSeeder::class,
            DeveloperUserSeeder::class,
            CategorySeeder::class,
            SiteSettingSeeder::class,
        ]);
    }
}

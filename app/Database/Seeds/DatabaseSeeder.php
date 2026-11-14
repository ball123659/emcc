<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * ข้อมูลเริ่มต้น (รันซ้ำได้): php spark db:seed DatabaseSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PageSeeder::class);
        $this->call(DeveloperUserSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(SiteSettingSeeder::class);
    }
}

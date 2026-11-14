<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

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

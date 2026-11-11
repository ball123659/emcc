<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserPagePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * บัญชี Developer เริ่มต้น (username: developer / รหัส: ค่าเริ่มต้นใน config/emcc.php)
 * must_change_password = 1 -> ล็อกอินครั้งแรกต้องเปลี่ยนรหัส
 * !! แก้ email ให้เป็นของจริงก่อนใช้งาน !!
 */
class DeveloperUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('users')->insertOrIgnore([
            'id' => 1,
            'username' => 'developer',
            'password' => Hash::make(config('emcc.default_password')),
            'role' => User::ROLE_DEVELOPER,
            'first_name' => 'ผู้พัฒนา',
            'last_name' => 'ระบบ',
            'email' => 'developer@example.com',
            'position' => 'Developer',
            'is_active' => true,
            'must_change_password' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // insert ผ่าน query builder ไม่มี model event -> เติมแถวสิทธิ์ 0 ให้ครบ (ผู้ใช้ทุกคน x ทุกหน้า)
        UserPagePermission::fillMissing();
    }
}

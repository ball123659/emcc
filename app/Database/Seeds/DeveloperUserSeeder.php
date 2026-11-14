<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use App\Models\UserPagePermissionModel;
use CodeIgniter\Database\Seeder;
use Config\Emcc;

/**
 * บัญชี Developer เริ่มต้น (username: developer / รหัสผ่านเริ่มต้น) — ล็อกอินครั้งแรกระบบบังคับเปลี่ยนรหัส
 * !! แก้ email ให้เป็นของจริงหลังติดตั้ง !!
 */
class DeveloperUserSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('users')->where('username', 'developer')->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'username'             => 'developer',
            'password'             => UserModel::hashPassword(config(Emcc::class)->defaultPassword),
            'role'                 => UserModel::ROLE_DEVELOPER,
            'first_name'           => 'ผู้พัฒนา',
            'last_name'            => 'ระบบ',
            'email'                => 'developer@example.com',
            'position'             => 'Developer',
            'is_active'            => 1,
            'must_change_password' => 1,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        UserPagePermissionModel::fillMissing();
    }
}

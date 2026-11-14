<?php

namespace App\Commands;

use App\Models\UserPagePermissionModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark emcc:fill-permissions — เติมแถวสิทธิ์ (ค่า 0) ที่ขาด หลัง insert users/pages ด้วย SQL ตรง ๆ
 * (ระบบไม่ใช้ trigger — MariaDB 10.4 crash)
 */
class FillPermissions extends BaseCommand
{
    protected $group       = 'EMCC';
    protected $name        = 'emcc:fill-permissions';
    protected $description = 'เติมแถวสิทธิ์รายหน้า (ค่าเริ่มต้น 0) ที่ขาดให้ผู้ใช้ทุกคน x ทุกหน้า';

    public function run(array $params)
    {
        CLI::write('เพิ่มแถวสิทธิ์ ' . UserPagePermissionModel::fillMissing() . ' แถว', 'green');

        return EXIT_SUCCESS;
    }
}

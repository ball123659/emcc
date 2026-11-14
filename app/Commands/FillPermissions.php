<?php

namespace App\Commands;

use App\Models\UserPagePermissionModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

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

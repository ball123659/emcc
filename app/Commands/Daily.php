<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Emcc;

/**
 * php spark emcc:daily — งานประจำวัน (ตั้งใน Windows Task Scheduler / cron วันละครั้ง)
 * - ลบบันทึกการใช้งานที่เก่ากว่า emcc.activityLogRetentionDays (อย่างน้อย 30 วัน)
 * - ลบไฟล์ session / cache ที่หมดอายุ
 */
class Daily extends BaseCommand
{
    protected $group       = 'EMCC';
    protected $name        = 'emcc:daily';
    protected $description = 'งานประจำวัน: ลบบันทึกการใช้งานเก่า และไฟล์ชั่วคราวที่หมดอายุ';

    public function run(array $params)
    {
        $days   = max(30, config(Emcc::class)->activityLogRetentionDays);
        $before = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $db     = db_connect();
        $db->table('activity_logs')->where('created_at <', $before)->delete();
        CLI::write('ลบบันทึกการใช้งานก่อน ' . $before . ' แล้ว ' . $db->affectedRows() . ' รายการ');

        cache()->clean();
        $removed = 0;

        foreach (glob(WRITEPATH . 'session/emcc_session*') ?: [] as $file) {
            if (filemtime($file) < time() - 86400 * 31) {
                @unlink($file) && $removed++;
            }
        }

        CLI::write("ลบไฟล์ session เก่า {$removed} ไฟล์", 'green');

        return EXIT_SUCCESS;
    }
}

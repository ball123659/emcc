<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Emcc;

/**
 * php spark emcc:daily — งานประจำวัน (ตั้งใน Windows Task Scheduler / cron วันละครั้ง)
 * - ลบบันทึกการใช้งานที่เก่ากว่า emcc.activityLogRetentionDays (อย่างน้อย 30 วัน)
 * - ลบ cache ที่หมดอายุแล้ว (ไม่ล้างทั้งหมด — ตัวนับการล็อกบัญชียังอยู่)
 * - ลบไฟล์ session เก่า และ log ที่เก่ากว่า 30 วัน
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

        // cache: อ่านทีละคีย์ — คีย์ที่หมดอายุจะถูกลบเอง
        $expired = 0;

        foreach (array_keys((array) cache()->getCacheInfo()) as $key) {
            $meta = cache()->getMetaData((string) $key);

            if (is_array($meta) && ($meta['expire'] ?? null) !== null && $meta['expire'] < time()) {
                cache()->delete((string) $key);
                $expired++;
            }
        }

        CLI::write("ลบ cache ที่หมดอายุ {$expired} รายการ");

        $logs = 0;

        foreach (glob(WRITEPATH . 'logs/log-*.log') ?: [] as $file) {
            if (filemtime($file) < time() - 86400 * 30) {
                @unlink($file) && $logs++;
            }
        }

        CLI::write("ลบไฟล์ log เก่ากว่า 30 วัน {$logs} ไฟล์");

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

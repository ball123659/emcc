<?php

namespace App\Libraries\VirusScan;

class NullScanner implements VirusScanner
{

    public static string $environment = ENVIRONMENT;

    public static function blocksUploads(): bool
    {
        return self::$environment === 'production';
    }

    public function scan(string $absolutePath): ScanResult
    {
        if (self::blocksUploads()) {
            log_message('error', 'ยังไม่ได้ตั้งค่าโปรแกรมสแกนไวรัส (emcc.virusScanner) บนโปรดักชัน — ปฏิเสธไฟล์อัปโหลด');

            return ScanResult::Failed;
        }

        return is_file($absolutePath) ? ScanResult::Clean : ScanResult::Failed;
    }
}

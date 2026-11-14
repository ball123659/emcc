<?php

namespace App\Libraries\VirusScan;

/**
 * ไม่สแกน (ใช้บนเครื่อง dev เท่านั้น)
 * โปรดักชันที่ยังไม่ได้ตั้งตัวสแกน -> ไม่รับไฟล์อัปโหลดเลย (กันลืมตั้งค่า)
 */
class NullScanner implements VirusScanner
{
    /** สภาพแวดล้อมที่ใช้ตัดสิน (เปลี่ยนได้เฉพาะในเทสต์) */
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

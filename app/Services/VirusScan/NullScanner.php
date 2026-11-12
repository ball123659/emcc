<?php

namespace App\Services\VirusScan;

use Illuminate\Support\Facades\Log;

/**
 * ไม่สแกนจริง — ใช้ได้เฉพาะเครื่องพัฒนา
 * โปรดักชันที่ยังไม่ได้ตั้งโปรแกรมสแกน (VIRUS_SCANNER=none) -> ไม่รับไฟล์ (fail closed)
 */
class NullScanner implements VirusScanner
{
    public static function blocksUploads(): bool
    {
        return app()->isProduction();
    }

    public function scan(string $absolutePath): ScanResult
    {
        if (static::blocksUploads()) {
            Log::error('ปฏิเสธไฟล์อัปโหลด: โปรดักชันยังไม่ได้ตั้งโปรแกรมสแกนไวรัส (VIRUS_SCANNER=none)');

            return ScanResult::Failed;
        }

        return ScanResult::Clean;
    }
}

<?php

namespace App\Services\VirusScan;

use Illuminate\Support\Facades\Log;

/**
 * ไม่สแกนจริง — สำหรับเครื่องพัฒนา ห้ามใช้ในโปรดักชัน (จะเขียน warning ลง log)
 */
class NullScanner implements VirusScanner
{
    public function scan(string $absolutePath): ScanResult
    {
        if (app()->isProduction()) {
            Log::warning('VIRUS_SCANNER=none ในโปรดักชัน — ไฟล์อัปโหลดไม่ได้ถูกสแกนไวรัส');
        }

        return ScanResult::Clean;
    }
}

<?php

namespace App\Services\VirusScan;

/**
 * สแกนไวรัสไฟล์อัปโหลด — สลับ driver ได้ใน config/emcc.php (virus_scanner.driver)
 *   defender : Windows Server (Microsoft Defender MpCmdRun.exe)
 *   clamav   : Linux (clamdscan / clamscan)
 *   none     : ไม่สแกน (ใช้ตอนพัฒนาเท่านั้น)
 */
interface VirusScanner
{
    public function scan(string $absolutePath): ScanResult;
}

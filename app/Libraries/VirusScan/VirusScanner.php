<?php

namespace App\Libraries\VirusScan;

/**
 * สแกนไวรัสไฟล์อัปโหลด — สลับตัวสแกนได้ผ่าน emcc.virusScanner
 * (Windows: Microsoft Defender, Linux: ClamAV, เครื่อง dev: none)
 */
interface VirusScanner
{
    public function scan(string $absolutePath): ScanResult;
}

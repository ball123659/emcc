<?php

namespace App\Services\VirusScan;

/**
 * ผลการสแกนไวรัส — ค่าตรงกับ enum scan_status ในฐานข้อมูล (clean / infected / failed)
 */
enum ScanResult: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
}

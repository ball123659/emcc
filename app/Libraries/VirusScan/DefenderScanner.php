<?php

namespace App\Libraries\VirusScan;

use Config\Emcc;

/**
 * Windows: Microsoft Defender — MpCmdRun.exe -Scan -ScanType 3 -File <path> -DisableRemediation
 * exit code: 0 = ไม่พบไวรัส, 2 = พบไวรัส, อื่น ๆ = ผิดพลาด
 * หมายเหตุ: บัญชีที่รัน PHP (IIS App Pool) ต้องมีสิทธิ์รัน MpCmdRun.exe และอ่านไฟล์ใน writable
 */
class DefenderScanner extends ProcessScanner
{
    protected function command(string $absolutePath): array
    {
        return [
            config(Emcc::class)->defenderBinary,
            '-Scan', '-ScanType', '3',
            '-File', $absolutePath,
            '-DisableRemediation',
        ];
    }

    protected function interpret(int $exitCode): ScanResult
    {
        return match ($exitCode) {
            0       => ScanResult::Clean,
            2       => ScanResult::Infected,
            default => ScanResult::Failed,
        };
    }
}

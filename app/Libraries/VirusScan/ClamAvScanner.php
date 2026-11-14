<?php

namespace App\Libraries\VirusScan;

use Config\Emcc;

/**
 * Linux: ClamAV — clamdscan (เร็ว, ต้องมี clamd) หรือ clamscan
 * exit code: 0 = ไม่พบไวรัส, 1 = พบไวรัส, อื่น ๆ = ผิดพลาด
 */
class ClamAvScanner extends ProcessScanner
{
    protected function command(string $absolutePath): array
    {
        $binary = config(Emcc::class)->clamavBinary;
        $args   = [$binary, '--no-summary'];

        if (str_contains(basename($binary), 'clamdscan')) {
            $args[] = '--fdpass';
        }

        return [...$args, $absolutePath];
    }

    protected function interpret(int $exitCode): ScanResult
    {
        return match ($exitCode) {
            0       => ScanResult::Clean,
            1       => ScanResult::Infected,
            default => ScanResult::Failed,
        };
    }
}

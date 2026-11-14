<?php

namespace App\Libraries\VirusScan;

use Config\Emcc;

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

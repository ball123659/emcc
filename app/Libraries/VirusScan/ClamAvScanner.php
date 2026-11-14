<?php

namespace App\Libraries\VirusScan;

use Config\Emcc;

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

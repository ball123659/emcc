<?php

namespace App\Services\VirusScan;

use InvalidArgumentException;

class VirusScannerFactory
{
    public static function make(?string $driver = null): VirusScanner
    {
        return match ($driver ?? config('emcc.virus_scanner.driver')) {
            'none', null, '' => new NullScanner,
            'clamav' => new ClamAvScanner,
            'defender' => new DefenderScanner,
            default => throw new InvalidArgumentException('ไม่รู้จัก VIRUS_SCANNER: '.$driver),
        };
    }
}

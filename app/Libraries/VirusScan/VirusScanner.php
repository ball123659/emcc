<?php

namespace App\Libraries\VirusScan;

interface VirusScanner
{
    public function scan(string $absolutePath): ScanResult;
}

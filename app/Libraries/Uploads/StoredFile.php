<?php

namespace App\Libraries\Uploads;

use App\Libraries\VirusScan\ScanResult;

final class StoredFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $hash,
        public readonly ScanResult $scanStatus,
    ) {
    }
}

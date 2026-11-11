<?php

namespace App\Services\Images;

/** ผลลัพธ์การบันทึกรูป (path บน disk local ซึ่งอยู่นอก public) */
final class StoredImage
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $thumbPath,
        public readonly int $width,
        public readonly int $height,
        public readonly int $size,
    ) {}
}

<?php

namespace App\Services\Files;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ส่งไฟล์ให้ดาวน์โหลด (บังคับ attachment, ชื่อไฟล์ภาษาไทยได้ผ่าน filename*, มีชื่อสำรองเป็น ASCII)
 */
class DownloadResponse
{
    public static function make(Filesystem $disk, string $path, string $name, string $mime, string $fallbackBase): StreamedResponse
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ascii = Str::slug(pathinfo(Str::ascii($name), PATHINFO_FILENAME));
        $fallback = ($ascii !== '' ? $ascii : $fallbackBase).'.'.$extension;

        return new StreamedResponse(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $disk->size($path),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

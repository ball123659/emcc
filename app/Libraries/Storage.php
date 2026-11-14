<?php

namespace App\Libraries;

use Config\Emcc;
use RuntimeException;

class Storage
{
    public static function root(): string
    {
        return rtrim(str_replace('\\', '/', config(Emcc::class)->storagePath), '/') . '/';
    }

    public static function path(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);

        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[a-zA-Z]:/', $relative) || str_contains($relative, "\0")) {
            throw new RuntimeException('path ไม่ถูกต้อง');
        }

        return self::root() . ltrim($relative, '/');
    }

    public static function exists(?string $relative): bool
    {
        if ($relative === null || $relative === '') {
            return false;
        }

        try {
            return is_file(self::path($relative));
        } catch (RuntimeException) {
            return false;
        }
    }

    public static function put(string $relative, string $contents): int
    {
        $path = self::path($relative);
        self::ensureDirectory(dirname($path));

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ กรุณาตรวจสิทธิ์เขียนโฟลเดอร์ writable/storage');
        }

        return strlen($contents);
    }

    public static function copyFrom(string $source, string $relative): void
    {
        $path = self::path($relative);
        self::ensureDirectory(dirname($path));

        if (! @copy($source, $path)) {
            throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ กรุณาตรวจสิทธิ์เขียนโฟลเดอร์ writable/storage');
        }
    }

    public static function delete(?string ...$paths): void
    {
        foreach (array_filter($paths) as $relative) {
            try {
                $path = self::path($relative);
            } catch (RuntimeException) {
                continue;
            }

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('สร้างโฟลเดอร์เก็บไฟล์ไม่ได้ กรุณาตรวจสิทธิ์เขียนโฟลเดอร์ writable/storage');
        }
    }
}

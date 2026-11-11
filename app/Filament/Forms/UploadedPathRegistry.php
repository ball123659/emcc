<?php

namespace App\Filament\Forms;

/**
 * path ไฟล์ที่ระบบเพิ่งบันทึกใน request นี้
 * ใช้กับ preventFilePathTampering: อนุญาตเฉพาะ path เดิมของ record + path ที่ระบบสร้างเอง
 * (รายการใหม่ใน Repeater ยังไม่มี record จึงต้องจำ path ที่เพิ่งบันทึกไว้)
 */
class UploadedPathRegistry
{
    private static array $paths = [];

    public static function remember(string $path): string
    {
        self::$paths[$path] = true;

        return $path;
    }

    public static function allows(string $path): bool
    {
        return isset(self::$paths[$path]);
    }
}

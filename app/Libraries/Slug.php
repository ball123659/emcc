<?php

namespace App\Libraries;

/**
 * slug สำหรับ URL: a-z 0-9 และขีด (-) เท่านั้น (อักษรไทยแปลงเป็น URL ไม่ได้ จะถูกตัดออก)
 */
class Slug
{
    public static function make(?string $value, int $maxWords = 0): string
    {
        $value = trim((string) $value);

        if ($maxWords > 0) {
            $value = implode(' ', array_slice(preg_split('/\s+/u', $value) ?: [], 0, $maxWords));
        }

        helper('text');
        $value = convert_accented_characters($value);
        $value = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value));

        return trim($value, '-');
    }

    public static function limit(string $slug, int $length): string
    {
        return rtrim(substr($slug, 0, $length), '-');
    }

    public static function random(int $length = 6): string
    {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }
}

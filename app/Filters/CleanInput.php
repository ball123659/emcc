<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ทำความสะอาดข้อความที่ส่งมา (แทน InvalidChars ของ CodeIgniter ที่ปฏิเสธทั้งคำขอด้วยหน้า "Bad Request")
 * - ตัดอักขระควบคุมที่มักติดมาตอนคัดลอกจาก Word/PDF (เว้น tab / ขึ้นบรรทัด)
 * - แก้ข้อความ UTF-8 ที่เสีย (ตัดไบต์ที่อ่านไม่ได้ทิ้ง)
 */
class CleanInput implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        foreach (['get', 'post'] as $global) {
            $data = $global === 'get' ? $request->getGet() : $request->getPost();

            if (is_array($data) && $data !== []) {
                $clean = self::clean($data);

                if ($clean !== $data) {
                    $request->setGlobal($global, $clean);
                }
            }
        }

        return null;
    }

    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $result[is_string($key) ? self::cleanString($key) : $key] = self::clean($item);
            }

            return $result;
        }

        return is_string($value) ? self::cleanString($value) : $value;
    }

    private static function cleanString(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $previous = mb_substitute_character();
            mb_substitute_character('none'); // ตัดไบต์ที่เสียทิ้ง (ไม่ใส่ ? แทน)
            $value = (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            mb_substitute_character($previous);
        }

        // อักขระควบคุม C0 (ยกเว้น \t \n \r), DEL และ C1
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{80}-\x{9F}]/u', '', $value);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

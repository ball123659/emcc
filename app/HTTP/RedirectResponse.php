<?php

namespace App\HTTP;

use CodeIgniter\HTTP\RedirectResponse as BaseRedirectResponse;

/**
 * redirect พร้อมค่าที่ผู้ใช้กรอก (old input) — ตัดช่องรหัสผ่าน/โทเค็นออกเสมอ
 * (ของเดิมของ CodeIgniter เก็บทุกช่องลงไฟล์ session รวมถึงรหัสผ่านแบบข้อความธรรมดา)
 */
class RedirectResponse extends BaseRedirectResponse
{
    /** ช่องที่ห้ามเก็บลง session */
    public const SENSITIVE = ['password', 'password_confirmation', 'current_password', 'current_password_pw', 'new_password', '_token'];

    public function withInput()
    {
        parent::withInput();

        $old = session()->getFlashdata('_ci_old_input');

        if (is_array($old)) {
            session()->setFlashdata('_ci_old_input', [
                'get'  => self::strip((array) ($old['get'] ?? [])),
                'post' => self::strip((array) ($old['post'] ?? [])),
            ]);
        }

        return $this;
    }

    public static function strip(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array((string) $key, self::SENSITIVE, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = self::strip($value);
            }
        }

        return $data;
    }
}

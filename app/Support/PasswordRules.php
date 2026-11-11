<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;

/**
 * กฎรหัสผ่านใหม่: อย่างน้อย 6 ตัวอักษร และห้ามซ้ำกับรหัสเริ่มต้น
 */
class PasswordRules
{
    /** ห้ามใช้รหัสผ่านเริ่มต้น */
    public static function notDefault(): NotIn
    {
        return Rule::notIn([(string) config('emcc.default_password')]);
    }

    public static function messages(): array
    {
        $min = config('emcc.password_min_length');

        return [
            'required' => 'กรุณาตั้งรหัสผ่านใหม่',
            'min' => "รหัสผ่านต้องมีอย่างน้อย {$min} ตัวอักษร",
            'max' => 'รหัสผ่านยาวได้ไม่เกิน 100 ตัวอักษร',
            'not_in' => 'ห้ามใช้รหัสผ่านเริ่มต้น กรุณาตั้งรหัสผ่านใหม่ที่ต่างออกไป',
            'same' => 'รหัสผ่านทั้งสองช่องไม่ตรงกัน กรุณาพิมพ์ให้เหมือนกัน',
        ];
    }
}

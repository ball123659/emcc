<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * หน้าเข้าสู่ระบบด้วย username (แทน email ของ Filament)
 * TODO ลำดับงานข้อ 2: ล็อก 15 นาทีเมื่อผิด 5 ครั้ง, บังคับเปลี่ยนรหัส, บันทึก activity log
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('ชื่อผู้ใช้ (Username)')
            ->required()
            ->maxLength(50)
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => mb_strtolower(trim((string) $data['username'])),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบแล้วลองใหม่',
        ]);
    }
}

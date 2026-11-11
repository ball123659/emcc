<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * หน้าเข้าสู่ระบบด้วย username
 * - ใส่รหัสผิดครบ 5 ครั้ง -> ล็อกบัญชี 15 นาที (ตั้งค่าใน config/emcc.php)
 * - บันทึก login / login_failed ลง activity_logs (ไม่เก็บรหัสผ่าน)
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
            ->validationMessages([
                'required' => 'กรุณากรอกชื่อผู้ใช้',
                'max' => 'ชื่อผู้ใช้ยาวได้ไม่เกิน 50 ตัวอักษร',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('รหัสผ่าน')
            ->validationMessages(['required' => 'กรุณากรอกรหัสผ่าน']);
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => mb_strtolower(trim((string) $data['username'])),
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $username = mb_strtolower(trim((string) ($this->data['username'] ?? '')));
        $user = $username === '' ? null : User::where('username', $username)->first();

        // บัญชีถูกล็อกอยู่ -> ไม่ตรวจรหัสผ่านเลย (กันเดารหัสระหว่างล็อก)
        if ($user?->isLocked()) {
            ActivityLogger::log('login_failed', $user, "บัญชีถูกล็อกอยู่: {$username}", userId: $user->id);

            $this->throwLockedException($user);
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            if ($username !== '' && array_key_exists('data.username', $exception->errors())) {
                $this->recordFailedAttempt($user, $username);
            }

            throw $exception;
        }

        if ($response !== null && ($authenticated = User::find(auth()->id()))) {
            $authenticated->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->saveQuietly();

            ActivityLogger::log('login', $authenticated, 'เข้าสู่ระบบ');
        }

        return $response;
    }

    private function recordFailedAttempt(?User $user, string $username): void
    {
        if ($user === null) {
            ActivityLogger::log('login_failed', null, "ไม่พบชื่อผู้ใช้: {$username}");

            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $locked = $attempts >= (int) config('emcc.login_max_attempts');

        $user->forceFill([
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until' => $locked ? now()->addMinutes((int) config('emcc.login_lockout_minutes')) : null,
        ])->saveQuietly();

        ActivityLogger::log(
            'login_failed',
            $user,
            $locked ? "รหัสผ่านผิดครบ {$attempts} ครั้ง ล็อกบัญชีชั่วคราว" : "รหัสผ่านผิดครั้งที่ {$attempts}",
            userId: $user->id,
        );

        if ($locked) {
            $this->throwLockedException($user);
        }
    }

    private function throwLockedException(User $user): never
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));

        throw ValidationException::withMessages([
            'data.username' => 'บัญชีนี้ถูกล็อกชั่วคราวเพราะใส่รหัสผ่านผิดเกิน '.config('emcc.login_max_attempts')." ครั้ง กรุณาลองใหม่อีก {$minutes} นาที หรือติดต่อผู้ดูแลระบบให้รีเซ็ตรหัสผ่าน",
        ]);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบแล้วลองใหม่ (ระวังปุ่ม Caps Lock)',
        ]);
    }
}

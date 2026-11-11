<?php

namespace App\Filament\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * หน้าเข้าสู่ระบบด้วย username
 * - ใส่รหัสผิดครบ 5 ครั้งภายใน 15 นาที -> ล็อกบัญชี 15 นาที (ตั้งค่าใน config/emcc.php)
 * - username ที่ไม่มีจริงก็ถูก "ล็อก" แบบเดียวกัน (กันการเดาว่า username ไหนมีอยู่)
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
        $password = (string) ($this->data['password'] ?? '');
        $user = $username === '' ? null : User::where('username', $username)->first();

        if ($user) {
            $this->resetStaleAttempts($user);
        }

        // บัญชีถูกล็อกอยู่ -> ไม่ตรวจรหัสผ่านเลย (กันเดารหัสระหว่างล็อก)
        if ($user?->isLocked()) {
            ActivityLogger::log('login_failed', $user, 'พยายามเข้าสู่ระบบระหว่างบัญชีถูกล็อก', userId: $user->id);

            $this->throwLockedException($user->locked_until);
        }

        // username ที่ไม่มีในระบบก็ "ล็อก" เหมือนกัน เพื่อไม่ให้เดาได้ว่า username ไหนมีจริง
        if ($user === null && $username !== '' && RateLimiter::tooManyAttempts($this->unknownUserKey($username), $this->maxAttempts())) {
            $this->throwLockedException(now()->addSeconds(RateLimiter::availableIn($this->unknownUserKey($username))));
        }

        // รหัสถูกแต่บัญชีถูกปิด -> แจ้งตรง ๆ (ยืนยันรหัสแล้ว) และไม่นับเป็นรหัสผิด
        if ($user && $password !== '' && ! $user->is_active && Hash::check($password, $user->password)) {
            ActivityLogger::log('login_failed', $user, 'บัญชีถูกปิดการใช้งาน', userId: $user->id);

            throw ValidationException::withMessages([
                'data.username' => 'บัญชีนี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
            ]);
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

    private function maxAttempts(): int
    {
        return (int) config('emcc.login_max_attempts');
    }

    private function lockoutMinutes(): int
    {
        return (int) config('emcc.login_lockout_minutes');
    }

    private function unknownUserKey(string $username): string
    {
        return 'login-unknown-user:'.sha1($username);
    }

    /** นับรหัสผิดเฉพาะภายในช่วงเวลาล็อก (15 นาที) — ผิดครั้งสุดท้ายนานกว่านั้นให้เริ่มนับใหม่ */
    private function resetStaleAttempts(User $user): void
    {
        if ($user->failed_login_attempts === 0 || $user->isLocked()) {
            return;
        }

        $lastFailure = ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'login_failed')
            ->latest('id')
            ->value('created_at');

        if ($lastFailure === null || Carbon::parse($lastFailure)->lt(now()->subMinutes($this->lockoutMinutes()))) {
            $user->forceFill(['failed_login_attempts' => 0])->saveQuietly();
        }
    }

    private function recordFailedAttempt(?User $user, string $username): void
    {
        if ($user === null) {
            RateLimiter::hit($this->unknownUserKey($username), $this->lockoutMinutes() * 60);

            // ไม่บันทึก username ที่พิมพ์มาเต็ม ๆ (ผู้ใช้อาจเผลอพิมพ์รหัสผ่านลงช่องนี้)
            ActivityLogger::log('login_failed', null, 'ไม่พบชื่อผู้ใช้: '.self::mask($username));

            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $locked = $attempts >= $this->maxAttempts();

        $user->forceFill([
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until' => $locked ? now()->addMinutes($this->lockoutMinutes()) : null,
        ])->saveQuietly();

        ActivityLogger::log(
            'login_failed',
            $user,
            $locked ? "รหัสผ่านผิดครบ {$attempts} ครั้ง ล็อกบัญชีชั่วคราว" : "รหัสผ่านผิดครั้งที่ {$attempts}",
            userId: $user->id,
        );

        if ($locked) {
            $this->throwLockedException($user->locked_until);
        }
    }

    /** แสดงแค่ 2 ตัวแรก + ความยาว เช่น "so*** (8 ตัวอักษร)" */
    public static function mask(string $value): string
    {
        return mb_substr($value, 0, 2).'*** ('.mb_strlen($value).' ตัวอักษร)';
    }

    private function throwLockedException(\DateTimeInterface $until): never
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($until) / 60));

        throw ValidationException::withMessages([
            'data.username' => 'บัญชีนี้ถูกล็อกชั่วคราวเพราะใส่รหัสผ่านผิดเกิน '.$this->maxAttempts()." ครั้ง กรุณาลองใหม่อีก {$minutes} นาที หรือติดต่อผู้ดูแลระบบให้รีเซ็ตรหัสผ่าน",
        ]);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบแล้วลองใหม่ (ระวังปุ่ม Caps Lock)',
        ]);
    }
}

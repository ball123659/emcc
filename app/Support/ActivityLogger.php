<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * บันทึกการใช้งานลง activity_logs — ห้ามเก็บรหัสผ่าน (กรองคีย์ที่อ่อนไหวออกเสมอ)
 */
class ActivityLogger
{
    /** คีย์ที่ห้ามบันทึกลง changes */
    public const SENSITIVE_KEYS = ['password', 'password_confirmation', 'passwordConfirmation', 'currentPassword', 'remember_token'];

    public static function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $changes = null,
        ?int $userId = null,
    ): ActivityLog {
        $request = request();

        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getTable(),
            'subject_id' => $subject?->getKey(),
            'description' => $description === null ? null : Str::limit($description, 497),
            'changes' => $changes === null ? null : static::sanitize($changes),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() === null ? null : Str::limit($request->userAgent(), 252),
        ]);
    }

    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = static::sanitize($value);
            }
        }

        return $data;
    }
}

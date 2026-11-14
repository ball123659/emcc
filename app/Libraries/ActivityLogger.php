<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;

class ActivityLogger
{

    public const SENSITIVE_KEYS = ['password', 'password_confirmation', 'current_password', 'current_password_pw', 'new_password', 'remember_token', '_token'];

    public const ACTIONS = [
        'login'             => 'เข้าสู่ระบบ',
        'login_failed'      => 'เข้าสู่ระบบไม่สำเร็จ',
        'logout'            => 'ออกจากระบบ',
        'create'            => 'สร้าง',
        'update'            => 'แก้ไข',
        'delete'            => 'ลบ',
        'restore'           => 'กู้คืน',
        'password_change'   => 'เปลี่ยนรหัสผ่าน',
        'password_reset'    => 'รีเซ็ตรหัสผ่าน',
        'unlock'            => 'ปลดล็อกบัญชี',
        'permission_update' => 'แก้สิทธิ์รายหน้า',
        'reorder'           => 'เรียงลำดับ',
    ];

    public const ACTION_COLORS = [
        'login_failed'      => 'danger',
        'delete'            => 'danger',
        'create'            => 'success',
        'login'             => 'success',
        'restore'           => 'success',
        'update'            => 'info',
        'permission_update' => 'info',
        'password_reset'    => 'warning',
        'password_change'   => 'warning',
        'unlock'            => 'warning',
    ];

    public static function log(
        string $action,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        ?string $description = null,
        ?array $changes = null,
        ?int $userId = null,
    ): int {
        $request = service('request');
        $agent   = method_exists($request, 'getUserAgent') ? (string) $request->getUserAgent() : '';

        $model = new ActivityLogModel();
        $model->insert([
            'user_id'      => $userId ?? service('auth')->id(),
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId === null ? null : (int) $subjectId,
            'description'  => $description === null ? null : mb_strimwidth($description, 0, 500, '...'),
            'changes'      => $changes === null ? null : json_encode(self::sanitize($changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address'   => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
            'user_agent'   => $agent === '' ? null : mb_strimwidth($agent, 0, 255, ''),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $model->getInsertID();
    }

    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }

        return $data;
    }
}

<?php

namespace App\Models\Concerns;

use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * บันทึก create / update / delete / restore ของ model ลง activity_logs อัตโนมัติ
 * (ไม่เก็บรหัสผ่าน และไม่บันทึกถ้าเปลี่ยนแค่คอลัมน์ระบบ)
 */
trait LogsActivity
{
    /** คอลัมน์ที่ไม่ต้องบันทึกการเปลี่ยนแปลง */
    protected static array $activityIgnored = [
        'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by',
        'failed_login_attempts', 'locked_until', 'last_login_at', 'last_login_ip',
        'password_changed_at', 'view_count', 'download_count',
    ];

    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            ActivityLogger::log('create', $model, static::activityLabel($model), [
                'new' => static::activityFilter($model->attributesToArray()),
            ]);
        });

        static::updated(function (Model $model): void {
            $new = static::activityFilter($model->getChanges());

            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $new);

            ActivityLogger::log('update', $model, static::activityLabel($model), [
                'old' => static::activityFilter(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, $old)),
                'new' => $new,
            ]);
        });

        static::deleted(function (Model $model): void {
            ActivityLogger::log('delete', $model, static::activityLabel($model));
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                ActivityLogger::log('restore', $model, static::activityLabel($model));
            });
        }
    }

    protected static function activityFilter(array $attributes): array
    {
        $attributes = ActivityLogger::sanitize(array_diff_key($attributes, array_flip(static::$activityIgnored)));

        // ข้อความยาว (เช่นเนื้อหาข่าว) เก็บแค่ความยาว ไม่ให้ log บวม
        return array_map(
            fn ($value) => is_string($value) && mb_strlen($value) > 300 ? '[ข้อความยาว '.mb_strlen($value).' ตัวอักษร]' : $value,
            $attributes,
        );
    }

    /** ข้อความสั้น ๆ ที่ใช้ในบันทึก เช่น ชื่อข่าว / username */
    protected static function activityLabel(Model $model): ?string
    {
        $attributes = $model->getAttributes();

        foreach (['username', 'title_th', 'title', 'name_th', 'label_th', 'key'] as $field) {
            if (filled($attributes[$field] ?? null)) {
                return (string) $attributes[$field];
            }
        }

        return null;
    }
}

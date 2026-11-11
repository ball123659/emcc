<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * เติม created_by / updated_by จากผู้ใช้ที่ล็อกอินอยู่ให้อัตโนมัติ
 * (ตารางที่ไม่มีคอลัมน์ created_by ให้ตั้ง const CREATED_BY = null)
 */
trait TracksUser
{
    public static function bootTracksUser(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            if ($model->getCreatedByColumn() && $model->getAttribute($model->getCreatedByColumn()) === null) {
                $model->setAttribute($model->getCreatedByColumn(), $userId);
            }

            $model->setAttribute('updated_by', $userId);
        });

        static::updating(function (Model $model): void {
            if (($userId = Auth::id()) !== null) {
                $model->setAttribute('updated_by', $userId);
            }
        });
    }

    public function getCreatedByColumn(): ?string
    {
        return defined(static::class.'::CREATED_BY') ? static::CREATED_BY : 'created_by';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}

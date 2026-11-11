<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * ข้อมูลที่แสดงตามช่วงวันที่ + เปิด/ปิด + ลำดับ (Slide, Popup)
 * สถานะสำหรับป้ายในหลังบ้าน: กำลังแสดง / รอแสดง / หมดอายุ / ปิด
 */
trait HasDisplayWindow
{
    public const STATUS_SHOWING = 'showing';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_LABELS = [
        self::STATUS_SHOWING => 'กำลังแสดง',
        self::STATUS_SCHEDULED => 'รอแสดง',
        self::STATUS_EXPIRED => 'หมดอายุ',
        self::STATUS_DISABLED => 'ปิด',
    ];

    public const STATUS_COLORS = [
        self::STATUS_SHOWING => 'success',
        self::STATUS_SCHEDULED => 'info',
        self::STATUS_EXPIRED => 'gray',
        self::STATUS_DISABLED => 'danger',
    ];

    public static function bootHasDisplayWindow(): void
    {
        // รายการใหม่ต่อท้ายลำดับ
        static::creating(function (Model $model): void {
            if ($model->getAttribute('sort_order') === null) {
                $model->setAttribute('sort_order', ((int) static::withTrashed()->max('sort_order')) + 1);
            }
        });
    }

    public function displayStatus(): string
    {
        $today = today();

        return match (true) {
            ! $this->is_active => self::STATUS_DISABLED,
            $this->start_date !== null && $this->start_date->gt($today) => self::STATUS_SCHEDULED,
            $this->end_date !== null && $this->end_date->lt($today) => self::STATUS_EXPIRED,
            default => self::STATUS_SHOWING,
        };
    }

    /** วันที่เหลือก่อนหมดอายุ (null = ไม่มีวันหมดอายุ) */
    public function daysUntilExpiry(): ?int
    {
        return $this->end_date === null ? null : (int) today()->diffInDays($this->end_date, false);
    }
}

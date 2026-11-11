<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * ตั้งค่าเว็บไซต์ (key/value 2 ภาษา) — ตารางนี้มีแค่ updated_at / updated_by
 */
class SiteSetting extends Model
{
    use HasBilingualFields, LogsActivity, TracksUser;

    public const CREATED_AT = null;

    public const CREATED_BY = null;

    private const CACHE_KEY = 'site_settings';

    protected $fillable = [
        'group',
        'key',
        'label_th',
        'value_th',
        'value_en',
        'input_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** อ่านค่าตาม key ในภาษาปัจจุบัน (ภาษาอังกฤษว่าง = ใช้ภาษาไทย) */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $values = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['key', 'value_th', 'value_en'])
            ->mapWithKeys(fn (self $s) => [$s->key => ['th' => $s->value_th, 'en' => $s->value_en]])
            ->all());

        if (! isset($values[$key])) {
            return $default;
        }

        $value = app()->getLocale() === 'en' && filled($values[$key]['en'])
            ? $values[$key]['en']
            : $values[$key]['th'];

        return $value ?? $default;
    }
}

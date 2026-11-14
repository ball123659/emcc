<?php

namespace App\Models;

class SiteSettingModel extends BaseModel
{
    private const CACHE_KEY = 'site_settings';

    public const GROUPS = [
        'general' => 'ข้อมูลทั่วไป',
        'contact' => 'ข้อมูลติดต่อ',
        'social'  => 'โซเชียลมีเดีย',
    ];

    protected $table          = 'site_settings';
    protected $useTimestamps  = true;
    protected $createdField   = '';
    protected bool $tracksUser   = false;
    protected bool $logsActivity = true;
    protected $allowedFields  = ['group', 'key', 'label_th', 'value_th', 'value_en', 'input_type', 'sort_order', 'updated_by'];

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->afterUpdate[] = 'forgetCache';
        $this->afterInsert[] = 'forgetCache';
    }

    protected function forgetCache(array $data): array
    {
        cache()->delete(self::CACHE_KEY);

        return $data;
    }

    public static function value(string $key, ?string $default = null, ?string $locale = null): ?string
    {
        $values = cache(self::CACHE_KEY);

        if (! is_array($values)) {
            $values = [];

            foreach ((new self())->select('key, value_th, value_en')->findAll() as $row) {
                $values[$row['key']] = ['th' => $row['value_th'], 'en' => $row['value_en']];
            }

            cache()->save(self::CACHE_KEY, $values, 3600);
        }

        if (! isset($values[$key])) {
            return $default;
        }

        $locale ??= service('request')->getLocale();
        $value = $locale === 'en' && trim((string) $values[$key]['en']) !== '' ? $values[$key]['en'] : $values[$key]['th'];

        return $value ?? $default;
    }
}

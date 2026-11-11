<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\App;

/**
 * ฟิลด์ 2 ภาษา: *_th (บังคับ) และ *_en (ไม่บังคับ — ว่างให้แสดงภาษาไทยแทน)
 * ใช้: $news->localized('title')
 */
trait HasBilingualFields
{
    public function localized(string $field, ?string $locale = null): mixed
    {
        $locale ??= App::getLocale();

        if ($locale === 'en') {
            $value = $this->getAttribute($field.'_en');

            if (filled($value)) {
                return $value;
            }
        }

        return $this->getAttribute($field.'_th');
    }
}

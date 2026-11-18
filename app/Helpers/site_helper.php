<?php

use App\Models\SiteSettingModel;

if (! function_exists('site_locale')) {
    /**
     * ภาษาของหน้าบ้านตอนนี้ ('th' หรือ 'en')
     */
    function site_locale(): string
    {
        return service('request')->getLocale() === 'en' ? 'en' : 'th';
    }
}

if (! function_exists('t')) {
    /**
     * เลือกข้อความตามภาษา — ภาษาอังกฤษว่างจะใช้ภาษาไทยแทน
     */
    function t(string $th, ?string $en = null): string
    {
        return site_locale() === 'en' && $en !== null && $en !== '' ? $en : $th;
    }
}

if (! function_exists('loc')) {
    /**
     * อ่านฟิลด์ 2 ภาษาจากแถวข้อมูล เช่น loc($news, 'title') → title_en (ถ้ามี) หรือ title_th
     */
    function loc(array $row, string $field): string
    {
        $en = trim((string) ($row[$field . '_en'] ?? ''));

        return site_locale() === 'en' && $en !== '' ? $en : (string) ($row[$field . '_th'] ?? '');
    }
}

if (! function_exists('setting')) {
    function setting(string $key, ?string $default = null): ?string
    {
        $value = SiteSettingModel::value($key, null, site_locale());

        return $value === null || trim($value) === '' ? $default : $value;
    }
}

if (! function_exists('site_date')) {
    /**
     * วันที่สำหรับหน้าบ้าน: ไทย "5 พ.ค. 2569" / อังกฤษ "5 May 2026"
     */
    function site_date(?string $value, bool $long = false): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return '';
        }

        $ts = strtotime($value);

        if (site_locale() === 'en') {
            return date($long ? 'j F Y' : 'j M Y', $ts);
        }

        static $short = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        static $full  = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $month = (int) date('n', $ts) - 1;

        return date('j', $ts) . ' ' . ($long ? $full[$month] : $short[$month]) . ' ' . ((int) date('Y', $ts) + 543);
    }
}

if (! function_exists('news_url')) {
    function news_url(array $news): string
    {
        return site_url('news/' . rawurlencode((string) $news['slug']));
    }
}

if (! function_exists('tel_links')) {
    /**
     * แยกเบอร์โทรหลายเบอร์ ("038-683933 / 081-7323485") เป็นลิงก์ tel: ทีละเบอร์
     *
     * @return list<array{label: string, href: string}>
     */
    function tel_links(?string $phones): array
    {
        $links = [];

        foreach (preg_split('~\s*[/,]\s*~', trim((string) $phones)) ?: [] as $phone) {
            $digits = preg_replace('/[^0-9+]/', '', $phone);

            if ($phone !== '' && $digits !== '') {
                $links[] = ['label' => $phone, 'href' => 'tel:' . $digits];
            }
        }

        return $links;
    }
}

if (! function_exists('safe_url')) {
    /**
     * คืน URL เฉพาะ http/https เท่านั้น (กัน javascript: ฯลฯ)
     */
    function safe_url(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('~^https?://~i', $url) === 1 ? $url : null;
    }
}

if (! function_exists('file_badge')) {
    /**
     * ป้ายชนิดไฟล์และคลาสสีไอคอนตามนามสกุล
     *
     * @return array{label: string, class: string}
     */
    function file_badge(?string $extension, bool $isLink = false): array
    {
        if ($isLink) {
            return ['label' => 'URL', 'class' => 'ico-link'];
        }

        $ext = strtolower((string) $extension);

        return match ($ext) {
            'pdf'                 => ['label' => 'PDF', 'class' => 'ico-pdf'],
            'xls', 'xlsx'         => ['label' => 'XLS', 'class' => 'ico-xls'],
            'csv'                 => ['label' => 'CSV', 'class' => 'ico-csv'],
            'doc', 'docx', 'txt'  => ['label' => strtoupper($ext === 'txt' ? 'txt' : 'doc'), 'class' => 'ico-doc'],
            'ppt', 'pptx'         => ['label' => 'PPT', 'class' => 'ico-zip'],
            'jpg', 'png', 'webp'  => ['label' => 'IMG', 'class' => 'ico-img'],
            'mp4', 'mp3'          => ['label' => strtoupper($ext), 'class' => 'ico-media'],
            default               => ['label' => strtoupper($ext !== '' ? substr($ext, 0, 4) : 'FILE'), 'class' => 'ico-doc'],
        };
    }
}

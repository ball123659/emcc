<?php

namespace App\Services\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * กรอง HTML จาก rich text (กัน XSS) — อนุญาตเฉพาะแท็ก/แอตทริบิวต์ใน allowlist
 * ลิงก์รับเฉพาะ http / https / mailto และลิงก์ภายในเว็บ (/path) — ใส่ rel="noopener noreferrer" ทุกลิงก์
 */
class HtmlSanitizer
{
    private static ?SymfonySanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html === null ? null : '';
        }

        return static::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): SymfonySanitizer
    {
        if (static::$sanitizer !== null) {
            return static::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(true) // ลิงก์ภายในเว็บ เช่น /downloads/5
            ->allowMediaSchemes([])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(2_000_000);

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'h2', 'h3', 'h4',
            'ul', 'ol', 'li', 'blockquote', 'hr', 'pre', 'code',
            'table', 'thead', 'tbody', 'tr'] as $tag) {
            $config = $config->allowElement($tag);
        }

        $config = $config
            ->allowElement('a', ['href', 'target', 'title'])
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan']);

        return static::$sanitizer = new SymfonySanitizer($config);
    }
}

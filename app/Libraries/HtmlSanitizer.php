<?php

namespace App\Libraries;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizer
{
    private static ?SymfonySanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        if (trim($html) === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }

    public static function isBlank(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}") === '';
    }

    public static function containsImage(?string $html): bool
    {
        return (bool) preg_match('/<img\b/i', (string) $html);
    }

    private static function sanitizer(): SymfonySanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(true)
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

        return self::$sanitizer = new SymfonySanitizer($config);
    }
}

<?php

namespace Config;

use App\Filters\AdminOnly;
use App\Filters\Authenticated;
use App\Filters\CleanInput;
use App\Filters\Csrf;
use App\Filters\PagePermission;
use App\Filters\PostTooLarge;
use App\Filters\SecurityHeaders;
use App\Filters\SiteLocale;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{

    public array $aliases = [
        'csrf'          => Csrf::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        'emccsecurity' => SecurityHeaders::class,
        'auth'         => Authenticated::class,
        'page'         => PagePermission::class,
        'admin'        => AdminOnly::class,
        'posttoolarge' => PostTooLarge::class,
        'cleaninput'   => CleanInput::class,
        'sitelocale'   => SiteLocale::class,
    ];

    public array $required = [
        'before' => [
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    public array $globals = [
        'before' => [
            'emccsecurity',
            'sitelocale' => ['except' => ['backend', 'backend/*']],
            'posttoolarge',

            // หน้าบ้านเป็น GET ล้วน ไม่ต้องเปิด session (ไม่สร้าง session cookie ให้ผู้เข้าชม)
            'csrf' => ['except' => ['/', 'news', 'news/*', 'about', 'contact', 'downloads', 'downloads/*', 'lang/*', 'media/*']],
            'cleaninput',
        ],
        'after' => [
            'emccsecurity',
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}

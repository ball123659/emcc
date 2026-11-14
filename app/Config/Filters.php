<?php

namespace Config;

use App\Filters\AdminOnly;
use App\Filters\Authenticated;
use App\Filters\PagePermission;
use App\Filters\SecurityHeaders;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        // EMCC
        'emccsecurity' => SecurityHeaders::class, // HTTPS + security headers ทุก request
        'auth'         => Authenticated::class,   // ต้องล็อกอิน + บัญชียังเปิดอยู่ + เปลี่ยนรหัสแล้ว
        'page'         => PagePermission::class,  // สิทธิ์รายหน้า เช่น page:news
        'admin'        => AdminOnly::class,       // เฉพาะ developer / administrator
    ];

    /**
     * @var array{before: list<string>, after: list<string>}
     */
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
            'csrf',
            'invalidchars',
        ],
        'after' => [
            'emccsecurity',
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}

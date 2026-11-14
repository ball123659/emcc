<?php

namespace Config;

use App\Filters\AdminOnly;
use App\Filters\Authenticated;
use App\Filters\CleanInput;
use App\Filters\Csrf;
use App\Filters\PagePermission;
use App\Filters\PostTooLarge;
use App\Filters\SecurityHeaders;
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
    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => Csrf::class, // ของ EMCC: แจ้งหน้าหมดอายุเป็นภาษาไทย + เก็บค่าที่กรอกไว้
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
        'posttoolarge' => PostTooLarge::class,    // ไฟล์รวมเกิน post_max_size -> แจ้งภาษาไทย
        'cleaninput'   => CleanInput::class,      // ตัดอักขระควบคุมแทนการปฏิเสธทั้งคำขอ
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
            'posttoolarge',
            // หน้าเว็บสาธารณะ (ไฟล์/รูป) ไม่มีฟอร์ม — ไม่เริ่ม session ให้ผู้เยี่ยมชมทุกคน/บอท
            'csrf' => ['except' => ['/', 'media/*', 'downloads/*', 'news/attachments/*']],
            'cleaninput',
        ],
        'after' => [
            'emccsecurity',
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}

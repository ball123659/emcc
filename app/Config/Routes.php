<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// รูปภาพหน้าเว็บ (เก็บนอก public) — ชื่อไฟล์สุ่มเป็น UUID + .webp เท่านั้น
$routes->get('media/(slides|popups|news|settings)/([0-9a-f\-]{36}(?:_thumb)?\.webp)', 'Media::show/$1/$2');

// ไฟล์ดาวน์โหลด / ลิงก์ภายนอก (ลิงก์คงที่ตาม id)
$routes->get('downloads/(:segment)', 'Downloads::show/$1');
$routes->match(['HEAD'], 'downloads/(:segment)', 'Downloads::show/$1');

// ไฟล์แนบข่าว (ดาวน์โหลดผ่าน controller เท่านั้น)
$routes->get('news/attachments/(:segment)', 'NewsAttachments::show/$1');

/*
 * หลังบ้าน /backend — ตรวจสิทธิ์ฝั่งเซิร์ฟเวอร์ทุกเส้นทาง (auth = ล็อกอิน, page:<code> = สิทธิ์รายหน้า, admin = ผู้ดูแล)
 */
$routes->group('backend', ['namespace' => 'App\Controllers\Backend'], static function (RouteCollection $routes): void {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attempt');

    $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->post('logout', 'Auth::logout');
        $routes->get('change-password', 'Auth::changePassword');
        $routes->post('change-password', 'Auth::updatePassword');

        $routes->get('/', 'Dashboard::index');
        $routes->get('search', 'Search::index');
        $routes->get('profile', 'Profile::edit');
        $routes->post('profile', 'Profile::update');
        $routes->post('profile/password', 'Profile::password');
    });

    $routes->get('monitoring', 'Monitoring::index', ['filter' => ['auth', 'page:monitoring']]);

    // CRUD มาตรฐาน: รายการ / เพิ่ม / แก้ไข / ลบ / กู้คืน / เปิด-ปิด / ลากเรียง
    $crud = static function (RouteCollection $routes, string $path, string $controller, string $page, bool $trash = true, bool $toggle = true, bool $reorder = true): void {
        $routes->group($path, ['filter' => ['auth', $page]], static function (RouteCollection $routes) use ($controller, $trash, $toggle, $reorder): void {
            $routes->get('/', "{$controller}::index");
            $routes->get('create', "{$controller}::create");
            $routes->post('/', "{$controller}::store");
            $routes->get('(:num)/edit', "{$controller}::edit/$1");
            $routes->post('(:num)', "{$controller}::update/$1");
            $routes->post('(:num)/delete', "{$controller}::delete/$1");

            if ($trash) {
                $routes->post('(:num)/restore', "{$controller}::restore/$1");
            }

            if ($toggle) {
                $routes->post('(:num)/toggle', "{$controller}::toggle/$1");
            }

            if ($reorder) {
                $routes->post('reorder', "{$controller}::reorder");
            }
        });
    };

    $crud($routes, 'slides', 'Slides', 'page:slides');
    $crud($routes, 'popups', 'Popups', 'page:popups');
    $crud($routes, 'news', 'News', 'page:news', true, false, false);
    $crud($routes, 'news-categories', 'NewsCategories', 'page:news', false);
    $crud($routes, 'downloads', 'Downloads', 'page:downloads');
    $crud($routes, 'download-categories', 'DownloadCategories', 'page:downloads', false);
    $crud($routes, 'users', 'Users', 'admin', true, true, false);

    $routes->group('news', ['filter' => ['auth', 'page:news']], static function (RouteCollection $routes): void {
        $routes->post('(:num)/publish', 'News::publish/$1');
        $routes->post('(:num)/hide', 'News::hide/$1');
    });

    $routes->post('popups/(:num)/extend', 'Popups::extend/$1', ['filter' => ['auth', 'page:popups']]);

    $routes->group('users', ['filter' => ['auth', 'admin']], static function (RouteCollection $routes): void {
        $routes->post('(:num)/reset-password', 'Users::resetPassword/$1');
        $routes->post('(:num)/unlock', 'Users::unlock/$1');
    });

    $routes->group('site-settings', ['filter' => ['auth', 'page:site_settings']], static function (RouteCollection $routes): void {
        $routes->get('/', 'SiteSettings::edit');
        $routes->post('/', 'SiteSettings::update');
    });

    $routes->group('activity-logs', ['filter' => ['auth', 'admin', 'page:activity_logs']], static function (RouteCollection $routes): void {
        $routes->get('/', 'ActivityLogs::index');
        $routes->get('(:num)', 'ActivityLogs::show/$1');
    });
});

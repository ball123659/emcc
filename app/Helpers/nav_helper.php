<?php

/**
 * เมนูหลังบ้าน (แสดงเฉพาะหน้าที่ผู้ใช้มีสิทธิ์ — การตรวจสิทธิ์จริงอยู่ที่ filter ของแต่ละเส้นทาง)
 */
if (! function_exists('backend_nav')) {
    function backend_nav(): array
    {
        $auth = service('auth');
        $user = $auth->user();

        if ($user === null || (bool) $user['must_change_password']) {
            return [];
        }

        $groups = [
            null => [
                ['แดชบอร์ด', 'home', 'backend', 'dashboard', true],
                ['จุดตรวจวัด', 'map-pin', 'backend/monitoring', 'monitoring', false],
            ],
            'จัดการ Highlight' => [
                ['Slide Show', 'photo', 'backend/slides', 'slides', false],
                ['Popup', 'window', 'backend/popups', 'popups', false],
            ],
            'ข่าวประชาสัมพันธ์' => [
                ['ข่าว', 'newspaper', 'backend/news', 'news', false],
                ['หมวดหมู่ข่าว', 'tag', 'backend/news-categories', 'news', false],
            ],
            'ไฟล์ดาวน์โหลด' => [
                ['ไฟล์ดาวน์โหลด', 'arrow-down-tray', 'backend/downloads', 'downloads', false],
                ['หมวดหมู่ไฟล์', 'tag', 'backend/download-categories', 'downloads', false],
            ],
            'ตั้งค่า' => [
                ['ตั้งค่าเว็บไซต์', 'cog-6-tooth', 'backend/site-settings', 'site_settings', false],
            ],
            'ผู้ดูแลระบบ' => [
                ['จัดการผู้ใช้งาน', 'users', 'backend/users', 'users', false],
                ['บันทึกการใช้งาน', 'clock', 'backend/activity-logs', 'activity_logs', false],
            ],
        ];

        $path   = trim(service('request')->getUri()->getPath(), '/');
        $result = [];

        foreach ($groups as $group => $items) {
            $visible = [];

            foreach ($items as [$label, $icon, $url, $code, $always]) {
                if (! $always && ! $auth->canViewPage($code)) {
                    continue;
                }

                $active    = $url === 'backend' ? $path === 'backend' : ($path === $url || str_starts_with($path, $url . '/'));
                $visible[] = ['label' => $label, 'icon' => $icon, 'url' => site_url($url), 'active' => $active];
            }

            if ($visible !== []) {
                $result[] = ['label' => $group ?: null, 'items' => $visible];
            }
        }

        return $result;
    }
}

<?php

namespace App\Filters;

use App\HTTP\RedirectResponse;
use CodeIgniter\Filters\CSRF as BaseCsrf;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

class Csrf extends BaseCsrf
{
    public const RESTORE_KEY = '_emcc_restore';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        try {
            service('security')->verify($request);
        } catch (SecurityException $e) {
            if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(403)->setJSON(['message' => 'หน้านี้หมดอายุแล้ว กรุณาโหลดหน้าใหม่ (กด F5) แล้วลองอีกครั้ง']);
            }

            $target = self::safeBackPath($request);
            $input  = RedirectResponse::strip((array) $request->getPost());

            if ($input !== [] && $target !== 'backend/login') {
                session()->set(self::RESTORE_KEY, ['path' => strtok($target, '?'), 'input' => $input, 'time' => time()]);
            }

            session()->setFlashdata('toast', [
                'type'  => 'warning',
                'title' => 'หน้านี้หมดอายุแล้ว (เปิดค้างไว้นานเกินไป)',
                'text'  => $input !== [] && $target !== 'backend/login'
                    ? 'ระบบเก็บข้อมูลที่กรอกไว้ให้แล้ว กรุณาตรวจสอบแล้วกดบันทึกอีกครั้ง (ไฟล์ที่เลือกไว้ต้องเลือกใหม่)'
                    : 'กรุณาลองทำรายการอีกครั้ง',
            ]);

            return redirect()->to(site_url($target));
        }

        return null;
    }

    public static function safeBackPath(RequestInterface $request): string
    {
        $referer = (string) $request->getServer('HTTP_REFERER');
        $base    = rtrim(base_url(), '/');

        if ($referer !== '' && str_starts_with($referer, $base . '/')) {
            $path = trim((string) parse_url($referer, PHP_URL_PATH), '/');
            $basePath = trim((string) parse_url($base, PHP_URL_PATH), '/');
            $path = $basePath !== '' && str_starts_with($path, $basePath) ? trim(substr($path, strlen($basePath)), '/') : $path;
            $query = (string) parse_url($referer, PHP_URL_QUERY);

            if (preg_match('~^backend(/[A-Za-z0-9/_\-]*)?$~', $path)) {
                return $path . ($query !== '' && preg_match('/^[A-Za-z0-9=&_\-%.]+$/', $query) ? '?' . $query : '');
            }
        }

        return 'backend';
    }
}

<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * หน้าหลังบ้าน: ต้องล็อกอิน, บัญชียังเปิดอยู่, รหัสผ่านไม่ถูกเปลี่ยนจากที่อื่น
 * และถ้ายังไม่ได้เปลี่ยนรหัสผ่านเริ่มต้น -> ใช้ได้แค่หน้าเปลี่ยนรหัส / ออกจากระบบ
 */
class Authenticated implements FilterInterface
{
    private const ALLOWED_BEFORE_PASSWORD_CHANGE = ['backend/change-password', 'backend/logout'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check() && ! $auth->loginFromRememberCookie()) {
            if ($this->wantsJson($request)) {
                return service('response')->setStatusCode(401)->setJSON(['message' => 'หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบใหม่']);
            }

            if (strtolower($request->getMethod()) === 'get') {
                session()->set('auth_intended', current_url(true)->getPath() . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : ''));
            }

            return redirect()->to(site_url('backend/login'));
        }

        if (($reason = $auth->invalidReason()) !== null) {
            $auth->logout(false);
            session()->setFlashdata('toast', ['type' => 'danger', 'title' => $reason]);

            if ($this->wantsJson($request)) {
                return service('response')->setStatusCode(401)->setJSON(['message' => $reason]);
            }

            return redirect()->to(site_url('backend/login'))->withCookies();
        }

        $path = trim($request->getUri()->getPath(), '/');

        if ((bool) $auth->user()['must_change_password'] && ! in_array($path, self::ALLOWED_BEFORE_PASSWORD_CHANGE, true)) {
            if ($this->wantsJson($request)) {
                return service('response')->setStatusCode(403)->setJSON(['message' => 'กรุณาเปลี่ยนรหัสผ่านก่อนใช้งาน']);
            }

            return redirect()->to(site_url('backend/change-password'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function wantsJson(RequestInterface $request): bool
    {
        return $request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json');
    }
}

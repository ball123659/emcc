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

    /** ค่าชั่วคราวที่ใส่ใน session ระหว่าง request นี้ (ลบทิ้งตอนจบ ไม่ให้ค้างไปหน้าถัดไป) */
    private static array $requestOnly = [];

    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check() && ! $auth->loginFromRememberCookie()) {
            if ($this->wantsJson($request)) {
                return service('response')->setStatusCode(401)->setJSON(['message' => 'หมดเวลาการใช้งาน กรุณาโหลดหน้าใหม่แล้วเข้าสู่ระบบอีกครั้ง']);
            }

            if (strtolower($request->getMethod()) === 'get') {
                session()->set('auth_intended', current_url(true)->getPath() . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : ''));
            }

            if (session()->getFlashdata('toast') !== null) {
                session()->keepFlashdata('toast'); // เช่น "หน้านี้หมดอายุ" ให้แสดงที่หน้าเข้าสู่ระบบ
            } elseif ($request->getCookie(config('Session')->cookieName) !== null) {
                session()->setFlashdata('toast', ['type' => 'info', 'title' => 'กรุณาเข้าสู่ระบบอีกครั้ง', 'text' => 'หมดเวลาการใช้งาน หรือออกจากระบบไปแล้ว']);
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

        if (strtolower($request->getMethod()) === 'get') {
            $this->restoreInput($path);
        }

        return null;
    }

    /** ฟอร์มที่ส่งไม่ผ่านเพราะหน้าหมดอายุ -> ใส่ค่าที่กรอกไว้กลับให้ (เฉพาะหน้าเดิม ภายใน 1 ชั่วโมง) */
    private function restoreInput(string $path): void
    {
        $restore = session(Csrf::RESTORE_KEY);

        if (! is_array($restore)) {
            return;
        }

        if (($restore['time'] ?? 0) < time() - 3600) {
            session()->remove(Csrf::RESTORE_KEY);

            return;
        }

        if (($restore['path'] ?? '') !== $path) {
            return;
        }

        session()->remove(Csrf::RESTORE_KEY);
        $_SESSION['_ci_old_input'] = ['get' => [], 'post' => (array) $restore['input']];
        self::$requestOnly[]       = '_ci_old_input';

        if (session('toast') === null) {
            $_SESSION['toast']     = ['type' => 'info', 'title' => 'นำข้อมูลที่กรอกไว้กลับมาให้แล้ว', 'text' => 'กรุณาตรวจสอบแล้วกดบันทึกอีกครั้ง (ไฟล์ที่เลือกไว้ต้องเลือกใหม่)'];
            self::$requestOnly[] = 'toast';
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        foreach (self::$requestOnly as $key) {
            session()->remove($key);
        }

        self::$requestOnly = [];

        return null;
    }

    private function wantsJson(RequestInterface $request): bool
    {
        return $request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json');
    }
}

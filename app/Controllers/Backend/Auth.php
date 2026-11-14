<?php

namespace App\Controllers\Backend;

use App\Libraries\ActivityLogger;
use App\Models\UserModel;
use Config\Emcc;

class Auth extends BackendController
{
    public function login()
    {
        if ($this->auth->check() && $this->auth->invalidReason() === null) {
            return redirect()->to(site_url('backend'));
        }

        return view('auth/login');
    }

    public function attempt()
    {

        if (! service('throttler')->check('login-' . md5($this->request->getIPAddress()), 10, MINUTE)) {
            return $this->backWithErrors(['username' => 'ลองเข้าสู่ระบบบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่'], null);
        }

        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');
        $errors   = [];

        if (trim($username) === '') {
            $errors['username'] = 'กรุณากรอกชื่อผู้ใช้';
        } elseif (mb_strlen(trim($username)) > 50) {
            $errors['username'] = 'ชื่อผู้ใช้ยาวได้ไม่เกิน 50 ตัวอักษร';
        }

        if ($password === '') {
            $errors['password'] = 'กรุณากรอกรหัสผ่าน';
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors, null);
        }

        $error = $this->auth->attempt($username, $password, $this->request->getPost('remember') === '1');

        if ($error !== null) {
            return redirect()->back()->withInput()->with('errors', ['username' => $error]);
        }

        $intended = (string) session('auth_intended');
        session()->remove('auth_intended');

        $target = preg_match('~^/?backend(/[A-Za-z0-9/_\-?=&%.]*)?$~', $intended) ? base_url(ltrim($intended, '/')) : site_url('backend');

        return redirect()->to($target)->withCookies();
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->to(site_url('backend/login'))->withCookies();
    }

    public function changePassword()
    {
        if (! (bool) $this->user()['must_change_password']) {
            return redirect()->to(site_url('backend/profile'));
        }

        return view('auth/change_password');
    }

    public function updatePassword()
    {
        $user = $this->user();

        if (! (bool) $user['must_change_password']) {
            return redirect()->to(site_url('backend/profile'));
        }

        $password = (string) $this->request->getPost('password');
        $errors   = self::passwordErrors($password, (string) $this->request->getPost('password_confirmation'), $user);

        if ($errors !== []) {
            return $this->backWithErrors($errors, null);
        }

        (new UserModel())->quietly(static fn (UserModel $m) => $m->update($user['id'], [
            'password'             => $password,
            'must_change_password' => 0,
            'password_changed_at'  => date('Y-m-d H:i:s'),
            'remember_token'       => null,
        ]));
        ActivityLogger::log('password_change', 'users', $user['id'], 'เปลี่ยนรหัสผ่าน (ล็อกอินครั้งแรก)');
        $this->auth->refreshPasswordFingerprint();
        $this->toast('ตั้งรหัสผ่านใหม่เรียบร้อย', 'success', 'ใช้รหัสผ่านใหม่นี้ในการเข้าสู่ระบบครั้งต่อไป');

        return redirect()->to(site_url('backend'));
    }

    public static function passwordErrors(string $password, string $confirmation, ?array $user = null, string $field = 'password'): array
    {
        $config = config(Emcc::class);

        $error = match (true) {
            $password === ''                                 => 'กรุณาตั้งรหัสผ่านใหม่',
            mb_strlen($password) < $config->passwordMinLength => "รหัสผ่านต้องมีอย่างน้อย {$config->passwordMinLength} ตัวอักษร",
            strlen($password) > 72                           => 'รหัสผ่านยาวได้ไม่เกิน 72 ไบต์ (ตัวอักษรไทยใช้หลายไบต์)',
            str_contains($password, "\0")                    => 'รหัสผ่านมีอักขระที่ไม่รองรับ กรุณาตั้งรหัสผ่านใหม่',
            $password === $config->defaultPassword           => 'ห้ามใช้รหัสผ่านเริ่มต้น กรุณาตั้งรหัสผ่านใหม่ที่ต่างออกไป',
            default                                          => null,
        };

        if ($error !== null) {
            return [$field => $error];
        }

        if ($password !== $confirmation) {
            return [$field . '_confirmation' => 'รหัสผ่านทั้งสองช่องไม่ตรงกัน กรุณาพิมพ์ให้เหมือนกัน'];
        }

        return [];
    }
}

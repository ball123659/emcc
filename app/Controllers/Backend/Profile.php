<?php

namespace App\Controllers\Backend;

use App\Libraries\ActivityLogger;
use App\Models\UserModel;

/**
 * โปรไฟล์ของฉัน — แก้ข้อมูลตัวเองและเปลี่ยนรหัสผ่าน
 * username เปลี่ยนได้เฉพาะ developer / administrator (ผู้ใช้ทั่วไปติดต่อผู้ดูแลระบบ)
 */
class Profile extends BackendController
{
    public function edit()
    {
        return view('backend/profile', ['title' => 'โปรไฟล์ของฉัน', 'me' => $this->user()]);
    }

    public function update()
    {
        $me          = $this->user();
        $canUsername = UserModel::isAdmin($me);

        $data = [
            'username'   => $canUsername ? mb_strtolower((string) $this->input('username')) : $me['username'],
            'email'      => mb_strtolower((string) $this->input('email')),
            'first_name' => $this->input('first_name'),
            'last_name'  => $this->input('last_name'),
            'phone'      => $this->input('phone'),
            'position'   => $this->input('position'),
        ];

        $errors = Users::userErrors($data, $me, $canUsername);

        // เปลี่ยนอีเมลต้องยืนยันรหัสผ่าน
        if ($data['email'] !== $me['email']) {
            $current = (string) $this->request->getPost('current_password');

            if ($current === '') {
                $errors['current_password'] = 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันการเปลี่ยนอีเมล';
            } elseif (($error = $this->auth->verifyCurrentPassword($me, $current)) !== null) {
                $errors['current_password'] = $error;
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        if (! $canUsername) {
            unset($data['username']);
        }

        (new UserModel())->update($me['id'], $data);
        $this->toast('บันทึกข้อมูลส่วนตัวแล้ว');

        return redirect()->to(site_url('backend/profile'));
    }

    public function password()
    {
        $me      = $this->user();
        $current = (string) $this->request->getPost('current_password_pw');
        $errors  = [];

        if (($error = $this->auth->verifyCurrentPassword($me, $current)) !== null) {
            $errors['current_password_pw'] = $error;
        }

        $errors += Auth::passwordErrors((string) $this->request->getPost('password'), (string) $this->request->getPost('password_confirmation'), $me);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        (new UserModel())->quietly(fn (UserModel $m) => $m->update($me['id'], [
            'password'             => (string) $this->request->getPost('password'),
            'must_change_password' => 0,
            'password_changed_at'  => date('Y-m-d H:i:s'),
            'remember_token'       => null,
        ]));
        ActivityLogger::log('password_change', 'users', $me['id'], 'เปลี่ยนรหัสผ่าน');
        $this->auth->refreshPasswordFingerprint(); // session อื่นของผู้ใช้คนนี้หลุด ยกเว้นเครื่องนี้
        $this->toast('เปลี่ยนรหัสผ่านแล้ว', 'success', 'อุปกรณ์อื่นที่ล็อกอินค้างไว้จะต้องเข้าสู่ระบบใหม่');

        return redirect()->to(site_url('backend/profile'));
    }
}

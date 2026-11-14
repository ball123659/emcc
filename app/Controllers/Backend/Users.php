<?php

namespace App\Controllers\Backend;

use App\Filters\Forbidden;
use App\Libraries\ActivityLogger;
use App\Libraries\UserPermissions;
use App\Models\UserModel;
use Config\Emcc;

/**
 * จัดการผู้ใช้งาน — เฉพาะ developer / administrator (filter admin)
 * - administrator แก้/ลบ developer ไม่ได้, กำหนดบทบาท developer ไม่ได้
 * - ห้ามลบ/ปิดบัญชีตัวเอง, ห้ามเปลี่ยนบทบาทตัวเอง
 * - ลบแบบกู้คืนได้เท่านั้น
 * - สิทธิ์รายหน้าแบบ เห็น / ไม่เห็น สำหรับบทบาท user
 */
class Users extends BackendController
{
    private const PATH = 'backend/users';

    public function index()
    {
        $q       = $this->searchTerm();
        $trashed = $this->request->getGet('tab') === 'trashed';
        $role    = (string) $this->request->getGet('role');
        $active  = (string) $this->request->getGet('active');
        $model   = new UserModel();

        if ($trashed) {
            $model->onlyDeleted();
        }

        if ($q !== '') {
            $model->groupStart()->like('username', $q)->orLike('first_name', $q)->orLike('last_name', $q)->orLike('email', $q)->groupEnd();
        }

        if (isset(UserModel::ROLES[$role])) {
            $model->where('role', $role);
        }

        if ($active === '1' || $active === '0') {
            $model->where('is_active', (int) $active);
        }

        $sort = $this->request->getGet('sort') === 'last_login_at' ? 'last_login_at' : 'id';
        $dir  = $this->request->getGet('dir') === 'asc' ? 'asc' : ($sort === 'id' ? 'asc' : 'desc');
        $model->orderBy($sort, $dir)->orderBy('id');

        return view('backend/users/index', [
            'title'       => 'ผู้ใช้งาน',
            'breadcrumbs' => ['ผู้ใช้งาน' => site_url(self::PATH), 'รายการ' => null],
            'tab'         => $trashed ? 'trashed' : 'all',
            'tabs'        => [
                'all'     => ['label' => 'ทั้งหมด', 'count' => (new UserModel())->countAllResults(), 'color' => 'primary'],
                'trashed' => ['label' => 'ถังขยะ', 'count' => (new UserModel())->onlyDeleted()->countAllResults(), 'color' => 'gray', 'icon' => 'trash'],
            ],
            'q'      => $q,
            'role'   => $role,
            'active' => $active,
            'pager'  => $this->paginate($model),
            'query'  => $this->query(['tab', 'q', 'per_page', 'role', 'active', 'sort', 'dir']),
            'sort'   => $sort,
            'dir'    => $dir,
            'me'     => $this->user(),
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id);

        if (! UserModel::canManage($this->user(), $user)) {
            return Forbidden::response($this->request);
        }

        return $this->form($user);
    }

    private function form(?array $user)
    {
        return view('backend/users/form', [
            'title'        => $user ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน',
            'breadcrumbs'  => ['ผู้ใช้งาน' => site_url(self::PATH), $user ? 'แก้ไข' : 'เพิ่ม' => null],
            'user'         => $user,
            'me'           => $this->user(),
            'roles'        => UserModel::assignableRoles($this->user()),
            'pageOptions'  => UserPermissions::assignablePageOptions(),
            'selectedPages'=> $user ? UserPermissions::selectedPageIds((int) $user['id']) : [],
            'defaultPassword' => config(Emcc::class)->defaultPassword,
        ]);
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id);

        if (! UserModel::canManage($this->user(), $user)) {
            return Forbidden::response($this->request);
        }

        return $this->save($user);
    }

    private function save(?array $user)
    {
        $me     = $this->user();
        $isSelf = $user !== null && (int) $user['id'] === (int) $me['id'];

        $data = [
            'username'   => mb_strtolower((string) $this->input('username')),
            'email'      => mb_strtolower((string) $this->input('email')),
            'first_name' => $this->input('first_name'),
            'last_name'  => $this->input('last_name'),
            'phone'      => $this->input('phone'),
            'position'   => $this->input('position'),
            'role'       => (string) $this->request->getPost('role'),
            'is_active'  => $this->bool('is_active'),
        ];

        // ห้ามเปลี่ยนบทบาท / ปิดบัญชีของตัวเอง (ตรวจฝั่งเซิร์ฟเวอร์ ไม่ใช่แค่ปิดช่องในฟอร์ม)
        if ($isSelf) {
            $data['role']      = $user['role'];
            $data['is_active'] = 1;
        }

        $errors = $this->userErrors($data, $user);

        if (! $isSelf && ! isset(UserModel::assignableRoles($me)[$data['role']])) {
            $errors['role'] = 'บทบาทที่เลือกไม่ถูกต้อง หรือคุณไม่มีสิทธิ์กำหนดบทบาทนี้';
        }

        // แก้อีเมลของตัวเองต้องยืนยันรหัสผ่านปัจจุบัน
        if ($isSelf && $data['email'] !== $user['email']) {
            $current = (string) $this->request->getPost('current_password');

            if ($current === '') {
                $errors['current_password'] = 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันการเปลี่ยนอีเมล';
            } elseif (! password_verify($current, (string) $user['password'])) {
                $errors['current_password'] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        $model = new UserModel();
        $db    = db_connect();
        $db->transStart();

        if ($user === null) {
            $id = (int) $model->insert($data + [
                'password'             => config(Emcc::class)->defaultPassword,
                'must_change_password' => 1,
            ]);
        } else {
            $id = (int) $user['id'];
            $model->update($id, $data);
        }

        $saved = (new UserModel())->find($id);
        UserPermissions::sync($saved, (array) $this->request->getPost('pages'));
        $db->transComplete();
        $this->auth->refresh();

        $this->toast('บันทึกผู้ใช้ ' . $data['username'] . ' แล้ว', 'success', $user === null ? 'รหัสผ่านเริ่มต้นคือ "' . config(Emcc::class)->defaultPassword . '" ผู้ใช้ต้องตั้งรหัสใหม่ตอนเข้าสู่ระบบครั้งแรก' : null);

        if ($user === null && $this->request->getPost('_another') === '1') {
            return redirect()->to(site_url(self::PATH . '/create'));
        }

        return redirect()->to(site_url(self::PATH . '/' . $id . '/edit'));
    }

    /** ตรวจข้อมูลผู้ใช้ (ใช้ร่วมกับหน้าโปรไฟล์) */
    public static function userErrors(array $data, ?array $user, bool $checkUsername = true): array
    {
        $errors = [];
        $model  = new UserModel();
        $id     = $user['id'] ?? null;

        if ($checkUsername) {
            if ($data['username'] === '') {
                $errors['username'] = 'กรุณากรอกชื่อผู้ใช้';
            } elseif (mb_strlen($data['username']) > 50) {
                $errors['username'] = 'ชื่อผู้ใช้ยาวได้ไม่เกิน 50 ตัวอักษร';
            } elseif (! preg_match(UserModel::USERNAME_PATTERN, $data['username'])) {
                $errors['username'] = 'ชื่อผู้ใช้ใช้ได้เฉพาะ a-z, 0-9, จุด, ขีดล่าง และขีด (ห้ามมีช่องว่างหรือภาษาไทย)';
            } elseif ($model->withDeleted()->where('username', $data['username'])->when($id, static fn ($q) => $q->where('id !=', $id))->countAllResults() > 0) {
                $errors['username'] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว (รวมถึงบัญชีที่ถูกลบ) กรุณาใช้ชื่ออื่น';
            }
        }

        if ($data['email'] === '') {
            $errors['email'] = 'กรุณากรอกอีเมล';
        } elseif (mb_strlen($data['email']) > 191 || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง ตัวอย่าง name@example.com';
        } elseif ((new UserModel())->withDeleted()->where('email', $data['email'])->when($id, static fn ($q) => $q->where('id !=', $id))->countAllResults() > 0) {
            $errors['email'] = 'อีเมลนี้มีผู้ใช้อื่นใช้แล้ว (รวมถึงบัญชีที่ถูกลบ) กรุณาใช้อีเมลอื่น';
        }

        foreach (['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล'] as $field => $label) {
            if ($data[$field] === null) {
                $errors[$field] = "กรุณากรอก{$label}";
            } elseif (mb_strlen($data[$field]) > 100) {
                $errors[$field] = "{$label}ยาวได้ไม่เกิน 100 ตัวอักษร";
            }
        }

        if ($data['phone'] !== null) {
            if (! preg_match('/^[0-9\-\s]+$/', $data['phone'])) {
                $errors['phone'] = 'เบอร์โทรศัพท์ใส่ได้เฉพาะตัวเลขและขีด (-)';
            } elseif (strlen((string) preg_replace('/\D+/', '', $data['phone'])) > 20) {
                $errors['phone'] = 'เบอร์โทรศัพท์ยาวเกินไป';
            }
        }

        if ($data['position'] !== null && mb_strlen($data['position']) > 150) {
            $errors['position'] = 'ตำแหน่งยาวได้ไม่เกิน 150 ตัวอักษร';
        }

        return $errors;
    }

    public function toggle(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id);

        if (! UserModel::canManage($this->user(), $user) || (int) $user['id'] === $this->auth->id()) {
            return $this->json(['message' => 'ปิดบัญชีของตัวเอง หรือบัญชีที่คุณไม่มีสิทธิ์จัดการไม่ได้'], 403);
        }

        $value = (string) $this->request->getPost('value') === '1' ? 1 : 0;
        (new UserModel())->update($id, ['is_active' => $value]);

        return $this->json(['message' => $value ? 'เปิดใช้งานบัญชีแล้ว' : 'ปิดใช้งานบัญชีแล้ว']);
    }

    public function delete(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id);

        if ((int) $user['id'] === $this->auth->id()) {
            $this->toast('ลบบัญชีของตัวเองไม่ได้', 'danger');

            return redirect()->to(site_url(self::PATH));
        }

        if (! UserModel::canManage($this->user(), $user)) {
            return Forbidden::response($this->request);
        }

        (new UserModel())->delete($id);
        (new UserModel())->updateQuietly($id, ['remember_token' => null]);
        $this->toast('ลบผู้ใช้ ' . $user['username'] . ' แล้ว', 'success', 'ผู้ใช้จะเข้าสู่ระบบไม่ได้ กู้คืนได้ที่แท็บถังขยะ');

        return redirect()->to(site_url(self::PATH));
    }

    public function restore(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id, true);

        if (! UserModel::canManage($this->user(), $user)) {
            return Forbidden::response($this->request);
        }

        (new UserModel())->restore($id);
        $this->toast('กู้คืนผู้ใช้ ' . $user['username'] . ' แล้ว');

        return redirect()->to(site_url(self::PATH . '?tab=trashed'));
    }

    /** รีเซ็ตรหัสผ่านกลับเป็นค่าเริ่มต้น — ผู้ใช้ต้องตั้งรหัสใหม่ตอนเข้าสู่ระบบครั้งถัดไป */
    public function resetPassword(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id);

        if (! UserModel::canManage($this->user(), $user) || (int) $user['id'] === $this->auth->id()) {
            return Forbidden::response($this->request);
        }

        $default = config(Emcc::class)->defaultPassword;
        (new UserModel())->quietly(static fn (UserModel $m) => $m->update($id, [
            'password' => $default, 'must_change_password' => 1, 'failed_login_attempts' => 0, 'locked_until' => null, 'remember_token' => null,
        ]));
        ActivityLogger::log('password_reset', 'users', $id, "รีเซ็ตรหัสผ่านของ {$user['username']}");
        $this->toast('รีเซ็ตรหัสผ่านแล้ว', 'success', 'รหัสผ่านเริ่มต้นคือ "' . $default . '" แจ้งผู้ใช้ให้เข้าสู่ระบบแล้วตั้งรหัสใหม่');

        return redirect()->back();
    }

    /** ปลดล็อกบัญชีที่ถูกล็อกจากการใส่รหัสผิด */
    public function unlock(int $id)
    {
        $user = $this->findOr404(new UserModel(), $id);

        if (! UserModel::canManage($this->user(), $user) || (int) $user['id'] === $this->auth->id()) {
            return Forbidden::response($this->request);
        }

        (new UserModel())->unlock($id, $user['username']);
        $this->toast('ปลดล็อกบัญชีแล้ว');

        return redirect()->back();
    }
}

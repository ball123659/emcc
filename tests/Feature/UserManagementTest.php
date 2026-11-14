<?php

namespace Tests\Feature;

use App\Libraries\UserPermissions;
use App\Models\UserModel;
use Tests\Support\TestCase;

/**
 * จัดการผู้ใช้งาน + โปรไฟล์
 */
final class UserManagementTest extends TestCase
{
    private function form(array $override = []): array
    {
        return $override + [
            'username' => ' New.User ', 'email' => ' New@Example.com ', 'first_name' => ' สมชาย ', 'last_name' => 'ใจดี',
            'phone' => '081-234-5678', 'position' => '', 'role' => 'user', 'is_active' => '1',
        ];
    }

    public function testCreateUserNormalizesFieldsWithDefaultPasswordAndPermissions(): void
    {
        $admin  = $this->admin();
        $pageId = db_connect()->table('pages')->where('code', 'news')->get()->getRowArray()['id'];

        $this->actingAs($admin)->post('backend/users', $this->form(['pages' => [(string) $pageId]]));

        $user = (new UserModel())->where('username', 'new.user')->first();
        $this->assertNotNull($user, json_encode(session('errors')));
        $this->assertSame('new@example.com', $user['email']);
        $this->assertSame('สมชาย', $user['first_name']);
        $this->assertSame('0812345678', $user['phone']);
        $this->assertNull($user['position']);
        $this->assertSame('1', (string) $user['must_change_password']);
        $this->assertTrue(password_verify('passwordemcc', $user['password']));
        $this->assertSame(['news'], UserPermissions::visibleCodes((int) $user['id']));
        $this->assertSame((int) $admin['id'], (int) $user['created_by']);
        $this->assertStringNotContainsString('"password"', (string) $this->lastLog('create')['changes']);
    }

    public function testValidationMessagesAreThai(): void
    {
        $admin = $this->admin();
        $this->makeUser(['username' => 'taken', 'email' => 'taken@example.com']);

        $this->actingAs($admin)->post('backend/users', $this->form(['username' => 'มีช่อง ว่าง', 'email' => 'bad', 'first_name' => '', 'phone' => 'abc']));
        $errors = session('errors');
        $this->assertStringContainsString('ใช้ได้เฉพาะ a-z', $errors['username']);
        $this->assertStringContainsString('รูปแบบอีเมลไม่ถูกต้อง', $errors['email']);
        $this->assertSame('กรุณากรอกชื่อ', $errors['first_name']);
        $this->assertStringContainsString('ตัวเลข', $errors['phone']);

        $this->actingAs($admin)->post('backend/users', $this->form(['username' => 'TAKEN', 'email' => 'Taken@example.com']));
        $this->assertStringContainsString('ถูกใช้แล้ว', session('errors')['username']);
        $this->assertStringContainsString('ใช้แล้ว', session('errors')['email']);
    }

    public function testAdministratorCannotManageDevelopersOrAssignDeveloperRole(): void
    {
        $admin = $this->admin();
        $dev   = $this->admin('developer');

        $this->actingAs($admin)->get('backend/users/' . $dev['id'] . '/edit')->assertStatus(403);
        $this->actingAs($admin)->post('backend/users/' . $dev['id'], $this->form())->assertStatus(403);
        $this->actingAs($admin)->post('backend/users/' . $dev['id'] . '/delete')->assertStatus(403);
        $this->actingAs($admin)->post('backend/users/' . $dev['id'] . '/reset-password')->assertStatus(403);

        $this->actingAs($admin)->post('backend/users', $this->form(['role' => 'developer']));
        $this->assertStringContainsString('ไม่มีสิทธิ์กำหนดบทบาทนี้', session('errors')['role']);

        // developer จัดการได้ทุกคน
        $this->actingAs($dev)->get('backend/users/' . $admin['id'] . '/edit')->assertOK();
    }

    public function testCannotDeleteDeactivateOrChangeRoleOfSelf(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('backend/users/' . $admin['id'] . '/delete');
        $this->assertNull((new UserModel())->find($admin['id'])['deleted_at']);

        $this->actingAs($admin)->post('backend/users/' . $admin['id'] . '/toggle', ['value' => '0'])->assertStatus(403);

        $this->actingAs($admin)->post('backend/users/' . $admin['id'], $this->form([
            'username' => $admin['username'], 'email' => $admin['email'], 'role' => 'user', 'is_active' => '0',
        ]));
        $fresh = (new UserModel())->find($admin['id']);
        $this->assertSame('administrator', $fresh['role']);
        $this->assertSame('1', (string) $fresh['is_active']);
    }

    public function testChangingOwnEmailRequiresCurrentPassword(): void
    {
        $admin = $this->admin();
        $data  = $this->form(['username' => $admin['username'], 'email' => 'changed@example.com']);

        $this->actingAs($admin)->post('backend/users/' . $admin['id'], $data);
        $this->assertStringContainsString('กรุณากรอกรหัสผ่านปัจจุบัน', session('errors')['current_password']);

        $this->actingAs($admin)->post('backend/users/' . $admin['id'], $data + ['current_password' => 'wrong']);
        $this->assertSame('รหัสผ่านปัจจุบันไม่ถูกต้อง', session('errors')['current_password']);

        $this->actingAs($admin)->post('backend/users/' . $admin['id'], $data + ['current_password' => 'secret123']);
        $this->assertSame('changed@example.com', (new UserModel())->find($admin['id'])['email']);

        // แก้อีเมลคนอื่นไม่ต้องใช้รหัส
        $other = $this->makeUser();
        $this->actingAs($admin)->post('backend/users/' . $other['id'], $this->form(['username' => $other['username'], 'email' => 'other2@example.com']));
        $this->assertSame('other2@example.com', (new UserModel())->find($other['id'])['email']);
    }

    public function testResetPasswordUnlockDeleteAndRestore(): void
    {
        $admin = $this->admin();
        $user  = $this->makeUser(['username' => 'staff']);
        (new UserModel())->updateQuietly($user['id'], ['failed_login_attempts' => 0, 'locked_until' => date('Y-m-d H:i:s', time() + 600)]);

        $this->actingAs($admin)->post('backend/users/' . $user['id'] . '/unlock');
        $this->assertFalse(UserModel::isLocked((new UserModel())->find($user['id'])));
        $this->assertSame('unlock', $this->lastLog()['action']);

        $this->actingAs($admin)->post('backend/users/' . $user['id'] . '/reset-password');
        $fresh = (new UserModel())->find($user['id']);
        $this->assertTrue(password_verify('passwordemcc', $fresh['password']));
        $this->assertSame('1', (string) $fresh['must_change_password']);
        $this->assertSame('password_reset', $this->lastLog()['action']);

        $this->actingAs($admin)->post('backend/users/' . $user['id'] . '/delete');
        $this->assertNull((new UserModel())->find($user['id']));
        $this->assertNotNull((new UserModel())->withDeleted()->find($user['id']));

        $this->actingAs($admin)->post('backend/users/' . $user['id'] . '/restore');
        $this->assertNotNull((new UserModel())->find($user['id']));
        $this->assertSame('restore', $this->lastLog()['action']);
    }

    public function testProfileUsernameChangeOnlyForAdmins(): void
    {
        $dev = $this->admin('developer');
        $this->actingAs($dev)->post('backend/profile', ['username' => ' Dev.Lead ', 'email' => $dev['email'], 'first_name' => 'ก', 'last_name' => 'ข']);
        $this->assertSame('dev.lead', (new UserModel())->find($dev['id'])['username']);

        $user = $this->makeUser(['username' => 'staff']);
        $res  = $this->actingAs($user)->get('backend/profile');
        $res->assertSee('ต้องการเปลี่ยนชื่อผู้ใช้ กรุณาติดต่อผู้ดูแลระบบ');
        $this->actingAs($user)->post('backend/profile', ['username' => 'hacker', 'email' => $user['email'], 'first_name' => 'ก', 'last_name' => 'ข']);
        $this->assertSame('staff', (new UserModel())->find($user['id'])['username']);
    }

    public function testProfilePasswordChange(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('backend/profile/password', ['current_password_pw' => 'wrong', 'password' => 'newpass1', 'password_confirmation' => 'newpass1']);
        $this->assertSame('รหัสผ่านปัจจุบันไม่ถูกต้อง', session('errors')['current_password_pw']);

        $this->actingAs($user)->post('backend/profile/password', ['current_password_pw' => 'secret123', 'password' => ' spaced ', 'password_confirmation' => ' spaced ']);
        $this->assertTrue(password_verify(' spaced ', (new UserModel())->find($user['id'])['password']), 'รหัสผ่านไม่ถูก trim');
        $this->assertSame('password_change', $this->lastLog()['action']);
    }

    public function testUserListAndActivityLogPages(): void
    {
        $admin = $this->admin();
        $res   = $this->actingAs($admin)->get('backend/users?q=' . $admin['username']);
        $res->assertOK();
        $res->assertSee($admin['email']);

        $res = $this->actingAs($admin)->get('backend/activity-logs?action=create');
        $res->assertOK();
        $log = $this->lastLog();
        $this->actingAs($admin)->get('backend/activity-logs/' . $log['id'])->assertOK();
        $this->actingAs($admin)->get('backend/activity-logs?from=2026-12-01&until=2026-01-01')->assertSee('วันสิ้นสุดต้องไม่ก่อนวันเริ่ม');

        // ไม่มีเส้นทางแก้/ลบ log
        $this->assertNotFound(fn () => $this->actingAs($admin)->post('backend/activity-logs/' . $log['id'] . '/delete'));
    }
}

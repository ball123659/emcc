<?php

namespace Tests\Feature;

use App\Libraries\Auth;
use App\Models\UserModel;
use Tests\Support\TestCase;

/**
 * ล็อกอิน / ล็อกบัญชี / บังคับเปลี่ยนรหัส / จดจำฉัน / ออกจากระบบ
 */
final class AuthTest extends TestCase
{
    public function testLoginSuccessLogsInAndRecordsActivity(): void
    {
        $user = $this->makeUser(['username' => 'staff']);

        $res = $this->post('backend/login', ['username' => '  STAFF ', 'password' => 'secret123']);
        $res->assertRedirectTo(site_url('backend'));
        $this->assertSame((int) $user['id'], (int) session('auth_user_id'));

        $fresh = (new UserModel())->find($user['id']);
        $this->assertNotNull($fresh['last_login_at']);
        $this->assertSame('login', $this->lastLog()['action']);
    }

    public function testWrongPasswordShowsThaiErrorAndLocksAfterFiveAttempts(): void
    {
        $user = $this->makeUser(['username' => 'staff']);

        for ($i = 1; $i <= 4; $i++) {
            $this->post('backend/login', ['username' => 'staff', 'password' => 'wrong']);
            $this->assertStringContainsString('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', session('errors')['username']);
        }

        $this->post('backend/login', ['username' => 'staff', 'password' => 'wrong']);
        $this->assertStringContainsString('ถูกล็อกชั่วคราว', session('errors')['username']);
        $this->assertTrue(UserModel::isLocked((new UserModel())->find($user['id'])));

        // รหัสถูกก็เข้าไม่ได้ระหว่างล็อก
        $this->post('backend/login', ['username' => 'staff', 'password' => 'secret123']);
        $this->assertNull(session('auth_user_id'));
        $this->assertStringContainsString('ถูกล็อกชั่วคราว', session('errors')['username']);

        // ไม่บันทึกรหัสผ่านใน log
        $this->assertSame(0, db_connect()->table('activity_logs')->like('changes', 'wrong')->orLike('description', 'secret123')->countAllResults());
    }

    public function testUnknownUsernameIsLockedTooAndMasked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('backend/login', ['username' => 'nobody123', 'password' => 'x']);
        }

        $this->assertStringContainsString('ถูกล็อกชั่วคราว', session('errors')['username']);
        $log = $this->lastLog('login_failed');
        $this->assertStringContainsString('no***', $log['description']);
        $this->assertStringNotContainsString('nobody123', $log['description']);
    }

    public function testInactiveAndDeletedAccountsCannotLogIn(): void
    {
        $this->makeUser(['username' => 'off', 'is_active' => 0]);
        $this->post('backend/login', ['username' => 'off', 'password' => 'secret123']);
        $this->assertSame('บัญชีนี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ', session('errors')['username']);

        $gone = $this->makeUser(['username' => 'gone']);
        (new UserModel())->delete($gone['id']);
        $this->post('backend/login', ['username' => 'gone', 'password' => 'secret123']);
        $this->assertNull(session('auth_user_id'));
    }

    public function testEmptyFieldsShowThaiMessages(): void
    {
        $this->post('backend/login', ['username' => '', 'password' => '']);
        $this->assertSame('กรุณากรอกชื่อผู้ใช้', session('errors')['username']);
        $this->assertSame('กรุณากรอกรหัสผ่าน', session('errors')['password']);
    }

    public function testFirstLoginMustChangePasswordBeforeUsingOtherPages(): void
    {
        $user = $this->makeUser(['must_change_password' => 1], ['news']);

        $this->actingAs($user)->get('backend/news')->assertRedirectTo(site_url('backend/change-password'));
        $this->actingAs($user)->get('backend')->assertRedirectTo(site_url('backend/change-password'));

        $this->actingAs($user)->post('backend/change-password', ['password' => 'passwordemcc', 'password_confirmation' => 'passwordemcc']);
        $this->assertStringContainsString('ห้ามใช้รหัสผ่านเริ่มต้น', session('errors')['password']);

        $this->actingAs($user)->post('backend/change-password', ['password' => '12345', 'password_confirmation' => '12345']);
        $this->assertStringContainsString('อย่างน้อย 6 ตัวอักษร', session('errors')['password']);

        $this->actingAs($user)->post('backend/change-password', ['password' => 'newpass1', 'password_confirmation' => 'newpass2']);
        $this->assertStringContainsString('ไม่ตรงกัน', session('errors')['password_confirmation']);

        $this->actingAs($user)->post('backend/change-password', ['password' => 'newpass1', 'password_confirmation' => 'newpass1'])
            ->assertRedirectTo(site_url('backend'));

        $fresh = (new UserModel())->find($user['id']);
        $this->assertSame('0', (string) $fresh['must_change_password']);
        $this->assertTrue(password_verify('newpass1', $fresh['password']));
        $this->assertStringStartsWith('$2y$12$', $fresh['password']);
        $this->assertSame('password_change', $this->lastLog()['action']);
    }

    public function testDeactivatedUserIsLoggedOutMidSession(): void
    {
        $user = $this->makeUser([], ['news']);
        $this->actingAs($user)->get('backend/news')->assertOK();

        (new UserModel())->updateQuietly($user['id'], ['is_active' => 0]);

        $this->actingAs($user)->get('backend/news')->assertRedirectTo(site_url('backend/login'));
        $this->assertStringContainsString('บัญชีของคุณถูกปิดการใช้งาน', session('toast')['title']);
    }

    public function testPasswordChangedElsewhereEndsOtherSessions(): void
    {
        $user    = $this->makeUser([], ['news']);
        $session = ['auth_user_id' => (int) $user['id'], 'auth_hash' => Auth::passwordFingerprint($user)];

        (new UserModel())->updateQuietly($user['id'], ['password' => UserModel::hashPassword('changed99')]);

        $this->withSession($session)->get('backend/news')->assertRedirectTo(site_url('backend/login'));
        $this->assertStringContainsString('รหัสผ่านของบัญชีนี้ถูกเปลี่ยน', session('toast')['title']);
    }

    public function testRememberMeCookieLogsInAgainAndRotates(): void
    {
        $user = $this->makeUser(['username' => 'staff']);
        $res  = $this->post('backend/login', ['username' => 'staff', 'password' => 'secret123', 'remember' => '1']);
        $cookie = $res->response()->getCookie(Auth::REMEMBER_COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertGreaterThan(time() + 29 * 86400, $cookie->getExpiresTimestamp());

        $stored = (new UserModel())->find($user['id'])['remember_token'];
        $this->assertSame(hash('sha256', explode('|', $cookie->getValue())[1]), $stored, 'ฐานข้อมูลเก็บเฉพาะ hash');

        // session หมด -> ใช้ cookie เข้าได้
        $_COOKIE[Auth::REMEMBER_COOKIE] = $cookie->getValue();
        $this->withSession(['fresh' => 1])->get('backend')->assertOK();
        $this->assertNotSame($stored, (new UserModel())->find($user['id'])['remember_token'], 'หมุน token ใหม่');

        // token เก่าใช้ซ้ำไม่ได้
        $this->withSession(['fresh' => 2])->get('backend')->assertRedirectTo(site_url('backend/login'));
        unset($_COOKIE[Auth::REMEMBER_COOKIE]);
    }

    public function testLogoutRequiresPostAndClearsSession(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('backend/logout')->assertRedirectTo(site_url('backend/login'));
        $this->assertNull(session('auth_user_id'));
        $this->assertSame('logout', $this->lastLog()['action']);
    }

    public function testIntendedUrlAfterLoginButNoOpenRedirect(): void
    {
        $this->makeUser(['username' => 'staff'], ['news']);
        $this->withSession([])->get('backend/news?tab=draft');
        $this->assertSame('/backend/news?tab=draft', session('auth_intended'));
        $this->withSession(['auth_intended' => '/backend/news?tab=draft'])->post('backend/login', ['username' => 'staff', 'password' => 'secret123'])
            ->assertRedirectTo(base_url('backend/news?tab=draft'));

        $this->withSession(['auth_intended' => '//evil.example/backend'])->post('backend/login', ['username' => 'staff', 'password' => 'secret123'])
            ->assertRedirectTo(site_url('backend'));
    }

    public function testCsrfTokenIsRequired(): void
    {
        $this->makeUser(['username' => 'staff']);
        $this->withoutCsrf()->post('backend/login', ['username' => 'staff', 'password' => 'secret123']);
        $this->assertNull(session('auth_user_id'));
        $this->assertStringContainsString('หน้านี้หมดอายุ', (string) session('toast')['title']);
    }
}

<?php

namespace Tests\Feature;

use App\Controllers\Backend\Auth as AuthController;
use App\Models\UserModel;
use Tests\Support\TestCase;

final class ContinuedAuditTest extends TestCase
{
    public function testLoginPreservesPasswordBytes(): void
    {
        $password = " secret\x01value ";
        $user = $this->makeUser(['username' => 'exactpassword', 'password' => $password]);
        $this->post('backend/login', ['username' => 'exactpassword', 'password' => $password]);
        $this->assertSame((int) $user['id'], (int) session('auth_user_id'));
    }

    public function testPasswordValidationRejectsBcryptTruncationAndNullBytes(): void
    {
        foreach ([str_repeat('a', 73), str_repeat('ก', 25), "secret\0value"] as $password) {
            $this->assertArrayHasKey('password', AuthController::passwordErrors($password, $password));
        }
        $password = str_repeat('ก', 24);
        $this->assertSame([], AuthController::passwordErrors($password, $password));
    }

    public function testPasswordLookingLikeHashIsStillPlaintextInput(): void
    {
        $password = password_hash('x', PASSWORD_BCRYPT, ['cost' => 4]);
        $user = $this->makeUser(['password' => $password]);
        $this->assertTrue(password_verify($password, $user['password']));
        $this->assertFalse(password_verify('x', $user['password']));
    }

    public function testForcedPasswordChangeWorksInSubdirectory(): void
    {
        config('App')->baseURL = 'http://localhost/emcc/';
        $user = $this->makeUser(['must_change_password' => 1]);
        $this->actingAs($user)->get('backend/change-password')->assertStatus(200);
    }

    public function testBackendInSubdirectoryCannotBeCached(): void
    {
        config('App')->baseURL = 'http://localhost/emcc/';
        $response = $this->get('backend/login');
        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
    public function testRevokedSessionCannotPreviewHiddenDownload(): void
    {
        $user = $this->makeUser([], ['downloads']);
        $id = (new \App\Models\DownloadModel())->insert([
            'category_id' => 1, 'title_th' => 'Private', 'type' => 'link',
            'external_url' => 'https://example.com/private', 'is_active' => 0,
        ]);
        $this->actingAs($user)->get('downloads/' . $id)->assertRedirectTo('https://example.com/private');
        $this->actingAs($user);
        (new UserModel())->updateQuietly($user['id'], ['password' => UserModel::hashPassword('replacement')]);
        $this->assertNotFound(fn () => $this->get('downloads/' . $id));
    }

    public function testInitialPasswordCannotPreviewHiddenDownload(): void
    {
        $user = $this->makeUser(['must_change_password' => 1], ['downloads']);
        $id = (new \App\Models\DownloadModel())->insert([
            'category_id' => 1, 'title_th' => 'Private', 'type' => 'link',
            'external_url' => 'https://example.com/private', 'is_active' => 0,
        ]);
        $this->assertNotFound(fn () => $this->actingAs($user)->get('downloads/' . $id));
    }

    public function testLoginWithoutRememberRemovesPreviousRememberCookie(): void
    {
        $this->makeUser(['username' => 'seconduser']);
        $_COOKIE[\App\Libraries\Auth::REMEMBER_COOKIE] = '999|' . str_repeat('a', 64);
        $response = $this->post('backend/login', ['username' => 'seconduser', 'password' => 'secret123']);
        $cookie = $response->response()->getCookie(\App\Libraries\Auth::REMEMBER_COOKIE);
        $this->assertNotNull($cookie);
        $this->assertLessThan(time(), $cookie->getExpiresTimestamp());
    }
}

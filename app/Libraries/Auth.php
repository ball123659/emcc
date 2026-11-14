<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;
use App\Models\UserModel;
use Config\Emcc;

class Auth
{
    public const REMEMBER_COOKIE = 'emcc_remember';

    private const DUMMY_HASH = '$2y$12$AWdUrixJQ9.NhuRP9fDr7u4oOhZJhwrxVdJQvjx6hAGF5.pFbDIJW';

    public const CURRENT_PASSWORD_MAX_ATTEMPTS = 5;

    private ?array $user = null;

    private bool $loaded = false;

    private ?array $visiblePageCodes = null;

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }

        $this->loaded = true;

        if (! $this->hasSession()) {
            return null;
        }

        $id = session('auth_user_id');

        if ($id === null) {
            return null;
        }

        $user = (new UserModel())->withDeleted()->find($id);

        return $this->user = $user ?: null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return ($user = $this->user()) !== null && UserModel::isAdmin($user);
    }

    public function isDeveloper(): bool
    {
        return ($user = $this->user()) !== null && UserModel::isDeveloper($user);
    }

    public function canViewPage(string $code): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (UserModel::isAdmin($user)) {
            return true;
        }

        $this->visiblePageCodes ??= array_column(
            db_connect()->table('pages')->select('pages.code')
                ->join('user_page_permissions upp', 'upp.page_id = pages.id')
                ->where('upp.user_id', $user['id'])->where('upp.can_view', 1)->where('pages.admin_only', 0)
                ->get()->getResultArray(),
            'code',
        );

        return in_array($code, $this->visiblePageCodes, true);
    }

    public function refresh(): void
    {
        $this->loaded           = false;
        $this->user             = null;
        $this->visiblePageCodes = null;
    }

    public function invalidReason(): ?string
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        if (! empty($user['deleted_at']) || ! (bool) $user['is_active']) {
            return 'บัญชีของคุณถูกปิดการใช้งาน ระบบได้ออกจากระบบให้แล้ว หากเป็นความผิดพลาด กรุณาติดต่อผู้ดูแลระบบ';
        }

        if (! hash_equals(self::passwordFingerprint($user), (string) session('auth_hash'))) {
            return 'รหัสผ่านของบัญชีนี้ถูกเปลี่ยน กรุณาเข้าสู่ระบบใหม่';
        }

        return null;
    }

    public function attempt(string $username, string $password, bool $remember = false): ?string
    {
        $config   = config(Emcc::class);
        $username = mb_strtolower(trim($username));
        $model    = new UserModel();
        $user     = $username === '' ? null : $model->withDeleted()->where('username', $username)->first();

        if ($user !== null && ! empty($user['deleted_at'])) {
            $user = null;
        }

        if ($user !== null) {
            $user = $this->resetStaleAttempts($user);
        }

        if ($user !== null && UserModel::isLocked($user)) {
            ActivityLogger::log('login_failed', 'users', $user['id'], 'พยายามเข้าสู่ระบบระหว่างบัญชีถูกล็อก', null, (int) $user['id']);

            return $this->lockedMessage(strtotime($user['locked_until']));
        }

        $unknownKey = 'login_unknown_' . sha1($username);

        if ($user === null && $username !== '' && (int) (cache($unknownKey)['count'] ?? 0) >= $config->loginMaxAttempts) {
            return $this->lockedMessage((int) cache($unknownKey)['until']);
        }

        $valid = password_verify($password, $user !== null ? (string) $user['password'] : self::DUMMY_HASH) && $user !== null;

        if ($valid && ! (bool) $user['is_active']) {
            ActivityLogger::log('login_failed', 'users', $user['id'], 'บัญชีถูกปิดการใช้งาน', null, (int) $user['id']);

            return 'บัญชีนี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
        }

        if (! $valid) {
            return $this->recordFailedAttempt($user, $username, $unknownKey);
        }

        if (strlen($password) <= 72 && ! str_contains($password, "\0") && password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $model->updateQuietly($user['id'], ['password' => UserModel::hashPassword($password)]);
            $user = $model->find($user['id']);
        }

        $this->login($user, $remember);

        return null;
    }

    public function login(array $user, bool $remember = false): void
    {
        $session = session();
        $session->regenerate(true);
        $session->set(['auth_user_id' => (int) $user['id'], 'auth_hash' => self::passwordFingerprint($user)]);

        (new UserModel())->updateQuietly($user['id'], [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => date('Y-m-d H:i:s'),
            'last_login_ip'         => service('request')->getIPAddress(),
        ]);

        $this->refresh();

        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        } else {
            $this->forgetRememberCookie();
        }

        ActivityLogger::log('login', 'users', $user['id'], 'เข้าสู่ระบบ', null, (int) $user['id']);
    }

    public function logout(bool $log = true): void
    {
        $user = $this->user();

        if ($user !== null) {
            if ($log) {
                ActivityLogger::log('logout', 'users', $user['id'], 'ออกจากระบบ', null, (int) $user['id']);
            }

            (new UserModel())->updateQuietly($user['id'], ['remember_token' => null]);
        }

        $this->forgetRememberCookie();

        if ($this->hasSession()) {
            session()->remove(['auth_user_id', 'auth_hash']);
            session()->regenerate(true);
        }

        $this->refresh();
    }

    public function verifyCurrentPassword(array $user, string $password): ?string
    {
        $key     = 'current_password_' . (int) $user['id'];
        $minutes = config(Emcc::class)->loginLockoutMinutes;
        $entry   = cache($key);
        $entry   = is_array($entry) ? $entry : ['count' => 0, 'until' => 0];

        if ($entry['count'] >= self::CURRENT_PASSWORD_MAX_ATTEMPTS && $entry['until'] > time()) {
            return 'ใส่รหัสผ่านปัจจุบันผิดหลายครั้ง กรุณารออีก ' . max(1, (int) ceil(($entry['until'] - time()) / 60)) . ' นาทีแล้วลองใหม่';
        }

        if ($password === '') {
            return 'กรุณากรอกรหัสผ่านปัจจุบัน';
        }

        if (password_verify($password, (string) $user['password'])) {
            cache()->delete($key);

            return null;
        }

        $entry['count']++;
        $entry['until'] = time() + $minutes * 60;
        cache()->save($key, $entry, $minutes * 60);

        return 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    }

    public function refreshPasswordFingerprint(): void
    {
        $this->refresh();

        if (($user = $this->user()) !== null) {
            session()->set('auth_hash', self::passwordFingerprint($user));
            session()->regenerate(true);
        }
    }

    public function loginFromRememberCookie(): bool
    {
        $cookie = (string) service('request')->getCookie(self::REMEMBER_COOKIE);

        if (! preg_match('/^(\d{1,19})\|([a-f0-9]{64})$/', $cookie, $m)) {
            return false;
        }

        $user = (new UserModel())->find((int) $m[1]);

        if ($user === null || empty($user['remember_token']) || ! hash_equals($user['remember_token'], hash('sha256', $m[2]))
            || ! (bool) $user['is_active'] || UserModel::isLocked($user)) {
            $this->forgetRememberCookie();

            return false;
        }

        $session = session();
        $session->regenerate(true);
        $session->set(['auth_user_id' => (int) $user['id'], 'auth_hash' => self::passwordFingerprint($user)]);
        $this->refresh();

        $this->issueRememberToken((int) $user['id']);

        return true;
    }

    private function issueRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        (new UserModel())->updateQuietly($userId, ['remember_token' => hash('sha256', $token)]);

        $days = config(Emcc::class)->rememberDays;
        service('response')->setCookie(self::REMEMBER_COOKIE, $userId . '|' . $token, $days * 86400, '', '/', '', null, true, 'Lax');
    }

    private function forgetRememberCookie(): void
    {
        if (service('request')->getCookie(self::REMEMBER_COOKIE) !== null) {
            service('response')->deleteCookie(self::REMEMBER_COOKIE);
        }
    }

    public static function passwordFingerprint(array $user): string
    {
        return hash('sha256', (string) $user['password']);
    }

    private function resetStaleAttempts(array $user): array
    {
        if ((int) $user['failed_login_attempts'] === 0 || UserModel::isLocked($user)) {
            return $user;
        }

        $last = (new ActivityLogModel())->where('user_id', $user['id'])->where('action', 'login_failed')->orderBy('id', 'desc')->first();

        if ($last === null || strtotime($last['created_at']) < time() - config(Emcc::class)->loginLockoutMinutes * 60) {
            (new UserModel())->updateQuietly($user['id'], ['failed_login_attempts' => 0]);
            $user['failed_login_attempts'] = 0;
        }

        return $user;
    }

    private function recordFailedAttempt(?array $user, string $username, string $unknownKey): string
    {
        $config  = config(Emcc::class);
        $failure = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบแล้วลองใหม่ (ระวังปุ่ม Caps Lock)';

        if ($username === '') {
            return $failure;
        }

        if ($user === null) {
            $entry = cache($unknownKey);
            $entry = is_array($entry) ? $entry : ['count' => 0, 'until' => 0];
            $entry['count']++;
            $entry['until'] = time() + $config->loginLockoutMinutes * 60;
            cache()->save($unknownKey, $entry, $config->loginLockoutMinutes * 60);

            ActivityLogger::log('login_failed', null, null, 'ไม่พบชื่อผู้ใช้: ' . self::mask($username));

            return $entry['count'] >= $config->loginMaxAttempts ? $this->lockedMessage($entry['until']) : $failure;
        }

        $attempts = (int) $user['failed_login_attempts'] + 1;
        $locked   = $attempts >= $config->loginMaxAttempts;
        $until    = time() + $config->loginLockoutMinutes * 60;

        (new UserModel())->updateQuietly($user['id'], [
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until'          => $locked ? date('Y-m-d H:i:s', $until) : null,
        ]);

        ActivityLogger::log(
            'login_failed',
            'users',
            $user['id'],
            $locked ? "รหัสผ่านผิดครบ {$attempts} ครั้ง ล็อกบัญชีชั่วคราว" : "รหัสผ่านผิดครั้งที่ {$attempts}",
            null,
            (int) $user['id'],
        );

        return $locked ? $this->lockedMessage($until) : $failure;
    }

    public static function mask(string $value): string
    {
        return mb_substr($value, 0, 2) . '*** (' . mb_strlen($value) . ' ตัวอักษร)';
    }

    private function lockedMessage(int $until): string
    {
        $minutes = max(1, (int) ceil(($until - time()) / 60));

        return 'บัญชีนี้ถูกล็อกชั่วคราวเพราะใส่รหัสผ่านผิดเกิน ' . config(Emcc::class)->loginMaxAttempts
            . " ครั้ง กรุณาลองใหม่อีก {$minutes} นาที หรือติดต่อผู้ดูแลระบบให้รีเซ็ตรหัสผ่าน";
    }

    private function hasSession(): bool
    {
        return ! is_cli() || ENVIRONMENT === 'testing';
    }
}

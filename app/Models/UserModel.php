<?php

namespace App\Models;

use App\Libraries\ActivityLogger;

class UserModel extends BaseModel
{
    public const ROLE_DEVELOPER     = 'developer';
    public const ROLE_ADMINISTRATOR = 'administrator';
    public const ROLE_USER          = 'user';

    public const ROLES = [
        self::ROLE_DEVELOPER     => 'Developer',
        self::ROLE_ADMINISTRATOR => 'Administrator',
        self::ROLE_USER          => 'User',
    ];

    public const USERNAME_PATTERN = '/^[a-z0-9._-]+$/';

    protected $table          = 'users';
    protected $useSoftDeletes = true;
    protected bool $tracksUser   = true;
    protected bool $logsActivity = true;
    protected $allowedFields  = [
        'username', 'password', 'role', 'first_name', 'last_name', 'email', 'phone', 'position',
        'is_active', 'must_change_password', 'password_changed_at', 'failed_login_attempts', 'locked_until',
        'remember_token', 'created_by', 'updated_by',
    ];

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        array_unshift($this->beforeInsert, 'normalize');
        array_unshift($this->beforeUpdate, 'normalize');
        $this->afterInsert[] = 'createPermissionRows';
    }

    public static function hashPassword(string $password): string
    {
        if (strlen($password) > 72 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('รหัสผ่านต้องไม่เกิน 72 ไบต์และไม่มีอักขระ null');
        }

        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    protected function normalize(array $data): array
    {
        $row = &$data['data'];

        foreach (['username', 'email'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = mb_strtolower(trim((string) $row[$field]));
            }
        }

        foreach (['first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = trim((string) $row[$field]);
            }
        }

        if (array_key_exists('position', $row)) {
            $row['position'] = trim((string) $row['position']) === '' ? null : trim((string) $row['position']);
        }

        if (array_key_exists('phone', $row)) {
            $digits       = preg_replace('/\D+/', '', (string) $row['phone']);
            $row['phone'] = $digits === '' ? null : $digits;
        }

        if (array_key_exists('password', $row)) {
            $row['password'] = self::hashPassword((string) $row['password']);
        }

        return $data;
    }

    protected function createPermissionRows(array $data): array
    {
        if ($data['result'] ?? false) {
            UserPagePermissionModel::createDefaultsForUser((int) $data['id']);
        }

        return $data;
    }

    public static function fullName(array $user): string
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        return $name !== '' ? $name : (string) ($user['username'] ?? '');
    }

    public static function isAdmin(array $user): bool
    {
        return in_array($user['role'] ?? null, [self::ROLE_DEVELOPER, self::ROLE_ADMINISTRATOR], true);
    }

    public static function isDeveloper(array $user): bool
    {
        return ($user['role'] ?? null) === self::ROLE_DEVELOPER;
    }

    public static function canManage(array $actor, array $target): bool
    {
        if (! self::isAdmin($actor)) {
            return false;
        }

        return ! (self::isDeveloper($target) && ! self::isDeveloper($actor));
    }

    public static function assignableRoles(array $actor): array
    {
        return self::isDeveloper($actor) ? self::ROLES : array_diff_key(self::ROLES, [self::ROLE_DEVELOPER => true]);
    }

    public static function isLocked(array $user): bool
    {
        return ! empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time();
    }

    public function unlock(int $id, string $username): void
    {
        $this->updateQuietly($id, ['failed_login_attempts' => 0, 'locked_until' => null]);
        ActivityLogger::log('unlock', 'users', $id, "ปลดล็อกบัญชี {$username}");
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksUser;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable, SoftDeletes, TracksUser;

    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_USER = 'user';

    public const ROLES = [
        self::ROLE_DEVELOPER => 'Developer',
        self::ROLE_ADMINISTRATOR => 'Administrator',
        self::ROLE_USER => 'User',
    ];

    protected $fillable = [
        'username',
        'password',
        'role',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'is_active',
        'must_change_password',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => self::ROLE_USER,
        'is_active' => true,
        'must_change_password' => true,
        'failed_login_attempts' => 0,
        'deleted_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // ผู้ใช้ใหม่ได้แถวสิทธิ์ของทุกหน้า = 0 (ทำในโค้ดเท่านั้น ไม่ใช้ trigger)
        static::created(fn (User $user) => UserPagePermission::createDefaultsForUser($user));
    }

    /** username: trim + ตัวพิมพ์เล็ก */
    protected function username(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    protected function firstName(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : trim($value));
    }

    protected function lastName(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : trim($value));
    }

    protected function position(): Attribute
    {
        return Attribute::set(fn (?string $value) => blank($value) ? null : trim($value));
    }

    /** เบอร์โทรเก็บเฉพาะตัวเลข */
    protected function phone(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $digits = preg_replace('/\D+/', '', (string) $value);

            return $digits === '' ? null : $digits;
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    public function getFilamentName(): string
    {
        return $this->full_name !== '' ? $this->full_name : $this->username;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    /** developer / administrator เข้าได้ทุกหน้าเสมอ */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_DEVELOPER, self::ROLE_ADMINISTRATOR], true);
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    /** ผู้ใช้คนนี้ (actor) จัดการบัญชี $target ได้หรือไม่ — administrator แก้/ลบ developer ไม่ได้ */
    public function canManage(User $target): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        return ! ($target->isDeveloper() && ! $this->isDeveloper());
    }

    /** บทบาทที่ผู้ใช้คนนี้กำหนดให้คนอื่นได้ */
    public function assignableRoles(): array
    {
        return $this->isDeveloper()
            ? self::ROLES
            : array_diff_key(self::ROLES, [self::ROLE_DEVELOPER => true]);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** ตรวจสิทธิ์เห็นหน้า ตาม pages.code */
    public function canViewPage(string $code): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->pages()
            ->where('pages.code', $code)
            ->where('pages.admin_only', false)
            ->wherePivot('can_view', true)
            ->exists();
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'user_page_permissions')
            ->using(UserPagePermission::class)
            ->withPivot(['can_view', 'updated_by', 'updated_at']);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPagePermission::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}

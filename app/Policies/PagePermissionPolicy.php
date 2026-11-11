<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy กลางสำหรับข้อมูลที่ใช้สิทธิ์รายหน้า (เห็น = ใช้งานได้ทั้งเพิ่ม/แก้/ลบ/กู้คืน)
 * ลูกคลาสกำหนด protected string $pageCode ให้ตรง pages.code — ไม่มีการลบถาวร
 */
abstract class PagePermissionPolicy
{
    protected string $pageCode;

    protected function allowed(User $user): bool
    {
        return $user->canViewPage($this->pageCode);
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function reorder(User $user): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\User;

/**
 * จัดการผู้ใช้งาน — เฉพาะ developer / administrator
 * - administrator แก้/ลบ developer ไม่ได้
 * - ห้ามลบบัญชีตัวเอง (การปิดบัญชีตัวเองตรวจในฟอร์ม/หน้าแก้ไข)
 * - ลบแบบกู้คืนได้เท่านั้น (ไม่มีลบถาวร)
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->canManage($target) && ! $target->trashed();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->canManage($target) && $actor->isNot($target);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->canManage($target);
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    /** รีเซ็ตรหัสผ่าน / ปลดล็อก */
    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->canManage($target) && $actor->isNot($target) && ! $target->trashed();
    }
}

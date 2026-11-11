<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * บันทึกการใช้งาน — ดูได้อย่างเดียว (ตามสิทธิ์หน้า activity_logs ซึ่งเป็น admin_only)
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewPage('activity_logs');
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->canViewPage('activity_logs');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * วิดเจ็ตแดชบอร์ดแสดงเมื่อผู้ใช้มีสิทธิ์หน้า dashboard (developer/administrator เห็นเสมอ)
 * และไม่อยู่ระหว่างบังคับเปลี่ยนรหัสผ่าน
 */
trait DashboardWidget
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ! $user->must_change_password
            && $user->canViewPage('dashboard');
    }
}

<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * ใช้กับ Resource / Page หลังบ้าน: ตรวจสิทธิ์รายหน้าตาม pages.code ฝั่งเซิร์ฟเวอร์ทุก request
 * - กำหนด protected static string $pageCode = 'news';
 * - developer / administrator เข้าได้ทุกหน้า, user ตาม user_page_permissions.can_view
 * - ระหว่างที่ต้องเปลี่ยนรหัสผ่าน ใช้หน้า/ค้นหา/คำสั่ง Livewire ของหน้าใดไม่ได้เลย (ไม่ใช่แค่ซ่อนเมนู)
 */
trait ChecksPagePermission
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ! $user->must_change_password
            && $user->canViewPage(static::$pageCode)
            && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! Auth::user()?->must_change_password && parent::shouldRegisterNavigation();
    }
}

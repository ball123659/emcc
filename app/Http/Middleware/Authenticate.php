<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Notifications\Notification;

/**
 * บัญชีที่ถูกปิดการใช้งานระหว่างที่ยังล็อกอินอยู่ -> ออกจากระบบทันที และแจ้งเหตุผลที่หน้าเข้าสู่ระบบ
 * (แทนการค้างอยู่กับหน้า 403 "ไม่มีสิทธิ์")
 *
 * ต้อง extend Authenticate ของ Filament (ไม่ใช่ middleware แยก) เพราะ Laravel จัดลำดับ middleware
 * ตาม priority ทำให้ Authenticate ทำงานก่อน middleware อื่นเสมอ
 */
class Authenticate extends FilamentAuthenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ! $user->is_active) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Notification::make()
                ->danger()
                ->title('บัญชีของคุณถูกปิดการใช้งาน')
                ->body('ระบบได้ออกจากระบบให้แล้ว หากเป็นความผิดพลาด กรุณาติดต่อผู้ดูแลระบบ')
                ->persistent()
                ->send();

            $this->unauthenticated($request, $guards);
        }

        parent::authenticate($request, $guards);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ผู้ใช้ที่ must_change_password = 1 ใช้ได้แค่หน้าโปรไฟล์ (ตั้งรหัสใหม่) และออกจากระบบ
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        $panel = Filament::getCurrentOrDefaultPanel();
        $allowed = [
            $panel->generateRouteName('auth.profile'),
            $panel->generateRouteName('auth.logout'),
        ];

        if (in_array($request->route()?->getName(), $allowed, true)) {
            return $next($request);
        }

        return redirect()->to(Filament::getProfileUrl());
    }
}

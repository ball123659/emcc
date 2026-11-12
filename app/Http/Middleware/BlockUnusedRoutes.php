<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ปิดเส้นทางที่แพ็กเกจเปิดไว้แต่ระบบไม่ได้ใช้ (ทำงานก่อนการจับเส้นทาง)
 * - ระบบส่งออก/นำเข้าของ Filament: ไม่มีตาราง exports/imports เปิดแล้ว error 500 ได้ทุกคน
 */
class BlockUnusedRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('filament/exports/*', 'filament/imports/*')) {
            abort(404);
        }

        return $next($request);
    }
}

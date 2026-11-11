<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

/**
 * แดชบอร์ด — หน้าแรกหลังล็อกอิน ทุกคนเข้าได้ (ข้อมูลสรุปที่จะเพิ่มในลำดับงานข้อ 6 ตรวจสิทธิ์ 'dashboard' แยก)
 */
class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return ! Auth::user()?->must_change_password && parent::shouldRegisterNavigation();
    }
}

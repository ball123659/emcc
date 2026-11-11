<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

/**
 * แดชบอร์ด — หน้าแรกหลังล็อกอิน ทุกคนเข้าได้ (มีกล่องต้อนรับ)
 * ข้อมูลสรุป (จำนวนข่าว, popup, ไฟล์ยอดนิยม) แสดงเฉพาะผู้มีสิทธิ์หน้า dashboard — ดู App\Filament\Widgets\Concerns\DashboardWidget
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'แดชบอร์ด';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! Auth::user()?->must_change_password && parent::shouldRegisterNavigation();
    }
}

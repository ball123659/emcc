<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * ความเคลื่อนไหวล่าสุด (ไทม์ไลน์จาก activity_logs) — เฉพาะผู้มีสิทธิ์ดูบันทึกการใช้งาน
 * เวลาแสดงแบบ "5 นาทีที่แล้ว" และชี้เพื่อดูวันเวลาเต็ม (รูปแบบเดียวกับทั้งระบบ)
 */
class RecentActivity extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.recent-activity';

    public const COLORS = [
        'login' => '#0e975f', 'create' => '#0e975f', 'restore' => '#0e975f',
        'update' => '#2563eb', 'permission_update' => '#2563eb',
        'delete' => '#dc2626', 'login_failed' => '#dc2626',
        'password_reset' => '#e0891b', 'password_change' => '#e0891b', 'unlock' => '#e0891b',
    ];

    public const SUBJECTS = [
        'news' => 'ข่าว', 'slides' => 'สไลด์', 'popups' => 'Popup', 'downloads' => 'ไฟล์ดาวน์โหลด',
        'users' => 'ผู้ใช้', 'site_settings' => 'ตั้งค่าเว็บไซต์', 'news_categories' => 'หมวดหมู่ข่าว',
        'download_categories' => 'หมวดหมู่ไฟล์',
    ];

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ! $user->must_change_password && $user->canViewPage('activity_logs');
    }

    protected function getViewData(): array
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->whereNotIn('action', ['logout'])
            ->latest('id')
            ->limit(8)
            ->get();

        return [
            'logs' => $logs,
            'actions' => ActivityLogResource::ACTIONS,
            'colors' => self::COLORS,
            'subjects' => self::SUBJECTS,
            'url' => ActivityLogResource::getUrl('index'),
        ];
    }
}

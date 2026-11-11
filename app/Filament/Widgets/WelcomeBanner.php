<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Downloads\DownloadResource;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\Popups\PopupResource;
use App\Filament\Resources\Slides\SlideResource;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * แบนเนอร์ต้อนรับ + ปุ่มลัด (แสดงเฉพาะเมนูที่ผู้ใช้มีสิทธิ์) — ทุกคนเห็น
 */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';

    public static function canView(): bool
    {
        return Auth::user() instanceof User && ! Auth::user()->must_change_password;
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $hour = (int) now()->format('G');

        $greeting = match (true) {
            $hour < 12 => 'สวัสดีตอนเช้า',
            $hour < 17 => 'สวัสดีตอนบ่าย',
            default => 'สวัสดีตอนเย็น',
        };

        $actions = array_values(array_filter([
            NewsResource::canAccess() ? ['label' => 'เพิ่มข่าว', 'url' => NewsResource::getUrl('create'), 'icon' => 'heroicon-o-newspaper', 'primary' => true] : null,
            PopupResource::canAccess() ? ['label' => 'เพิ่ม Popup', 'url' => PopupResource::getUrl('create'), 'icon' => 'heroicon-o-window'] : null,
            SlideResource::canAccess() ? ['label' => 'เพิ่มสไลด์', 'url' => SlideResource::getUrl('create'), 'icon' => 'heroicon-o-photo'] : null,
            DownloadResource::canAccess() ? ['label' => 'อัปโหลดไฟล์', 'url' => DownloadResource::getUrl('create'), 'icon' => 'heroicon-o-arrow-up-tray'] : null,
        ]));

        if ($actions !== [] && ! collect($actions)->contains('primary', true)) {
            $actions[0]['primary'] = true;
        }

        return [
            'greeting' => $greeting,
            'name' => $user->first_name ?: $user->username,
            'today' => now()->format(config('emcc.date_format')),
            'weekday' => ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][(int) now()->format('w')],
            'actions' => $actions,
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Downloads\DownloadResource;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\Popups\PopupResource;
use App\Filament\Resources\Slides\SlideResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\Concerns\DashboardWidget;
use App\Models\Download;
use App\Models\News;
use App\Models\Popup;
use App\Models\Slide;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * "งานที่ต้องทำ" — รายการเตือนที่ระบบสร้างให้อัตโนมัติ (แสดงเฉพาะเรื่องที่ผู้ใช้มีสิทธิ์จัดการ)
 */
class TodoList extends Widget
{
    use DashboardWidget;

    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.todo-list';

    protected function getViewData(): array
    {
        $today = today();
        $items = [];

        if (PopupResource::canAccess()) {
            $count = Popup::query()->visible()->whereDate('end_date', '<=', $today->copy()->addDays(3))->count();
            $items[] = [$count, 'Popup ใกล้หมดอายุ', 'ภายใน 3 วัน — ต่ออายุหรือเตรียมรายการใหม่', '#e0891b',
                PopupResource::getUrl('index', ['tab' => 'showing'])];
        }

        if (SlideResource::canAccess()) {
            $count = Slide::query()->where('is_active', true)->whereNotNull('end_date')
                ->whereDate('end_date', '>=', $today)->whereDate('end_date', '<=', $today->copy()->addDays(3))->count();
            $items[] = [$count, 'สไลด์ใกล้หมดอายุ', 'ภายใน 3 วัน', '#e0891b', SlideResource::getUrl('index')];
        }

        if (NewsResource::canAccess()) {
            $items[] = [
                News::query()->where('status', News::STATUS_PUBLISHED)->whereDate('published_at', $today)->count(),
                'ข่าวที่เผยแพร่วันนี้', 'รวมข่าวที่ตั้งเวลาไว้สำหรับวันนี้', '#0e975f', NewsResource::getUrl('index', ['tab' => 'published']),
            ];
            $items[] = [
                News::query()->where('status', News::STATUS_DRAFT)->where('updated_at', '<', now()->subDays(7))->count(),
                'ข่าวร่างค้างเกิน 7 วัน', 'ตรวจทานแล้วเผยแพร่ หรือลบทิ้ง', '#704a9f', NewsResource::getUrl('index', ['tab' => 'draft']),
            ];
        }

        if (DownloadResource::canAccess()) {
            $items[] = [
                Download::query()->where('type', Download::TYPE_FILE)->where('scan_status', '!=', Download::SCAN_CLEAN)->count(),
                'ไฟล์ที่สแกนไม่ผ่าน', 'ไม่แสดงบนหน้าเว็บจนกว่าจะอัปไฟล์ใหม่', '#dc2626', DownloadResource::getUrl('index'),
            ];
        }

        if (UserResource::canAccess()) {
            $items[] = [
                User::query()->whereNotNull('locked_until')->where('locked_until', '>', now())->count(),
                'บัญชีที่ถูกล็อก', 'ใส่รหัสผิดเกินกำหนด — ปลดล็อกหรือรีเซ็ตรหัสได้', '#dc2626', UserResource::getUrl('index'),
            ];
        }

        $todos = collect($items)
            ->filter(fn (array $item) => $item[0] > 0)
            ->map(fn (array $item) => ['count' => $item[0], 'title' => $item[1], 'sub' => $item[2], 'color' => $item[3], 'url' => $item[4]])
            ->values();

        return ['todos' => $todos];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardWidget;
use App\Models\Download;
use App\Models\News;
use App\Models\Popup;
use App\Models\Slide;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * สรุปจำนวนข่าว / Highlight / ไฟล์ดาวน์โหลด
 */
class ContentStats extends StatsOverviewWidget
{
    use DashboardWidget;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $now = now();
        $published = News::query()->where('status', News::STATUS_PUBLISHED)->where('published_at', '<=', $now);
        $thisMonth = (clone $published)->where('published_at', '>=', $now->copy()->startOfMonth())->count();
        $scheduled = News::query()->where('status', News::STATUS_PUBLISHED)->where('published_at', '>', $now)->count();
        $drafts = News::query()->where('status', News::STATUS_DRAFT)->count();

        $popupsShowing = Popup::query()->visible()->count();
        $popupsExpiring = Popup::query()->visible()->whereDate('end_date', '<=', today()->addDays(7))->count();

        return [
            Stat::make('ข่าวที่เผยแพร่แล้ว', number_format($published->count()))
                ->description("เดือนนี้ {$thisMonth} ข่าว")
                ->icon(Heroicon::OutlinedNewspaper)
                ->color('primary'),
            Stat::make('ข่าวตั้งเวลา / ร่าง', number_format($scheduled).' / '.number_format($drafts))
                ->description('รอเผยแพร่ตามวันที่ตั้งไว้ / ยังไม่เผยแพร่')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray'),
            Stat::make('Popup ที่กำลังแสดง', number_format($popupsShowing))
                ->description($popupsExpiring > 0 ? "{$popupsExpiring} รายการจะหมดอายุใน 7 วัน" : 'ไม่มีรายการใกล้หมดอายุ')
                ->icon(Heroicon::OutlinedWindow)
                ->color($popupsExpiring > 0 ? 'warning' : 'success'),
            Stat::make('สไลด์ที่กำลังแสดง', number_format(Slide::query()->visible()->count()))
                ->icon(Heroicon::OutlinedPhoto)
                ->color('success'),
            Stat::make('ไฟล์ดาวน์โหลด', number_format(Download::query()->visible()->count()))
                ->description('ดาวน์โหลดรวม '.number_format((int) Download::query()->sum('download_count')).' ครั้ง')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }
}

<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Popup;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * แท็บกรองพร้อมตัวเลข สำหรับ Slide / Popup: ทั้งหมด / กำลังแสดง / รอแสดง / หมดอายุ / ปิด
 * (ใช้แทนการเปิดตัวกรอง — กดครั้งเดียวเห็นผล)
 */
trait DisplayWindowTabs
{
    /** @return class-string คลาสตารางที่มี filterStatus() */
    abstract protected static function tableClass(): string;

    public function getTabs(): array
    {
        $model = static::getResource()::getModel();
        $table = static::tableClass();

        $tabs = ['all' => Tab::make('ทั้งหมด')->badge(fn () => $model::query()->count())];

        foreach (Popup::STATUS_LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(fn () => $table::filterStatus($model::query(), $status)->count())
                ->badgeColor(Popup::STATUS_COLORS[$status])
                ->modifyQueryUsing(fn (Builder $query) => $table::filterStatus($query, $status));
        }

        return $tabs;
    }
}

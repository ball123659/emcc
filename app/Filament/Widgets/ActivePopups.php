<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Popups\PopupResource;
use App\Filament\Widgets\Concerns\DashboardWidget;
use App\Models\Popup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Popup ที่กำลังแสดง และรอแสดง — เรียงตามวันหมดอายุ (ใกล้หมดอายุขึ้นก่อน)
 */
class ActivePopups extends TableWidget
{
    use DashboardWidget;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Popup ที่กำลังแสดง / ใกล้หมดอายุ';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Popup::query()
                ->where('is_active', true)
                ->whereDate('end_date', '>=', today())
                ->orderBy('end_date')
                ->orderBy('sort_order'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('ไม่มี Popup ที่กำลังแสดง')
            ->columns([
                TextColumn::make('title')->label('ชื่อเรียก')->wrap(),
                TextColumn::make('end_date')->label('สิ้นสุด')->date('d/m/Y'),
                TextColumn::make('display_status')
                    ->label('สถานะ')
                    ->badge()
                    ->state(fn (Popup $record) => Popup::STATUS_LABELS[$record->displayStatus()])
                    ->color(fn (Popup $record) => Popup::STATUS_COLORS[$record->displayStatus()]),
                TextColumn::make('days_left')
                    ->label('เหลือ')
                    ->state(fn (Popup $record) => match ($days = $record->daysUntilExpiry()) {
                        0 => 'หมดวันนี้',
                        default => "{$days} วัน",
                    })
                    ->color(fn (Popup $record) => $record->daysUntilExpiry() <= 3 ? 'danger' : ($record->daysUntilExpiry() <= 7 ? 'warning' : 'gray')),
            ])
            ->recordUrl(fn (Popup $record) => PopupResource::canAccess() ? PopupResource::getUrl('edit', ['record' => $record]) : null);
    }
}

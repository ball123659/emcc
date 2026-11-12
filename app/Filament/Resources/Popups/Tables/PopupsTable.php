<?php

namespace App\Filament\Resources\Popups\Tables;

use App\Filament\Resources\Concerns\HighlightFormComponents as C;
use App\Filament\Resources\Concerns\QuickActions;
use App\Filament\Resources\Concerns\SafeReorder;
use App\Filament\Resources\Popups\PopupResource;
use App\Models\Popup;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PopupsTable
{
    public static function configure(Table $table): Table
    {
        return SafeReorder::configure($table, ' Popup ')
            ->emptyStateIcon('heroicon-o-window')
            ->emptyStateHeading('ไม่มี Popup ในรายการนี้')
            ->emptyStateDescription('Popup จะแสดงเมื่อผู้ชมเปิดหน้าแรก ตามช่วงวันที่ที่กำหนด')
            ->emptyStateActions([
                Action::make('createFirst')
                    ->label('เพิ่ม Popup')
                    ->icon('heroicon-m-plus')
                    ->url(fn () => PopupResource::getUrl('create'))
                    ->visible(fn () => PopupResource::canCreate()),
            ])
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->columns([
                C::thumbnailColumn(),
                TextColumn::make('title')
                    ->label('ชื่อเรียก')
                    ->searchable()
                    ->description(fn (Popup $record) => $record->alt_th)
                    ->wrap(),
                TextColumn::make('link_url')
                    ->label('ลิงก์')
                    ->limit(30)
                    ->placeholder('ไม่มี'),
                C::dateRangeColumn(),
                C::statusColumn(),
                ToggleColumn::make('is_active')
                    ->label('เปิดแสดง')
                    ->disabled(fn (Popup $record) => $record->trashed() || ! auth()->user()->can('update', $record)),
            ])
            ->filters([
                TrashedFilter::make()->label('รายการที่ถูกลบ'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    QuickActions::extendPopup(),
                    QuickActions::duplicate(),
                    DeleteAction::make()->modalDescription('รายการจะหายจากหน้าเว็บ และกู้คืนได้จากตัวกรอง "รายการที่ถูกลบ"'),
                    RestoreAction::make(),
                ]),
            ]);
    }

    public static function filterStatus(Builder $query, ?string $status): Builder
    {
        $today = today()->toDateString();

        return match ($status) {
            Popup::STATUS_DISABLED => $query->where('is_active', false),
            Popup::STATUS_SCHEDULED => $query->where('is_active', true)->whereDate('start_date', '>', $today),
            Popup::STATUS_EXPIRED => $query->where('is_active', true)->whereDate('end_date', '<', $today),
            Popup::STATUS_SHOWING => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
                ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
            default => $query,
        };
    }
}

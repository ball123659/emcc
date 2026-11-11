<?php

namespace App\Filament\Resources\Slides\Tables;

use App\Filament\Resources\Concerns\HighlightFormComponents as C;
use App\Models\Slide;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SlidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->columns([
                C::thumbnailColumn(),
                TextColumn::make('title')
                    ->label('ชื่อเรียก')
                    ->searchable()
                    ->description(fn (Slide $record) => $record->alt_th)
                    ->wrap(),
                TextColumn::make('link_type')
                    ->label('ลิงก์')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Slide::LINK_NEWS => 'ข่าว',
                        Slide::LINK_URL => 'URL',
                        default => 'ไม่มี',
                    }),
                C::dateRangeColumn(),
                C::statusColumn(),
                ToggleColumn::make('is_active')
                    ->label('เปิดแสดง')
                    ->disabled(fn (Slide $record) => $record->trashed() || ! auth()->user()->can('update', $record)),
            ])
            ->filters([
                SelectFilter::make('display_status')
                    ->label('สถานะ')
                    ->options(Slide::STATUS_LABELS)
                    ->query(fn (Builder $query, array $data) => self::filterStatus($query, $data['value'] ?? null)),
                TrashedFilter::make()->label('รายการที่ถูกลบ'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make()->modalDescription('รายการจะหายจากหน้าเว็บ และกู้คืนได้จากตัวกรอง "รายการที่ถูกลบ"'),
                    RestoreAction::make(),
                ]),
            ]);
    }

    private static function filterStatus(Builder $query, ?string $status): Builder
    {
        $today = today()->toDateString();

        return match ($status) {
            Slide::STATUS_DISABLED => $query->where('is_active', false),
            Slide::STATUS_SCHEDULED => $query->where('is_active', true)->whereDate('start_date', '>', $today),
            Slide::STATUS_EXPIRED => $query->where('is_active', true)->whereDate('end_date', '<', $today),
            Slide::STATUS_SHOWING => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
                ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today)),
            default => $query,
        };
    }
}

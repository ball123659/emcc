<?php

namespace App\Filament\Resources\News\Tables;

use App\Models\News;
use App\Models\NewsCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('cover_thumb')
                    ->label('รูปปก')
                    ->disk('local')
                    ->visibility('private')
                    ->imageHeight(48),
                TextColumn::make('title_th')
                    ->label('หัวข้อข่าว')
                    ->searchable(['title_th', 'title_en'])
                    ->description(fn (News $record) => $record->category?->name_th)
                    ->wrap()
                    ->limit(90),
                TextColumn::make('pinned')
                    ->label('ปักหมุด')
                    ->state(fn (News $record) => $record->is_pinned ? 'ปักหมุด' : null)
                    ->badge()
                    ->icon('heroicon-s-bookmark')
                    ->color('primary'),
                TextColumn::make('display_status')
                    ->label('สถานะ')
                    ->badge()
                    ->state(fn (News $record) => $record->trashed() ? 'ถูกลบ' : News::DISPLAY_LABELS[$record->displayStatus()])
                    ->color(fn (News $record) => $record->trashed() ? 'danger' : News::DISPLAY_COLORS[$record->displayStatus()]),
                TextColumn::make('published_at')
                    ->label('วันที่เผยแพร่')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('view_count')
                    ->label('เข้าชม')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('display_status')
                    ->label('สถานะ')
                    ->options(News::DISPLAY_LABELS)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        News::DISPLAY_SCHEDULED => $query->where('status', News::STATUS_PUBLISHED)->where('published_at', '>', now()),
                        News::STATUS_PUBLISHED => $query->where('status', News::STATUS_PUBLISHED)->where('published_at', '<=', now()),
                        News::STATUS_DRAFT, News::STATUS_HIDDEN => $query->where('status', $data['value']),
                        default => $query,
                    }),
                SelectFilter::make('category_id')
                    ->label('หมวดหมู่')
                    ->options(fn () => NewsCategory::query()->ordered()->pluck('name_th', 'id')->all()),
                TernaryFilter::make('is_pinned')->label('ปักหมุด'),
                TrashedFilter::make()->label('รายการที่ถูกลบ'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make()->modalDescription('ข่าวจะหายจากหน้าเว็บ และกู้คืนได้จากตัวกรอง "รายการที่ถูกลบ"'),
                    RestoreAction::make(),
                ]),
            ]);
    }
}

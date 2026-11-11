<?php

namespace App\Filament\Resources\Downloads\Tables;

use App\Models\Download;
use App\Models\DownloadCategory;
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

class DownloadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('title_th')
                    ->label('ชื่อ')
                    ->searchable(['title_th', 'title_en', 'original_name'])
                    ->description(fn (Download $record) => $record->category?->name_th)
                    ->wrap(),
                TextColumn::make('kind')
                    ->label('ประเภท')
                    ->badge()
                    ->state(fn (Download $record) => $record->isFile() ? strtoupper((string) $record->file_extension) : 'ลิงก์')
                    ->color(fn (Download $record) => $record->isFile() ? 'gray' : 'info')
                    ->description(fn (Download $record) => $record->humanFileSize()),
                TextColumn::make('scan_status')
                    ->label('สแกนไวรัส')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Download::SCAN_CLEAN => 'ผ่าน',
                        Download::SCAN_PENDING => 'รอสแกน',
                        Download::SCAN_INFECTED => 'พบไวรัส',
                        Download::SCAN_FAILED => 'สแกนไม่สำเร็จ',
                        default => '-',
                    })
                    ->color(fn (?string $state) => $state === Download::SCAN_CLEAN ? 'success' : 'danger')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('download_count')
                    ->label('ดาวน์โหลด')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('เปิดแสดง')
                    ->disabled(fn (Download $record) => $record->trashed()),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('หมวดหมู่')
                    ->options(fn () => DownloadCategory::query()->ordered()->pluck('name_th', 'id')->all()),
                SelectFilter::make('type')
                    ->label('ประเภท')
                    ->options([Download::TYPE_FILE => 'ไฟล์', Download::TYPE_LINK => 'ลิงก์ภายนอก']),
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
}

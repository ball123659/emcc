<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Downloads\DownloadResource;
use App\Filament\Widgets\Concerns\DashboardWidget;
use App\Models\Download;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * ไฟล์ยอดนิยม (ดาวน์โหลดมากที่สุด)
 */
class PopularDownloads extends TableWidget
{
    use DashboardWidget;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'ไฟล์ยอดนิยม';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Download::query()
                ->with('category')
                ->where('is_active', true)
                ->where('download_count', '>', 0)
                ->orderByDesc('download_count')
                ->orderBy('id'))
            ->paginated(false)
            ->modifyQueryUsing(fn (Builder $query) => $query->limit(5))
            ->emptyStateHeading('ยังไม่มีการดาวน์โหลด')
            ->columns([
                TextColumn::make('rank')->label('#')->rowIndex(),
                TextColumn::make('title_th')
                    ->label('ไฟล์')
                    ->description(fn (Download $record) => $record->category?->name_th)
                    ->wrap(),
                TextColumn::make('download_count')->label('ดาวน์โหลด')->numeric(),
            ])
            ->recordUrl(fn (Download $record) => DownloadResource::canAccess() ? DownloadResource::getUrl('edit', ['record' => $record]) : null);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\News\NewsResource;
use App\Filament\Widgets\Concerns\DashboardWidget;
use App\Models\News;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * ข่าวยอดนิยม (เข้าชมมากที่สุด) พร้อมรูปปก
 */
class PopularNews extends TableWidget
{
    use DashboardWidget;

    protected static ?int $sort = 6;

    protected static ?string $heading = 'ข่าวยอดนิยม';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => News::query()
                ->with('category')
                ->where('status', News::STATUS_PUBLISHED)
                ->where('published_at', '<=', now())
                ->where('view_count', '>', 0)
                ->orderByDesc('view_count')
                ->orderByDesc('id'))
            ->paginated(false)
            ->modifyQueryUsing(fn (Builder $query) => $query->limit(5))
            ->emptyStateHeading('ยังไม่มีการเข้าชมข่าว')
            ->emptyStateDescription('ยอดเข้าชมจะเริ่มนับเมื่อเปิดหน้าเว็บไซต์')
            ->columns([
                ImageColumn::make('cover_thumb')->label('')->disk('local')->visibility('private')->imageHeight(40),
                TextColumn::make('title_th')
                    ->label('ข่าว')
                    ->description(fn (News $record) => $record->category?->name_th)
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('view_count')->label('เข้าชม')->numeric(),
            ])
            ->recordUrl(fn (News $record) => NewsResource::canAccess() ? NewsResource::getUrl('edit', ['record' => $record]) : null);
    }
}

<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\NewsResource;
use App\Models\News;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListNews extends ListRecords
{
    protected static string $resource = NewsResource::class;

    /** แท็บกรองพร้อมตัวเลข: ทั้งหมด / เผยแพร่แล้ว / ตั้งเวลา / ร่าง / ซ่อน */
    public function getTabs(): array
    {
        $scopes = [
            News::STATUS_PUBLISHED => fn (Builder $query) => $query->where('status', News::STATUS_PUBLISHED)->where('published_at', '<=', now()),
            News::DISPLAY_SCHEDULED => fn (Builder $query) => $query->where('status', News::STATUS_PUBLISHED)->where('published_at', '>', now()),
            News::STATUS_DRAFT => fn (Builder $query) => $query->where('status', News::STATUS_DRAFT),
            News::STATUS_HIDDEN => fn (Builder $query) => $query->where('status', News::STATUS_HIDDEN),
        ];

        $tabs = ['all' => Tab::make('ทั้งหมด')->badge(fn () => News::query()->count())];

        foreach ($scopes as $key => $scope) {
            $tabs[$key] = Tab::make(News::DISPLAY_LABELS[$key])
                ->badge(fn () => $scope(News::query())->count())
                ->badgeColor(News::DISPLAY_COLORS[$key])
                ->modifyQueryUsing($scope);
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่มข่าว'),
        ];
    }
}

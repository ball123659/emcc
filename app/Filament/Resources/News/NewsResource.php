<?php

namespace App\Filament\Resources\News;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\News\Pages\EditNews;
use App\Filament\Resources\News\Pages\ListNews;
use App\Filament\Resources\News\Schemas\NewsForm;
use App\Filament\Resources\News\Tables\NewsTable;
use App\Models\News;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * ข่าวประชาสัมพันธ์ (pages.code = news)
 */
class NewsResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'news';

    protected static ?string $model = News::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'ข่าวประชาสัมพันธ์';

    protected static ?string $navigationLabel = 'ข่าว';

    protected static ?string $modelLabel = 'ข่าว';

    protected static ?string $pluralModelLabel = 'ข่าวประชาสัมพันธ์';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title_th';

    /** ตัวเลขบนเมนู: ข่าวร่างที่ยังไม่เผยแพร่ */
    public static function getNavigationBadge(): ?string
    {
        $count = News::query()->where('status', News::STATUS_DRAFT)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'ข่าวร่างที่ยังไม่เผยแพร่';
    }

    public static function form(Schema $schema): Schema
    {
        return NewsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNews::route('/'),
            'create' => CreateNews::route('/create'),
            'edit' => EditNews::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

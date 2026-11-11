<?php

namespace App\Filament\Resources\NewsCategories;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\Concerns\CategorySchema;
use App\Filament\Resources\NewsCategories\Pages\ManageNewsCategories;
use App\Models\NewsCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * หมวดหมู่ข่าว (ใช้สิทธิ์หน้า news)
 */
class NewsCategoryResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'news';

    protected static ?string $model = NewsCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'ข่าวประชาสัมพันธ์';

    protected static ?string $navigationLabel = 'หมวดหมู่ข่าว';

    protected static ?string $modelLabel = 'หมวดหมู่ข่าว';

    protected static ?string $pluralModelLabel = 'หมวดหมู่ข่าว';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'news-categories';

    public static function form(Schema $schema): Schema
    {
        return CategorySchema::form($schema);
    }

    public static function table(Table $table): Table
    {
        return CategorySchema::table($table, 'news', 'จำนวนข่าว');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNewsCategories::route('/'),
        ];
    }
}

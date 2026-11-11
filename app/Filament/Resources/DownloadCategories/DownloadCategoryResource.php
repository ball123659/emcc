<?php

namespace App\Filament\Resources\DownloadCategories;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\Concerns\CategorySchema;
use App\Filament\Resources\DownloadCategories\Pages\ManageDownloadCategories;
use App\Models\DownloadCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * หมวดหมู่ไฟล์ (ใช้สิทธิ์หน้า downloads)
 */
class DownloadCategoryResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'downloads';

    protected static ?string $model = DownloadCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'ไฟล์ดาวน์โหลด';

    protected static ?string $navigationLabel = 'หมวดหมู่ไฟล์';

    protected static ?string $modelLabel = 'หมวดหมู่ไฟล์';

    protected static ?string $pluralModelLabel = 'หมวดหมู่ไฟล์';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'download-categories';

    public static function form(Schema $schema): Schema
    {
        return CategorySchema::form($schema);
    }

    public static function table(Table $table): Table
    {
        return CategorySchema::table($table, 'downloads', 'จำนวนไฟล์');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDownloadCategories::route('/'),
        ];
    }
}

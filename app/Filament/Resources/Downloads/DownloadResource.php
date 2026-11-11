<?php

namespace App\Filament\Resources\Downloads;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\Downloads\Pages\CreateDownload;
use App\Filament\Resources\Downloads\Pages\EditDownload;
use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Filament\Resources\Downloads\Schemas\DownloadForm;
use App\Filament\Resources\Downloads\Tables\DownloadsTable;
use App\Models\Download;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * ไฟล์ดาวน์โหลด (pages.code = downloads)
 */
class DownloadResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'downloads';

    protected static ?string $model = Download::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'ไฟล์ดาวน์โหลด';

    protected static ?string $navigationLabel = 'ไฟล์ดาวน์โหลด';

    protected static ?string $modelLabel = 'ไฟล์';

    protected static ?string $pluralModelLabel = 'ไฟล์ดาวน์โหลด';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title_th';

    public static function form(Schema $schema): Schema
    {
        return DownloadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DownloadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDownloads::route('/'),
            'create' => CreateDownload::route('/create'),
            'edit' => EditDownload::route('/{record}/edit'),
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

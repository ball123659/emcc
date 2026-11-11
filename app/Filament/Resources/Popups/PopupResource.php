<?php

namespace App\Filament\Resources\Popups;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\Popups\Pages\CreatePopup;
use App\Filament\Resources\Popups\Pages\EditPopup;
use App\Filament\Resources\Popups\Pages\ListPopups;
use App\Filament\Resources\Popups\Schemas\PopupForm;
use App\Filament\Resources\Popups\Tables\PopupsTable;
use App\Models\Popup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * จัดการ Highlight › Popup (pages.code = popups)
 */
class PopupResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'popups';

    protected static ?string $model = Popup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static string|UnitEnum|null $navigationGroup = 'จัดการ Highlight';

    protected static ?string $navigationLabel = 'Popup';

    protected static ?string $modelLabel = 'Popup';

    protected static ?string $pluralModelLabel = 'Popup';

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'title';

    /** ตัวเลขบนเมนู: Popup ที่กำลังแสดงและจะหมดอายุภายใน 3 วัน */
    public static function getNavigationBadge(): ?string
    {
        $count = Popup::query()->visible()->whereDate('end_date', '<=', today()->addDays(3))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Popup ที่จะหมดอายุภายใน 3 วัน';
    }

    public static function form(Schema $schema): Schema
    {
        return PopupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PopupsTable::configure($table);
    }

    public static function normalizeLink(array $data): array
    {
        $data['link_url'] = filled($data['link_url'] ?? null) ? trim($data['link_url']) : null;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPopups::route('/'),
            'create' => CreatePopup::route('/create'),
            'edit' => EditPopup::route('/{record}/edit'),
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

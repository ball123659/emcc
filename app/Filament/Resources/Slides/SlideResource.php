<?php

namespace App\Filament\Resources\Slides;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\Slides\Pages\CreateSlide;
use App\Filament\Resources\Slides\Pages\EditSlide;
use App\Filament\Resources\Slides\Pages\ListSlides;
use App\Filament\Resources\Slides\Schemas\SlideForm;
use App\Filament\Resources\Slides\Tables\SlidesTable;
use App\Models\Slide;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * จัดการ Highlight › Slide Show (pages.code = slides)
 */
class SlideResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'slides';

    protected static ?string $model = Slide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'จัดการ Highlight';

    protected static ?string $navigationLabel = 'Slide Show';

    protected static ?string $modelLabel = 'Slide Show';

    protected static ?string $pluralModelLabel = 'Slide Show';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SlideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlidesTable::configure($table);
    }

    /** ล้างค่าลิงก์ที่ไม่ตรงกับประเภทที่เลือก (ตรวจฝั่งเซิร์ฟเวอร์) */
    public static function normalizeLink(array $data): array
    {
        $type = $data['link_type'] ?? Slide::LINK_NONE;

        if ($type !== Slide::LINK_NEWS) {
            $data['news_id'] = null;
        }

        if ($type !== Slide::LINK_URL) {
            $data['link_url'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlides::route('/'),
            'create' => CreateSlide::route('/create'),
            'edit' => EditSlide::route('/{record}/edit'),
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

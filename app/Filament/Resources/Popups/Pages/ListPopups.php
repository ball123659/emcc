<?php

namespace App\Filament\Resources\Popups\Pages;

use App\Filament\Resources\Concerns\DisplayWindowTabs;
use App\Filament\Resources\Popups\PopupResource;
use App\Filament\Resources\Popups\Tables\PopupsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPopups extends ListRecords
{
    use DisplayWindowTabs;

    protected static function tableClass(): string
    {
        return PopupsTable::class;
    }

    protected static string $resource = PopupResource::class;

    protected ?string $subheading = 'กดปุ่ม ⇅ ที่มุมตาราง แล้วลากเพื่อจัดลำดับการแสดงบนหน้าเว็บ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่ม Popup'),
        ];
    }
}

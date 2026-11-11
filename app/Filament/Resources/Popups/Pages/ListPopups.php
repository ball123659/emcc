<?php

namespace App\Filament\Resources\Popups\Pages;

use App\Filament\Resources\Popups\PopupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPopups extends ListRecords
{
    protected static string $resource = PopupResource::class;

    protected ?string $subheading = 'ลากไอคอน ⋮⋮ (กดปุ่ม "เรียงลำดับ" ก่อน) เพื่อเรียงลำดับการแสดงหน้าเว็บ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่ม Popup'),
        ];
    }
}

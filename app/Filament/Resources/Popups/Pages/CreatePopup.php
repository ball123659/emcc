<?php

namespace App\Filament\Resources\Popups\Pages;

use App\Filament\Resources\Popups\PopupResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePopup extends CreateRecord
{
    protected static string $resource = PopupResource::class;

    protected static ?string $title = 'เพิ่ม Popup';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PopupResource::normalizeLink($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

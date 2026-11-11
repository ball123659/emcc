<?php

namespace App\Filament\Resources\Downloads\Pages;

use App\Filament\Resources\Downloads\DownloadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDownload extends CreateRecord
{
    protected static string $resource = DownloadResource::class;

    protected static ?string $title = 'เพิ่มไฟล์ดาวน์โหลด';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

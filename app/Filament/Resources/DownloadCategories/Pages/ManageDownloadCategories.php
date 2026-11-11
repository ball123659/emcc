<?php

namespace App\Filament\Resources\DownloadCategories\Pages;

use App\Filament\Resources\DownloadCategories\DownloadCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDownloadCategories extends ManageRecords
{
    protected static string $resource = DownloadCategoryResource::class;

    protected ?string $subheading = 'กดปุ่มเรียงลำดับแล้วลากเพื่อจัดลำดับหมวดบนหน้าเว็บ · หมวดที่ยังมีไฟล์ลบไม่ได้';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่มหมวดหมู่'),
        ];
    }
}

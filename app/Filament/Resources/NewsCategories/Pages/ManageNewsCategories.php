<?php

namespace App\Filament\Resources\NewsCategories\Pages;

use App\Filament\Resources\NewsCategories\NewsCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsCategories extends ManageRecords
{
    protected static string $resource = NewsCategoryResource::class;

    protected ?string $subheading = 'กดปุ่ม ⇅ ที่มุมตาราง แล้วลากเพื่อจัดลำดับหมวดบนหน้าเว็บ · หมวดที่ยังมีข่าวลบไม่ได้';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่มหมวดหมู่'),
        ];
    }
}

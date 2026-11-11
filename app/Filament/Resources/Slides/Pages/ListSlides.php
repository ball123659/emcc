<?php

namespace App\Filament\Resources\Slides\Pages;

use App\Filament\Resources\Slides\SlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlides extends ListRecords
{
    protected static string $resource = SlideResource::class;

    protected ?string $subheading = 'ลากไอคอน ⋮⋮ (กดปุ่ม "เรียงลำดับ" ก่อน) เพื่อเรียงลำดับการแสดงหน้าเว็บ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่ม Slide Show'),
        ];
    }
}

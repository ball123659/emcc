<?php

namespace App\Filament\Resources\Slides\Pages;

use App\Filament\Resources\Concerns\DisplayWindowTabs;
use App\Filament\Resources\Slides\SlideResource;
use App\Filament\Resources\Slides\Tables\SlidesTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlides extends ListRecords
{
    use DisplayWindowTabs;

    protected static function tableClass(): string
    {
        return SlidesTable::class;
    }

    protected static string $resource = SlideResource::class;

    protected ?string $subheading = 'กดปุ่ม ⇅ ที่มุมตาราง แล้วลากเพื่อจัดลำดับการแสดงบนหน้าเว็บ';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('เพิ่ม Slide Show'),
        ];
    }
}

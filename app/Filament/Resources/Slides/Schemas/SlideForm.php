<?php

namespace App\Filament\Resources\Slides\Schemas;

use App\Filament\Forms\ImageUpload;
use App\Filament\Resources\Concerns\HighlightFormComponents as C;
use App\Models\News;
use App\Models\Slide;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('ข้อมูลสไลด์')
                    ->schema([C::title()]),

                Section::make('รูปภาพ')
                    ->description('รูปภาษาไทยบังคับ — ถ้าไม่ใส่รูปภาษาอังกฤษหรือรูปมือถือ ระบบจะใช้รูปภาษาไทยแทน')
                    ->columns(3)
                    ->schema([
                        ImageUpload::make('image_th', 'slides', '1920 × 600')
                            ->label('รูปภาษาไทย')
                            ->required(),
                        ImageUpload::make('image_en', 'slides', '1920 × 600')
                            ->label('รูปภาษาอังกฤษ'),
                        ImageUpload::make('image_mobile', 'slides', '800 × 800')
                            ->label('รูปสำหรับมือถือ'),
                        C::altTh()->columnSpan(['default' => 3, 'md' => 1]),
                        C::altEn()->columnSpan(['default' => 3, 'md' => 2]),
                    ]),

                Section::make('ลิงก์เมื่อคลิกสไลด์')
                    ->columns(2)
                    ->schema([
                        Radio::make('link_type')
                            ->label('ประเภทลิงก์')
                            ->options([
                                Slide::LINK_NONE => 'ไม่มีลิงก์',
                                Slide::LINK_NEWS => 'ข่าวในเว็บไซต์',
                                Slide::LINK_URL => 'URL ภายนอก',
                            ])
                            ->default(Slide::LINK_NONE)
                            ->required()
                            ->inline()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('news_id')
                            ->label('เลือกข่าว')
                            ->options(fn () => News::query()->latest('id')->limit(200)->pluck('title_th', 'id')->all())
                            ->getSearchResultsUsing(fn (string $search) => News::query()
                                ->where('title_th', 'like', "%{$search}%")
                                ->latest('id')->limit(50)->pluck('title_th', 'id')->all())
                            ->searchable()
                            ->exists('news', 'id')
                            ->visible(fn (Get $get) => $get('link_type') === Slide::LINK_NEWS)
                            ->required(fn (Get $get) => $get('link_type') === Slide::LINK_NEWS)
                            ->helperText('ถ้าข่าวถูกลบภายหลัง สไลด์จะแสดงแบบไม่มีลิงก์')
                            ->validationMessages([
                                'required' => 'กรุณาเลือกข่าวที่ต้องการลิงก์ไป',
                                'exists' => 'ไม่พบข่าวที่เลือก กรุณาเลือกใหม่',
                            ]),
                        C::linkUrl()
                            ->visible(fn (Get $get) => $get('link_type') === Slide::LINK_URL)
                            ->required(fn (Get $get) => $get('link_type') === Slide::LINK_URL),
                        C::openNewTab()
                            ->visible(fn (Get $get) => $get('link_type') !== Slide::LINK_NONE),
                    ]),

                Section::make('การแสดงผล')
                    ->columns(3)
                    ->schema([
                        C::isActive(),
                        C::startDate(required: false),
                        C::endDate(required: false),
                    ]),
            ]);
    }
}

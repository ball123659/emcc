<?php

namespace App\Filament\Resources\Popups\Schemas;

use App\Filament\Forms\ImageUpload;
use App\Filament\Resources\Concerns\HighlightFormComponents as C;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PopupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('ข้อมูล Popup')
                    ->description('หน้าเว็บแสดง Popup ที่อยู่ในช่วงวันที่ต่อกันทีละอันตามลำดับ ผู้ชมติ๊ก "ไม่ต้องแสดงอีกวันนี้" ได้')
                    ->schema([C::title()]),

                Section::make('รูปภาพ')
                    ->description('รูปภาษาไทยบังคับ — ถ้าไม่ใส่รูปภาษาอังกฤษ ระบบจะใช้รูปภาษาไทยแทน')
                    ->columns(2)
                    ->schema([
                        ImageUpload::make('image_th', 'popups', '800 × 800')
                            ->label('รูปภาษาไทย')
                            ->required(),
                        ImageUpload::make('image_en', 'popups', '800 × 800')
                            ->label('รูปภาษาอังกฤษ'),
                        C::altTh(),
                        C::altEn(),
                    ]),

                Section::make('ลิงก์เมื่อคลิกรูป (ไม่บังคับ)')
                    ->columns(2)
                    ->schema([
                        C::linkUrl()->live(onBlur: true),
                        C::openNewTab()->visible(fn (Get $get) => filled($get('link_url'))),
                    ]),

                Section::make('การแสดงผล')
                    ->columns(3)
                    ->schema([
                        C::isActive(),
                        C::startDate(required: true)->default(today()),
                        C::endDate(required: true),
                    ]),
            ]);
    }
}

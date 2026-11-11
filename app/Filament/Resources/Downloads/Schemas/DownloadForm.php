<?php

namespace App\Filament\Resources\Downloads\Schemas;

use App\Filament\Forms\DocumentUpload;
use App\Models\Download;
use App\Models\DownloadCategory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class DownloadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('ข้อมูลไฟล์')
                        ->columns(2)
                        ->schema([
                            TextInput::make('title_th')
                                ->label('ชื่อ (ภาษาไทย)')
                                ->required()
                                ->maxLength(255)
                                ->validationMessages(['required' => 'กรุณากรอกชื่อที่จะแสดงบนหน้าเว็บ']),
                            TextInput::make('title_en')
                                ->label('ชื่อ (ภาษาอังกฤษ)')
                                ->maxLength(255)
                                ->helperText('เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน'),
                            Textarea::make('description_th')
                                ->label('คำอธิบาย (ภาษาไทย)')
                                ->rows(3)
                                ->maxLength(2000),
                            Textarea::make('description_en')
                                ->label('คำอธิบาย (ภาษาอังกฤษ)')
                                ->rows(3)
                                ->maxLength(2000),
                        ]),

                    Section::make('ไฟล์หรือลิงก์')
                        ->schema([
                            Radio::make('type')
                                ->label('ประเภท')
                                ->options([
                                    Download::TYPE_FILE => 'อัปโหลดไฟล์',
                                    Download::TYPE_LINK => 'ลิงก์ภายนอก (เช่น Google Drive, YouTube)',
                                ])
                                ->default(Download::TYPE_FILE)
                                ->required()
                                ->inline()
                                ->live(),
                            DocumentUpload::make('file_path', 'downloads')
                                ->label('ไฟล์')
                                ->storeFileNamesIn('original_name')
                                ->visible(fn (Get $get) => $get('type') === Download::TYPE_FILE)
                                ->required(fn (Get $get) => $get('type') === Download::TYPE_FILE)
                                ->belowContent(fn (?Download $record) => $record?->isFile()
                                    ? 'ต้องการเปลี่ยนไฟล์: กด × ลบไฟล์เดิมแล้วอัปไฟล์ใหม่ — ลิงก์ดาวน์โหลดเดิมยังใช้ได้'
                                    : null),
                            Hidden::make('original_name'),
                            TextInput::make('external_url')
                                ->label('ลิงก์ URL')
                                ->url()
                                ->maxLength(500)
                                ->placeholder('https://')
                                ->rules(['url:http,https'])
                                ->visible(fn (Get $get) => $get('type') === Download::TYPE_LINK)
                                ->required(fn (Get $get) => $get('type') === Download::TYPE_LINK)
                                ->helperText('ระบบจะพาผู้ชมไปที่ลิงก์นี้ (ไม่ดาวน์โหลดไฟล์มาเก็บ)')
                                ->validationMessages([
                                    'required' => 'กรุณากรอกลิงก์',
                                    'url' => 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://drive.google.com/...',
                                ]),
                        ]),
                ])->columnSpan(['default' => 3, 'lg' => 2]),

                Section::make('การแสดงผล')
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        Select::make('category_id')
                            ->label('หมวดหมู่')
                            // หมวดที่ปิดแล้วไม่ให้เลือกใหม่ แต่รายการเดิมที่อยู่ในหมวดนั้นต้องบันทึกต่อได้
                            ->options(fn (?Model $record) => DownloadCategory::query()
                                ->where(fn ($q) => $q->where('is_active', true)->when($record?->category_id, fn ($q, $id) => $q->orWhere('id', $id)))
                                ->ordered()
                                ->get()
                                ->mapWithKeys(fn (DownloadCategory $c) => [$c->id => $c->name_th.($c->is_active ? '' : ' (ปิดใช้งาน)')])
                                ->all())
                            ->required()
                            ->exists('download_categories', 'id')
                            ->validationMessages(['required' => 'กรุณาเลือกหมวดหมู่']),
                        Toggle::make('is_active')
                            ->label('เปิดแสดงบนหน้าเว็บ')
                            ->default(true),
                        TextEntry::make('public_url')
                            ->label('ลิงก์ดาวน์โหลด')
                            ->state(fn (Download $record) => $record->publicUrl())
                            ->copyable()
                            ->copyMessage('คัดลอกลิงก์แล้ว')
                            ->helperText('ลิงก์นี้คงเดิมแม้อัปไฟล์ใหม่ทับ')
                            ->visibleOn('edit'),
                        TextEntry::make('download_count')
                            ->label('ยอดดาวน์โหลด')
                            ->numeric()
                            ->visibleOn('edit'),
                        TextEntry::make('file_info')
                            ->label('ไฟล์ปัจจุบัน')
                            ->state(fn (Download $record) => $record->isFile()
                                ? $record->original_name.' · '.$record->humanFileSize()
                                : null)
                            ->placeholder('-')
                            ->visibleOn('edit'),
                    ]),
            ]);
    }
}

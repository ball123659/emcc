<?php

namespace App\Filament\Resources\News\Schemas;

use App\Filament\Forms\DocumentUpload;
use App\Filament\Forms\ImageUpload;
use App\Models\News;
use App\Models\NewsCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class NewsForm
{
    /** ปุ่มเครื่องมือ rich text — ตรงกับแท็กที่ HtmlSanitizer อนุญาต */
    private const TOOLBAR = [
        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
        ['h2', 'h3'],
        ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
        ['table'],
        ['undo', 'redo'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    self::contentSection(),
                    self::coverSection(),
                    self::gallerySection(),
                    self::attachmentSection(),
                ])->columnSpan(['default' => 3, 'lg' => 2]),

                Group::make([
                    self::publishSection(),
                ])->columnSpan(['default' => 3, 'lg' => 1]),
            ]);
    }

    private static function contentSection(): Section
    {
        return Section::make('เนื้อหาข่าว')
            ->schema([
                Tabs::make('ภาษา')->tabs([
                    Tab::make('ภาษาไทย')->schema([
                        TextInput::make('title_th')
                            ->label('หัวข้อข่าว')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages(['required' => 'กรุณากรอกหัวข้อข่าวภาษาไทย']),
                        Textarea::make('excerpt_th')
                            ->label('คำโปรย')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('แสดงบนการ์ดข่าวหน้ารายการ ไม่เกิน 500 ตัวอักษร'),
                        RichEditor::make('content_th')
                            ->label('เนื้อหา')
                            ->required()
                            ->toolbarButtons(self::TOOLBAR)
                            ->extraInputAttributes(['style' => 'min-height: 18rem'])
                            ->validationMessages(['required' => 'กรุณากรอกเนื้อหาข่าวภาษาไทย']),
                    ]),
                    Tab::make('English')->schema([
                        TextInput::make('title_en')
                            ->label('หัวข้อข่าว (อังกฤษ)')
                            ->maxLength(255)
                            ->helperText('เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน'),
                        Textarea::make('excerpt_en')
                            ->label('คำโปรย (อังกฤษ)')
                            ->rows(2)
                            ->maxLength(500),
                        RichEditor::make('content_en')
                            ->label('เนื้อหา (อังกฤษ)')
                            ->toolbarButtons(self::TOOLBAR)
                            ->extraInputAttributes(['style' => 'min-height: 18rem']),
                    ]),
                ]),
            ]);
    }

    private static function coverSection(): Section
    {
        return Section::make('รูปปก')
            ->columns(2)
            ->schema([
                ImageUpload::make('cover_image', 'news', '1200 × 675 (16:9)', withThumbnail: true)
                    ->label('รูปปก')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('cover_alt_th')
                    ->label('คำอธิบายรูปปก (ไทย)')
                    ->maxLength(255)
                    ->helperText('เว้นว่าง = ใช้หัวข้อข่าว'),
                TextInput::make('cover_alt_en')
                    ->label('คำอธิบายรูปปก (อังกฤษ)')
                    ->maxLength(255),
            ]);
    }

    private static function gallerySection(): Section
    {
        return Section::make('แกลเลอรีรูป')
            ->description('ลากที่หัวรายการเพื่อเรียงลำดับ · ใส่คำบรรยายแต่ละรูปได้')
            ->collapsible()
            ->schema([
                Repeater::make('images')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->cloneable(false)
                    ->defaultItems(0)
                    ->grid(['default' => 1, 'md' => 2])
                    ->addActionLabel('เพิ่มรูป')
                    ->itemLabel(fn (array $state) => $state['caption_th'] ?? null)
                    ->maxItems(50)
                    ->schema([
                        ImageUpload::make('image_path', 'news', 'ด้านยาว 1920', withThumbnail: true)
                            ->label('รูป')
                            ->required(),
                        TextInput::make('caption_th')->label('คำบรรยาย (ไทย)')->maxLength(255),
                        TextInput::make('caption_en')->label('คำบรรยาย (อังกฤษ)')->maxLength(255),
                    ]),
            ]);
    }

    private static function attachmentSection(): Section
    {
        return Section::make('ไฟล์แนบ')
            ->description('ไฟล์ผ่านการสแกนไวรัสก่อนบันทึก และดาวน์โหลดผ่านระบบเท่านั้น')
            ->collapsible()
            ->schema([
                Repeater::make('attachments')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->cloneable(false)
                    ->defaultItems(0)
                    ->addActionLabel('เพิ่มไฟล์แนบ')
                    ->itemLabel(fn (array $state) => $state['title_th'] ?? null)
                    ->maxItems(20)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title_th')
                            ->label('ชื่อไฟล์ที่แสดง (ไทย)')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages(['required' => 'กรุณาตั้งชื่อไฟล์ที่จะแสดงบนหน้าเว็บ']),
                        TextInput::make('title_en')->label('ชื่อไฟล์ที่แสดง (อังกฤษ)')->maxLength(255),
                        DocumentUpload::make('file_path', 'news-attachments')
                            ->label('ไฟล์')
                            ->required()
                            ->storeFileNamesIn('original_name')
                            ->columnSpanFull(),
                        Hidden::make('original_name'),
                    ]),
            ]);
    }

    private static function publishSection(): Section
    {
        return Section::make('การเผยแพร่')
            ->schema([
                Select::make('status')
                    ->label('สถานะ')
                    ->options(News::STATUSES)
                    ->default(News::STATUS_DRAFT)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live()
                    ->helperText(fn (Get $get) => match ($get('status')) {
                        News::STATUS_DRAFT => 'ร่าง — ยังไม่แสดงหน้าเว็บ',
                        News::STATUS_HIDDEN => 'ซ่อน — เคยเผยแพร่แล้วแต่ไม่แสดงหน้าเว็บ',
                        default => 'แสดงหน้าเว็บเมื่อถึงวันเวลาเผยแพร่',
                    }),
                DateTimePicker::make('published_at')
                    ->label('วันเวลาเผยแพร่')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
                    ->helperText('เว้นว่าง = เผยแพร่ทันทีที่บันทึก · ตั้งเป็นอนาคต = ตั้งเวลาเผยแพร่'),
                Select::make('category_id')
                    ->label('หมวดหมู่')
                    ->options(fn () => NewsCategory::query()->active()->ordered()->pluck('name_th', 'id')->all())
                    ->required()
                    ->exists('news_categories', 'id')
                    ->validationMessages(['required' => 'กรุณาเลือกหมวดหมู่']),
                Toggle::make('is_pinned')
                    ->label('ปักหมุดไว้บนสุด'),
                TextInput::make('slug')
                    ->label('ชื่อใน URL (slug)')
                    ->maxLength(200)
                    ->regex('/^[a-zA-Z0-9\-]*$/')
                    ->unique(ignoreRecord: true)
                    ->prefix('/news/')
                    ->helperText('เว้นว่างเพื่อสร้างอัตโนมัติ (ปี-เดือน-ชื่อภาษาอังกฤษ) · ใช้ได้เฉพาะ a-z, 0-9 และขีด (-)')
                    ->validationMessages([
                        'regex' => 'ใช้ได้เฉพาะ a-z, 0-9 และขีด (-) ห้ามมีช่องว่างหรือภาษาไทย',
                        'unique' => 'ชื่อใน URL นี้ถูกใช้แล้ว (รวมข่าวที่ถูกลบ) กรุณาใช้ชื่ออื่น',
                    ]),
                Grid::make(2)
                    ->visibleOn('edit')
                    ->schema([
                        TextEntry::make('view_count')->label('ยอดเข้าชม')->numeric(),
                        TextEntry::make('updated_at')->label('แก้ไขล่าสุด')->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}

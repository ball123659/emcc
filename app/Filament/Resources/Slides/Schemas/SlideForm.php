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
use Illuminate\Database\Eloquent\Builder;

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
                        C::altEn()->columnSpan(['default' => 3, 'md' => 1]),
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
                            ->searchable()
                            ->options(fn (?Slide $record) => self::newsOptions($record?->news_id))
                            ->getSearchResultsUsing(fn (string $search) => self::publishedNews()
                                ->where(fn ($q) => $q->where('title_th', 'like', "%{$search}%")->orWhere('title_en', 'like', "%{$search}%"))
                                ->limit(50)->pluck('title_th', 'id')->all())
                            // ใช้ตรวจค่าที่เลือกด้วย: ต้องเป็นข่าวที่เผยแพร่แล้ว หรือข่าวเดิมที่สไลด์นี้ลิงก์อยู่
                            ->getOptionLabelUsing(fn ($value, ?Slide $record) => self::newsLabel($value, $record?->news_id))
                            ->visible(fn (Get $get) => $get('link_type') === Slide::LINK_NEWS)
                            ->required(fn (Get $get) => $get('link_type') === Slide::LINK_NEWS)
                            ->helperText('เลือกได้เฉพาะข่าวที่เผยแพร่แล้ว พิมพ์เพื่อค้นหาข่าวเก่า · ถ้าข่าวถูกลบภายหลัง สไลด์จะแสดงแบบไม่มีลิงก์')
                            ->validationMessages([
                                'required' => 'กรุณาเลือกข่าวที่ต้องการลิงก์ไป',
                                'in' => 'เลือกได้เฉพาะข่าวที่เผยแพร่แล้ว กรุณาเลือกใหม่',
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

    private static function publishedNews(): Builder
    {
        return News::query()
            ->where('status', News::STATUS_PUBLISHED)
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->latest('id');
    }

    /** ข่าวเผยแพร่แล้ว 50 ข่าวล่าสุด + ข่าวเดิมของสไลด์ (แม้จะเลิกเผยแพร่แล้ว) */
    private static function newsOptions(?int $currentId): array
    {
        $options = self::publishedNews()->limit(50)->pluck('title_th', 'id')->all();

        if ($currentId && ! isset($options[$currentId]) && ($label = self::newsLabel($currentId, $currentId))) {
            $options = [$currentId => $label] + $options;
        }

        return $options;
    }

    private static function newsLabel(mixed $value, ?int $currentId): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ((int) $value === $currentId) {
            $news = News::withTrashed()->find($value);

            return $news ? $news->title_th.($news->isPubliclyVisible() ? '' : ' (ยังไม่เผยแพร่/ถูกซ่อน)') : null;
        }

        return self::publishedNews()->whereKey($value)->value('title_th');
    }
}

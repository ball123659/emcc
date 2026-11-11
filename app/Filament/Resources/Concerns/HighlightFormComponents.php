<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Popup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * ช่องฟอร์ม/คอลัมน์ที่ Slide Show และ Popup ใช้ร่วมกัน
 */
class HighlightFormComponents
{
    public static function title(): TextInput
    {
        return TextInput::make('title')
            ->label('ชื่อเรียก (ใช้ภายในระบบ ไม่แสดงบนหน้าเว็บ)')
            ->required()
            ->maxLength(200)
            ->validationMessages(['required' => 'กรุณาตั้งชื่อเรียก เช่น "รณรงค์ลด PM2.5 พ.ย. 69"']);
    }

    public static function altTh(): TextInput
    {
        return TextInput::make('alt_th')
            ->label('คำอธิบายรูป (ภาษาไทย)')
            ->required()
            ->maxLength(255)
            ->helperText('ข้อความแทนรูปสำหรับผู้พิการทางสายตา/โปรแกรมอ่านหน้าจอ อธิบายสั้น ๆ ว่ารูปสื่ออะไร')
            ->validationMessages(['required' => 'กรุณากรอกคำอธิบายรูปภาษาไทย']);
    }

    public static function altEn(): TextInput
    {
        return TextInput::make('alt_en')
            ->label('คำอธิบายรูป (ภาษาอังกฤษ)')
            ->maxLength(255)
            ->helperText('เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน');
    }

    public static function linkUrl(): TextInput
    {
        return TextInput::make('link_url')
            ->label('ลิงก์ URL')
            ->url()
            ->maxLength(500)
            ->placeholder('https://')
            ->rules(['url:http,https'])
            ->validationMessages([
                'required' => 'กรุณากรอกลิงก์ที่ต้องการให้เปิด',
                'url' => 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://www.example.com',
                'max' => 'ลิงก์ยาวได้ไม่เกิน 500 ตัวอักษร',
            ]);
    }

    public static function openNewTab(): Toggle
    {
        return Toggle::make('open_new_tab')
            ->label('เปิดลิงก์ในแท็บใหม่')
            ->default(true);
    }

    public static function isActive(): Toggle
    {
        return Toggle::make('is_active')
            ->label('เปิดแสดง')
            ->default(true)
            ->helperText('ปิดไว้ = ไม่แสดงหน้าเว็บ แม้อยู่ในช่วงวันที่');
    }

    public static function startDate(bool $required): DatePicker
    {
        return DatePicker::make('start_date')
            ->label('วันเริ่มแสดง')
            ->native(false)
            ->closeOnDateSelection()
            ->required($required)
            ->live()
            ->helperText($required ? null : 'เว้นว่าง = แสดงทันที')
            ->validationMessages(['required' => 'กรุณาเลือกวันเริ่มแสดง']);
    }

    public static function endDate(bool $required): DatePicker
    {
        return DatePicker::make('end_date')
            ->label('วันสิ้นสุด')
            ->native(false)
            ->closeOnDateSelection()
            ->required($required)
            ->minDate(fn (Get $get) => $get('start_date'))
            ->afterOrEqual('start_date')
            ->helperText($required ? 'แสดงถึงสิ้นวันนี้' : 'เว้นว่าง = ไม่มีวันหมดอายุ')
            ->validationMessages([
                'required' => 'กรุณาเลือกวันสิ้นสุด',
                'after_or_equal' => 'วันสิ้นสุดต้องเป็นวันเดียวกันหรือหลังวันเริ่มแสดง',
            ]);
    }

    public static function thumbnailColumn(): ImageColumn
    {
        return ImageColumn::make('image_th')
            ->label('รูป')
            ->disk('local')
            ->visibility('private')
            ->imageHeight(48)
            ->extraImgAttributes(['loading' => 'lazy', 'class' => 'rounded']);
    }

    public static function dateRangeColumn(): TextColumn
    {
        return TextColumn::make('start_date')
            ->label('ช่วงวันที่แสดง')
            ->state(function (Model $record): string {
                $start = $record->start_date?->format(config('emcc.date_format')) ?? 'ทันที';
                $end = $record->end_date?->format(config('emcc.date_format')) ?? 'ไม่มีกำหนด';

                return "{$start} – {$end}";
            });
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('display_status')
            ->label('สถานะ')
            ->badge()
            ->state(fn (Model $record) => $record->trashed() ? 'ถูกลบ' : Popup::STATUS_LABELS[$record->displayStatus()])
            ->color(fn (Model $record) => $record->trashed() ? 'danger' : Popup::STATUS_COLORS[$record->displayStatus()])
            ->description(function (Model $record): ?string {
                $days = $record->daysUntilExpiry();

                return ! $record->trashed() && $record->displayStatus() === Popup::STATUS_SHOWING && $days !== null && $days <= 3
                    ? ($days === 0 ? 'หมดอายุวันนี้' : "อีก {$days} วันหมดอายุ")
                    : null;
            });
    }
}

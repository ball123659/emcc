<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ฟอร์ม/ตารางหมวดหมู่ที่ใช้ร่วมกัน (หมวดหมู่ข่าว, หมวดหมู่ไฟล์ดาวน์โหลด)
 * หมวดที่ยังมีข้อมูลลบไม่ได้ (Policy + ปุ่มลบถูกปิดพร้อมคำอธิบาย)
 */
class CategorySchema
{
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_th')
                ->label('ชื่อหมวด (ภาษาไทย)')
                ->required()
                ->maxLength(100)
                ->validationMessages(['required' => 'กรุณากรอกชื่อหมวดภาษาไทย']),
            TextInput::make('name_en')
                ->label('ชื่อหมวด (ภาษาอังกฤษ)')
                ->maxLength(100)
                ->helperText('เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน'),
            TextInput::make('slug')
                ->label('ชื่อใน URL (slug)')
                ->maxLength(100)
                ->regex('/^[a-zA-Z0-9\-\s]*$/')
                ->unique(ignoreRecord: true)
                ->helperText('a-z, 0-9 และขีด (-) · เว้นว่างเพื่อสร้างจากชื่อภาษาอังกฤษ')
                ->validationMessages([
                    'regex' => 'ใช้ได้เฉพาะ a-z, 0-9 และขีด (-)',
                    'unique' => 'ชื่อใน URL นี้ถูกใช้แล้ว กรุณาใช้ชื่ออื่น',
                ]),
            Toggle::make('is_active')
                ->label('เปิดใช้งาน')
                ->default(true)
                ->inline(false),
        ]);
    }

    public static function table(Table $table, string $countRelation, string $countLabel): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name_th')->label('ชื่อหมวด')->searchable()
                    ->description(fn (Model $record) => $record->name_en),
                TextColumn::make('slug')->label('slug')->color('gray'),
                TextColumn::make($countRelation.'_count')
                    ->label($countLabel)
                    ->counts([$countRelation => fn ($query) => $query->withTrashed()])
                    ->badge()
                    ->color('gray'),
                ToggleColumn::make('is_active')->label('เปิดใช้งาน')
                    ->disabled(fn (Model $record) => ! auth()->user()->can('update', $record)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->authorize(fn (Model $record) => auth()->user()->can('update', $record))
                    ->disabled(fn (Model $record) => $record->{$countRelation.'_count'} > 0)
                    ->tooltip(fn (Model $record) => $record->{$countRelation.'_count'} > 0
                        ? 'หมวดนี้ยังมีข้อมูลอยู่ ย้ายข้อมูลไปหมวดอื่นก่อนจึงจะลบได้'
                        : null)
                    ->before(function (DeleteAction $action, Model $record): void {
                        // ตรวจซ้ำฝั่งเซิร์ฟเวอร์
                        if (! auth()->user()->can('delete', $record)) {
                            $action->failureNotificationTitle('หมวดนี้ยังมีข้อมูลอยู่ ลบไม่ได้')->failure();
                            $action->halt();
                        }
                    }),
            ]);
    }
}

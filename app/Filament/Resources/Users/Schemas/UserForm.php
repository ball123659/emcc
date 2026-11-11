<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\UserPermissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(Auth::user());

        return $schema
            ->columns(1)
            ->components([
                Callout::make('รหัสผ่านเริ่มต้นคือ "'.config('emcc.default_password').'"')
                    ->description('ผู้ใช้ใหม่และผู้ใช้ที่ถูกรีเซ็ตรหัสผ่านจะใช้รหัสนี้เข้าสู่ระบบครั้งแรก แล้วระบบจะบังคับให้ตั้งรหัสใหม่ (อย่างน้อย '.config('emcc.password_min_length').' ตัวอักษร) ก่อนใช้งานเมนูอื่น')
                    ->warning(),

                Section::make('ข้อมูลผู้ใช้งาน')
                    ->columns(2)
                    ->schema([
                        TextInput::make('username')
                            ->label('ชื่อผู้ใช้ (Username)')
                            ->required()
                            ->maxLength(50)
                            ->regex('/^\s*[a-zA-Z0-9._-]+\s*$/')
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->helperText('ใช้ได้เฉพาะ a-z, 0-9, จุด (.), ขีดล่าง (_) และขีด (-) ระบบจะแปลงเป็นตัวพิมพ์เล็กให้')
                            ->validationMessages([
                                'required' => 'กรุณากรอกชื่อผู้ใช้',
                                'max' => 'ชื่อผู้ใช้ยาวได้ไม่เกิน 50 ตัวอักษร',
                                'regex' => 'ชื่อผู้ใช้ใช้ได้เฉพาะ a-z, 0-9, จุด, ขีดล่าง และขีด (ห้ามมีช่องว่างหรือภาษาไทย)',
                                'unique' => 'ชื่อผู้ใช้นี้ถูกใช้แล้ว (รวมถึงบัญชีที่ถูกลบ) กรุณาใช้ชื่ออื่น',
                            ]),
                        TextInput::make('email')
                            ->label('อีเมล')
                            ->email()
                            ->required()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->validationMessages([
                                'required' => 'กรุณากรอกอีเมล',
                                'email' => 'รูปแบบอีเมลไม่ถูกต้อง ตัวอย่าง name@example.com',
                                'unique' => 'อีเมลนี้มีผู้ใช้อื่นใช้แล้ว (รวมถึงบัญชีที่ถูกลบ) กรุณาใช้อีเมลอื่น',
                            ]),
                        TextInput::make('first_name')
                            ->label('ชื่อ')
                            ->required()
                            ->maxLength(100)
                            ->validationMessages(['required' => 'กรุณากรอกชื่อ']),
                        TextInput::make('last_name')
                            ->label('นามสกุล')
                            ->required()
                            ->maxLength(100)
                            ->validationMessages(['required' => 'กรุณากรอกนามสกุล']),
                        TextInput::make('phone')
                            ->label('เบอร์โทรศัพท์')
                            ->tel()
                            ->maxLength(20)
                            ->regex('/^[0-9\-\s+()]*$/')
                            ->helperText('ตัวอย่าง 081-234-5678 (ระบบเก็บเฉพาะตัวเลข)')
                            ->validationMessages(['regex' => 'เบอร์โทรศัพท์ใส่ได้เฉพาะตัวเลขและขีด (-)']),
                        TextInput::make('position')
                            ->label('ตำแหน่ง')
                            ->maxLength(150),
                        Select::make('role')
                            ->label('บทบาท')
                            ->options(fn () => Auth::user()->assignableRoles())
                            ->default(User::ROLE_USER)
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->disabled($isSelf)
                            ->helperText(fn (?User $record) => $isSelf($record)
                                ? 'เปลี่ยนบทบาทของตัวเองไม่ได้'
                                : 'Developer / Administrator เข้าได้ทุกหน้าและจัดการผู้ใช้ได้, User เห็นเฉพาะหน้าที่ให้สิทธิ์')
                            ->validationMessages(['in' => 'บทบาทที่เลือกไม่ถูกต้อง หรือคุณไม่มีสิทธิ์กำหนดบทบาทนี้']),
                        Toggle::make('is_active')
                            ->label('เปิดใช้งาน')
                            ->default(true)
                            ->disabled($isSelf)
                            ->helperText(fn (?User $record) => $isSelf($record)
                                ? 'ปิดบัญชีของตัวเองไม่ได้'
                                : 'ปิดแล้วผู้ใช้จะเข้าสู่ระบบไม่ได้'),
                    ]),

                Section::make('สิทธิ์การเข้าถึงหน้า')
                    ->description('เลือกหน้าที่ผู้ใช้คนนี้เห็นและใช้งานได้ (ค่าเริ่มต้นคือไม่เห็นทุกหน้า)')
                    ->schema([
                        Text::make('Developer และ Administrator เข้าได้ทุกหน้าเสมอ ไม่ต้องกำหนดสิทธิ์')
                            ->visible(fn (Get $get) => $get('role') !== User::ROLE_USER),
                        CheckboxList::make('page_permissions')
                            ->hiddenLabel()
                            ->options(fn () => UserPermissions::assignablePageOptions())
                            ->columns(2)
                            ->bulkToggleable()
                            ->visible(fn (Get $get) => $get('role') === User::ROLE_USER)
                            ->helperText('หน้า "จัดการผู้ใช้งาน" และ "บันทึกการใช้งาน" ให้สิทธิ์ได้เฉพาะ Developer / Administrator'),
                    ]),
            ]);
    }
}

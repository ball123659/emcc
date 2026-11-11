<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PasswordRules;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * โปรไฟล์ของฉัน — แก้ข้อมูลส่วนตัวและรหัสผ่านของตัวเอง
 * ถ้า must_change_password = 1 หน้านี้จะบังคับตั้งรหัสใหม่ก่อนใช้เมนูอื่น
 */
class EditProfile extends BaseEditProfile
{
    private bool $passwordChanged = false;

    public static function getLabel(): string
    {
        return 'โปรไฟล์ของฉัน';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->mustChangePassword() ? 'ตั้งรหัสผ่านใหม่' : 'โปรไฟล์ของฉัน';
    }

    public function mustChangePassword(): bool
    {
        return (bool) $this->getUser()->must_change_password;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('กรุณาตั้งรหัสผ่านใหม่ก่อนเริ่มใช้งาน')
                ->description('บัญชีนี้ยังใช้รหัสผ่านเริ่มต้นหรือเพิ่งถูกรีเซ็ต ต้องเปลี่ยนรหัสผ่านก่อนจึงจะใช้เมนูอื่นได้')
                ->warning()
                ->visible(fn () => $this->mustChangePassword()),

            Section::make('ข้อมูลส่วนตัว')
                ->schema([
                    TextInput::make('username')
                        ->label('ชื่อผู้ใช้ (Username)')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('เปลี่ยนชื่อผู้ใช้ได้โดยผู้ดูแลระบบเท่านั้น'),
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
                    $this->getEmailFormComponent(),
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
                ])
                ->columns(2)
                ->hidden(fn () => $this->mustChangePassword()),

            Section::make('เปลี่ยนรหัสผ่าน')
                ->description('รหัสผ่านใหม่ต้องมีอย่างน้อย '.config('emcc.password_min_length').' ตัวอักษร และห้ามใช้รหัสผ่านเริ่มต้น'.($this->mustChangePassword() ? '' : ' — เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน'))
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('อีเมล')
            ->email()
            ->required()
            ->maxLength(191)
            ->unique(ignoreRecord: true)
            ->validationMessages([
                'required' => 'กรุณากรอกอีเมล',
                'email' => 'รูปแบบอีเมลไม่ถูกต้อง ตัวอย่าง name@example.com',
                'unique' => 'อีเมลนี้มีผู้ใช้อื่นใช้แล้ว กรุณาใช้อีเมลอื่น',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('รหัสผ่านใหม่')
            ->password()
            ->revealable()
            ->required(fn () => $this->mustChangePassword())
            ->minLength((int) config('emcc.password_min_length'))
            ->maxLength(100)
            ->rule(PasswordRules::notDefault())
            ->autocomplete('new-password')
            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state))
            ->dehydrateStateUsing(fn (#[SensitiveParameter] $state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation')
            ->validationMessages(PasswordRules::messages());
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('ยืนยันรหัสผ่านใหม่')
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->required()
            ->visible(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false)
            ->validationMessages(['required' => 'กรุณาพิมพ์รหัสผ่านใหม่อีกครั้งเพื่อยืนยัน']);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label('รหัสผ่านปัจจุบัน')
            ->helperText('เพื่อยืนยันว่าเป็นเจ้าของบัญชี')
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('password')) || ($get('email') !== null && $get('email') !== $this->getUser()->getAttributeValue('email')))
            ->dehydrated(false)
            ->validationMessages([
                'required' => 'กรุณากรอกรหัสผ่านปัจจุบัน',
                'current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
            ]);
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $data = array_intersect_key($data, array_flip(['first_name', 'last_name', 'email', 'phone', 'position', 'password']));

        if (array_key_exists('password', $data)) {
            $data['must_change_password'] = false;
            $data['password_changed_at'] = now();
            $this->passwordChanged = true;
        }

        $record->update($data);

        if ($this->passwordChanged) {
            ActivityLogger::log('password_change', $record, 'เปลี่ยนรหัสผ่านของตนเอง');
        }

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->passwordChanged ? 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว' : 'บันทึกข้อมูลเรียบร้อยแล้ว';
    }

    protected function getRedirectUrl(): ?string
    {
        // เปลี่ยนรหัสครั้งแรกเสร็จ -> ไปหน้าแรกของหลังบ้าน
        return $this->passwordChanged ? Filament::getUrl() : null;
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->hidden(fn () => $this->mustChangePassword());
    }
}

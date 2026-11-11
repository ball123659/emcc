<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Users\Actions\UserActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (User $record) => filament()->getUserAvatarUrl($record))
                    ->circular()
                    ->imageSize(40),
                TextColumn::make('full_name')
                    ->label('ผู้ใช้งาน')
                    ->weight('medium')
                    ->description(fn (User $record) => $record->username.' · '.$record->email)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn (Builder $q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                    )),
                TextColumn::make('position')
                    ->label('ตำแหน่ง')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('บทบาท')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => User::ROLES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        User::ROLE_DEVELOPER => 'danger',
                        User::ROLE_ADMINISTRATOR => 'primary',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('เปิดใช้งาน')
                    ->boolean(),
                TextColumn::make('account_status')
                    ->label('สถานะบัญชี')
                    ->badge()
                    ->state(fn (User $record) => match (true) {
                        $record->trashed() => 'ถูกลบ',
                        $record->isLocked() => 'ถูกล็อก',
                        $record->must_change_password => 'รอเปลี่ยนรหัส',
                        default => 'ปกติ',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'ถูกลบ', 'ถูกล็อก' => 'danger',
                        'รอเปลี่ยนรหัส' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('last_login_at')
                    ->label('เข้าสู่ระบบล่าสุด')
                    ->dateTime()
                    ->placeholder('ยังไม่เคย')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('บทบาท')->options(User::ROLES),
                TernaryFilter::make('is_active')->label('เปิดใช้งาน'),
                TrashedFilter::make()->label('รายการที่ถูกลบ'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('activity')
                        ->label('ดูบันทึกการใช้งาน')
                        ->icon(Heroicon::OutlinedClock)
                        ->color('gray')
                        ->url(fn (User $record) => ActivityLogResource::getUrl('index', ['filters' => ['user_id' => ['value' => $record->id]]])),
                    UserActions::resetPassword(),
                    UserActions::unlock(),
                    DeleteAction::make()
                        ->modalDescription('ผู้ใช้จะเข้าสู่ระบบไม่ได้ และกู้คืนได้ภายหลังจากตัวกรอง "รายการที่ถูกลบ"'),
                    RestoreAction::make(),
                ]),
            ]);
    }
}

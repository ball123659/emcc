<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Actions\UserActions;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('username')
                    ->label('ชื่อผู้ใช้')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('full_name')
                    ->label('ชื่อ-นามสกุล')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn (Builder $q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                    ))
                    ->description(fn (User $record) => $record->position),
                TextColumn::make('email')
                    ->label('อีเมล')
                    ->searchable()
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
                    ->dateTime('d/m/Y H:i')
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
                    UserActions::resetPassword(),
                    UserActions::unlock(),
                    DeleteAction::make()
                        ->modalDescription('ผู้ใช้จะเข้าสู่ระบบไม่ได้ และกู้คืนได้ภายหลังจากตัวกรอง "รายการที่ถูกลบ"'),
                    RestoreAction::make(),
                ]),
            ]);
    }
}

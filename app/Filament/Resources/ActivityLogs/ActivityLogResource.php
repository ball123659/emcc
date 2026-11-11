<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * บันทึกการใช้งาน — ดูได้อย่างเดียว (สิทธิ์ใน App\Policies\ActivityLogPolicy)
 */
class ActivityLogResource extends Resource
{
    use ChecksPagePermission;

    protected static string $pageCode = 'activity_logs';

    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'ผู้ดูแลระบบ';

    protected static ?string $navigationLabel = 'บันทึกการใช้งาน';

    protected static ?string $modelLabel = 'บันทึกการใช้งาน';

    protected static ?string $pluralModelLabel = 'บันทึกการใช้งาน';

    protected static ?int $navigationSort = 70;

    public const ACTIONS = [
        'login' => 'เข้าสู่ระบบ',
        'login_failed' => 'เข้าสู่ระบบไม่สำเร็จ',
        'logout' => 'ออกจากระบบ',
        'create' => 'สร้าง',
        'update' => 'แก้ไข',
        'delete' => 'ลบ',
        'restore' => 'กู้คืน',
        'password_change' => 'เปลี่ยนรหัสผ่าน',
        'password_reset' => 'รีเซ็ตรหัสผ่าน',
        'unlock' => 'ปลดล็อกบัญชี',
        'permission_update' => 'แก้สิทธิ์รายหน้า',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('วันเวลา')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.username')
                    ->label('ผู้ใช้')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('การกระทำ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::ACTIONS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'login_failed', 'delete' => 'danger',
                        'create', 'login', 'restore' => 'success',
                        'update', 'permission_update' => 'info',
                        'password_reset', 'password_change', 'unlock' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('ข้อมูล')
                    ->formatStateUsing(fn (ActivityLog $record) => $record->subject_type.($record->subject_id ? " #{$record->subject_id}" : ''))
                    ->placeholder('-'),
                TextColumn::make('description')
                    ->label('รายละเอียด')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('การกระทำ')->options(self::ACTIONS),
                SelectFilter::make('user_id')
                    ->label('ผู้ใช้')
                    ->options(fn () => User::withTrashed()->orderBy('username')->pluck('username', 'id')->all())
                    ->searchable(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('ตั้งแต่วันที่')->native(false),
                        DatePicker::make('until')->label('ถึงวันที่')->native(false)
                            ->afterOrEqual('from')
                            ->validationMessages(['after_or_equal' => 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม']),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('วันเวลา')->dateTime(),
            TextEntry::make('user.username')->label('ผู้ใช้')->placeholder('-'),
            TextEntry::make('action')->label('การกระทำ')->formatStateUsing(fn (string $state) => self::ACTIONS[$state] ?? $state),
            TextEntry::make('subject_type')->label('ข้อมูล')
                ->formatStateUsing(fn (ActivityLog $record) => $record->subject_type.($record->subject_id ? " #{$record->subject_id}" : ''))
                ->placeholder('-'),
            TextEntry::make('description')->label('รายละเอียด')->columnSpanFull()->placeholder('-'),
            TextEntry::make('ip_address')->label('IP')->placeholder('-'),
            TextEntry::make('user_agent')->label('เบราว์เซอร์')->placeholder('-'),
            Section::make('การเปลี่ยนแปลง')
                ->columnSpanFull()
                ->columns(2)
                ->visible(fn (ActivityLog $record) => filled($record->changes))
                ->schema([
                    KeyValueEntry::make('changes.old')->label('ค่าเดิม')->state(fn (ActivityLog $record) => self::flatten($record->changes['old'] ?? [])),
                    KeyValueEntry::make('changes.new')->label('ค่าใหม่')->state(fn (ActivityLog $record) => self::flatten($record->changes['new'] ?? [])),
                ]),
        ]);
    }

    /** แปลงค่าให้แสดงใน KeyValue ได้ */
    private static function flatten(array $values): array
    {
        return array_map(
            fn ($value) => is_array($value) ? implode(', ', array_map('strval', $value)) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
            $values,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * แท็บ "บันทึกการใช้งาน" ในหน้าแก้ไขผู้ใช้ — ดูอย่างเดียว (เฉพาะผู้มีสิทธิ์ดูบันทึกการใช้งาน)
 */
class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'บันทึกการใช้งานของผู้ใช้นี้';

    protected static ?string $modelLabel = 'บันทึกการใช้งาน';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canViewPage('activity_logs');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('ยังไม่มีบันทึกการใช้งาน')
            ->columns([
                TextColumn::make('created_at')->label('วันเวลา')->dateTime()->sortable(),
                TextColumn::make('action')
                    ->label('การกระทำ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ActivityLogResource::ACTIONS[$state] ?? $state),
                TextColumn::make('subject_type')
                    ->label('ข้อมูล')
                    ->formatStateUsing(fn (ActivityLog $record) => $record->subject_type.($record->subject_id ? " #{$record->subject_id}" : ''))
                    ->placeholder('-'),
                TextColumn::make('description')->label('รายละเอียด')->limit(60)->placeholder('-'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('การกระทำ')->options(ActivityLogResource::ACTIONS),
            ]);
    }
}

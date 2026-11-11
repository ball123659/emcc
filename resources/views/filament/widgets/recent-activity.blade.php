<x-filament-widgets::widget>
    <x-filament::section heading="ความเคลื่อนไหวล่าสุด">
        <x-slot name="afterHeader">
            <x-filament::link :href="$url" size="sm">ดูทั้งหมด</x-filament::link>
        </x-slot>

        @if ($logs->isEmpty())
            <div class="emcc-empty">ยังไม่มีความเคลื่อนไหว</div>
        @else
            <div class="emcc-timeline">
                @foreach ($logs as $log)
                    <div class="emcc-timeline-item" style="--emcc-dot: {{ $colors[$log->action] ?? '#8b84a0' }}">
                        <div class="emcc-timeline-time" title="{{ $log->created_at->format(config('emcc.datetime_format')) }}">
                            {{ $log->created_at->gt(now()->subMinute()) ? 'เมื่อสักครู่' : $log->created_at->locale('th')->diffForHumans() }}
                        </div>
                        <div class="emcc-timeline-text">
                            <strong>{{ $log->user?->username ?? 'ไม่ทราบผู้ใช้' }}</strong>
                            {{ $actions[$log->action] ?? $log->action }}
                            @if ($log->subject_type && isset($subjects[$log->subject_type]) && ! in_array($log->action, ['login', 'logout', 'login_failed'], true))
                                {{ $subjects[$log->subject_type] }}
                            @endif
                            @if ($log->description && ! in_array($log->action, ['login'], true))
                                <span style="color:#6b6380">· {{ \Illuminate\Support\Str::limit($log->description, 60) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

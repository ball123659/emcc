<x-filament-widgets::widget>
    <div class="emcc-welcome">
        <h2>{{ $greeting }}, คุณ{{ $name }}</h2>
        <p>วัน{{ $weekday }}ที่ {{ $today }} · ยินดีต้อนรับสู่ระบบจัดการเว็บไซต์ EMCC</p>

        @if ($actions)
            <div class="emcc-welcome-actions">
                @foreach ($actions as $action)
                    <a href="{{ $action['url'] }}" @class(['is-primary' => $action['primary'] ?? false])>
                        <x-filament::icon :icon="$action['icon']" />
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

<x-filament-widgets::widget>
    <x-filament::section heading="งานที่ต้องทำ" description="ระบบตรวจให้อัตโนมัติ — กดเพื่อไปจัดการ">
        @if ($todos->isEmpty())
            <div class="emcc-empty">✅ ไม่มีงานค้าง ทุกอย่างเรียบร้อย</div>
        @else
            <div class="emcc-list">
                @foreach ($todos as $todo)
                    <a href="{{ $todo['url'] }}" class="emcc-todo" style="--emcc-todo-color: {{ $todo['color'] }}">
                        <div class="emcc-todo-body">
                            <div class="emcc-todo-title">{{ $todo['title'] }}</div>
                            <div class="emcc-todo-sub">{{ $todo['sub'] }}</div>
                        </div>
                        <span class="emcc-todo-count">{{ number_format($todo['count']) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

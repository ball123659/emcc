<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('fonts/prompt/prompt.css') }}">
    <style>
        :root { --primary: #704a9f; --green: #0e975f; --text: #1f2937; --muted: #6b7280; --bg: #f7f5fa; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
               font-family: 'Prompt', system-ui, sans-serif; background: var(--bg); color: var(--text); }
        .card { max-width: 520px; width: 100%; background: #fff; border-radius: 16px; padding: 40px 32px; text-align: center;
                box-shadow: 0 10px 30px rgba(112, 74, 159, .12); border-top: 6px solid var(--primary); }
        .code { font-size: 64px; font-weight: 700; color: var(--primary); line-height: 1; margin: 0 0 8px; }
        h1 { font-size: 22px; font-weight: 600; margin: 0 0 12px; }
        p { color: var(--muted); margin: 0 0 24px; line-height: 1.7; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.btn { display: inline-block; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 500; }
        a.primary { background: var(--primary); color: #fff; }
        a.secondary { border: 1px solid #d1d5db; color: var(--text); }
        a.btn:focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
    </style>
</head>
<body>
<main class="card">
    <p class="code">@yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        <a class="btn primary" href="{{ url('/') }}">กลับหน้าแรก</a>
        @if (request()->is('backend*'))
            <a class="btn secondary" href="{{ url('/backend') }}">EMCC Backend</a>
        @endif
    </div>
</main>
</body>
</html>

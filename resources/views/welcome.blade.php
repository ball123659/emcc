{{-- หน้าแรกชั่วคราว — แทนที่ด้วยหน้าบ้านจาก template ในลำดับงานข้อ 7 --}}
@php
    $siteName = 'ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม';
    $phone = $email = null;

    try {
        $siteName = \App\Models\SiteSetting::getValue('site_name', $siteName);
        $phone = \App\Models\SiteSetting::getValue('phone');
        $email = \App\Models\SiteSetting::getValue('email');
    } catch (\Illuminate\Database\QueryException) {
        // ยังไม่ได้ migrate / ฐานข้อมูลยังไม่พร้อม -> แสดงหน้าโดยไม่มีข้อมูลติดต่อ
    }
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }}</title>
    <meta name="description" content="{{ $siteName }} (EMCC) — เว็บไซต์กำลังปรับปรุง">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('fonts/prompt/prompt.css') }}">
    <style>
        :root { --primary: #704a9f; --green: #0e975f; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
               font-family: 'Prompt', system-ui, sans-serif; color: #fff;
               background: radial-gradient(circle at 85% 15%, rgba(255,255,255,.12) 0 16%, transparent 17%),
                           radial-gradient(circle at 10% 95%, rgba(14,151,95,.45) 0 22%, transparent 23%),
                           linear-gradient(150deg, #2a1b45 0%, var(--primary) 75%, #8a5fbf 100%); }
        main { max-width: 640px; text-align: center; }
        .logo { width: 72px; height: 72px; border-radius: 18px; margin: 0 auto 24px; }
        h1 { font-size: clamp(1.5rem, 4vw, 2.2rem); font-weight: 700; line-height: 1.4; margin: 0 0 8px; }
        .en { color: rgba(255,255,255,.75); margin: 0 0 28px; }
        .badge { display: inline-block; background: var(--green); padding: 8px 18px; border-radius: 999px; font-weight: 500; }
        p { line-height: 1.8; color: rgba(255,255,255,.88); }
        .contact { margin-top: 28px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .contact a, .contact span { color: #fff; text-decoration: none; background: rgba(255,255,255,.14); padding: 8px 14px; border-radius: 10px; }
    </style>
</head>
<body>
<main>
    <img class="logo" src="{{ asset('favicon.svg') }}" alt="">
    <h1>{{ $siteName }}</h1>
    <p class="en">Environmental Monitoring &amp; Control Center (EMCC)</p>
    <span class="badge">เว็บไซต์กำลังปรับปรุง · Coming soon</span>
    <p>ขออภัยในความไม่สะดวก เว็บไซต์ใหม่จะเปิดให้บริการเร็ว ๆ นี้</p>
    @if ($phone || $email)
        <div class="contact">
            @if ($phone)<span>โทร {{ $phone }}</span>@endif
            @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
        </div>
    @endif
</main>
</body>
</html>

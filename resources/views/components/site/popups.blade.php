{{-- Popup หน้าเว็บ — ใช้ <x-site.popups /> ในหน้าแรก (CSS ใช้ตัวแปรสีหลักของเว็บ) --}}
@php($en = app()->getLocale() === 'en')
<div id="site-popups" class="site-popups" hidden>
    @foreach ($popups as $popup)
        <div class="site-popup" role="dialog" aria-modal="true" aria-label="{{ $popup->localized('alt') }}" data-popup hidden>
            <div class="site-popup__backdrop" data-popup-close></div>
            <div class="site-popup__box">
                <button type="button" class="site-popup__close" data-popup-close aria-label="{{ $en ? 'Close' : 'ปิด' }}">&times;</button>
                @if ($popup->link_url)
                    <a href="{{ $popup->link_url }}" @if ($popup->open_new_tab) target="_blank" rel="noopener noreferrer" @endif>
                        <img src="{{ $popup->localizedImageUrl() }}" alt="{{ $popup->localized('alt') }}" class="site-popup__image">
                    </a>
                @else
                    <img src="{{ $popup->localizedImageUrl() }}" alt="{{ $popup->localized('alt') }}" class="site-popup__image">
                @endif
                <label class="site-popup__skip">
                    <input type="checkbox" data-popup-skip>
                    {{ $en ? "Don't show again today" : 'ไม่ต้องแสดงอีกวันนี้' }}
                </label>
            </div>
        </div>
    @endforeach
</div>

<style>
    .site-popups .site-popup { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .site-popups .site-popup[hidden] { display: none; }
    .site-popup__backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .6); }
    .site-popup__box { position: relative; max-width: min(800px, 100%); max-height: 100%; background: #fff; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
    .site-popup__image { display: block; max-width: 100%; max-height: calc(100vh - 110px); object-fit: contain; margin: 0 auto; }
    .site-popup__close { position: absolute; top: 8px; right: 8px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: rgba(0, 0, 0, .55); color: #fff; font-size: 26px; line-height: 1; cursor: pointer; }
    .site-popup__close:focus-visible { outline: 3px solid #0e975f; outline-offset: 2px; }
    .site-popup__skip { display: flex; align-items: center; gap: 8px; padding: 10px 16px; font-size: 15px; cursor: pointer; }
</style>

<script>
    (function () {
        var KEY = 'emcc_popup_hidden_date';
        var today = new Date();
        var todayKey = today.getFullYear() + '-' + (today.getMonth() + 1) + '-' + today.getDate();

        function hiddenToday() {
            try { return localStorage.getItem(KEY) === todayKey; } catch (e) { return false; }
        }

        if (hiddenToday()) return;

        var root = document.getElementById('site-popups');
        var items = root ? Array.prototype.slice.call(root.querySelectorAll('[data-popup]')) : [];
        var index = -1;
        var lastFocus = document.activeElement;

        function show(next) {
            items.forEach(function (item) { item.hidden = true; });
            index = next;

            if (index >= items.length) {
                root.hidden = true;
                document.removeEventListener('keydown', onKey);
                if (lastFocus && lastFocus.focus) lastFocus.focus();
                return;
            }

            root.hidden = false;
            items[index].hidden = false;
            items[index].querySelector('.site-popup__close').focus();
        }

        function close() {
            var skip = items[index].querySelector('[data-popup-skip]');

            if (skip && skip.checked) {
                try { localStorage.setItem(KEY, todayKey); } catch (e) {}
                show(items.length); // ไม่แสดงอันที่เหลือวันนี้
                return;
            }

            show(index + 1);
        }

        function onKey(e) {
            if (e.key === 'Escape') close();
        }

        items.forEach(function (item) {
            item.querySelectorAll('[data-popup-close]').forEach(function (el) {
                el.addEventListener('click', close);
            });
        });

        if (items.length) {
            document.addEventListener('keydown', onKey);
            show(0);
        }
    })();
</script>

/* EMCC — หน้าบ้าน (JavaScript ล้วน ไม่ต้อง build) */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ===== เมนูมือถือ ===== */
  (function () {
    var drawer = $('#drawer');
    var toggle = $('[data-drawer-toggle]');
    var backdrop = $('.drawer-backdrop');
    if (!drawer || !toggle) return;

    function open() {
      drawer.removeAttribute('inert');
      drawer.classList.add('open');
      backdrop.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
      var first = $('a, button', drawer);
      if (first) first.focus();
    }
    function close() {
      if (!drawer.classList.contains('open')) return;
      drawer.classList.remove('open');
      backdrop.classList.remove('open');
      drawer.setAttribute('inert', '');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
      toggle.focus();
    }

    toggle.addEventListener('click', function () { drawer.classList.contains('open') ? close() : open(); });
    $$('[data-drawer-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1060) close(); });
  })();

  /* ===== สไลด์โชว์ ===== */
  (function () {
    var root = $('[data-slideshow]');
    if (!root) return;
    var track = $('[data-track]', root);
    var slides = $$('.slide', track);
    var dots = $$('.slide-dot', root);
    var bar = $('[data-progress]', root);
    if (slides.length < 2) return;

    var DELAY = 6000;
    var current = 0;
    var timer = null;
    var started = 0;
    var raf = null;

    function goTo(i) {
      current = (i + slides.length) % slides.length;
      track.style.transform = 'translateX(-' + (current * 100) + '%)';
      slides.forEach(function (s, idx) {
        var on = idx === current;
        s.setAttribute('aria-hidden', on ? 'false' : 'true');
        if (s.tagName === 'A') s.tabIndex = on ? 0 : -1;
      });
      dots.forEach(function (d, idx) {
        d.classList.toggle('active', idx === current);
        if (idx === current) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
      });
      restart();
    }
    function tick() {
      if (!bar) return;
      bar.style.width = Math.min(100, (Date.now() - started) / DELAY * 100) + '%';
      raf = window.requestAnimationFrame(tick);
    }
    function stop() {
      clearTimeout(timer); timer = null;
      if (raf) window.cancelAnimationFrame(raf);
      raf = null;
    }
    function restart() {
      stop();
      if (reduceMotion || document.hidden) { if (bar) bar.style.width = '0%'; return; }
      started = Date.now();
      timer = setTimeout(function () { goTo(current + 1); }, DELAY);
      tick();
    }

    $('[data-next]', root).addEventListener('click', function () { goTo(current + 1); });
    $('[data-prev]', root).addEventListener('click', function () { goTo(current - 1); });
    dots.forEach(function (d, i) { d.addEventListener('click', function () { goTo(i); }); });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', restart);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', restart);
    document.addEventListener('visibilitychange', restart);
    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') goTo(current + 1);
      if (e.key === 'ArrowLeft') goTo(current - 1);
    });

    var startX = 0;
    root.addEventListener('touchstart', function (e) { startX = e.changedTouches[0].screenX; stop(); }, { passive: true });
    root.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].screenX - startX;
      if (Math.abs(dx) > 50) goTo(dx < 0 ? current + 1 : current - 1); else restart();
    }, { passive: true });

    goTo(0);
  })();

  /* ===== Popup: แสดงทีละอันตามลำดับ, "ไม่ต้องแสดงอีกวันนี้" เก็บใน localStorage ===== */
  (function () {
    var layer = $('[data-popups]');
    if (!layer) return;
    var KEY = 'emcc.popup.skip';
    var today = new Date();
    today = today.getFullYear() + '-' + (today.getMonth() + 1) + '-' + today.getDate();
    var popups = $$('[data-popup]', layer);
    var skipped = [];

    try {
      var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (saved && saved.date === today && Array.isArray(saved.ids)) skipped = saved.ids.map(String);
    } catch (e) { /* localStorage ใช้ไม่ได้ — แสดงตามปกติ */ }

    var queue = popups.filter(function (p) { return skipped.indexOf(p.getAttribute('data-popup')) === -1; });
    if (!queue.length) return;

    var index = -1;
    var lastFocus = document.activeElement;

    function remember() {
      try {
        var ids = skipped.concat(popups.map(function (p) { return p.getAttribute('data-popup'); }));
        localStorage.setItem(KEY, JSON.stringify({ date: today, ids: ids.filter(function (v, i) { return ids.indexOf(v) === i; }) }));
      } catch (e) { /* ignore */ }
    }
    function show(i) {
      queue.forEach(function (p) { p.hidden = true; });
      index = i;
      if (index >= queue.length) {
        layer.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
        return;
      }
      layer.hidden = false;
      document.body.style.overflow = 'hidden';
      queue[index].hidden = false;
      var btn = $('.popup-close', queue[index]);
      if (btn) btn.focus();
    }
    function next() {
      var skip = $('[data-popup-skip]', queue[index]);
      if (skip && skip.checked) { remember(); show(queue.length); return; }
      show(index + 1);
    }

    $$('[data-popup-close]', layer).forEach(function (el) { el.addEventListener('click', next); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !layer.hidden) next(); });
    // ติ๊กอันใดอันหนึ่ง = ติ๊กทุกอัน (ข้อความเดียวกัน "ไม่ต้องแสดงอีกวันนี้")
    $$('[data-popup-skip]', layer).forEach(function (cb) {
      cb.addEventListener('change', function () {
        $$('[data-popup-skip]', layer).forEach(function (o) { o.checked = cb.checked; });
      });
    });

    show(0);
  })();

  /* ===== ดาวน์โหลด: กรองตามหมวดและค้นหา ===== */
  (function () {
    var rows = $$('.dl-row[data-cat]');
    var tabs = $$('.dl-tabs button');
    var search = $('[data-dl-search]');
    var count = $('[data-dl-count]');
    var empty = $('[data-dl-empty]');
    if (!rows.length) return;
    var cat = 'all';

    function apply() {
      var q = (search ? search.value : '').trim().toLowerCase();
      var shown = 0;
      rows.forEach(function (r) {
        var ok = (cat === 'all' || r.getAttribute('data-cat') === cat) && (!q || r.getAttribute('data-name').indexOf(q) !== -1);
        r.hidden = !ok;
        if (ok) shown++;
      });
      if (count) count.textContent = count.getAttribute('data-template').replace('{n}', shown.toLocaleString());
      if (empty) empty.hidden = shown !== 0;
    }

    tabs.forEach(function (b) {
      b.addEventListener('click', function () {
        cat = b.getAttribute('data-cat');
        tabs.forEach(function (x) {
          x.classList.toggle('active', x === b);
          x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
        });
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
  })();

  /* ===== คัดลอกลิงก์ ===== */
  $$('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      var done = function () {
        btn.classList.add('copied');
        btn.setAttribute('title', btn.getAttribute('data-copied'));
        setTimeout(function () { btn.classList.remove('copied'); }, 2000);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { window.prompt('', text); });
      } else {
        window.prompt('', text);
      }
    });
  });

  /* ===== Lightbox รูป gallery ===== */
  (function () {
    var links = $$('[data-lightbox]');
    if (!links.length) return;
    var box = null;
    var opener = null;

    function close() {
      if (!box) return;
      box.remove();
      box = null;
      document.body.style.overflow = '';
      if (opener) opener.focus();
    }

    links.forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        opener = a;
        box = document.createElement('div');
        box.className = 'lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        var fig = document.createElement('figure');
        fig.style.margin = '0';
        var img = document.createElement('img');
        img.src = a.getAttribute('href');
        img.alt = (a.querySelector('img') || {}).alt || '';
        fig.appendChild(img);
        if (a.getAttribute('data-caption')) {
          var p = document.createElement('p');
          p.textContent = a.getAttribute('data-caption');
          fig.appendChild(p);
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '✕';
        btn.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Close' : 'ปิด');
        btn.addEventListener('click', close);
        box.addEventListener('click', function (ev) { if (ev.target === box) close(); });
        box.appendChild(fig);
        box.appendChild(btn);
        document.body.appendChild(box);
        document.body.style.overflow = 'hidden';
        btn.focus();
      });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  })();
})();

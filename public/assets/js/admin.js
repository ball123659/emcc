/*
 * สคริปต์หลังบ้าน EMCC (JavaScript ล้วน ไม่ต้อง build)
 * ตรวจฟอร์มฝั่งเบราว์เซอร์ด้วยข้อความภาษาไทย — ฝั่งเซิร์ฟเวอร์ตรวจซ้ำเสมอ
 */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };
    var csrf = function () {
        var name = $('meta[name="csrf-name"]'), token = $('meta[name="csrf-token"]');
        return { name: name ? name.content : '_token', token: token ? token.content : '' };
    };
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var ICONS = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
        danger: '<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
        warning: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
        info: '<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>',
        close: '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>'
    };
    var svg = function (paths) { return '<svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">' + paths + '</svg>'; };

    /* ---------------- แจ้งเตือน ---------------- */
    function toast(opts) {
        var box = $('[data-toasts]');
        if (!box) { return; }
        var type = opts.type || 'success';
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        el.innerHTML = svg(ICONS[type] || ICONS.info) +
            '<div class="toast-body"><div class="toast-title">' + esc(opts.title) + '</div>' +
            (opts.text ? '<div class="toast-text">' + esc(opts.text) + '</div>' : '') + '</div>' +
            '<button type="button" class="toast-close" aria-label="ปิด">' + svg(ICONS.close) + '</button>';
        box.appendChild(el);
        var remove = function () { if (el.parentNode) { el.parentNode.removeChild(el); } };
        el.querySelector('.toast-close').addEventListener('click', remove);
        if (!opts.persistent) { setTimeout(remove, type === 'danger' ? 9000 : 5000); }
    }
    window.emccToast = toast;
    var initial = $('[data-initial-toast]');
    if (initial) { try { toast(JSON.parse(initial.textContent)); } catch (e) {} }

    /* ---------------- ส่งคำขอ AJAX ---------------- */
    function post(url, data) {
        var body = new FormData();
        var c = csrf();
        body.append(c.name, c.token);
        Object.keys(data || {}).forEach(function (k) {
            var v = data[k];
            if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); } else { body.append(k, v); }
        });
        return fetch(url, {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': c.token }
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok) { throw new Error(json.message || 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'); }
                return json;
            });
        });
    }
    window.emccPost = post;

    /* ---------------- เมนูข้าง ---------------- */
    var root = document.documentElement;
    $$('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                root.classList.toggle('sidebar-open');
                return;
            }
            root.classList.toggle('sidebar-collapsed');
            store.set('emcc.sidebar', root.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open');
        });
    });
    $$('[data-sidebar-close]').forEach(function (el) { el.addEventListener('click', function () { root.classList.remove('sidebar-open'); }); });

    var collapsedGroups = (store.get('emcc.navGroups') || '').split(',').filter(Boolean);
    $$('[data-nav-group]').forEach(function (group) {
        var key = group.querySelector('.nav-group-label span');
        key = key ? key.textContent : '';
        if (collapsedGroups.indexOf(key) !== -1 && !group.querySelector('.is-active')) { group.classList.add('is-collapsed'); }
        var btn = group.querySelector('[data-nav-group-toggle]');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            group.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', group.classList.contains('is-collapsed') ? 'false' : 'true');
            var list = $$('[data-nav-group].is-collapsed .nav-group-label span').map(function (s) { return s.textContent; });
            store.set('emcc.navGroups', list.join(','));
        });
    });

    /* ---------------- ธีม สว่าง / มืด / ตามระบบ ---------------- */
    var darkQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    function applyTheme(theme) {
        var dark = theme === 'dark' || (theme === 'system' && darkQuery && darkQuery.matches);
        root.classList.toggle('dark', dark);
        $$('[data-theme]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-theme') === theme); });
    }
    var currentTheme = store.get('emcc.theme') || 'system';
    applyTheme(currentTheme);
    $$('[data-theme]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentTheme = btn.getAttribute('data-theme');
            store.set('emcc.theme', currentTheme);
            applyTheme(currentTheme);
        });
    });
    if (darkQuery && darkQuery.addEventListener) {
        darkQuery.addEventListener('change', function () { if (currentTheme === 'system') { applyTheme('system'); } });
    }

    /* ---------------- dropdown ---------------- */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown-toggle]');
        $$('[data-dropdown] > .dropdown-panel').forEach(function (panel) {
            if (!toggle || panel.parentNode !== toggle.closest('[data-dropdown]')) {
                if (!panel.contains(e.target)) { panel.hidden = true; }
            }
        });
        if (toggle) {
            var panel = toggle.closest('[data-dropdown]').querySelector('.dropdown-panel');
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            $$('[data-dropdown] > .dropdown-panel').forEach(function (p) { p.hidden = true; });
            closeModal();
        }
    });

    /* ---------------- ยืนยันก่อนทำรายการ ---------------- */
    var modal = $('[data-confirm-modal]');
    var pending = null;
    function closeModal() { if (modal) { modal.hidden = true; } pending = null; $$('.modal[data-modal]').forEach(function (m) { m.hidden = true; }); }
    function confirmDialog(el, onOk) {
        if (!modal) { if (window.confirm(el.getAttribute('data-confirm'))) { onOk(); } return; }
        $('[data-confirm-title]', modal).textContent = el.getAttribute('data-confirm-title') || 'ยืนยันการทำรายการ';
        $('[data-confirm-text]', modal).textContent = el.getAttribute('data-confirm') || '';
        var ok = $('[data-confirm-ok]', modal);
        var variant = el.getAttribute('data-confirm-variant') || 'danger';
        ok.textContent = el.getAttribute('data-confirm-ok') || 'ยืนยัน';
        ok.className = 'btn ' + (variant === 'danger' ? 'btn-danger' : 'btn-primary');
        $('[data-confirm-icon]', modal).className = 'modal-icon' + (variant === 'danger' ? '' : ' is-warning');
        pending = onOk;
        modal.hidden = false;
        ok.focus();
    }
    if (modal) {
        $('[data-confirm-ok]', modal).addEventListener('click', function () { var fn = pending; closeModal(); if (fn) { fn(); } });
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-close]')) { closeModal(); return; }
        var opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            var m = document.getElementById(opener.getAttribute('data-modal-open'));
            if (m) { m.hidden = false; var f = m.querySelector('input:not([type=hidden]),select,textarea'); if (f) { f.focus(); } }
            return;
        }
        var el = e.target.closest('[data-confirm]');
        if (!el || el.dataset.confirmed === '1') { return; }
        e.preventDefault();
        confirmDialog(el, function () {
            el.dataset.confirmed = '1';
            if (el.form && el.type === 'submit') {
                if (el.form.requestSubmit) { el.form.requestSubmit(el); } else { el.form.submit(); }
            } else { el.click(); }
            el.dataset.confirmed = '';
        });
    });
    $$('.modal[data-modal][data-open-on-load]').forEach(function (m) { m.hidden = false; });

    /* ---------------- ตรวจฟอร์ม (ภาษาไทย) ---------------- */
    function labelOf(input) {
        var field = input.closest('.field');
        var label = field ? field.querySelector('.field-label') : null;
        var text = label ? label.childNodes[0].textContent : (input.getAttribute('aria-label') || '');
        return text.replace(/\s*\(.*?\)\s*$/, '').trim();
    }
    function messageFor(input) {
        var v = input.validity;
        var label = labelOf(input);
        if (input.dataset.customError) { return input.dataset.customError; }
        if (v.valueMissing && input.dataset.rejected) { return input.dataset.rejected; }
        if (v.valueMissing) {
            if (input.type === 'file') { return 'กรุณาเลือกไฟล์' + (label ? ' (' + label + ')' : ''); }
            if (input.tagName === 'SELECT' || input.type === 'radio' || input.dataset.date !== undefined) { return 'กรุณาเลือก' + label; }
            return 'กรุณากรอก' + label;
        }
        if (v.tooLong) { return label + 'ยาวได้ไม่เกิน ' + input.maxLength + ' ตัวอักษร (ตอนนี้ ' + input.value.length + ')'; }
        if (v.tooShort) { return label + 'ต้องมีอย่างน้อย ' + input.minLength + ' ตัวอักษร'; }
        if (v.typeMismatch && input.type === 'email') { return 'รูปแบบอีเมลไม่ถูกต้อง เช่น name@example.com'; }
        if (v.typeMismatch && input.type === 'url') { return 'ลิงก์ไม่ถูกต้อง ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://www.example.com'; }
        if (v.patternMismatch) { return input.dataset.patternMessage || 'รูปแบบข้อมูลไม่ถูกต้อง'; }
        if (v.rangeUnderflow || v.rangeOverflow) { return 'ค่าไม่อยู่ในช่วงที่กำหนด'; }
        return input.validationMessage;
    }
    function showError(input, message) {
        var field = input.closest('.field');
        if (!field) { return; }
        var box = field.querySelector('.field-error');
        field.classList.toggle('has-error', !!message);
        if (box) { box.textContent = message || ''; box.hidden = !message; }
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
    }
    /* ขนาด/ชนิดไฟล์ที่เลือก (ตามข้อจำกัดจริงของเซิร์ฟเวอร์) */
    var metaNum = function (name) { var m = $('meta[name="' + name + '"]'); return m ? parseInt(m.content, 10) || 0 : 0; };
    var mb = function (bytes) { return (bytes / 1048576).toFixed(bytes >= 10485760 ? 0 : 1) + ' MB'; };
    function fileError(input) {
        var file = input.files && input.files[0];
        if (!file) { return ''; }
        var limit = metaNum('upload-max');
        var zone = input.closest('[data-max-kb]');
        if (zone) { var kb = parseInt(zone.getAttribute('data-max-kb'), 10) * 1024; limit = limit > 0 ? Math.min(limit, kb) : kb; }
        if (limit > 0 && file.size > limit) {
            return 'ไฟล์ใหญ่เกิน ' + mb(limit) + ' (ไฟล์นี้ ' + mb(file.size) + ') กรุณาลดขนาดไฟล์ก่อนอัปโหลด';
        }
        if (/image\//.test(input.getAttribute('accept') || '') && !/^image\/(jpeg|png|webp)$/.test(file.type)) {
            return 'รองรับเฉพาะรูป JPG, PNG หรือ WebP เท่านั้น';
        }
        return '';
    }

    function extraChecks(input) {
        input.dataset.customError = '';
        input.setCustomValidity('');
        if (input.type === 'url' && input.value.trim() !== '' && !/^https?:\/\//i.test(input.value.trim())) {
            input.dataset.customError = 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://www.example.com';
        }
        if (input.dataset.after && input.value) {
            var start = document.getElementById(input.dataset.after);
            if (start && start.value && input.value < start.value) { input.dataset.customError = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม กรุณาเลือกใหม่'; }
        }
        if (input.dataset.same) {
            var other = document.getElementById(input.dataset.same);
            if (other && other.value !== input.value) { input.dataset.customError = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน กรุณาพิมพ์ให้เหมือนกัน'; }
        }
        if (input.dataset.notIn && input.value !== '' && input.dataset.notIn.split('|').indexOf(input.value) !== -1) {
            input.dataset.customError = input.dataset.notInMessage || 'ค่านี้ใช้ไม่ได้';
        }
        if (input.dataset.customError) { input.setCustomValidity(input.dataset.customError); }
    }
    function validateField(input) {
        if (input.matches('textarea[data-rich]')) { return input.dataset.richInvalid !== '1'; }
        // ช่องวันที่ (flatpickr ซ่อนช่องจริงไว้) ตรวจเอง
        if (input.hasAttribute('data-date')) {
            if (input.disabled) { return true; }
            extraChecks(input);
            var msg = input.dataset.customError || (input.required && !input.value ? 'กรุณาเลือก' + labelOf(input) + 'จากปฏิทิน' : '');
            showError(input, msg);
            return !msg;
        }
        if (input.disabled || input.type === 'hidden' || !input.willValidate) { return true; }
        extraChecks(input);
        var ok = input.checkValidity();
        showError(input, ok ? '' : messageFor(input));
        return ok;
    }
    function revealPanel(input) {
        var panel = input.closest('.tab-panel');
        if (panel && panel.hidden) {
            var btn = document.querySelector('[data-tab-target="' + panel.id + '"]');
            if (btn) { btn.click(); }
        }
        var section = input.closest('.section.is-collapsed');
        if (section) { section.classList.remove('is-collapsed'); }
    }
    $$('form[data-validate]').forEach(function (form) {
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('input', function (e) { if (e.target.closest('.field.has-error')) { validateField(e.target); } });
        form.addEventListener('change', function (e) { if (e.target.matches('input,select,textarea')) { validateField(e.target); } });
        form.addEventListener('submit', function (e) {
            // ตัดช่องว่างหัวท้ายทุกช่อง (ยกเว้นรหัสผ่าน)
            $$('input[type=text],input[type=email],input[type=url],input[type=tel],input[type=search],textarea:not([data-rich])', form).forEach(function (i) {
                if (!i.readOnly && !i.disabled) { i.value = i.value.trim(); }
            });
            form.dispatchEvent(new CustomEvent('emcc:before-validate'));
            var first = null;
            $$('input,select,textarea', form).forEach(function (input) {
                if (!validateField(input) && !first) { first = input; }
            });
            // ขนาดไฟล์แต่ละไฟล์ + ขนาดรวม (เกิน post_max_size เซิร์ฟเวอร์จะทิ้งข้อมูลทั้งฟอร์ม)
            var total = 0;
            $$('input[type=file]', form).forEach(function (input) {
                if (input.disabled) { return; }
                Array.prototype.forEach.call(input.files || [], function (f) { total += f.size; });
                var err = fileError(input);
                if (err) { showError(input, err); if (!first) { first = input; } }
            });
            var postMax = metaNum('post-max');
            if (!first && postMax > 0 && total > postMax * 0.95) {
                e.preventDefault();
                toast({ type: 'danger', title: 'ไฟล์ที่แนบรวมกันใหญ่เกิน ' + mb(postMax), text: 'ตอนนี้รวม ' + mb(total) + ' — กรุณาลดขนาดไฟล์ หรือบันทึกทีละน้อยไฟล์' });
                return;
            }
            $$('.tab-list [data-tab-target]', form).forEach(function (btn) {
                var panel = document.getElementById(btn.getAttribute('data-tab-target'));
                var dot = btn.querySelector('.tab-error-dot');
                var bad = panel && panel.querySelector('.has-error');
                if (bad && !dot) { btn.insertAdjacentHTML('beforeend', '<span class="tab-error-dot" aria-label="มีข้อผิดพลาด"></span>'); }
                if (!bad && dot) { dot.parentNode.removeChild(dot); }
            });
            if (first) {
                e.preventDefault();
                revealPanel(first);
                var target = first.type === 'file' ? first.closest('.dropzone') || first : (first.matches('textarea[data-rich]') ? first.previousElementSibling : (first._fp && first._fp.altInput ? first._fp.altInput : first));
                if (target.scrollIntoView) { target.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
                try { first.focus({ preventScroll: true }); } catch (err) {}
                toast({ type: 'danger', title: 'ยังบันทึกไม่ได้', text: 'กรุณาแก้ไขช่องที่มีข้อความสีแดง' });
                return;
            }
            var submitter = e.submitter;
            if (submitter) {
                if (submitter.dataset.busy === '1') { e.preventDefault(); return; }
                submitter.dataset.busy = '1';
                submitter.classList.add('is-loading');
                if (submitter.name) {
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value;
                    form.appendChild(h);
                }
                setTimeout(function () { $$('button[type=submit]', form).forEach(function (b) { b.disabled = true; }); }, 0);
            }
        });
    });
    // ข้อผิดพลาดจากเซิร์ฟเวอร์: เปิดแท็บ/ส่วนที่มีช่องผิด
    var serverError = $('.field.has-error');
    if (serverError) {
        $$('.tab-panel').forEach(function (panel) {
            if (panel.querySelector('.has-error')) {
                var btn = document.querySelector('[data-tab-target="' + panel.id + '"]');
                if (btn && !btn.querySelector('.tab-error-dot')) { btn.insertAdjacentHTML('beforeend', '<span class="tab-error-dot"></span>'); }
            }
        });
        var input = serverError.querySelector('input:not([type=hidden]),select,textarea');
        if (input) { revealPanel(input); }
        setTimeout(function () { serverError.scrollIntoView({ block: 'center' }); }, 50);
    }

    /* ---------------- แสดงรหัสผ่าน ---------------- */
    $$('[data-reveal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-reveal'));
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.setAttribute('aria-label', input.type === 'password' ? 'แสดงรหัสผ่าน' : 'ซ่อนรหัสผ่าน');
        });
    });

    /* ---------------- อัปโหลด + preview ---------------- */
    $$('[data-dropzone]').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        var img = zone.querySelector('.dropzone-preview img');
        var name = zone.querySelector('.dropzone-name');
        var initialSrc = img ? img.getAttribute('src') : '';
        var maxKb = parseInt(zone.getAttribute('data-max-kb') || '0', 10);
        var isImage = zone.getAttribute('data-kind') === 'image';

        function reset() {
            input.value = '';
            if (initialSrc) {
                img.src = initialSrc; img.hidden = false; name.textContent = 'รูปปัจจุบัน';
            } else if (name.textContent && !isImage && zone.dataset.initialName) {
                name.textContent = zone.dataset.initialName;
            } else {
                zone.classList.remove('has-file');
            }
        }
        zone.dataset.initialName = name ? name.textContent : '';
        function chosen() {
            var file = input.files && input.files[0];
            input.setCustomValidity('');
            input.dataset.customError = '';
            if (!file) { reset(); return; }
            var error = '';
            if (maxKb && file.size > maxKb * 1024) {
                error = 'ไฟล์ใหญ่เกิน ' + mb(maxKb * 1024) + ' (ไฟล์นี้ ' + mb(file.size) + ') กรุณาลดขนาดไฟล์ก่อนอัปโหลด';
            } else if (isImage && !/^image\/(jpeg|png|webp)$/.test(file.type)) {
                error = 'รองรับเฉพาะรูป JPG, PNG หรือ WebP เท่านั้น';
            }
            if (error) {
                input.value = '';
                input.dataset.rejected = error; // จำไว้ ตอนกดบันทึกจะแสดงเหตุผลนี้แทน "กรุณาเลือกไฟล์"
                showError(input, error);
                reset();
                return;
            }
            input.dataset.rejected = '';
            showError(input, '');
            zone.classList.add('has-file');
            name.textContent = file.name + ' (' + (file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB') + ')';
            if (isImage && img) {
                var reader = new FileReader();
                reader.onload = function () { img.src = reader.result; img.hidden = false; };
                reader.readAsDataURL(file);
            }
        }
        input.addEventListener('change', chosen);
        ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-dragover'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-dragover'); }); });
        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length && !zone.classList.contains('has-file')) { return; }
            e.preventDefault();
            if (e.dataTransfer && e.dataTransfer.files.length) {
                try { input.files = e.dataTransfer.files; chosen(); } catch (err) {}
            }
        });
        var change = zone.querySelector('[data-dropzone-change]');
        if (change) { change.addEventListener('click', function () { input.click(); }); }
    });

    /* ---------------- ปฏิทิน ---------------- */
    if (window.flatpickr) {
        $$('input[data-date]').forEach(function (input) {
            var fp = window.flatpickr(input, {
                locale: window.th && window.th.th ? window.th.th : 'default',
                dateFormat: 'Y-m-d', altInput: true, altInputClass: 'input date-alt', altFormat: 'd/m/Y', allowInput: false, disableMobile: true,
                onChange: function () { input.dispatchEvent(new Event('change', { bubbles: true })); }
            });
            var alt = fp.altInput;
            if (alt) {
                alt.removeAttribute('required');
                alt.setAttribute('placeholder', 'วว/ดด/ปปปป');
                alt.setAttribute('aria-label', labelOf(input));
            }
            input.addEventListener('focus', function () { fp.open(); });
            input._fp = fp;
        });
        // วันสิ้นสุดเลือกก่อนวันเริ่มไม่ได้
        $$('input[data-after]').forEach(function (end) {
            var start = document.getElementById(end.dataset.after);
            if (!start || !start._fp || !end._fp) { return; }
            var sync = function () { end._fp.set('minDate', start.value || null); };
            start.addEventListener('change', sync);
            sync();
        });
    }

    /* ---------------- แท็บ ---------------- */
    $$('[data-tab-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var list = btn.closest('.tab-list');
            $$('[data-tab-target]', list).forEach(function (b) {
                var active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
                var panel = document.getElementById(b.getAttribute('data-tab-target'));
                if (panel) { panel.hidden = !active; }
            });
        });
    });

    /* ---------------- ส่วนที่ย่อ/ขยายได้ ---------------- */
    $$('[data-collapse]').forEach(function (head) {
        head.addEventListener('click', function (e) {
            if (e.target.closest('a,.card-actions')) { return; }
            head.closest('.section').classList.toggle('is-collapsed');
        });
    });

    /* ---------------- สวิตช์เปิด/ปิดในตาราง ---------------- */
    $$('input[data-toggle-url]').forEach(function (input) {
        input.addEventListener('change', function () {
            var value = input.checked;
            input.disabled = true;
            post(input.getAttribute('data-toggle-url'), { value: value ? 1 : 0 }).then(function (json) {
                toast({ type: 'success', title: json.message || 'บันทึกแล้ว' });
                if (json.badge) {
                    var cell = input.closest('tr').querySelector('[data-status-cell]');
                    if (cell) { cell.innerHTML = json.badge; }
                }
            }).catch(function (err) {
                input.checked = !value;
                toast({ type: 'danger', title: err.message });
            }).then(function () { input.disabled = false; });
        });
    });

    /* ---------------- ลากเรียงลำดับในตาราง ---------------- */
    $$('[data-reorder]').forEach(function (card) {
        var tbody = card.querySelector('tbody[data-sortable]');
        var btn = card.querySelector('[data-reorder-toggle]');
        if (!tbody || !btn || !window.Sortable) { return; }
        var sortable = null;
        btn.addEventListener('click', function () {
            var on = !card.classList.contains('is-reordering');
            card.classList.toggle('is-reordering', on);
            $$('.handle', card).forEach(function (h) { h.hidden = !on; });
            $$('[data-reorder-hint]', card).forEach(function (h) { h.hidden = !on; });
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) {
                sortable = window.Sortable.create(tbody, {
                    handle: '.handle', animation: 150, ghostClass: 'sortable-ghost',
                    onEnd: function () {
                        var ids = $$('tr[data-id]', tbody).map(function (tr) { return tr.getAttribute('data-id'); });
                        post(card.getAttribute('data-reorder'), { ids: ids }).then(function (json) {
                            toast({ type: 'success', title: json.message || 'บันทึกลำดับแล้ว' });
                        }).catch(function (err) { toast({ type: 'danger', title: err.message }); });
                    }
                });
            } else if (sortable) { sortable.destroy(); sortable = null; }
        });
    });

    /* ---------------- รายการลากเรียง/เพิ่ม/ลบในฟอร์ม (แกลเลอรี, ไฟล์แนบ) ---------------- */
    $$('[data-repeater]').forEach(function (rep) {
        var list = rep.querySelector('[data-repeater-list]');
        var tpl = rep.querySelector('template[data-repeater-template]');
        var addBtn = rep.querySelector('[data-repeater-add]');
        var counter = 0;
        var renumber = function () {
            $$('[data-repeater-item]', list).forEach(function (item, i) {
                var order = item.querySelector('[data-sort-input]');
                if (order) { order.value = i + 1; }
            });
            var empty = rep.querySelector('[data-repeater-empty]');
            if (empty) { empty.hidden = $$('[data-repeater-item]', list).length > 0; }
        };
        if (window.Sortable) { window.Sortable.create(list, { handle: '.handle', animation: 150, onEnd: renumber }); }
        rep.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-repeater-remove]');
            if (!rm) { return; }
            var item = rm.closest('[data-repeater-item]');
            var flag = item.querySelector('[data-delete-input]');
            if (flag) {
                var removed = flag.value !== '1';
                flag.value = removed ? '1' : '0';
                item.classList.toggle('is-removed', removed);
                rm.textContent = removed ? 'ยกเลิกการลบ' : 'ลบ';
                $$('input:not([type=hidden]),textarea', item).forEach(function (i) { i.disabled = removed; });
            } else {
                item.parentNode.removeChild(item);
                renumber();
            }
        });
        if (addBtn && tpl) {
            addBtn.addEventListener('click', function () {
                var html = tpl.innerHTML.replace(/__INDEX__/g, String(Date.now()) + (counter++));
                list.insertAdjacentHTML('beforeend', html);
                var item = list.lastElementChild;
                renumber();
                var first = item.querySelector('input[type=file], input:not([type=hidden])');
                if (first && first.type === 'file') { first.click(); } else if (first) { first.focus(); }
                $$('input[type=file]', item).forEach(function (fileInput) {
                    fileInput.addEventListener('change', function () {
                        var err = fileError(fileInput);
                        showError(fileInput, err);
                        if (err) { fileInput.value = ''; }
                        var label = item.querySelector('[data-file-name]');
                        var f = fileInput.files && fileInput.files[0];
                        if (label) { label.textContent = f ? f.name : ''; }
                        var titleInput = item.querySelector('[data-default-title]');
                        if (f && titleInput && !titleInput.value) { titleInput.value = f.name.replace(/\.[^.]+$/, ''); }
                        var preview = item.querySelector('img[data-preview]');
                        if (f && preview) { var r = new FileReader(); r.onload = function () { preview.src = r.result; preview.hidden = false; }; r.readAsDataURL(f); }
                    });
                });
            });
        }
        renumber();
    });

    /* ---------------- แสดงช่องตามค่าที่เลือก เช่น data-show-when="link_type=url" ---------------- */
    $$('[data-show-when]').forEach(function (box) {
        var parts = box.getAttribute('data-show-when').split('=');
        var name = parts[0], values = parts[1].split('|');
        var form = box.closest('form') || document;
        var inputs = $$('[name="' + name + '"]', form);
        var update = function () {
            var current = '';
            inputs.forEach(function (i) {
                if (i.type === 'radio') { if (i.checked) { current = i.value; } }
                else if (i.type === 'checkbox') { current = i.checked ? '1' : '0'; }
                else if (i.type !== 'hidden') { current = i.value; }
            });
            var show = values.indexOf(current) !== -1;
            box.hidden = !show;
            $$('input,select,textarea', box).forEach(function (i) { i.disabled = !show; });
        };
        inputs.forEach(function (i) { i.addEventListener('change', update); });
        update();
    });

    /* ---------------- เลือกทั้งหมด / ไม่เลือก ---------------- */
    $$('[data-check-all]').forEach(function (btn) {
        var form = btn.closest('form') || document;
        var boxes = function () { return $$('input[type=checkbox][name="' + btn.getAttribute('data-check-all') + '"]', form); };
        var label = function () { btn.textContent = boxes().every(function (b) { return b.checked; }) ? 'ไม่เลือกทั้งหมด' : 'เลือกทั้งหมด'; };
        btn.addEventListener('click', function () {
            var all = boxes().every(function (b) { return b.checked; });
            boxes().forEach(function (b) { b.checked = !all; });
            label();
        });
        boxes().forEach(function (b) { b.addEventListener('change', label); });
        label();
    });

    /* ---------------- แก้อีเมลของตัวเอง ต้องยืนยันรหัสผ่าน ---------------- */
    $$('[data-self-email]').forEach(function (box) {
        var form = box.closest('form');
        var email = form.querySelector('input[name="email"]');
        var pass = box.querySelector('input');
        var update = function () {
            var changed = email.value.trim().toLowerCase() !== box.getAttribute('data-self-email');
            box.hidden = !changed;
            pass.disabled = !changed;
        };
        email.addEventListener('input', update);
        update();
    });

    /* ---------------- ตัวกรอง/จำนวนต่อหน้า ส่งฟอร์มทันที ---------------- */
    $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

    /* ---------------- ค้นหาทั้งระบบ ---------------- */
    var search = $('[data-global-search]');
    var searchToggle = $('[data-search-toggle]');
    if (search && searchToggle) {
        searchToggle.addEventListener('click', function () {
            var bar = searchToggle.closest('.topbar');
            bar.classList.toggle('search-open');
            if (bar.classList.contains('search-open')) { search.querySelector('input').focus(); }
        });
    }
    if (search) {
        var sInput = search.querySelector('input');
        var sBox = search.querySelector('.global-search-results');
        var timer = null, seq = 0;
        sInput.addEventListener('input', function () {
            clearTimeout(timer);
            var q = sInput.value.trim();
            if (q.length < 2) { sBox.hidden = true; return; }
            timer = setTimeout(function () {
                var my = ++seq;
                fetch(sInput.dataset.url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        if (my !== seq) { return; }
                        var html = '';
                        (json.groups || []).forEach(function (g) {
                            html += '<h3>' + esc(g.label) + '</h3>';
                            g.items.forEach(function (it) { html += '<a href="' + esc(it.url) + '">' + esc(it.title) + (it.sub ? '<small>' + esc(it.sub) + '</small>' : '') + '</a>'; });
                        });
                        sBox.innerHTML = html || '<div class="empty">ไม่พบข้อมูลที่ตรงกับ "' + esc(q) + '"</div>';
                        sBox.hidden = false;
                    }).catch(function () {});
            }, 250);
        });
        document.addEventListener('click', function (e) {
            if (!search.contains(e.target)) {
                sBox.hidden = true;
                if (!e.target.closest('[data-search-toggle]')) { search.closest('.topbar').classList.remove('search-open'); }
            }
        });
        sInput.addEventListener('keydown', function (e) { if (e.key === 'Escape') { sBox.hidden = true; sInput.blur(); } });
    }

    /* ---------------- ตัวแก้ไขข้อความ (Quill) ---------------- */
    if (window.Quill) {
        var Quill = window.Quill;
        var BlockEmbed = Quill.import('blots/block/embed');
        var Divider = function () { return Reflect.construct(BlockEmbed, arguments, Divider); };
        Divider.prototype = Object.create(BlockEmbed.prototype);
        Divider.prototype.constructor = Divider;
        Object.setPrototypeOf(Divider, BlockEmbed);
        Divider.blotName = 'divider';
        Divider.tagName = 'hr';
        Quill.register(Divider);

        $$('textarea[data-rich]').forEach(function (area) {
            var wrap = document.createElement('div');
            wrap.className = 'rich';
            var editorEl = document.createElement('div');
            area.parentNode.insertBefore(wrap, area);
            wrap.appendChild(editorEl);
            area.hidden = true;
            area.removeAttribute('required');
            var quill = new Quill(editorEl, {
                theme: 'snow',
                formats: ['bold', 'italic', 'underline', 'strike', 'script', 'link', 'header', 'blockquote', 'list', 'divider', 'table', 'code-block'],
                modules: {
                    table: true,
                    toolbar: {
                        container: [
                            ['bold', 'italic', 'underline', 'strike'], [{ script: 'sub' }, { script: 'super' }], ['link'],
                            [{ header: 2 }, { header: 3 }], ['blockquote', { list: 'bullet' }, { list: 'ordered' }], ['hr'],
                            ['table-insert', 'table-row', 'table-col', 'table-delete'], ['undo', 'redo'], ['clean']
                        ],
                        handlers: {
                            hr: function () {
                                var range = this.quill.getSelection(true);
                                this.quill.insertEmbed(range.index, 'divider', true, 'user');
                                this.quill.setSelection(range.index + 1, 'silent');
                            },
                            'table-insert': function () { this.quill.getModule('table').insertTable(2, 2); },
                            'table-row': function () { this.quill.getModule('table').insertRowBelow(); },
                            'table-col': function () { this.quill.getModule('table').insertColumnRight(); },
                            'table-delete': function () { this.quill.getModule('table').deleteTable(); },
                            undo: function () { this.quill.history.undo(); },
                            redo: function () { this.quill.history.redo(); }
                        }
                    },
                    clipboard: { matchers: [['IMG', function (node, delta) { wrap.dataset.droppedImage = '1'; return new (Quill.import('delta'))(); }]] }
                }
            });
            var titles = { bold: 'ตัวหนา', italic: 'ตัวเอียง', underline: 'ขีดเส้นใต้', strike: 'ขีดฆ่า', link: 'ลิงก์', blockquote: 'ข้อความอ้างอิง', clean: 'ล้างรูปแบบ', hr: 'เส้นคั่น', undo: 'ย้อนกลับ', redo: 'ทำซ้ำ' };
            $$('.ql-toolbar button', wrap).forEach(function (b) {
                var key = (b.className.match(/ql-([\w-]+)/) || [])[1];
                if (titles[key]) { b.title = titles[key]; }
                if (key === 'undo') { b.innerHTML = '↶'; }
                if (key === 'redo') { b.innerHTML = '↷'; }
            });
            var tip = wrap.querySelector('.ql-tooltip input[type=text]');
            if (tip) { tip.setAttribute('data-link', 'https://'); }
            quill.root.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    e.preventDefault();
                    toast({ type: 'warning', title: 'เพิ่มรูปในเนื้อหาไม่ได้', text: 'กรุณาเพิ่มรูปในส่วน "แกลเลอรีรูป" ด้านล่าง' });
                }
            }, true);
            quill.root.addEventListener('paste', function (e) {
                var items = e.clipboardData && e.clipboardData.files;
                if (items && items.length) {
                    e.preventDefault();
                    toast({ type: 'warning', title: 'เพิ่มรูปในเนื้อหาไม่ได้', text: 'กรุณาเพิ่มรูปในส่วน "แกลเลอรีรูป" ด้านล่าง' });
                }
            }, true);
            if (area.value.trim() !== '') { quill.clipboard.dangerouslyPasteHTML(area.value, 'silent'); }
            var sync = function () {
                var text = quill.getText().trim();
                area.value = text === '' && !quill.root.querySelector('hr,table') ? '' : quill.getSemanticHTML();
            };
            quill.on('text-change', function () {
                sync();
                if (area.dataset.required === '1') { wrap.closest('.field').classList.remove('has-error'); var er = wrap.closest('.field').querySelector('.field-error'); if (er) { er.hidden = true; } }
            });
            var form = area.form;
            if (form) {
                form.addEventListener('emcc:before-validate', function () {
                    sync();
                    if (area.dataset.required === '1' && area.value === '') {
                        var field = wrap.closest('.field');
                        field.classList.add('has-error');
                        var er = field.querySelector('.field-error');
                        er.textContent = 'กรุณากรอก' + labelOf(area);
                        er.hidden = false;
                        area.dataset.richInvalid = '1';
                    } else {
                        area.dataset.richInvalid = '';
                    }
                });
            }
            area._quill = quill;
        });
    }
})();

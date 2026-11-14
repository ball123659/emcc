<?php

/**
 * ตัวช่วยแสดงผลหลังบ้าน: ไอคอน วันที่ ป้าย และช่องฟอร์ม (label + คำแนะนำ + ข้อความผิดพลาดใต้ช่อง)
 * ทุกค่าที่แสดงผ่าน esc() เสมอ
 */

use App\Libraries\Storage;
use Config\Emcc;

if (! function_exists('icon')) {
    function icon(string $name, string $class = 'icon'): string
    {
        static $icons = null;
        $icons ??= require APPPATH . 'Libraries/icons.php';

        $paths = $icons[$name] ?? $icons['information-circle'];

        return '<svg class="' . esc($class, 'attr') . '" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">' . $paths . '</svg>';
    }
}

if (! function_exists('th_date')) {
    /** วันที่แบบ ค.ศ. d/m/Y ('-' ถ้าว่าง) */
    function th_date(?string $value, string $empty = '-'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return $empty;
        }

        return date(config(Emcc::class)->dateFormat, strtotime($value));
    }
}

if (! function_exists('th_datetime')) {
    function th_datetime(?string $value, string $empty = '-'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return $empty;
        }

        return date(config(Emcc::class)->datetimeFormat, strtotime($value));
    }
}

if (! function_exists('time_ago')) {
    /** เวลาแบบสัมพัทธ์ เช่น "5 นาทีที่แล้ว" */
    function time_ago(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        $seconds = time() - strtotime($value);

        return match (true) {
            $seconds < 60       => 'เมื่อสักครู่',
            $seconds < 3600     => floor($seconds / 60) . ' นาทีที่แล้ว',
            $seconds < 86400    => floor($seconds / 3600) . ' ชั่วโมงที่แล้ว',
            $seconds < 86400 * 30 => floor($seconds / 86400) . ' วันที่แล้ว',
            default             => th_date($value),
        };
    }
}

if (! function_exists('thai_weekday')) {
    function thai_weekday(?int $timestamp = null): string
    {
        return ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][(int) date('w', $timestamp ?? time())];
    }
}

if (! function_exists('badge')) {
    /** ป้ายสี: success / danger / warning / info / gray / primary */
    function badge(string $label, string $color = 'gray'): string
    {
        return '<span class="badge badge-' . esc($color, 'attr') . '">' . esc($label) . '</span>';
    }
}

if (! function_exists('human_size')) {
    function human_size(?int $bytes): string
    {
        if ($bytes === null) {
            return '-';
        }

        return match (true) {
            $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1) . ' MB',
            $bytes >= 1024      => number_format($bytes / 1024) . ' KB',
            default             => $bytes . ' B',
        };
    }
}

if (! function_exists('media_url')) {
    /** URL รูป (เก็บนอก public ส่งผ่าน controller) */
    function media_url(?string $path): ?string
    {
        if ($path === null || $path === '' || ! Storage::exists($path)) {
            return null;
        }

        return site_url('media/' . $path);
    }
}

if (! function_exists('form_errors')) {
    /** ข้อความผิดพลาดของฟอร์ม (คีย์แบบจุด เช่น attachments.0.title_th) */
    function form_errors(): array
    {
        $errors = session('errors');

        return is_array($errors) ? $errors : [];
    }
}

if (! function_exists('field_error')) {
    function field_error(string $name): ?string
    {
        $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');

        return form_errors()[$key] ?? null;
    }
}

if (! function_exists('field_value')) {
    /** ค่าที่ผู้ใช้กรอกไว้ (หลังบันทึกไม่ผ่าน) หรือค่าจากฐานข้อมูล */
    function field_value(string $name, mixed $default = null): mixed
    {
        $old = old($name);

        return $old ?? $default;
    }
}

if (! function_exists('field_id')) {
    function field_id(string $name): string
    {
        return 'f-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
    }
}

if (! function_exists('field_wrap')) {
    /** label + ช่อง + คำแนะนำ + ข้อความผิดพลาด */
    function field_wrap(string $name, ?string $label, string $control, array $o = []): string
    {
        $id       = $o['id'] ?? field_id($name);
        $error    = field_error($name);
        $required = ! empty($o['required']);
        $html     = '<div class="field' . ($error ? ' has-error' : '') . (isset($o['class']) ? ' ' . esc($o['class'], 'attr') : '') . '" data-field="' . esc($name, 'attr') . '">';

        if ($label !== null) {
            $html .= '<label class="field-label" for="' . esc($id, 'attr') . '">' . esc($label) . ($required ? '<sup class="req">*</sup>' : '') . '</label>';
        }

        $html .= $control;

        if (! empty($o['hint'])) {
            $html .= '<p class="field-hint">' . esc($o['hint']) . '</p>';
        }

        $html .= '<p class="field-error" role="alert"' . ($error ? '' : ' hidden') . '>' . esc((string) $error) . '</p>';

        return $html . '</div>';
    }
}

if (! function_exists('attrs')) {
    function attrs(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $html .= $value === true ? ' ' . esc($key, 'attr') : ' ' . esc($key, 'attr') . '="' . esc((string) $value, 'attr') . '"';
        }

        return $html;
    }
}

if (! function_exists('text_field')) {
    /**
     * ช่องข้อความ — $o: label, value, required, type, maxlength, minlength, hint, placeholder, prefix,
     * autocomplete, pattern, pattern_message, disabled, readonly, attrs
     */
    function text_field(string $name, array $o = []): string
    {
        $id    = $o['id'] ?? field_id($name);
        $value = array_key_exists('old', $o) && $o['old'] === false ? ($o['value'] ?? '') : field_value($name, $o['value'] ?? '');
        $input = '<input' . attrs([
            'type'                 => $o['type'] ?? 'text',
            'name'                 => $name,
            'id'                   => $id,
            'value'                => ($o['type'] ?? '') === 'password' ? null : (string) $value,
            'class'                => 'input',
            'required'             => ! empty($o['required']),
            'maxlength'            => $o['maxlength'] ?? null,
            'minlength'            => $o['minlength'] ?? null,
            'placeholder'          => $o['placeholder'] ?? null,
            'autocomplete'         => $o['autocomplete'] ?? null,
            'pattern'              => $o['pattern'] ?? null,
            'data-pattern-message' => $o['pattern_message'] ?? null,
            'disabled'             => ! empty($o['disabled']),
            'readonly'             => ! empty($o['readonly']),
            'inputmode'            => $o['inputmode'] ?? null,
            'aria-invalid'         => field_error($name) ? 'true' : null,
        ] + ($o['attrs'] ?? [])) . '>';

        if (($o['type'] ?? '') === 'password') {
            $input = '<div class="input-group">' . $input . '<button type="button" class="input-addon-btn" data-reveal="' . esc($id, 'attr') . '" aria-label="แสดงรหัสผ่าน">' . icon('eye') . '</button></div>';
        } elseif (isset($o['prefix'])) {
            $input = '<div class="input-group"><span class="input-prefix">' . esc($o['prefix']) . '</span>' . $input . '</div>';
        }

        return field_wrap($name, $o['label'] ?? null, $input, $o + ['id' => $id]);
    }
}

if (! function_exists('textarea_field')) {
    function textarea_field(string $name, array $o = []): string
    {
        $id    = $o['id'] ?? field_id($name);
        $value = field_value($name, $o['value'] ?? '');
        $input = '<textarea' . attrs([
            'name'         => $name,
            'id'           => $id,
            'class'        => 'input',
            'rows'         => $o['rows'] ?? 3,
            'required'     => ! empty($o['required']),
            'maxlength'    => $o['maxlength'] ?? null,
            'placeholder'  => $o['placeholder'] ?? null,
            'aria-invalid' => field_error($name) ? 'true' : null,
        ] + ($o['attrs'] ?? [])) . '>' . esc((string) $value) . '</textarea>';

        return field_wrap($name, $o['label'] ?? null, $input, $o + ['id' => $id]);
    }
}

if (! function_exists('select_field')) {
    /** $o: options [value => label], placeholder, value, required */
    function select_field(string $name, array $o = []): string
    {
        $id      = $o['id'] ?? field_id($name);
        $current = (string) field_value($name, $o['value'] ?? '');
        $html    = '<select' . attrs([
            'name'         => $name,
            'id'           => $id,
            'class'        => 'input select',
            'required'     => ! empty($o['required']),
            'disabled'     => ! empty($o['disabled']),
            'aria-invalid' => field_error($name) ? 'true' : null,
        ] + ($o['attrs'] ?? [])) . '>';

        if (array_key_exists('placeholder', $o)) {
            $html .= '<option value="">' . esc($o['placeholder'] ?? 'เลือก') . '</option>';
        }

        foreach ($o['options'] ?? [] as $value => $label) {
            $html .= '<option value="' . esc((string) $value, 'attr') . '"' . ((string) $value === $current ? ' selected' : '') . '>' . esc($label) . '</option>';
        }

        return field_wrap($name, $o['label'] ?? null, $html . '</select>', $o + ['id' => $id]);
    }
}

if (! function_exists('toggle_field')) {
    /** สวิตช์เปิด/ปิด (ส่งค่า 0 เสมอถ้าไม่ได้เปิด) */
    function toggle_field(string $name, array $o = []): string
    {
        $id      = $o['id'] ?? field_id($name);
        $checked = (string) field_value($name, ! empty($o['value']) ? '1' : '0') === '1';
        $html    = '<div class="field field-toggle' . (field_error($name) ? ' has-error' : '') . '" data-field="' . esc($name, 'attr') . '">'
            . '<label class="toggle-row" for="' . esc($id, 'attr') . '">'
            . '<input type="hidden" name="' . esc($name, 'attr') . '" value="0">'
            . '<input type="checkbox" class="toggle" role="switch" name="' . esc($name, 'attr') . '" id="' . esc($id, 'attr') . '" value="1"' . ($checked ? ' checked' : '') . (! empty($o['disabled']) ? ' disabled' : '') . attrs($o['attrs'] ?? []) . '>'
            . '<span class="toggle-label">' . esc($o['label'] ?? '') . '</span></label>';

        if (! empty($o['hint'])) {
            $html .= '<p class="field-hint">' . esc($o['hint']) . '</p>';
        }

        return $html . '<p class="field-error" role="alert"' . (field_error($name) ? '' : ' hidden') . '>' . esc((string) field_error($name)) . '</p></div>';
    }
}

if (! function_exists('date_field')) {
    /** เลือกวันจากปฏิทิน (ส่งค่า Y-m-d, แสดง d/m/Y) — $o['after'] = ชื่อช่องวันเริ่ม (วันสิ้นสุดต้องไม่ก่อน) */
    function date_field(string $name, array $o = []): string
    {
        $id    = $o['id'] ?? field_id($name);
        $value = (string) field_value($name, $o['value'] ?? '');
        $value = $value !== '' ? date('Y-m-d', strtotime($value)) : '';
        $input = '<input' . attrs([
            'type'         => 'date',
            'name'         => $name,
            'id'           => $id,
            'value'        => $value,
            'class'        => 'input',
            'data-date'    => true,
            'data-after'   => isset($o['after']) ? field_id($o['after']) : null,
            'required'     => ! empty($o['required']),
            'aria-invalid' => field_error($name) ? 'true' : null,
        ]) . '>';

        return field_wrap($name, $o['label'] ?? null, $input, $o + ['id' => $id]);
    }
}

if (! function_exists('radio_field')) {
    function radio_field(string $name, array $o = []): string
    {
        $current = (string) field_value($name, $o['value'] ?? '');
        $html    = '<div class="radio-row" role="radiogroup">';

        foreach ($o['options'] ?? [] as $value => $label) {
            $id = field_id($name . '-' . $value);
            $html .= '<label class="radio" for="' . esc($id, 'attr') . '"><input type="radio" name="' . esc($name, 'attr') . '" id="' . esc($id, 'attr') . '" value="' . esc((string) $value, 'attr') . '"'
                . ((string) $value === $current ? ' checked' : '') . (! empty($o['required']) ? ' required' : '') . '><span>' . esc($label) . '</span></label>';
        }

        return field_wrap($name, $o['label'] ?? null, $html . '</div>', $o + ['id' => field_id($name)]);
    }
}

if (! function_exists('image_field')) {
    /**
     * อัปโหลดรูป + preview ก่อนบันทึก — $o: current (path เดิม), required, hint, removable (ช่องติ๊กลบรูปเดิม)
     */
    function image_field(string $name, array $o = []): string
    {
        $config  = config(Emcc::class);
        $id      = $o['id'] ?? field_id($name);
        $current = media_url($o['current'] ?? null);
        $maxKb   = upload_limit_kb($config->imageMaxKb);
        $hint    = trim(($o['hint'] ?? '') . ' · รองรับ JPG, PNG, WebP ไม่เกิน ' . mb_label($maxKb) . ' · ระบบย่อและแปลงเป็น WebP ให้อัตโนมัติ', ' ·');

        $html = '<div class="dropzone' . ($current ? ' has-file' : '') . '" data-dropzone data-max-kb="' . $maxKb . '" data-kind="image">'
            . '<input' . attrs([
                'type'          => 'file',
                'name'          => $name,
                'id'            => $id,
                'accept'        => 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
                'required'      => ! empty($o['required']) && ! $current,
                'data-required' => ! empty($o['required']) ? '1' : null,
                'aria-invalid'  => field_error($name) ? 'true' : null,
            ]) . '>'
            . '<div class="dropzone-empty">' . icon('arrow-up-tray', 'icon dz-icon') . '<span>ลากรูปมาวางที่นี่ หรือ <u>คลิกเพื่อเลือกไฟล์</u></span></div>'
            . '<div class="dropzone-preview">'
            . '<img alt="" src="' . esc((string) $current, 'attr') . '"' . ($current ? '' : ' hidden') . '>'
            . '<div class="dropzone-meta"><span class="dropzone-name">' . ($current ? 'รูปปัจจุบัน' : '') . '</span>'
            . '<button type="button" class="link-btn" data-dropzone-change>เปลี่ยนรูป</button></div>'
            . '</div></div>';

        if ($current && ! empty($o['removable'])) {
            $html .= '<label class="check-inline"><input type="checkbox" name="remove_' . esc($name, 'attr') . '" value="1"> ลบรูปนี้</label>';
        }

        return field_wrap($name, $o['label'] ?? null, $html, ['hint' => $hint] + $o + ['id' => $id]);
    }
}

if (! function_exists('file_field')) {
    /** อัปโหลดไฟล์เอกสาร — $o: current (ชื่อไฟล์เดิม), accept, max_kb */
    function file_field(string $name, array $o = []): string
    {
        $id   = $o['id'] ?? field_id($name);
        $html = '<div class="dropzone dropzone-file' . (! empty($o['current']) ? ' has-file' : '') . '" data-dropzone data-max-kb="' . upload_limit_kb((int) ($o['max_kb'] ?? 20480)) . '" data-kind="file">'
            . '<input' . attrs([
                'type'         => 'file',
                'name'         => $name,
                'id'           => $id,
                'accept'       => $o['accept'] ?? null,
                'required'     => ! empty($o['required']) && empty($o['current']),
                'aria-invalid' => field_error($name) ? 'true' : null,
            ] + ($o['attrs'] ?? [])) . '>'
            . '<div class="dropzone-empty">' . icon('arrow-up-tray', 'icon dz-icon') . '<span>ลากไฟล์มาวางที่นี่ หรือ <u>คลิกเพื่อเลือกไฟล์</u></span></div>'
            . '<div class="dropzone-preview">' . icon('document-text', 'icon dz-file-icon')
            . '<div class="dropzone-meta"><span class="dropzone-name">' . esc((string) ($o['current'] ?? '')) . '</span>'
            . '<button type="button" class="link-btn" data-dropzone-change>เปลี่ยนไฟล์</button></div></div></div>';

        return field_wrap($name, $o['label'] ?? null, $html, $o + ['id' => $id]);
    }
}

if (! function_exists('section_open')) {
    function section_open(string $title, ?string $description = null, array $o = []): string
    {
        $collapsible = ! empty($o['collapsible']);

        return '<section class="card section' . ($collapsible ? ' is-collapsible' : '') . '"' . (isset($o['id']) ? ' id="' . esc($o['id'], 'attr') . '"' : '') . '>'
            . '<header class="card-header"' . ($collapsible ? ' data-collapse' : '') . '><div><h2 class="card-title">' . esc($title) . '</h2>'
            . ($description ? '<p class="card-description">' . esc($description) . '</p>' : '') . '</div>'
            . ($collapsible ? '<button type="button" class="icon-btn" aria-label="ย่อ/ขยาย">' . icon('chevron-up') . '</button>' : '')
            . (isset($o['actions']) ? '<div class="card-actions">' . $o['actions'] . '</div>' : '')
            . '</header><div class="card-body' . (isset($o['grid']) ? ' grid-' . (int) $o['grid'] : '') . '">';
    }
}

if (! function_exists('section_close')) {
    function section_close(): string
    {
        return '</div></section>';
    }
}

if (! function_exists('list_url')) {
    /** ลิงก์หน้ารายการพร้อม query เดิม (แทนค่าบางตัวได้, null = ลบออก) */
    function list_url(string $base, array $query, array $overrides = []): string
    {
        $query = array_filter(array_merge($query, $overrides), static fn ($v) => $v !== null && $v !== '');

        return $base . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

if (! function_exists('sort_link')) {
    /** หัวตารางที่กดเรียงได้ */
    function sort_link(string $label, string $column, string $base, array $query, string $current, string $direction): string
    {
        $active = $current === $column;
        $next   = $active && $direction === 'asc' ? 'desc' : 'asc';
        $icon   = $active ? icon($direction === 'asc' ? 'chevron-up' : 'chevron-down') : '';

        return '<a href="' . esc(list_url($base, $query, ['sort' => $column, 'dir' => $next, 'page' => null]), 'attr') . '">' . esc($label) . $icon . '</a>';
    }
}

if (! function_exists('partial')) {
    /** แสดง view ย่อยโดยไม่เก็บตัวแปรไว้ใช้ต่อ (view() ปกติของ CodeIgniter จำตัวแปรข้ามการเรียก) */
    function partial(string $name, array $data = []): string
    {
        return view($name, $data, ['saveData' => false]);
    }
}

if (! function_exists('ini_bytes')) {
    /** แปลงค่าใน php.ini เช่น 20M เป็นไบต์ (-1 = ไม่จำกัด) */
    function ini_bytes(string $value): int
    {
        return \App\Libraries\Uploads\ImageProcessor::iniBytes($value);
    }
}

if (! function_exists('upload_limit_kb')) {
    /** ขนาดไฟล์สูงสุดที่รับได้จริง = ค่าของระบบ หรือ upload_max_filesize ของ PHP (อันที่น้อยกว่า) */
    function upload_limit_kb(int $configKb): int
    {
        $ini = ini_bytes((string) ini_get('upload_max_filesize'));

        return $ini > 0 ? min($configKb, intdiv($ini, 1024)) : $configKb;
    }
}

if (! function_exists('mb_label')) {
    function mb_label(int $kb): string
    {
        $mb = $kb / 1024;

        return ($mb >= 1 ? rtrim(rtrim(number_format($mb, 1), '0'), '.') : number_format($mb, 2)) . ' MB';
    }
}

if (! function_exists('sparkline')) {
    /** กราฟเส้นเล็ก (SVG) ใต้ตัวเลขบนการ์ดสถิติ */
    function sparkline(array $values): string
    {
        $values = array_values(array_map('intval', $values));
        $count  = count($values);

        if ($count < 2) {
            return '';
        }

        $max    = max(1, max($values));
        $points = [];

        foreach ($values as $i => $v) {
            $points[] = round($i * 100 / ($count - 1), 2) . ',' . round(28 - $v * 24 / $max, 2);
        }

        $line = implode(' ', $points);

        return '<svg class="sparkline" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">'
            . '<polygon points="0,30 ' . $line . ' 100,30" class="sparkline-fill"/>'
            . '<polyline points="' . $line . '" class="sparkline-line"/></svg>';
    }
}

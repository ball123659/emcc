<?php
use App\Libraries\ActivityLogger;

$flatten = static function (array $data, string $prefix = '') use (&$flatten): array {
    $out = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += $v === [] ? [$key => '(ว่าง)'] : $flatten($v, $key);
        } else {
            $out[$key] = $v === null ? '(ว่าง)' : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
        }
    }
    return $out;
};
?>
<?= $this->extend('layouts/backend') ?>
<?= $this->section('header_actions') ?>
<a class="btn btn-gray" href="<?= site_url('backend/activity-logs') ?>"><?= icon('chevron-left') ?> กลับ</a>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= section_open('ข้อมูลบันทึก') ?>
    <dl class="kv">
        <dt>วันเวลา</dt><dd><?= esc(th_datetime($log['created_at'])) ?></dd>
        <dt>ผู้ใช้</dt><dd><?= esc($log['username'] ?? '-') ?></dd>
        <dt>การกระทำ</dt><dd><?= badge(ActivityLogger::ACTIONS[$log['action']] ?? $log['action'], ActivityLogger::ACTION_COLORS[$log['action']] ?? 'gray') ?></dd>
        <dt>ข้อมูล</dt><dd><?= esc($log['subject_type'] ? $log['subject_type'] . ($log['subject_id'] ? ' #' . $log['subject_id'] : '') : '-') ?></dd>
        <dt>รายละเอียด</dt><dd><?= esc($log['description'] ?? '-') ?></dd>
        <dt>IP</dt><dd><?= esc($log['ip_address'] ?? '-') ?></dd>
        <dt>เบราว์เซอร์</dt><dd class="muted"><?= esc($log['user_agent'] ?? '-') ?></dd>
    </dl>
<?= section_close() ?>
<?php if ($changes !== []): ?>
<?= section_open('การเปลี่ยนแปลง', null, ['grid' => 2]) ?>
    <?php foreach (['old' => 'ค่าเดิม', 'new' => 'ค่าใหม่'] as $key => $label): if (! isset($changes[$key])) { continue; } ?>
    <div>
        <h3 class="card-title" style="margin-bottom:.5rem"><?= $label ?></h3>
        <table class="kv-table"><tbody>
            <?php foreach ($flatten((array) $changes[$key]) as $k => $v): ?><tr><th><?= esc($k) ?></th><td><?= esc($v) ?></td></tr><?php endforeach ?>
        </tbody></table>
    </div>
    <?php endforeach ?>
<?= section_close() ?>
<?php endif ?>
<?= $this->endSection() ?>

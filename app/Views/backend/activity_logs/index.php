<?php
use App\Libraries\ActivityLogger;

$base = site_url('backend/activity-logs');
?>
<?= $this->extend('layouts/backend') ?>
<?= $this->section('content') ?>
<div class="card table-card">
    <form method="get" action="<?= esc($base, 'attr') ?>" class="table-toolbar" role="search">
        <?php if (isset($query['per_page'])): ?><input type="hidden" name="per_page" value="<?= esc($query['per_page'], 'attr') ?>"><?php endif ?>
        <div class="filters">
            <select name="action" class="input select" data-autosubmit aria-label="การกระทำ">
                <option value="">ทุกการกระทำ</option>
                <?php foreach (ActivityLogger::ACTIONS as $k => $label): ?><option value="<?= $k ?>"<?= $action === $k ? ' selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
            </select>
            <select name="user" class="input select" data-autosubmit aria-label="ผู้ใช้">
                <option value="">ผู้ใช้ทุกคน</option>
                <?php foreach ($users as $id => $name): ?><option value="<?= (int) $id ?>"<?= (int) $userId === (int) $id ? ' selected' : '' ?>><?= esc($name) ?></option><?php endforeach ?>
            </select>
            <input type="date" name="from" class="input" value="<?= esc((string) $from, 'attr') ?>" data-date aria-label="ตั้งแต่วันที่" id="f-from" style="width:9.5rem">
            <input type="date" name="until" class="input" value="<?= esc((string) $until, 'attr') ?>" data-date data-after="f-from" aria-label="ถึงวันที่" style="width:9.5rem">
            <button type="submit" class="btn btn-gray">กรอง</button>
            <?php if ($query !== [] && array_diff_key($query, ['per_page' => 1]) !== []): ?><a class="link-btn is-gray" href="<?= esc($base, 'attr') ?>">ล้างตัวกรอง</a><?php endif ?>
        </div>
        <div class="spacer"></div>
        <div class="table-search"><?= icon('magnifying-glass') ?><input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="ค้นหา" aria-label="ค้นหา"></div>
    </form>
    <?php if ($dateError): ?><p class="field-error" style="padding:.75rem 1.5rem 0"><?= esc($dateError) ?></p><?php endif ?>
    <?php if ($pager['rows'] === []): ?>
        <?= partial('partials/table_empty', ['heading' => 'ไม่พบบันทึกที่ตรงกับเงื่อนไข', 'icon' => 'clock']) ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><a href="<?= esc(list_url($base, $query, ['dir' => $dir === 'desc' ? 'asc' : 'desc']), 'attr') ?>">วันเวลา <?= icon($dir === 'desc' ? 'chevron-down' : 'chevron-up') ?></a></th><th>ผู้ใช้</th><th>การกระทำ</th><th>ข้อมูล</th><th>รายละเอียด</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pager['rows'] as $log): ?>
                <tr>
                    <td class="nowrap"><?= esc(th_datetime($log['created_at'])) ?></td>
                    <td><?= $log['username'] ? esc($log['username']) : '<span class="muted">-</span>' ?></td>
                    <td><?= badge(ActivityLogger::ACTIONS[$log['action']] ?? $log['action'], ActivityLogger::ACTION_COLORS[$log['action']] ?? 'gray') ?></td>
                    <td><?= $log['subject_type'] ? esc($log['subject_type'] . ($log['subject_id'] ? ' #' . $log['subject_id'] : '')) : '<span class="muted">-</span>' ?></td>
                    <td><?= esc(mb_strimwidth((string) $log['description'], 0, 60, '…')) ?></td>
                    <td class="cell-actions"><a class="link-btn is-gray" href="<?= site_url('backend/activity-logs/' . $log['id']) ?>"><?= icon('eye') ?> ดู</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
    <?= partial('partials/table_footer', ['pager' => $pager, 'base' => $base, 'query' => $query]) ?>
</div>
<?= $this->endSection() ?>

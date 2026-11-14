<?php
use App\Controllers\Backend\Dashboard;
use App\Libraries\ActivityLogger;
use App\Models\DisplayWindowModel;

$can = static fn (string $code) => service('auth')->canViewPage($code);
?>
<?= $this->extend('layouts/backend') ?>
<?= $this->section('content') ?>
<div class="welcome">
    <h2><?= esc($greeting) ?>, คุณ<?= esc($name) ?></h2>
    <p>วัน<?= esc(thai_weekday()) ?>ที่ <?= esc(th_date(date('Y-m-d'))) ?> · ยินดีต้อนรับสู่ระบบจัดการเว็บไซต์ EMCC</p>
    <?php if ($actions): ?>
    <div class="welcome-actions">
        <?php foreach ($actions as $a): ?>
        <a href="<?= esc($a['url'], 'attr') ?>"<?= ! empty($a['primary']) ? ' class="is-primary"' : '' ?>><?= icon($a['icon']) ?> <?= esc($a['label']) ?></a>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<?php if ($showSummary): ?>
<div class="stats">
    <div class="stat">
        <div class="stat-label"><?= icon('newspaper') ?> ข่าวที่เผยแพร่แล้ว</div>
        <div class="stat-value"><?= number_format($stats['published']) ?></div>
        <div class="stat-desc c-primary">เดือนนี้ <?= number_format($stats['publishedMonth']) ?> ข่าว</div>
    </div>
    <div class="stat">
        <div class="stat-label"><?= icon('clock') ?> ข่าวตั้งเวลา / ร่าง</div>
        <div class="stat-value"><?= number_format($stats['scheduled']) ?> / <?= number_format($stats['drafts']) ?></div>
        <div class="stat-desc">รอเผยแพร่ตามวันที่ตั้งไว้ / ยังไม่เผยแพร่</div>
    </div>
    <div class="stat">
        <div class="stat-label"><?= icon('window') ?> Popup ที่กำลังแสดง</div>
        <div class="stat-value"><?= number_format($stats['popupsShowing']) ?></div>
        <div class="stat-desc <?= $stats['popupsExpiring'] > 0 ? 'c-warning' : 'c-success' ?>"><?= $stats['popupsExpiring'] > 0 ? number_format($stats['popupsExpiring']) . ' รายการจะหมดอายุใน 7 วัน' : 'ไม่มีรายการใกล้หมดอายุ' ?></div>
    </div>
    <div class="stat">
        <div class="stat-label"><?= icon('photo') ?> สไลด์ที่กำลังแสดง</div>
        <div class="stat-value"><?= number_format($stats['slidesShowing']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label"><?= icon('arrow-down-tray') ?> ไฟล์ดาวน์โหลด</div>
        <div class="stat-value"><?= number_format($stats['downloads']) ?></div>
        <div class="stat-desc c-primary">ดาวน์โหลดรวม <?= number_format($stats['downloadTotal']) ?> ครั้ง</div>
    </div>
</div>
<?php endif ?>

<?php if ($showSummary || $showLogs): ?>
<div class="dash-grid">
    <?php if ($showSummary): ?>
    <div style="display:grid;gap:1.5rem">
        <section class="card">
            <header class="card-header"><div><h2 class="card-title">งานที่ต้องทำ</h2><p class="card-description">ระบบตรวจให้อัตโนมัติ — กดเพื่อไปจัดการ</p></div></header>
            <div class="card-body">
                <?php if ($todos === []): ?>
                <div class="empty-note">✅ ไม่มีงานค้าง ทุกอย่างเรียบร้อย</div>
                <?php else: ?>
                <div class="todo-list">
                    <?php foreach ($todos as $t): ?>
                    <a class="todo" href="<?= esc($t['url'], 'attr') ?>" style="--todo: <?= esc($t['color'], 'attr') ?>">
                        <div class="todo-body"><div class="todo-title"><?= esc($t['title']) ?></div><div class="todo-sub"><?= esc($t['sub']) ?></div></div>
                        <span class="todo-count"><?= number_format($t['count']) ?></span>
                    </a>
                    <?php endforeach ?>
                </div>
                <?php endif ?>
            </div>
        </section>

        <section class="card table-card">
            <header class="card-header"><h2 class="card-title">Popup ที่กำลังแสดง / ใกล้หมดอายุ</h2></header>
            <?php if ($activePopups === []): ?>
                <?= partial('partials/table_empty', ['heading' => 'ไม่มี Popup ที่กำลังแสดง']) ?>
            <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>ชื่อเรียก</th><th>สิ้นสุด</th><th>เหลือ</th></tr></thead>
                <tbody>
                <?php foreach ($activePopups as $p): $days = DisplayWindowModel::daysUntilExpiry($p); ?>
                    <tr>
                        <td><?php if ($can('popups')): ?><a href="<?= site_url('backend/popups/' . $p['id'] . '/edit') ?>" style="color:inherit"><?= esc($p['title']) ?></a><?php else: ?><?= esc($p['title']) ?><?php endif ?></td>
                        <td><?= esc(th_date($p['end_date'])) ?></td>
                        <td><?= $days === 0 ? badge('วันนี้', 'danger') : ($days <= 3 ? badge($days . ' วัน', 'warning') : '<span class="muted">' . $days . ' วัน</span>') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
            <?php endif ?>
        </section>

        <section class="card table-card">
            <header class="card-header"><h2 class="card-title">ข่าวยอดนิยม</h2></header>
            <?php if ($popularNews === []): ?>
                <?= partial('partials/table_empty', ['heading' => 'ยังไม่มีการเข้าชมข่าว', 'text' => 'ยอดเข้าชมจะเริ่มนับเมื่อเปิดหน้าเว็บไซต์']) ?>
            <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>ข่าว</th><th>เข้าชม</th></tr></thead>
                <tbody>
                <?php foreach ($popularNews as $n): ?>
                    <tr><td><?php if ($can('news')): ?><a href="<?= site_url('backend/news/' . $n['id'] . '/edit') ?>" style="color:inherit"><?= esc($n['title_th']) ?></a><?php else: ?><?= esc($n['title_th']) ?><?php endif ?></td><td><?= number_format((int) $n['view_count']) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
            <?php endif ?>
        </section>
    </div>
    <?php endif ?>

    <div style="display:grid;gap:1.5rem">
        <?php if ($showLogs): ?>
        <section class="card">
            <header class="card-header"><h2 class="card-title">ความเคลื่อนไหวล่าสุด</h2><a href="<?= site_url('backend/activity-logs') ?>">ดูทั้งหมด</a></header>
            <div class="card-body">
                <?php if ($logs === []): ?>
                <div class="empty-note">ยังไม่มีความเคลื่อนไหว</div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($logs as $log): ?>
                    <div class="timeline-item" style="--dot: <?= esc(Dashboard::ACTIVITY_COLORS[$log['action']] ?? '#8b84a0', 'attr') ?>">
                        <div class="timeline-time" title="<?= esc(th_datetime($log['created_at']), 'attr') ?>"><?= esc(time_ago($log['created_at'])) ?></div>
                        <div class="timeline-text">
                            <strong><?= esc($log['username'] ?? 'ไม่ทราบผู้ใช้') ?></strong>
                            <?= esc(ActivityLogger::ACTIONS[$log['action']] ?? $log['action']) ?>
                            <?php if ($log['subject_type'] && isset(Dashboard::SUBJECTS[$log['subject_type']]) && ! in_array($log['action'], ['login', 'logout', 'login_failed'], true)): ?><?= esc(Dashboard::SUBJECTS[$log['subject_type']]) ?><?php endif ?>
                            <?php if ($log['description'] && $log['action'] !== 'login'): ?><span class="muted">· <?= esc(mb_strimwidth($log['description'], 0, 60, '…')) ?></span><?php endif ?>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
                <?php endif ?>
            </div>
        </section>
        <?php endif ?>

        <?php if ($showSummary): ?>
        <section class="card table-card">
            <header class="card-header"><h2 class="card-title">ไฟล์ยอดนิยม</h2></header>
            <?php if ($popularDownloads === []): ?>
                <?= partial('partials/table_empty', ['heading' => 'ยังไม่มีการดาวน์โหลด']) ?>
            <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>#</th><th>ไฟล์</th><th>ดาวน์โหลด</th></tr></thead>
                <tbody>
                <?php foreach ($popularDownloads as $i => $d): ?>
                    <tr><td><?= $i + 1 ?></td><td><?php if ($can('downloads')): ?><a href="<?= site_url('backend/downloads/' . $d['id'] . '/edit') ?>" style="color:inherit"><?= esc($d['title_th']) ?></a><?php else: ?><?= esc($d['title_th']) ?><?php endif ?></td><td><?= number_format((int) $d['download_count']) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
            <?php endif ?>
        </section>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>

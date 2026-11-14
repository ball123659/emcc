<?php
use App\Models\UserModel;

$base = site_url('backend/users');
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<a class="btn btn-primary" href="<?= site_url('backend/users/create') ?>">เพิ่มผู้ใช้งาน</a>
<?= $this->endSection() ?>

<?= $this->section('header_extra') ?>
<?= partial('partials/list_tabs', ['tabs' => $tabs, 'active' => $tab, 'base' => $base, 'query' => $query]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card table-card">
    <form method="get" action="<?= esc($base, 'attr') ?>" class="table-toolbar" role="search">
        <?php foreach (['tab', 'per_page', 'sort', 'dir'] as $k): if (isset($query[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= esc($query[$k], 'attr') ?>"><?php endif; endforeach ?>
        <div class="filters">
            <select name="role" class="input select" data-autosubmit aria-label="บทบาท">
                <option value="">ทุกบทบาท</option>
                <?php foreach (UserModel::ROLES as $k => $label): ?><option value="<?= $k ?>"<?= $role === $k ? ' selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
            </select>
            <select name="active" class="input select" data-autosubmit aria-label="เปิดใช้งาน">
                <option value="">เปิดใช้งาน: ทั้งหมด</option>
                <option value="1"<?= $active === '1' ? ' selected' : '' ?>>เปิดใช้งาน</option>
                <option value="0"<?= $active === '0' ? ' selected' : '' ?>>ปิดใช้งาน</option>
            </select>
        </div>
        <div class="spacer"></div>
        <div class="table-search"><?= icon('magnifying-glass') ?><input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="ค้นหา" aria-label="ค้นหา"></div>
    </form>

    <?php if ($pager['rows'] === []): ?>
        <?= partial('partials/table_empty', ['heading' => $tab === 'trashed' ? 'ถังขยะว่างเปล่า' : 'ไม่พบผู้ใช้งาน', 'icon' => $tab === 'trashed' ? 'trash' : 'users']) ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>ผู้ใช้งาน</th><th>ตำแหน่ง</th><th>บทบาท</th><th>เปิดใช้งาน</th><th>สถานะบัญชี</th><th><?= sort_link('เข้าสู่ระบบล่าสุด', 'last_login_at', $base, $query, $sort, $dir) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pager['rows'] as $u):
                $trashed = ! empty($u['deleted_at']);
                $self    = (int) $u['id'] === (int) $me['id'];
                $manage  = UserModel::canManage($me, $u);
                [$statusLabel, $statusColor] = match (true) {
                    $trashed                    => ['ถูกลบ', 'danger'],
                    UserModel::isLocked($u)     => ['ถูกล็อก', 'danger'],
                    (bool) $u['must_change_password'] => ['รอเปลี่ยนรหัส', 'warning'],
                    default                     => ['ปกติ', 'success'],
                };
                $extra = [['label' => 'ดูบันทึกการใช้งาน', 'url' => site_url('backend/activity-logs?user=' . $u['id']), 'icon' => 'clock', 'link' => true]];
                if ($manage && ! $self && ! $trashed) {
                    $extra[] = ['label' => 'รีเซ็ตรหัสผ่าน', 'url' => site_url('backend/users/' . $u['id'] . '/reset-password'), 'icon' => 'key', 'confirm' => 'รหัสผ่านจะกลับเป็นค่าเริ่มต้น และผู้ใช้ต้องตั้งรหัสใหม่ตอนเข้าสู่ระบบครั้งถัดไป', 'ok' => 'รีเซ็ตรหัสผ่าน', 'variant' => 'warning'];
                    if (UserModel::isLocked($u)) {
                        $extra[] = ['label' => 'ปลดล็อก', 'url' => site_url('backend/users/' . $u['id'] . '/unlock'), 'icon' => 'lock-open', 'confirm' => 'ปลดล็อกบัญชี ' . $u['username'] . '?', 'ok' => 'ปลดล็อก', 'variant' => 'warning'];
                    }
                }
            ?>
                <tr<?= $trashed ? ' class="row-trashed"' : '' ?>>
                    <td style="width:4rem"><span class="avatar" style="cursor:default"><?= esc(mb_substr(trim((string) $u['first_name']) ?: $u['username'], 0, 1)) ?></span></td>
                    <td><?= esc(UserModel::fullName($u)) ?><?= $self ? ' ' . badge('คุณ', 'primary') : '' ?><span class="cell-sub"><?= esc($u['username']) ?> · <?= esc($u['email']) ?></span></td>
                    <td><?= esc($u['position'] ?? '') ?: '<span class="muted">-</span>' ?></td>
                    <td><?= badge(UserModel::ROLES[$u['role']] ?? $u['role'], match ($u['role']) { 'developer' => 'danger', 'administrator' => 'primary', default => 'gray' }) ?></td>
                    <td><?= $u['is_active'] ? '<span style="color:var(--success)">' . icon('check-circle') . '</span>' : '<span style="color:var(--danger)">' . icon('x-circle') . '</span>' ?></td>
                    <td><?= badge($statusLabel, $statusColor) ?></td>
                    <td class="nowrap"><?= $u['last_login_at'] ? esc(th_datetime($u['last_login_at'])) : '<span class="muted">ยังไม่เคย</span>' ?></td>
                    <td class="cell-actions">
                        <?php if ($manage && ! $trashed): ?><a class="link-btn" href="<?= site_url('backend/users/' . $u['id'] . '/edit') ?>"><?= icon('pencil-square') ?> แก้ไข</a><?php endif ?>
                        <?php if ($manage || $extra !== []): ?>
                        <?= partial('partials/row_menu', [
                            'base' => site_url('backend/users/' . $u['id']), 'trashed' => $trashed && $manage, 'label' => $u['username'],
                            'canDelete' => ! $self && $manage, 'extra' => $extra,
                        ]) ?>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
    <?= partial('partials/table_footer', ['pager' => $pager, 'base' => $base, 'query' => $query]) ?>
</div>
<?= $this->endSection() ?>

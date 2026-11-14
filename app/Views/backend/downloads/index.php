<?php
use App\Controllers\Backend\Downloads;

$base = site_url('backend/downloads');
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<a class="btn btn-primary" href="<?= site_url('backend/downloads/create') ?>">เพิ่มไฟล์ดาวน์โหลด</a>
<?= $this->endSection() ?>

<?= $this->section('header_extra') ?>
<?= partial('partials/list_tabs', ['tabs' => $tabs, 'active' => $tab, 'base' => $base, 'query' => $query]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card table-card"<?= $reorder ? ' data-reorder="' . esc(site_url('backend/downloads/reorder'), 'attr') . '"' : '' ?>>
    <form method="get" action="<?= esc($base, 'attr') ?>" class="table-toolbar" role="search">
        <?php if ($reorder): ?>
        <button type="button" class="icon-btn" data-reorder-toggle aria-pressed="false" title="ลากเพื่อจัดลำดับ" aria-label="จัดลำดับ"><?= icon('arrows-up-down') ?></button>
        <?php endif ?>
        <?php foreach (['tab', 'per_page', 'sort', 'dir'] as $k): if (isset($query[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= esc($query[$k], 'attr') ?>"><?php endif; endforeach ?>
        <div class="filters">
            <select name="category" class="input select" data-autosubmit aria-label="หมวดหมู่">
                <option value="">ทุกหมวดหมู่</option>
                <?php foreach ($categories as $id => $name): ?><option value="<?= (int) $id ?>"<?= (int) $category === (int) $id ? ' selected' : '' ?>><?= esc($name) ?></option><?php endforeach ?>
            </select>
            <select name="type" class="input select" data-autosubmit aria-label="ประเภท">
                <option value="">ทุกประเภท</option>
                <option value="file"<?= $type === 'file' ? ' selected' : '' ?>>ไฟล์</option>
                <option value="link"<?= $type === 'link' ? ' selected' : '' ?>>ลิงก์ภายนอก</option>
            </select>
        </div>
        <div class="spacer"></div>
        <div class="table-search">
            <?= icon('magnifying-glass') ?>
            <input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="ค้นหา" aria-label="ค้นหา">
        </div>
    </form>
    <?php if ($reorder): ?><div class="reorder-hint" data-reorder-hint hidden><?= icon('arrows-up-down') ?> ลากที่ไอคอน ☰ หน้าแถวเพื่อจัดลำดับ ระบบบันทึกให้ทันที</div><?php endif ?>

    <?php if ($pager['rows'] === []): ?>
        <?= partial('partials/table_empty', $q !== '' || $category || $type !== ''
            ? ['heading' => 'ไม่พบรายการที่ตรงกับเงื่อนไข', 'text' => 'ลองเปลี่ยนคำค้นหรือตัวกรอง', 'icon' => 'magnifying-glass']
            : ($tab === 'trashed' ? ['heading' => 'ถังขยะว่างเปล่า', 'icon' => 'trash'] : ['heading' => 'ยังไม่มีไฟล์ดาวน์โหลด', 'text' => 'อัปโหลดเอกสารเผยแพร่ชิ้นแรก หรือเพิ่มลิงก์ภายนอก', 'icon' => 'arrow-down-tray'])) ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($reorder): ?><th class="handle" hidden></th><?php endif ?>
                    <th>ชื่อ</th><th>ประเภท</th><th>สแกนไวรัส</th>
                    <th><?= sort_link('ดาวน์โหลด', 'download_count', $base, $query, $sort, $dir) ?></th>
                    <th><?= $tab === 'trashed' ? 'ลบเมื่อ' : 'เปิดแสดง' ?></th><th></th>
                </tr>
            </thead>
            <tbody<?= $reorder ? ' data-sortable' : '' ?>>
            <?php foreach ($pager['rows'] as $row): $trashed = ! empty($row['deleted_at']); $scan = Downloads::SCAN_LABELS[$row['scan_status'] ?? ''] ?? null; ?>
                <tr data-id="<?= (int) $row['id'] ?>"<?= $trashed ? ' class="row-trashed"' : '' ?>>
                    <?php if ($reorder): ?><td class="handle" hidden><?= icon('bars-3') ?></td><?php endif ?>
                    <td style="max-width:28rem">
                        <?php if ($trashed): ?><?= esc($row['title_th']) ?><?php else: ?><a href="<?= site_url('backend/downloads/' . $row['id'] . '/edit') ?>" style="color:inherit"><?= esc($row['title_th']) ?></a><?php endif ?>
                        <span class="cell-sub"><?= esc($row['category_name'] ?? '') ?></span>
                    </td>
                    <td>
                        <?= $row['type'] === 'file' ? badge(strtoupper((string) $row['file_extension']), 'primary') : badge('ลิงก์', 'info') ?>
                        <?php if ($row['type'] === 'file'): ?><span class="cell-sub"><?= esc(human_size($row['file_size'] === null ? null : (int) $row['file_size'])) ?></span><?php endif ?>
                    </td>
                    <td><?= $scan ? badge($scan[0], $scan[1]) : '<span class="muted">-</span>' ?></td>
                    <td><?= number_format((int) $row['download_count']) ?></td>
                    <td>
                        <?php if ($trashed): ?><?= esc(th_datetime($row['deleted_at'])) ?>
                        <?php else: ?><input type="checkbox" class="toggle" role="switch" aria-label="เปิดแสดง <?= esc($row['title_th'], 'attr') ?>" data-toggle-url="<?= site_url('backend/downloads/' . $row['id'] . '/toggle') ?>"<?= $row['is_active'] ? ' checked' : '' ?>><?php endif ?>
                    </td>
                    <td class="cell-actions">
                        <?php if (! $trashed): ?><a class="link-btn" href="<?= site_url('backend/downloads/' . $row['id'] . '/edit') ?>"><?= icon('pencil-square') ?> แก้ไข</a><?php endif ?>
                        <?= partial('partials/row_menu', ['base' => site_url('backend/downloads/' . $row['id']), 'trashed' => $trashed, 'label' => $row['title_th']]) ?>
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

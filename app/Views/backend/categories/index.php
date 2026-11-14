<?php
$base    = site_url($path);
$reorder = $q === '';
$c       = $edit ?? [];
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<a class="btn btn-primary" href="<?= esc($base . '?create=1', 'attr') ?>">เพิ่มหมวดหมู่</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card table-card"<?= $reorder ? ' data-reorder="' . esc(site_url($path . '/reorder'), 'attr') . '"' : '' ?>>
    <div class="table-toolbar">
        <?php if ($reorder && count($rows) > 1): ?>
        <button type="button" class="icon-btn" data-reorder-toggle aria-pressed="false" title="ลากเพื่อจัดลำดับ" aria-label="จัดลำดับ"><?= icon('arrows-up-down') ?></button>
        <?php endif ?>
        <div class="spacer"></div>
        <form method="get" action="<?= esc($base, 'attr') ?>" class="table-search" role="search">
            <?= icon('magnifying-glass') ?>
            <input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="ค้นหา" aria-label="ค้นหา">
        </form>
    </div>
    <div class="reorder-hint" data-reorder-hint hidden><?= icon('arrows-up-down') ?> ลากที่ไอคอน ☰ หน้าแถวเพื่อจัดลำดับ ระบบบันทึกให้ทันที</div>
    <?php if ($rows === []): ?>
        <?= partial('partials/table_empty', ['heading' => $q !== '' ? 'ไม่พบหมวดที่ตรงกับ "' . $q . '"' : 'ยังไม่มีหมวดหมู่']) ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th class="handle" hidden></th><th>ชื่อหมวด</th><th>slug</th><th><?= esc($countLabel) ?></th><th>เปิดใช้งาน</th><th></th></tr></thead>
            <tbody data-sortable>
            <?php foreach ($rows as $row): ?>
                <tr data-id="<?= (int) $row['id'] ?>">
                    <td class="handle" hidden><?= icon('bars-3') ?></td>
                    <td><?= esc($row['name_th']) ?><?php if ($row['name_en']): ?><span class="cell-sub"><?= esc($row['name_en']) ?></span><?php endif ?></td>
                    <td class="muted"><?= esc($row['slug']) ?></td>
                    <td><?= badge(number_format($row['item_count'])) ?></td>
                    <td><input type="checkbox" class="toggle" role="switch" aria-label="เปิดใช้งาน <?= esc($row['name_th'], 'attr') ?>" data-toggle-url="<?= site_url($path . '/' . $row['id'] . '/toggle') ?>"<?= $row['is_active'] ? ' checked' : '' ?>></td>
                    <td class="cell-actions">
                        <a class="link-btn" href="<?= esc($base . '?edit=' . $row['id'], 'attr') ?>"><?= icon('pencil-square') ?> แก้ไข</a>
                        <?php if ($row['item_count'] > 0): ?>
                        <span class="link-btn is-gray" title="หมวดนี้ยังมีข้อมูลอยู่ ย้ายข้อมูลไปหมวดอื่นก่อนจึงจะลบได้" style="cursor:not-allowed;opacity:.5"><?= icon('trash') ?> ลบ</span>
                        <?php else: ?>
                        <form method="post" action="<?= site_url($path . '/' . $row['id'] . '/delete') ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="link-btn is-danger" data-confirm="ต้องการลบหมวด &quot;<?= esc($row['name_th'], 'attr') ?>&quot; ใช่หรือไม่?" data-confirm-title="ลบหมวดหมู่" data-confirm-ok="ลบ"><?= icon('trash') ?> ลบ</button>
                        </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>

<div class="modal" id="category-modal" data-modal hidden<?= $openModal ? ' data-open-on-load' : '' ?> role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-window">
        <div class="modal-head">
            <h2 class="modal-title" id="category-modal-title"><?= $edit ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' ?></h2>
            <a class="icon-btn" href="<?= esc($base, 'attr') ?>" aria-label="ปิด"><?= icon('x-mark') ?></a>
        </div>
        <form method="post" action="<?= site_url($edit ? $path . '/' . $c['id'] : $path) ?>" class="modal-form" data-validate>
            <?= csrf_field() ?>
            <div class="grid-2" style="display:grid;gap:1.25rem 1.5rem">
                <?= text_field('name_th', ['label' => 'ชื่อหมวด (ภาษาไทย)', 'value' => $c['name_th'] ?? '', 'required' => true, 'maxlength' => 100]) ?>
                <?= text_field('name_en', ['label' => 'ชื่อหมวด (ภาษาอังกฤษ)', 'value' => $c['name_en'] ?? '', 'maxlength' => 100, 'hint' => 'เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน']) ?>
                <?= text_field('slug', ['label' => 'ชื่อใน URL (slug)', 'value' => $c['slug'] ?? '', 'maxlength' => 100, 'pattern' => '[a-zA-Z0-9\-\s]*', 'pattern_message' => 'ใช้ได้เฉพาะ a-z, 0-9 และขีด (-)', 'hint' => 'a-z, 0-9 และขีด (-) · เว้นว่างเพื่อสร้างจากชื่อภาษาอังกฤษ']) ?>
                <?= toggle_field('is_active', ['label' => 'เปิดใช้งาน', 'value' => $c['is_active'] ?? 1]) ?>
            </div>
            <div class="modal-actions">
                <a class="btn btn-gray" href="<?= esc($base, 'attr') ?>">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

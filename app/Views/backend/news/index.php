<?php
use App\Models\NewsModel;

$base = site_url('backend/news');
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<a class="btn btn-primary" href="<?= site_url('backend/news/create') ?>">เพิ่มข่าว</a>
<?= $this->endSection() ?>

<?= $this->section('header_extra') ?>
<?= partial('partials/list_tabs', ['tabs' => $tabs, 'active' => $tab, 'base' => $base, 'query' => $query]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card table-card">
    <form method="get" action="<?= esc($base, 'attr') ?>" class="table-toolbar" role="search">
        <?php foreach (['tab', 'per_page', 'sort', 'dir'] as $k): if (isset($query[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= esc($query[$k], 'attr') ?>"><?php endif; endforeach ?>
        <div class="filters">
            <select name="category" class="input select" data-autosubmit aria-label="หมวดหมู่">
                <option value="">ทุกหมวดหมู่</option>
                <?php foreach ($categories as $id => $name): ?><option value="<?= (int) $id ?>"<?= (int) $category === (int) $id ? ' selected' : '' ?>><?= esc($name) ?></option><?php endforeach ?>
            </select>
            <select name="pinned" class="input select" data-autosubmit aria-label="ปักหมุด">
                <option value="">ปักหมุด: ทั้งหมด</option>
                <option value="1"<?= $pinned === '1' ? ' selected' : '' ?>>ปักหมุดเท่านั้น</option>
                <option value="0"<?= $pinned === '0' ? ' selected' : '' ?>>ไม่ปักหมุด</option>
            </select>
        </div>
        <div class="spacer"></div>
        <div class="table-search">
            <?= icon('magnifying-glass') ?>
            <input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="ค้นหาหัวข้อข่าว" aria-label="ค้นหา">
        </div>
    </form>

    <?php if ($pager['rows'] === []): ?>
        <?= partial('partials/table_empty', $q !== '' || $category || $pinned !== ''
            ? ['heading' => 'ไม่พบข่าวที่ตรงกับเงื่อนไข', 'text' => 'ลองเปลี่ยนคำค้นหรือตัวกรอง', 'icon' => 'magnifying-glass']
            : ($tab === 'trashed' ? ['heading' => 'ถังขยะว่างเปล่า', 'icon' => 'trash'] : ['heading' => 'ยังไม่มีข่าว', 'text' => 'เริ่มเผยแพร่ข่าวประชาสัมพันธ์ชิ้นแรกของศูนย์ฯ', 'icon' => 'newspaper'])) ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>รูปปก</th>
                    <th>หัวข้อข่าว</th>
                    <th>ปักหมุด</th>
                    <th>สถานะ</th>
                    <th><?= sort_link('วันที่เผยแพร่', 'published_at', $base, $query, $sort, $dir) ?></th>
                    <th><?= sort_link('เข้าชม', 'view_count', $base, $query, $sort, $dir) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pager['rows'] as $row):
                $trashed = ! empty($row['deleted_at']);
                $display = NewsModel::displayStatus($row);
                $thumb   = media_url($row['cover_thumb'] ?: $row['cover_image']);
                $extra   = [];
                if (! $trashed && $display !== NewsModel::STATUS_PUBLISHED) {
                    $extra[] = ['label' => 'เผยแพร่ทันที', 'url' => site_url('backend/news/' . $row['id'] . '/publish'), 'icon' => 'check-circle', 'confirm' => 'ข่าวจะแสดงบนหน้าเว็บทันที (วันที่เผยแพร่ = วันนี้ ถ้ายังไม่เคยกำหนดหรือกำหนดเป็นอนาคต)', 'ok' => 'เผยแพร่', 'variant' => 'primary'];
                }
                if (! $trashed && $row['status'] === NewsModel::STATUS_PUBLISHED) {
                    $extra[] = ['label' => 'ซ่อนจากหน้าเว็บ', 'url' => site_url('backend/news/' . $row['id'] . '/hide'), 'icon' => 'eye-slash', 'confirm' => 'ข่าวจะไม่แสดงบนหน้าเว็บ เปิดกลับได้ด้วยปุ่ม "เผยแพร่ทันที"', 'ok' => 'ซ่อน', 'variant' => 'warning'];
                }
            ?>
                <tr<?= $trashed ? ' class="row-trashed"' : '' ?>>
                    <td><?php if ($thumb): ?><img class="thumb thumb-wide" src="<?= esc($thumb, 'attr') ?>" alt="" loading="lazy"><?php endif ?></td>
                    <td style="max-width:28rem">
                        <?php if ($trashed): ?><?= esc(mb_strimwidth($row['title_th'], 0, 90, '…')) ?><?php else: ?><a href="<?= site_url('backend/news/' . $row['id'] . '/edit') ?>" style="color:inherit"><?= esc(mb_strimwidth($row['title_th'], 0, 90, '…')) ?></a><?php endif ?>
                        <span class="cell-sub"><?= esc($row['category_name'] ?? '') ?></span>
                    </td>
                    <td><?= $row['is_pinned'] ? badge('ปักหมุด', 'primary') : '' ?></td>
                    <td><?= $trashed ? badge('ถูกลบ', 'danger') : badge(NewsModel::DISPLAY_LABELS[$display], NewsModel::DISPLAY_COLORS[$display]) ?></td>
                    <td class="nowrap"><?= esc(th_date($row['published_at'])) ?></td>
                    <td><?= number_format((int) $row['view_count']) ?></td>
                    <td class="cell-actions">
                        <?php if (! $trashed): ?><a class="link-btn" href="<?= site_url('backend/news/' . $row['id'] . '/edit') ?>"><?= icon('pencil-square') ?> แก้ไข</a><?php endif ?>
                        <?= partial('partials/row_menu', ['base' => site_url('backend/news/' . $row['id']), 'trashed' => $trashed, 'label' => mb_strimwidth($row['title_th'], 0, 60, '…'), 'extra' => $extra]) ?>
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

<?php
use App\Models\DisplayWindowModel;
use App\Models\SlideModel;

/** @var bool $isSlides */
$base      = site_url($path);
$addLabel  = $isSlides ? 'เพิ่มสไลด์' : 'เพิ่ม Popup';
$reorder   = $tab !== 'trashed' && $q === '';
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<a class="btn btn-primary" href="<?= site_url($path . '/create') ?>"><?= esc($addLabel) ?></a>
<?= $this->endSection() ?>

<?= $this->section('header_extra') ?>
<?= partial('partials/list_tabs', ['tabs' => $tabs, 'active' => $tab, 'base' => $base, 'query' => $query]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card table-card"<?= $reorder ? ' data-reorder="' . esc(site_url($path . '/reorder'), 'attr') . '"' : '' ?>>
    <div class="table-toolbar">
        <?php if ($reorder): ?>
        <button type="button" class="icon-btn" data-reorder-toggle aria-pressed="false" title="ลากเพื่อจัดลำดับ" aria-label="จัดลำดับ"><?= icon('arrows-up-down') ?></button>
        <?php endif ?>
        <div class="spacer"></div>
        <form method="get" action="<?= esc($base, 'attr') ?>" class="table-search" role="search">
            <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>"><?php endif ?>
            <?= icon('magnifying-glass') ?>
            <input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="ค้นหา" aria-label="ค้นหา">
        </form>
    </div>
    <?php if ($reorder): ?>
    <div class="reorder-hint" data-reorder-hint hidden><?= icon('arrows-up-down') ?> ลากที่ไอคอน ☰ หน้าแถวเพื่อจัดลำดับ ระบบบันทึกให้ทันที — กดปุ่ม ⇅ อีกครั้งเมื่อเสร็จ</div>
    <?php endif ?>

    <?php if ($pager['rows'] === []): ?>
        <?= partial('partials/table_empty', $q !== ''
            ? ['heading' => 'ไม่พบรายการที่ตรงกับ "' . $q . '"', 'text' => 'ลองค้นหาด้วยคำอื่น', 'icon' => 'magnifying-glass']
            : ($tab === 'trashed' ? ['heading' => 'ถังขยะว่างเปล่า', 'icon' => 'trash'] : ['heading' => 'ยังไม่มีรายการ', 'text' => 'กดปุ่ม "' . $addLabel . '" เพื่อเริ่มต้น'])) ?>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($reorder): ?><th class="handle" hidden></th><?php endif ?>
                    <th>รูป</th>
                    <th>ชื่อเรียก</th>
                    <th>ลิงก์</th>
                    <th>ช่วงวันที่แสดง</th>
                    <th>สถานะ</th>
                    <th><?= $tab === 'trashed' ? 'ลบเมื่อ' : 'เปิดแสดง' ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody<?= $reorder ? ' data-sortable' : '' ?>>
            <?php foreach ($pager['rows'] as $row):
                $status  = DisplayWindowModel::displayStatus($row);
                $trashed = ! empty($row['deleted_at']);
                $thumb   = media_url($row['image_th']);
                if ($isSlides) {
                    $link = match ($row['link_type']) {
                        SlideModel::LINK_NEWS => $row['news_id'] ? 'ข่าว #' . $row['news_id'] : 'ข่าว (ถูกลบแล้ว)',
                        SlideModel::LINK_URL  => (string) parse_url((string) $row['link_url'], PHP_URL_HOST),
                        default               => null,
                    };
                } else {
                    $link = $row['link_url'] ? (string) parse_url((string) $row['link_url'], PHP_URL_HOST) : null;
                }
            ?>
                <tr data-id="<?= (int) $row['id'] ?>"<?= $trashed ? ' class="row-trashed"' : '' ?>>
                    <?php if ($reorder): ?><td class="handle" hidden><?= icon('bars-3') ?></td><?php endif ?>
                    <td><?php if ($thumb): ?><img class="thumb<?= $isSlides ? ' thumb-wide' : '' ?>" src="<?= esc($thumb, 'attr') ?>" alt="" loading="lazy"><?php endif ?></td>
                    <td>
                        <?php if ($trashed): ?><?= esc($row['title']) ?><?php else: ?><a href="<?= site_url($path . '/' . $row['id'] . '/edit') ?>" style="color:inherit"><?= esc($row['title']) ?></a><?php endif ?>
                        <span class="cell-sub"><?= esc(mb_strimwidth((string) $row['alt_th'], 0, 60, '…')) ?></span>
                    </td>
                    <td><?= $link ? esc($link) : '<span class="muted">ไม่มี</span>' ?></td>
                    <td class="nowrap"><?= esc(th_date($row['start_date'], 'ทันที')) ?> – <?= esc(th_date($row['end_date'], 'ไม่มีกำหนด')) ?></td>
                    <td data-status-cell><?= badge(DisplayWindowModel::STATUS_LABELS[$status], DisplayWindowModel::STATUS_COLORS[$status]) ?></td>
                    <td>
                        <?php if ($trashed): ?><?= esc(th_datetime($row['deleted_at'])) ?>
                        <?php else: ?>
                        <input type="checkbox" class="toggle" role="switch" aria-label="เปิดแสดง <?= esc($row['title'], 'attr') ?>" data-toggle-url="<?= site_url($path . '/' . $row['id'] . '/toggle') ?>"<?= $row['is_active'] ? ' checked' : '' ?>>
                        <?php endif ?>
                    </td>
                    <td class="cell-actions">
                        <?php if (! $trashed): ?><a class="link-btn" href="<?= site_url($path . '/' . $row['id'] . '/edit') ?>"><?= icon('pencil-square') ?> แก้ไข</a><?php endif ?>
                        <?= partial('partials/row_menu', [
                            'base'    => site_url($path . '/' . $row['id']),
                            'trashed' => $trashed,
                            'label'   => $row['title'],
                            'extra'   => ! $isSlides && ! $trashed ? [['label' => 'ต่ออายุ +7 วัน', 'url' => site_url($path . '/' . $row['id'] . '/extend'), 'icon' => 'calendar-days']] : [],
                        ]) ?>
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

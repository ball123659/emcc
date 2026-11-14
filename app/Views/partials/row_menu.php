<?php
/**
 * เมนู ⋮ ท้ายแถว: ลบ (ยืนยันก่อน) หรือ กู้คืน (แถวที่ถูกลบ)
 * @var string $base URL ของรายการ เช่น backend/slides/5 @var bool $trashed @var string $label ชื่อรายการ
 * @var array $extra รายการเพิ่มเติม [['label','url','icon','confirm','variant']]
 */
$extra   = $extra ?? [];
$trashed = $trashed ?? false;
$canDelete = $canDelete ?? true;
if (! $trashed && ! $canDelete && $extra === []) {
    return;
}
?>
<div class="row-menu" data-dropdown>
    <button type="button" class="icon-btn" data-dropdown-toggle aria-label="เมนูเพิ่มเติม" aria-haspopup="true"><?= icon('ellipsis-vertical') ?></button>
    <div class="dropdown-panel" hidden>
        <?php foreach ($extra as $item): ?>
        <form method="post" action="<?= esc($item['url'], 'attr') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item<?= ($item['variant'] ?? '') === 'danger' ? ' is-danger' : '' ?>"
                <?php if (! empty($item['confirm'])): ?>data-confirm="<?= esc($item['confirm'], 'attr') ?>" data-confirm-title="<?= esc($item['label'], 'attr') ?>" data-confirm-ok="<?= esc($item['ok'] ?? $item['label'], 'attr') ?>" data-confirm-variant="<?= esc($item['variant'] ?? 'warning', 'attr') ?>"<?php endif ?>>
                <?= icon($item['icon'] ?? 'arrow-path') ?> <?= esc($item['label']) ?>
            </button>
        </form>
        <?php endforeach ?>
        <?php if ($trashed): ?>
        <form method="post" action="<?= esc($base . '/restore', 'attr') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item"><?= icon('arrow-uturn-left') ?> กู้คืน</button>
        </form>
        <?php elseif ($canDelete): ?>
        <form method="post" action="<?= esc($base . '/delete', 'attr') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item is-danger" data-confirm="ต้องการลบ &quot;<?= esc($label, 'attr') ?>&quot; ใช่หรือไม่?<?= ($restorable ?? true) ? ' (กู้คืนได้ที่แท็บถังขยะ)' : '' ?>" data-confirm-title="ลบรายการ" data-confirm-ok="ลบ">
                <?= icon('trash') ?> ลบ
            </button>
        </form>
        <?php endif ?>
    </div>
</div>

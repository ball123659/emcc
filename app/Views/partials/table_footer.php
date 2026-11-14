<?php
/** @var array $pager ผลจาก BackendController::paginate() @var string $base @var array $query */
$from = $pager['total'] === 0 ? 0 : ($pager['page'] - 1) * $pager['perPage'] + 1;
$to   = min($pager['total'], $pager['page'] * $pager['perPage']);
?>
<div class="table-footer">
    <div class="count">
        <?php if ($pager['pages'] > 1): ?>แสดง <?= number_format($from) ?> ถึง <?= number_format($to) ?> จาก <?= number_format($pager['total']) ?> รายการ
        <?php else: ?>แสดง <?= number_format($pager['total']) ?> รายการ<?php endif ?>
    </div>
    <form method="get" action="<?= esc($base, 'attr') ?>" class="per-page">
        <?php foreach ($query as $k => $v): if (in_array($k, ['per_page', 'page'], true)) { continue; } ?>
        <input type="hidden" name="<?= esc($k, 'attr') ?>" value="<?= esc($v, 'attr') ?>">
        <?php endforeach ?>
        <span>รายการต่อหน้า</span>
        <select name="per_page" class="input select" data-autosubmit aria-label="รายการต่อหน้า">
            <?php foreach ([10, 25, 50, 100] as $n): ?>
            <option value="<?= $n ?>"<?= $n === $pager['perPage'] ? ' selected' : '' ?>><?= $n ?></option>
            <?php endforeach ?>
        </select>
    </form>
    <?php if ($pager['pages'] > 1): ?>
    <nav class="pagination" aria-label="เลขหน้า">
        <?php
        $page  = $pager['page'];
        $pages = $pager['pages'];
        $range = array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], static fn ($p) => $p >= 1 && $p <= $pages));
        sort($range);
        ?>
        <?php if ($page > 1): ?><a href="<?= esc(list_url($base, $query, ['page' => $page - 1]), 'attr') ?>" aria-label="หน้าก่อน"><?= icon('chevron-left') ?></a><?php endif ?>
        <?php $prev = 0; foreach ($range as $p): ?>
            <?php if ($p - $prev > 1): ?><span class="is-disabled">…</span><?php endif ?>
            <?php if ($p === $page): ?><span class="is-current" aria-current="page"><?= $p ?></span>
            <?php else: ?><a href="<?= esc(list_url($base, $query, ['page' => $p]), 'attr') ?>"><?= $p ?></a><?php endif ?>
        <?php $prev = $p; endforeach ?>
        <?php if ($page < $pages): ?><a href="<?= esc(list_url($base, $query, ['page' => $page + 1]), 'attr') ?>" aria-label="หน้าถัดไป"><?= icon('chevron-right') ?></a><?php endif ?>
    </nav>
    <?php else: ?><div></div><?php endif ?>
</div>

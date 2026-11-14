<?php
/** @var array $tabs [key => ['label' => .., 'count' => .., 'color' => ..]] @var string $active @var string $base @var array $query */
?>
<div class="tabs-center">
    <nav class="tabs" aria-label="กรองตามสถานะ">
        <?php foreach ($tabs as $key => $tab): ?>
        <a class="tab<?= $key === $active ? ' is-active' : '' ?>" href="<?= esc(list_url($base, $query, ['tab' => $key === 'all' ? null : $key, 'page' => null]), 'attr') ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
            <?php if (! empty($tab['icon'])): ?><?= icon($tab['icon']) ?><?php endif ?>
            <?= esc($tab['label']) ?>
            <?php if (isset($tab['count'])): ?><span class="tab-count c-<?= esc($tab['color'] ?? 'gray', 'attr') ?>"><?= number_format($tab['count']) ?></span><?php endif ?>
        </a>
        <?php endforeach ?>
    </nav>
</div>

<div class="table-empty">
    <div class="empty-icon"><?= icon($icon ?? 'x-mark') ?></div>
    <h3><?= esc($heading ?? 'ไม่พบรายการ') ?></h3>
    <?php if (! empty($text)): ?><p><?= esc($text) ?></p><?php endif ?>
</div>

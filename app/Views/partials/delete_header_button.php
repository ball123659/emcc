<?php /** ปุ่มลบบนหัวหน้าแก้ไข @var string $url @var string $label */ ?>
<form method="post" action="<?= esc($url, 'attr') ?>">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger" data-confirm="ต้องการลบ &quot;<?= esc($label, 'attr') ?>&quot; ใช่หรือไม่?<?= ($restorable ?? true) ? ' (กู้คืนได้ที่แท็บถังขยะ)' : '' ?>" data-confirm-title="ลบรายการ" data-confirm-ok="ลบ"><?= icon('trash') ?> ลบ</button>
</form>

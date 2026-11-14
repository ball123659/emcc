<?php /** @var bool $isNew @var string $cancel */ ?>
<div class="form-actions">
    <button type="submit" class="btn btn-primary">บันทึก</button>
    <?php if (! empty($isNew)): ?>
    <button type="submit" class="btn btn-gray" name="_another" value="1">บันทึกและเพิ่มอีกรายการ</button>
    <?php endif ?>
    <a class="btn btn-gray" href="<?= esc($cancel, 'attr') ?>">ยกเลิก</a>
</div>

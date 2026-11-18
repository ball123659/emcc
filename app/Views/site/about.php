<?php
$phones = tel_links(setting('phone'));
$email  = setting('email');
?>
<?= $this->extend('layouts/site') ?>

<?= $this->section('content') ?>
<?= view('site/partials/banner', [
    'heading' => t('เกี่ยวกับศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', 'About the Environmental Monitoring & Control Center'),
    'text'    => t('ศูนย์กลางการเผยแพร่และติดตามข้อมูลการระบายมลพิษจากโรงงานในพื้นที่นิคมอุตสาหกรรม เพื่อความโปร่งใสและการมีส่วนร่วมของทุกภาคส่วน', 'A central hub for publishing and monitoring industrial emission data from factories in industrial estates — for transparency and participation from all sectors.'),
    'crumbs'  => [[t('เกี่ยวกับ', 'About'), null]],
]) ?>

<section class="about-intro">
    <div class="container about-grid">
        <div>
            <h2><?= esc(t('เรามีหน้าที่อะไร', 'What we do')) ?></h2>
            <p><?= esc(t('ศูนย์ฯ ทำหน้าที่รวบรวมข้อมูลการตรวจวัดการระบายมลพิษจากโรงงานหลายระบบ ทั้งอากาศเสียจากปล่อง (CEMS) น้ำทิ้ง (WPMS) และระบบส่งข้อมูลกลาง (POMS) มาแสดงผลบนเว็บไซต์เดียว พร้อมแผนที่และข้อมูลย้อนหลัง', 'The center aggregates emission monitoring data from multiple factory systems — stack air (CEMS), wastewater (WPMS) and central data (POMS) — into a single portal with a map and historical records.')) ?></p>
            <p><?= esc(t('เป้าหมายคือการทำให้ข้อมูลด้านสิ่งแวดล้อมเข้าถึงได้ง่าย โปร่งใส และช่วยให้เจ้าหน้าที่ตอบสนองต่อเหตุการณ์ผิดปกติได้อย่างรวดเร็ว', 'Our goal is to make environmental data easy to access and transparent, and to help officers respond quickly to abnormal events.')) ?></p>
        </div>
        <div class="about-visual" aria-hidden="true">🌏</div>
    </div>
</section>

<section class="stats">
    <div class="container stats-grid">
        <div class="stat"><strong>126</strong><span><?= esc(t('โรงงานในระบบ', 'Factories')) ?></span></div>
        <div class="stat"><strong>5</strong><span><?= esc(t('นิคมอุตสาหกรรม', 'Industrial estates')) ?></span></div>
        <div class="stat"><strong>3</strong><span><?= esc(t('ระบบตรวจวัด', 'Monitoring systems')) ?></span></div>
        <div class="stat"><strong>24/7</strong><span><?= esc(t('ติดตามต่อเนื่อง', 'Continuous monitoring')) ?></span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head center">
            <h2><?= esc(t('ระบบตรวจวัดที่รวมไว้', 'Integrated monitoring systems')) ?></h2>
            <p><?= esc(t('รวมข้อมูลจากหลายระบบของโรงงานไว้ในที่เดียว เน้นค่าการระบายและสถานะตามเกณฑ์', 'Multiple factory systems in one place, focused on emission values and threshold status.')) ?></p>
        </div>
        <div class="sys-grid">
            <div class="sys-card"><div class="sys-ico" aria-hidden="true">🏭</div><div class="sys-body"><h3>CEMS</h3><p><?= esc(t('ตรวจวัดการระบายอากาศเสียจากปล่อง เช่น SO₂, NOx, CO, O₂, Dust และ Flow', 'Stack air emission monitoring such as SO₂, NOx, CO, O₂, dust and flow.')) ?></p></div></div>
            <div class="sys-card"><div class="sys-ico" aria-hidden="true">💧</div><div class="sys-body"><h3>WPMS</h3><p><?= esc(t('ติดตามข้อมูลน้ำทิ้งจากโรงงาน เช่น pH, COD, BOD, TSS และ Flow', 'Wastewater monitoring such as pH, COD, BOD, TSS and flow.')) ?></p></div></div>
            <div class="sys-card"><div class="sys-ico" aria-hidden="true">📡</div><div class="sys-body"><h3>POMS</h3><p><?= esc(t('รวมข้อมูลตรวจวัดจากระบบส่งข้อมูลกลาง เพื่อติดตามสถานะและข้อมูลย้อนหลัง', 'Central data aggregation for status tracking and historical values.')) ?></p></div></div>
        </div>
    </div>
</section>

<section class="section how">
    <div class="container">
        <div class="section-head center">
            <h2><?= esc(t('ข้อมูลเดินทางอย่างไร', 'How the data flows')) ?></h2>
            <p><?= esc(t('ตั้งแต่อุปกรณ์ตรวจวัดที่โรงงาน จนถึงการแสดงผลบนเว็บไซต์', 'From sensors at the factory to display on the website')) ?></p>
        </div>
        <ol class="steps">
            <li class="step"><h4><?= esc(t('ตรวจวัดที่โรงงาน', 'Measure at factory')) ?></h4><p><?= esc(t('อุปกรณ์ CEMS/WPMS วัดค่าการระบายแบบต่อเนื่อง', 'CEMS/WPMS devices measure emissions continuously.')) ?></p></li>
            <li class="step"><h4><?= esc(t('ส่งเข้าฐานข้อมูลกลาง', 'Send to central DB')) ?></h4><p><?= esc(t('ข้อมูลถูกส่งผ่านระบบ POMS เข้าสู่ฐานข้อมูลของหน่วยงาน', 'Data is transmitted via POMS into the agency database.')) ?></p></li>
            <li class="step"><h4><?= esc(t('ประมวลผลและเทียบเกณฑ์', 'Process & compare')) ?></h4><p><?= esc(t('ระบบเปรียบเทียบกับค่ามาตรฐานและกำหนดสถานะ', 'The system compares values with standards and sets a status.')) ?></p></li>
            <li class="step"><h4><?= esc(t('แสดงผลและแจ้งเตือน', 'Display & alert')) ?></h4><p><?= esc(t('แสดงบนแผนที่ พร้อมแจ้งเตือนเมื่อพบค่าผิดปกติ', 'Shown on the map with alerts on abnormal readings.')) ?></p></li>
        </ol>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="contact-card">
            <div>
                <h3><?= esc(t('ติดต่อสอบถามเพิ่มเติม', 'Get in touch')) ?></h3>
                <?php if ($phones !== []): ?>
                <p>📞 <?php foreach ($phones as $i => $p): ?><?= $i > 0 ? ' / ' : '' ?><a href="<?= esc($p['href'], 'attr') ?>"><?= esc($p['label']) ?></a><?php endforeach ?></p>
                <?php endif ?>
                <?php if ($email): ?><p>✉ <a href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a></p><?php endif ?>
            </div>
            <div class="contact-actions">
                <a class="btn btn-primary" href="<?= site_url('downloads') ?>"><?= esc(t('ศูนย์ดาวน์โหลด', 'Downloads Center')) ?></a>
                <a class="btn btn-outline" href="<?= site_url('news') ?>"><?= esc(t('ข่าวสารและกิจกรรม', 'News & Activities')) ?></a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>

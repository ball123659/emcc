# EMCC — เว็บไซต์ + ระบบจัดการ (EMCC Backend)

ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม (Environmental Monitoring & Control Center)

- Laravel 13 + Filament 5 — ระบบจัดการที่ `/backend`, หน้าเว็บ Blade ที่ `/`
- PHP 8.3+, MySQL 8.0.16+ หรือ MariaDB 10.4+ (ฐานข้อมูล `webemcc`, `utf8mb4_general_ci`, เวลา Asia/Bangkok)
- รันได้ทั้ง Windows (IIS / XAMPP / Laravel Herd) และ Linux

## เริ่มใช้งานบนเครื่อง dev

```bash
composer install
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed  # หรือ import database/webemcc.sql แล้วรัน php artisan migrate && php artisan db:seed
php artisan serve
```

เปิด http://localhost:8000/backend แล้วเข้าสู่ระบบด้วย `developer` / `passwordemcc` (ระบบบังคับตั้งรหัสใหม่ครั้งแรก)

ตรวจความพร้อมของเครื่อง (php.ini, สิทธิ์โฟลเดอร์, ฐานข้อมูล, cache):

```bash
php artisan emcc:check
```

หลัง `git pull` ทุกครั้ง: `composer install` → `php artisan migrate` → `php artisan optimize:clear`

## ฐานข้อมูล

- โครงสร้างต้นฉบับคือ `database/webemcc.sql` — migrations ใน `database/migrations` สร้างโครงสร้างเหมือนกันทุกตาราง
  (CI ตรวจให้ทั้ง MySQL 8 และ MariaDB 10.4) ถ้าเปลี่ยนโครงสร้างต้องแก้ทั้งสองที่
- **ไม่ใช้ trigger แล้ว** (MariaDB 10.4 / XAMPP crash ตอน INSERT users) แถวสิทธิ์รายหน้าค่าเริ่มต้น `can_view = 0`
  สร้างในโค้ด: เพิ่มผู้ใช้ผ่าน Model `User` / เพิ่มหน้าผ่าน Model `Page` ได้แถวอัตโนมัติ
  ถ้าเพิ่มข้อมูลด้วย SQL ตรง ๆ ให้รัน `php artisan db:seed` (เรียก `UserPagePermission::fillMissing()`) เพื่อเติมแถวที่ขาด
- ฐานที่เคยมี trigger จาก SQL รุ่นเก่า: `php artisan migrate` จะลบ trigger ให้ (`2026_11_18_000001_drop_permission_triggers`)
- ตาราง `map_*` เป็นของระบบแผนที่ (dev อีกคน) ไม่อยู่ใน migrations ชุดนี้

## ทดสอบ

```bash
php artisan test
```

ค่าเริ่มต้นใช้ SQLite ในหน่วยความจำ ทดสอบกับ MySQL/MariaDB จริง: ตั้ง `DB_CONNECTION=mysql DB_DATABASE=<ฐานทดสอบ>` ก่อนรัน (ฐานนั้นจะถูกล้าง)

## ติดตั้งบนเซิร์ฟเวอร์

ดู [docs/DEPLOY.md](docs/DEPLOY.md)

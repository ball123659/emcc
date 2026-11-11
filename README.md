# EMCC — เว็บไซต์ + ระบบหลังบ้าน

ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม (Environmental Monitoring & Control Center)

- Laravel 13 + Filament 5 (หลังบ้านที่ `/backend`), หน้าบ้าน Blade อยู่ที่ `/` ในโปรเจกต์เดียวกัน
- MySQL 8.0.16+ / MariaDB 10.4+ ฐานข้อมูล `webemcc` (`utf8mb4_general_ci`), เวลา Asia/Bangkok
- PHP **8.3+** (Laravel 13 ต้องการ 8.3) พร้อม extension: `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `gd`, `zip`, `openssl`
- รันได้ทั้ง Windows (IIS) และ Linux

## ติดตั้ง (เครื่อง dev)

```bash
composer install
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

แก้ `.env` ส่วน `DB_*` ให้ชี้ไป MySQL แล้วเลือก **อย่างใดอย่างหนึ่ง**

**ก. ฐานข้อมูลว่าง** — ให้ Laravel สร้างตารางทั้งหมด

```bash
php artisan migrate --seed
```

**ข. import `database/webemcc.sql` ไว้แล้ว** — migration จะข้ามตารางที่มีอยู่ และสร้างเฉพาะตารางระบบของ Laravel (`sessions`, `cache`, `jobs`, `migrations`)

```bash
php artisan migrate
php artisan db:seed          # รันซ้ำได้ ไม่ทับข้อมูลเดิม
```

เปิดเว็บ

```bash
php artisan serve
```

แล้วเข้า http://localhost:8000/backend ด้วย `developer` / `passwordemcc` (ระบบจะบังคับเปลี่ยนรหัสเมื่อทำลำดับงานข้อ 2 เสร็จ) — **แก้ email ของบัญชีนี้ก่อนใช้งานจริง**

## ทดสอบ

```bash
php artisan test
```

ค่าเริ่มต้นใช้ SQLite ในหน่วยความจำ ถ้าจะทดสอบกับ MySQL จริงให้ตั้ง `DB_CONNECTION=mysql DB_DATABASE=<ฐานทดสอบ>` ก่อนรัน (ฐานนั้นจะถูกล้าง)

## โครงสร้างฐานข้อมูล

- ต้นฉบับ: `database/webemcc.sql` — migrations ใน `database/migrations` สร้างโครงสร้างเหมือนกันทุกตาราง
  (คอลัมน์, index, FK, CHECK constraint, comment และ trigger สิทธิ์รายหน้า) ถ้าเปลี่ยนโครงสร้าง ต้องแก้ทั้งสองที่ให้ตรงกัน
- ข้อมูลเริ่มต้นอยู่ใน `database/seeders` (ตรงกับส่วน "ข้อมูลเริ่มต้น" ของ SQL)
- ตาราง `map_*` เป็นของระบบแผนที่ (dev อีกคน) ไม่อยู่ใน migrations ชุดนี้
- สิทธิ์รายหน้า: trigger ใน MySQL สร้างแถว `can_view = 0` ให้อัตโนมัติ และ Model `User` / `Page` ทำซ้ำในโค้ดด้วย
  (กรณีเซิร์ฟเวอร์สร้าง trigger ไม่ได้)

## งานตั้งเวลา

ใช้ Laravel scheduler — Linux: cron `* * * * * php artisan schedule:run`,
Windows: Task Scheduler รัน `php artisan schedule:run` ทุก 1 นาที

## โปรดักชัน

`.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` (ระบบบังคับ HTTPS อัตโนมัติเมื่อเป็น production)

# คู่มือติดตั้งบนเซิร์ฟเวอร์ (Windows Server + IIS / Linux)

## สิ่งที่ต้องมี
- PHP 8.3+ (extension: `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `gd` ที่รองรับ WebP, `zip`, `exif`, `openssl`)
- MySQL 8.0.16+ หรือ MariaDB 10.4+ (ฐานข้อมูล `webemcc`, `utf8mb4_general_ci`)
- Composer
- IIS: URL Rewrite module + PHP ผ่าน FastCGI

## php.ini (ค่าขั้นต่ำ)
```ini
upload_tmp_dir = "C:\php\tmp"   ; ต้องมีโฟลเดอร์จริงและผู้ใช้ที่รัน PHP เขียนได้
upload_max_filesize = 20M
post_max_size = 30M
memory_limit = 256M
max_execution_time = 120
expose_php = Off
```
IIS: ตั้ง FastCGI `activityTimeout` / `requestTimeout` ให้มากกว่า 120 วินาที

## ติดตั้ง
```bash
composer install --no-dev --optimize-autoloader
copy .env.example .env      # Linux: cp .env.example .env
php artisan key:generate
```

แก้ `.env`:
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<โดเมนจริง>
DB_DATABASE=webemcc
DB_USERNAME=...
DB_PASSWORD=...
SESSION_SECURE_COOKIE=true
VIRUS_SCANNER=defender      # Linux: clamav
# TRUSTED_PROXIES=10.0.0.5  # ถ้าอยู่หลัง reverse proxy / load balancer
```

ฐานข้อมูล (เลือกอย่างใดอย่างหนึ่ง):
- ฐานว่าง: `php artisan migrate --seed --force`
- import `database/webemcc.sql` แล้ว: `php artisan migrate --force` แล้ว `php artisan db:seed --force`

```bash
php artisan optimize
php artisan emcc:check      # ทุกข้อควรผ่าน
```

## IIS
- Physical path ของเว็บไซต์ = โฟลเดอร์ `public` (มี `web.config` และ `.user.ini` ให้แล้ว)
- ให้สิทธิ์ Modify กับ App Pool identity / `IIS_IUSRS` ที่ `storage` และ `bootstrap\cache`
- ผูกใบรับรอง HTTPS ระบบจะ redirect http → https เอง (ปิดได้ด้วย `FORCE_HTTPS=false` ถ้า IIS redirect ให้แล้ว)
- สแกนไวรัส (Defender): App Pool identity ต้องรัน `MpCmdRun.exe` ได้ และโฟลเดอร์ `storage` ต้องไม่อยู่ใน exclusion

## งานตั้งเวลา
Task Scheduler (Windows) / cron (Linux) รันทุก 1 นาที:
```bash
php artisan schedule:run
```
งานที่มี: ลบบันทึกการใช้งานที่เก่ากว่า `ACTIVITY_LOG_RETENTION_DAYS` (ค่าเริ่มต้น 730 วัน) ทุกวันเวลา 02:15

## หลังติดตั้ง
- ล็อกอิน `/backend` ด้วย `developer` / `passwordemcc` แล้วตั้งรหัสใหม่
- แก้ email ของบัญชี developer ให้เป็นของจริง

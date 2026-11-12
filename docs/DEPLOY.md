# คู่มือติดตั้งบนเซิร์ฟเวอร์ (Windows Server + IIS / Linux)

## สิ่งที่ต้องมี
- PHP 8.3+ (extension: `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `gd` ที่รองรับ WebP, `zip`, `exif`, `openssl`)
- MySQL 8.0.16+ หรือ MariaDB 10.4+ (ฐานข้อมูล `webemcc`, `utf8mb4_general_ci`) — ไม่ใช้ trigger (สิทธิ์รายหน้าสร้างในโค้ด)
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
VIRUS_SCANNER=defender      # Linux: clamav — ถ้าเป็น none ระบบจะไม่รับไฟล์อัปโหลดในโปรดักชัน
# TRUSTED_PROXIES=10.0.0.5  # ถ้าอยู่หลัง reverse proxy / load balancer ที่ทำ HTTPS ให้ (จำเป็น ไม่งั้น redirect https ไม่ทำงาน)
# LOG_LEVEL / SESSION_SECURE_COOKIE ไม่ต้องตั้ง — โปรดักชันเป็น warning / Secure อัตโนมัติ
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

## อัปเดตโค้ด (หลัง pull ทุกครั้ง)
```bash
git pull
composer install            # โปรดักชัน: composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize        # โปรดักชันเท่านั้น — เครื่อง dev ไม่ต้องรัน (ถ้ารันแล้ว pull โค้ดใหม่ เมนู/วิดเจ็ตใหม่จะไม่แสดง)
```

## สำรองข้อมูล (backup)
สิ่งที่ต้องสำรอง **ทั้งสองอย่างคู่กัน** (ฐานข้อมูลเก็บแค่ path ไฟล์จริงอยู่ในโฟลเดอร์):
1. ฐานข้อมูล `webemcc`
2. โฟลเดอร์ `storage/app/private` (รูป ข่าว ไฟล์แนบ ไฟล์ดาวน์โหลด)
3. ไฟล์ `.env` (มี `APP_KEY` — ถ้าหาย session/ข้อมูลที่เข้ารหัสใช้ไม่ได้) เก็บแยกในที่ปลอดภัย

ตัวอย่างสำรองรายวัน (ตั้งใน Task Scheduler / cron เวลากลางคืน):
```bat
:: Windows (XAMPP/MySQL) — ปรับ path ให้ตรงเครื่อง
set D=%date:~-4%%date:~3,2%%date:~0,2%
"C:\xampp\mysql\bin\mysqldump.exe" -u backup -p*** --single-transaction --routines webemcc > D:\backup\webemcc-%D%.sql
robocopy C:\inetpub\emcc\storage\app\private D:\backup\files-%D% /MIR
```
```bash
# Linux
mysqldump --single-transaction webemcc | gzip > /backup/webemcc-$(date +%F).sql.gz
tar czf /backup/files-$(date +%F).tar.gz -C /var/www/emcc/storage/app private
```
- ใช้บัญชีฐานข้อมูลสำหรับสำรองที่มีสิทธิ์อ่านอย่างเดียว เก็บสำรองไว้ **นอกเครื่องเซิร์ฟเวอร์** อย่างน้อย 30 วัน
- ทดลองกู้คืนอย่างน้อยปีละครั้ง: import SQL ลงฐานทดสอบ + คัดลอกไฟล์กลับ แล้วเปิดหลังบ้านดูรูป/ไฟล์ได้ครบ

## หลังติดตั้ง
- ล็อกอิน `/backend` ด้วย `developer` / `passwordemcc` แล้วตั้งรหัสใหม่
- แก้ email ของบัญชี developer ให้เป็นของจริง

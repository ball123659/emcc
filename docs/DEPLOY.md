# คู่มือติดตั้งบนเซิร์ฟเวอร์ (Windows Server + IIS / Linux) — CodeIgniter 4

## สิ่งที่ต้องมี
- PHP 8.3+ (extension: `mysqli`, `mbstring`, `intl`, `fileinfo`, `gd` ที่รองรับ WebP, `zip`, `exif`, `openssl`)
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
php spark key:generate
```

แก้ `.env`:
```dotenv
CI_ENVIRONMENT = production
app.baseURL = 'https://<โดเมนจริง>/'
database.default.database = webemcc
database.default.username = ...
database.default.password = ...
emcc.virusScanner = defender      # Linux: clamav — ถ้าเป็น none ระบบจะไม่รับไฟล์อัปโหลดในโปรดักชัน
# TRUSTED_PROXIES = '10.0.0.5'    # ถ้าอยู่หลัง reverse proxy / load balancer ที่ทำ HTTPS ให้ (คั่นหลายตัวด้วย , รองรับ CIDR)
```
โปรดักชัน: cookie เป็น Secure + HttpOnly อัตโนมัติ, หน้า error ไม่แสดงรายละเอียดระบบ

**อยู่หลัง reverse proxy / load balancer ต้องตั้ง `TRUSTED_PROXIES`** ไม่งั้นผู้ใช้ทุกคนจะมี IP เดียวกัน
(จำกัดการล็อกอิน 10 ครั้ง/นาที และดาวน์โหลด 60 ครั้ง/นาที รวมกันทั้งองค์กร, ยอดดาวน์โหลดเพี้ยน, บันทึก IP ผิด)

ฐานข้อมูล (เลือกอย่างใดอย่างหนึ่ง):
- ฐานว่าง: `php spark migrate --all` แล้ว `php spark db:seed DatabaseSeeder`
- import `database/webemcc.sql` แล้ว: `php spark migrate --all` (ข้ามตารางที่มีอยู่แล้ว) แล้ว `php spark db:seed DatabaseSeeder`

```bash
php spark emcc:check      # ทุกข้อควรผ่าน
```

## IIS
- Physical path ของเว็บไซต์ = โฟลเดอร์ `public` (มี `web.config` และ `.user.ini` ให้แล้ว)
- ให้สิทธิ์ Modify กับ App Pool identity / `IIS_IUSRS` ที่โฟลเดอร์ `writable`
- ผูกใบรับรอง HTTPS ระบบจะ redirect http → https เอง (ปิดได้ด้วย `emcc.forceHttps = false` ถ้า IIS redirect ให้แล้ว)
- สแกนไวรัส (Defender): App Pool identity ต้องรัน `MpCmdRun.exe` ได้ และโฟลเดอร์ `writable` ต้องไม่อยู่ใน exclusion

## งานตั้งเวลา
Task Scheduler (Windows) / cron (Linux) รันวันละครั้ง (เช่น 02:15):
```bash
php spark emcc:daily
```
ลบบันทึกการใช้งานที่เก่ากว่า `emcc.activityLogRetentionDays` (ค่าเริ่มต้น 730 วัน), cache ที่หมดอายุ, session เก่า และ log เก่ากว่า 30 วัน

## อัปเดตโค้ด (หลัง pull ทุกครั้ง)
```bash
git pull
composer install            # โปรดักชัน: composer install --no-dev --optimize-autoloader
php spark migrate --all
php spark cache:clear
```

## สำรองข้อมูล (backup)
สิ่งที่ต้องสำรอง **ทั้งสองอย่างคู่กัน** (ฐานข้อมูลเก็บแค่ path ไฟล์จริงอยู่ในโฟลเดอร์):
1. ฐานข้อมูล `webemcc`
2. โฟลเดอร์ `writable/storage` (รูป ข่าว ไฟล์แนบ ไฟล์ดาวน์โหลด)
3. ไฟล์ `.env` (มี `encryption.key`) เก็บแยกในที่ปลอดภัย

ตัวอย่างสำรองรายวัน (ตั้งใน Task Scheduler / cron เวลากลางคืน):
```bat
:: Windows (XAMPP/MySQL) — ปรับ path ให้ตรงเครื่อง
set D=%date:~-4%%date:~3,2%%date:~0,2%
"C:\xampp\mysql\bin\mysqldump.exe" -u backup -p*** --single-transaction webemcc > D:\backup\webemcc-%D%.sql
robocopy C:\inetpub\emcc\writable\storage D:\backup\files-%D% /MIR
```
```bash
# Linux
mysqldump --single-transaction webemcc | gzip > /backup/webemcc-$(date +%F).sql.gz
tar czf /backup/files-$(date +%F).tar.gz -C /var/www/emcc/writable storage
```
- ใช้บัญชีฐานข้อมูลสำหรับสำรองที่มีสิทธิ์อ่านอย่างเดียว เก็บสำรองไว้ **นอกเครื่องเซิร์ฟเวอร์** อย่างน้อย 30 วัน
- ทดลองกู้คืนอย่างน้อยปีละครั้ง: import SQL ลงฐานทดสอบ + คัดลอกไฟล์กลับ แล้วเปิดหลังบ้านดูรูป/ไฟล์ได้ครบ

## ย้ายจากเวอร์ชัน Laravel
ข้อมูลใช้ต่อได้ (โครงสร้างเดียวกัน, รหัสผ่าน bcrypt เดิมล็อกอินได้):
```bash
php spark emcc:from-laravel     # เก็บตาราง migrations ของ Laravel ไว้เป็น laravel_migrations (รูปแบบต่างกัน)
php spark migrate --all         # ข้ามตารางที่มีอยู่แล้ว
php spark db:seed DatabaseSeeder
```
แล้วคัดลอกไฟล์จาก `storage/app/private` ของ Laravel ไปไว้ที่ `writable/storage`
(โครงโฟลเดอร์ย่อยเหมือนเดิม: `slides`, `popups`, `news`, `news-attachments`, `downloads`, `settings`)

## หลังติดตั้ง
- ล็อกอิน `/backend` ด้วย `developer` / `passwordemcc` แล้วตั้งรหัสใหม่
- แก้ email ของบัญชี developer ให้เป็นของจริง

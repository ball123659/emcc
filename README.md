# EMCC — เว็บไซต์ + ระบบจัดการ (EMCC Backend) · CodeIgniter 4

ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม (Environmental Monitoring & Control Center)

> branch `codeigniter` — เขียนใหม่ด้วย CodeIgniter 4 ให้หน้าตาและการทำงานเหมือนเวอร์ชัน Laravel + Filament (branch `main`)
> ใช้ฐานข้อมูลโครงสร้างเดียวกัน (`database/webemcc.sql`) สลับกันได้

- CodeIgniter 4.7 — ระบบจัดการที่ `/backend`, หน้าเว็บที่ `/`
- PHP 8.3+, MySQL 8.0.16+ หรือ MariaDB 10.4+ (ฐานข้อมูล `webemcc`, `utf8mb4_general_ci`, เวลา Asia/Bangkok)
- รันได้ทั้ง Windows (IIS / XAMPP) และ Linux, ไม่ต้อง build หน้าเว็บ (CSS/JS ล้วน ไลบรารีเก็บในเครื่องที่ `public/assets/vendor`)

## เริ่มใช้งานบนเครื่อง dev

```bash
composer install
cp .env.example .env        # Windows: copy .env.example .env  แล้วแก้ CI_ENVIRONMENT = development และค่าฐานข้อมูล
php spark key:generate
php spark migrate --all
php spark db:seed DatabaseSeeder
php spark serve
```

เปิด http://localhost:8080/backend แล้วเข้าสู่ระบบด้วย `developer` / `passwordemcc` (ระบบบังคับตั้งรหัสใหม่ครั้งแรก)

ตรวจความพร้อมของเครื่อง (php.ini, สิทธิ์โฟลเดอร์, ฐานข้อมูล, สแกนไวรัส):

```bash
php spark emcc:check
```

หลัง `git pull` ทุกครั้ง: `composer install` → `php spark migrate --all`

## โครงสร้างโค้ด

| ส่วน | ที่อยู่ |
|---|---|
| เส้นทาง + การตรวจสิทธิ์ทุกเส้นทาง | `app/Config/Routes.php` (filter `auth`, `page:<code>`, `admin`) |
| หน้าหลังบ้าน | `app/Controllers/Backend/*`, `app/Views/backend/*` |
| ล็อกอิน / ล็อกบัญชี / จดจำฉัน | `app/Libraries/Auth.php` |
| สิทธิ์รายหน้า | `app/Libraries/UserPermissions.php`, `app/Models/UserPagePermissionModel.php` |
| อัปโหลดรูป/ไฟล์ + สแกนไวรัส | `app/Libraries/Uploads/*`, `app/Libraries/VirusScan/*` |
| บันทึกการใช้งาน (อัตโนมัติ) | `app/Models/BaseModel.php`, `app/Libraries/ActivityLogger.php` |
| ธีม (สีม่วง/เขียว ฟอนต์ Prompt) | `public/assets/css/admin.css`, `public/assets/js/admin.js` |
| ค่าตั้งค่าระบบ | `app/Config/Emcc.php` (แก้ผ่าน `.env` เช่น `emcc.virusScanner`) |

## ฐานข้อมูล

- โครงสร้างต้นฉบับคือ `database/webemcc.sql` — migrations ใน `app/Database/Migrations` สร้างโครงสร้างเหมือนกันทุกตาราง
  (CI ตรวจให้ทั้ง MySQL 8 และ MariaDB 10.4) ถ้าเปลี่ยนโครงสร้างต้องแก้ทั้งสองที่
- **ไม่ใช้ trigger** (MariaDB 10.4 / XAMPP crash ตอน INSERT users) แถวสิทธิ์รายหน้าค่าเริ่มต้น `can_view = 0`
  สร้างในโค้ด: เพิ่มผู้ใช้ผ่าน `UserModel` / เพิ่มหน้าผ่าน `PageModel` ได้แถวอัตโนมัติ
  ถ้าเพิ่มข้อมูลด้วย SQL ตรง ๆ ให้รัน `php spark emcc:fill-permissions`
- import `database/webemcc.sql` มาก่อนแล้ว: ไม่ต้องรัน migrate (โครงสร้างครบแล้ว) แต่ให้รัน `php spark db:seed DatabaseSeeder` ได้ (ข้ามข้อมูลที่มีอยู่)
- ตาราง `map_*` เป็นของระบบแผนที่ (dev อีกคน) ไม่อยู่ใน migrations ชุดนี้
- ไฟล์อัปโหลดเก็บที่ `writable/storage` (นอก public) ฐานข้อมูลเก็บแค่ path

## ทดสอบ

```bash
vendor/bin/phpunit
```

ใช้ฐานข้อมูลทดสอบแยก (ตั้ง `database.tests.*` ใน `.env`) — ฐานนั้นจะถูกล้างทุกเทสต์

## ติดตั้งบนเซิร์ฟเวอร์

ดู [docs/DEPLOY.md](docs/DEPLOY.md)

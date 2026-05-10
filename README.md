[README.md](https://github.com/user-attachments/files/27567956/README.md)
# 🏠 ระบบจัดการหอพัก (Dormitory Management System)

ระบบบริหารจัดการหอพักครบวงจร พัฒนาด้วย PHP + MySQL รองรับการจัดการห้องพัก, ผู้เช่า, สัญญา, ค่าน้ำ-ไฟ, การชำระเงิน, แจ้งซ่อม, และการแจ้งเตือนผ่าน LINE OA

---

## 📋 สารบัญ

1. [ความต้องการของระบบ](#-ความต้องการของระบบ)
2. [โครงสร้างโปรเจกต์](#-โครงสร้างโปรเจกต์)
3. [การติดตั้งสภาพแวดล้อม (XAMPP)](#-การติดตั้งสภาพแวดล้อม-xampp)
4. [การตั้งค่าฐานข้อมูล](#-การตั้งค่าฐานข้อมูล)
5. [การตั้งค่าโปรเจกต์](#-การตั้งค่าโปรเจกต์)
6. [การตั้งค่า LINE OA (ไม่บังคับ)](#-การตั้งค่า-line-oa-ไม่บังคับ)
7. [การตั้งค่า Google OAuth (ไม่บังคับ)](#-การตั้งค่า-google-oauth-ไม่บังคับ)
8. [การตั้งค่า Cron Job (งานอัตโนมัติ)](#-การตั้งค่า-cron-job-งานอัตโนมัติ)
9. [การ Deploy บน Apache Server จริง](#-การ-deploy-บน-apache-server-จริง)
10. [การ Deploy บน VPS (Ubuntu)](#-การ-deploy-บน-vps-ubuntu)
11. [การตั้งค่าหลังติดตั้ง](#-การตั้งค่าหลังติดตั้ง)
12. [ข้อมูลเข้าสู่ระบบเริ่มต้น](#-ข้อมูลเข้าสู่ระบบเริ่มต้น)
13. [การแก้ปัญหาที่พบบ่อย](#-การแก้ปัญหาที่พบบ่อย)

---

## 💻 ความต้องการของระบบ

| Component | เวอร์ชันที่รองรับ |
|-----------|----------------|
| PHP | 8.0 ขึ้นไป (แนะนำ 8.2) |
| MySQL | 5.7 ขึ้นไป หรือ MariaDB 10.4+ |
| Apache | 2.4 ขึ้นไป |
| Extension PHP | PDO, PDO_MySQL, mbstring, gd, curl, json, zip, openssl |

---

## 📁 โครงสร้างโปรเจกต์

```
dormitory_management-1/
├── Login.php                  # หน้าล็อกอินหลัก
├── index.php                  # หน้าแรก (Dashboard)
├── ConnectDB.php              # การเชื่อมต่อฐานข้อมูล
├── config.php                 # ตั้งค่า Host / Protocol
├── LineHelper.php             # ส่งข้อความ LINE OA
├── phpqrcode.php              # สร้าง QR Code
├── auto_generate_expenses.php # สร้างค่าใช้จ่ายอัตโนมัติ
│
├── Public/                    # หน้าสาธารณะ (ผู้เช่าเข้าถึงได้)
│   ├── rooms.php              # ดูห้องว่าง
│   ├── booking.php            # จองห้อง
│   ├── booking_status.php     # ตรวจสอบสถานะการจอง
│   └── news.php               # ข่าวประกาศ
│
├── Reports/                   # หน้าจัดการ (Admin)
│   ├── dashboard.php          # ภาพรวมระบบ
│   ├── manage_rooms.php       # จัดการห้องพัก
│   ├── manage_tenants.php     # จัดการผู้เช่า
│   ├── manage_contracts.php   # จัดการสัญญา
│   ├── manage_payments.php    # จัดการการชำระเงิน
│   ├── manage_utility.php     # จัดการค่าน้ำ-ไฟ
│   ├── manage_repairs.php     # จัดการแจ้งซ่อม
│   ├── manage_expenses.php    # จัดการค่าใช้จ่าย
│   ├── system_settings.php    # ตั้งค่าระบบ
│   └── settings/              # ส่วนย่อยของการตั้งค่า
│
├── Manage/                    # Backend / AJAX handlers
├── includes/                  # ไฟล์ที่ใช้ร่วมกัน (sidebar, header ฯลฯ)
├── langs/                     # ไฟล์ภาษา (th.php, en.php)
├── backups/                   # ไฟล์ SQL backup (ตัวอย่างข้อมูล)
└── logs/                      # Log ไฟล์ระบบ
```

---

## 🛠 การติดตั้งสภาพแวดล้อม (XAMPP)

> วิธีนี้เหมาะสำหรับ **ทดสอบบนเครื่องท้องถิ่น** ก่อน deploy จริง

### ขั้นตอนที่ 1 — ดาวน์โหลดและติดตั้ง XAMPP

1. ไปที่ https://www.apachefriends.org และดาวน์โหลด XAMPP สำหรับ OS ของคุณ
2. ติดตั้งตามปกติ เลือก component: **Apache**, **MySQL**, **PHP**
3. เปิด XAMPP Control Panel แล้วกด **Start** ที่ Apache และ MySQL

### ขั้นตอนที่ 2 — Clone โปรเจกต์

```bash
cd C:\xampp\htdocs        # Windows
# หรือ
cd /opt/lampp/htdocs      # Linux
# หรือ
cd /Applications/XAMPP/htdocs  # macOS

git clone https://github.com/st651102064101-design/dormitory_management-1.git
```

หลัง clone แล้วจะได้โฟลเดอร์ `dormitory_management-1/` ใน htdocs

---

## 🗄 การตั้งค่าฐานข้อมูล

### ขั้นตอนที่ 1 — สร้างฐานข้อมูล

เปิดเบราว์เซอร์ไปที่ `http://localhost/phpmyadmin` แล้วทำดังนี้:

1. คลิก **New** (หรือ "ใหม่") ในเมนูด้านซ้าย
2. ตั้งชื่อฐานข้อมูลว่า `dormitory_management_db`
3. เลือก Collation เป็น `utf8mb4_general_ci`
4. คลิก **Create**

หรือใช้คำสั่ง SQL:

```sql
CREATE DATABASE dormitory_management_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
```

### ขั้นตอนที่ 2 — Import ข้อมูล

นำเข้าไฟล์ SQL ที่อยู่ในโฟลเดอร์ `backups/` (เลือกไฟล์ล่าสุด):

**วิธีที่ 1 — ผ่าน phpMyAdmin:**
1. เลือก database `dormitory_management_db`
2. คลิกแท็บ **Import**
3. กด **Choose File** เลือกไฟล์ `.sql` จากโฟลเดอร์ `backups/`
4. คลิก **Go**

**วิธีที่ 2 — ผ่าน Command Line:**
```bash
mysql -u root -p dormitory_management_db < backups/backup_2025-12-11_16-46-58.sql
```

### ขั้นตอนที่ 3 — ตรวจสอบการเชื่อมต่อ (ConnectDB.php)

ไฟล์ `ConnectDB.php` ตั้งค่าไว้แล้วดังนี้ หากใช้ XAMPP มาตรฐาน **ไม่ต้องแก้ไข**:

```php
$host = 'localhost';
$port = '3306';
$db   = 'dormitory_management_db';
$user = 'root';
$pass = '';  // XAMPP ค่าเริ่มต้นไม่มีรหัสผ่าน
```

> ⚠️ **หากใช้งานจริง (Production)** ให้เปลี่ยน `$user` และ `$pass` เป็น MySQL user ที่มีสิทธิ์เฉพาะฐานข้อมูลนี้ และ **อย่าใช้ root บน server จริง**

---

## ⚙️ การตั้งค่าโปรเจกต์

### ไฟล์ config.php

เปิดไฟล์ `config.php` และตั้งค่าตามสภาพแวดล้อมของคุณ:

```php
// ถ้าใช้ XAMPP บนเครื่องตัวเอง — ปล่อยว่างไว้ได้เลย (ระบบจะ auto-detect)
define('SITE_HOST', '');
define('SITE_PROTOCOL', '');

// ถ้า deploy บน server จริง ใส่ domain ของคุณ:
define('SITE_HOST', 'yourdomain.com');   // เช่น 'dormitory.mysite.com'
define('SITE_PROTOCOL', 'https');        // 'https' หรือ 'http'
```

### ทดสอบการเข้าถึง (XAMPP)

เปิดเบราว์เซอร์ไปที่:
- หน้าล็อกอิน Admin: `http://localhost/dormitory_management-1/Login.php`
- หน้าสาธารณะ: `http://localhost/dormitory_management-1/Public/rooms.php`

---

## 📱 การตั้งค่า LINE OA (ไม่บังคับ)

ระบบรองรับการแจ้งเตือนผ่าน LINE OA เช่น แจ้งครบกำหนดชำระ, ยืนยันการจอง ฯลฯ

### ขั้นตอนที่ 1 — สร้าง LINE OA และ Messaging API Channel

1. ไปที่ https://developers.line.biz → เข้าสู่ระบบด้วยบัญชี LINE
2. สร้าง Provider ใหม่ (ถ้ายังไม่มี)
3. สร้าง Channel ประเภท **Messaging API**
4. ในหน้า Channel settings คัดลอก:
   - **Channel access token** (Long-lived token)
   - **Channel secret**

### ขั้นตอนที่ 2 — บันทึกค่าในระบบ

เข้าสู่ระบบ Admin แล้วไปที่ **ตั้งค่าระบบ → LINE OA** แล้วกรอก:
- Channel Access Token
- Channel Secret

---

## 🔐 การตั้งค่า Google OAuth (ไม่บังคับ)

ระบบรองรับ Login ด้วยบัญชี Google สำหรับผู้เช่า

### ขั้นตอนที่ 1 — สร้าง Google OAuth Client

1. ไปที่ https://console.cloud.google.com
2. สร้าง Project ใหม่ (หรือเลือก Project ที่มีอยู่)
3. ไปที่ **APIs & Services → Credentials**
4. คลิก **Create Credentials → OAuth 2.0 Client IDs**
5. เลือก Application type: **Web application**
6. เพิ่ม Authorized redirect URI:
   ```
   https://yourdomain.com/dormitory_management-1/Login.php
   ```
7. คัดลอก **Client ID** และ **Client Secret**

### ขั้นตอนที่ 2 — บันทึกค่าในระบบ

เข้าสู่ระบบ Admin → **ตั้งค่าระบบ → Google Auth** แล้วกรอก:
- Google Client ID
- Google Client Secret

---

## ⏰ การตั้งค่า Cron Job (งานอัตโนมัติ)

ระบบมีงานที่ต้องทำงานอัตโนมัติตามเวลา เช่น สร้างค่าใช้จ่ายประจำเดือน, อัปเดตสถานะสัญญา, ส่งแจ้งเตือน

### บน Linux Server

เปิด crontab ด้วยคำสั่ง:
```bash
crontab -e
```

เพิ่มบรรทัดดังนี้ (แก้ path ให้ตรงกับ server ของคุณ):
```cron
# สร้างค่าใช้จ่ายอัตโนมัติ — ทุกวันที่ 1 ของเดือน เวลา 00:05
5 0 1 * * php /var/www/html/dormitory_management-1/auto_generate_expenses.php

# อัปเดตสถานะสัญญา — ทุกวัน เวลา 01:00
0 1 * * * php /var/www/html/dormitory_management-1/Manage/update_contract_status.php

# ยกเลิกการจองที่หมดอายุ — ทุกชั่วโมง
0 * * * * php /var/www/html/dormitory_management-1/Manage/auto_cancel_bookings.php

# ส่งแจ้งเตือนชำระเงิน — ทุกวัน เวลา 09:00
0 9 * * * php /var/www/html/dormitory_management-1/Manage/send_payment_reminders_cron.php
```

### บน Windows (Task Scheduler)

1. เปิด **Task Scheduler** → **Create Basic Task**
2. ตั้งชื่อ เช่น `DormAutoGenerate`
3. เลือก Trigger: **Monthly** (วันที่ 1) หรือ **Daily**
4. Action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\dormitory_management-1\auto_generate_expenses.php`

---

## 🚀 การ Deploy บน Apache Server จริง

> สำหรับการนำขึ้น Production บนเครื่อง server หรือ hosting ที่ใช้ Apache

### ขั้นตอนที่ 1 — อัปโหลดไฟล์

**วิธีที่ 1 — ผ่าน Git (แนะนำ):**
```bash
cd /var/www/html
git clone https://github.com/st651102064101-design/dormitory_management-1.git
```

**วิธีที่ 2 — ผ่าน FTP/SFTP:**
- ใช้ FileZilla หรือโปรแกรม FTP อื่น
- อัปโหลดทุกไฟล์ไปที่ `/var/www/html/dormitory_management-1/`
  (หรือ folder ที่ hosting กำหนด เช่น `public_html/`)

### ขั้นตอนที่ 2 — ตั้งค่า PHP Extensions

ตรวจสอบว่า Apache/PHP มี extension ที่จำเป็น:
```bash
php -m | grep -E "pdo|pdo_mysql|mbstring|gd|curl|json|zip"
```

ถ้าขาด extension ไหน ติดตั้งด้วย (Ubuntu/Debian):
```bash
sudo apt update
sudo apt install php8.2-mysql php8.2-mbstring php8.2-gd php8.2-curl php8.2-zip php8.2-xml
sudo systemctl restart apache2
```

### ขั้นตอนที่ 3 — ตั้งค่าฐานข้อมูลบน Server

```bash
# เข้า MySQL
mysql -u root -p

# สร้างฐานข้อมูลและ user
CREATE DATABASE dormitory_management_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'dorm_user'@'localhost' IDENTIFIED BY 'รหัสผ่านที่แข็งแกร่ง';
GRANT ALL PRIVILEGES ON dormitory_management_db.* TO 'dorm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import ข้อมูล
mysql -u dorm_user -p dormitory_management_db < /var/www/html/dormitory_management-1/backups/backup_2025-12-11_16-46-58.sql
```

### ขั้นตอนที่ 4 — แก้ไข ConnectDB.php บน Server

เปิดไฟล์ `ConnectDB.php` แล้วแก้ไข:
```php
$host = 'localhost';
$db   = 'dormitory_management_db';
$user = 'dorm_user';          // ← เปลี่ยนเป็น user ที่สร้างไว้
$pass = 'รหัสผ่านที่แข็งแกร่ง';  // ← ใส่รหัสผ่านที่ตั้งไว้
```

### ขั้นตอนที่ 5 — แก้ไข config.php บน Server

```php
define('SITE_HOST', 'yourdomain.com');   // ใส่ domain จริงของคุณ
define('SITE_PROTOCOL', 'https');        // ใช้ https ถ้ามี SSL
```

### ขั้นตอนที่ 6 — ตั้งค่า Apache VirtualHost (ถ้าต้องการ subdomain)

สร้างไฟล์ config ใหม่:
```bash
sudo nano /etc/apache2/sites-available/dormitory.conf
```

ใส่เนื้อหา:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/dormitory_management-1

    <Directory /var/www/html/dormitory_management-1>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/dormitory_error.log
    CustomLog ${APACHE_LOG_DIR}/dormitory_access.log combined
</VirtualHost>
```

เปิดใช้งาน:
```bash
sudo a2ensite dormitory.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### ขั้นตอนที่ 7 — ตั้งค่า Permission ของโฟลเดอร์

```bash
cd /var/www/html/dormitory_management-1

# ให้ Apache เขียนไฟล์ได้ในโฟลเดอร์ที่จำเป็น
sudo chown -R www-data:www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;

# โฟลเดอร์ที่ต้องเขียนได้
sudo chmod -R 775 backups/
sudo chmod -R 775 logs/
sudo chmod -R 775 Reports/payment-proof/
sudo chmod -R 775 Public/Assets/uploads/ 2>/dev/null || true
```

---

## 🖥 การ Deploy บน VPS (Ubuntu)

> กรณีเช่า VPS และต้องติดตั้ง LAMP Stack ตั้งแต่ต้น

### ขั้นตอนที่ 1 — ติดตั้ง LAMP Stack

```bash
sudo apt update && sudo apt upgrade -y

# ติดตั้ง Apache
sudo apt install -y apache2

# ติดตั้ง MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation   # ตั้งค่าความปลอดภัย (แนะนำให้ทำ)

# ติดตั้ง PHP 8.2
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-mysql php8.2-mbstring php8.2-gd \
     php8.2-curl php8.2-zip php8.2-xml php8.2-json libapache2-mod-php8.2

# เปิดใช้ mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### ขั้นตอนที่ 2 — ตรวจสอบการติดตั้ง

```bash
php -v          # ควรแสดง PHP 8.2.x
mysql --version # ควรแสดง MySQL 8.x หรือ MariaDB 10.x
apache2 -v      # ควรแสดง Apache/2.4.x
```

### ขั้นตอนที่ 3 — Clone และตั้งค่า (ต่อจาก Deploy บน Apache ด้านบน)

ทำตามขั้นตอนที่ 1–7 ในหัวข้อ "การ Deploy บน Apache Server จริง" ได้เลย

### ขั้นตอนที่ 4 — ติดตั้ง SSL (Let's Encrypt — ฟรี)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com

# ตรวจสอบ auto-renewal
sudo certbot renew --dry-run
```

หลังจากได้ SSL แล้ว อย่าลืมแก้ `config.php`:
```php
define('SITE_PROTOCOL', 'https');
```

---

## 🔧 การตั้งค่าหลังติดตั้ง

หลังติดตั้งเสร็จ เข้าสู่ระบบในฐานะ Admin แล้วไปที่ **ตั้งค่าระบบ** เพื่อกำหนด:

- **ข้อมูลหอพัก**: ชื่อหอ, โลโก้, ข้อมูลติดต่อ
- **อัตราค่าน้ำ-ไฟ**: ราคาต่อหน่วยน้ำ และไฟฟ้า
- **การแจ้งเตือน**: เปิด/ปิด LINE OA, วันที่ครบกำหนดชำระ
- **ธีม**: สีและรูปแบบหน้าเว็บสาธารณะ
- **Session timeout**: กำหนดเวลา logout อัตโนมัติ

---

## 🔑 ข้อมูลเข้าสู่ระบบเริ่มต้น

> ⚠️ **สำคัญ: เปลี่ยนรหัสผ่านทันทีหลังติดตั้ง!**

| Username | Password | บทบาท |
|----------|----------|--------|
| `admin01` | `123456` | ผู้ดูแลระบบ |
| `admin02` | `admin@2025` | ผู้ดูแลระบบ |

URL เข้าสู่ระบบ: `http://yourdomain.com/dormitory_management-1/Login.php`

---

## 🐛 การแก้ปัญหาที่พบบ่อย

### ❌ หน้าเว็บขึ้น "Connection failed"

ตรวจสอบ:
1. MySQL กำลังทำงานอยู่หรือไม่ (`sudo systemctl status mysql`)
2. ชื่อ database ถูกต้องใน `ConnectDB.php` — ต้องเป็น `dormitory_management_db`
3. Username/Password ถูกต้องหรือไม่

### ❌ หน้าขึ้น 403 Forbidden

```bash
sudo chmod -R 755 /var/www/html/dormitory_management-1/
sudo chown -R www-data:www-data /var/www/html/dormitory_management-1/
```

### ❌ ภาษาไทยแสดงผลเป็นเครื่องหมาย ???

ตรวจสอบว่า MySQL ใช้ charset `utf8mb4`:
```sql
ALTER DATABASE dormitory_management_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### ❌ อัปโหลดรูปไม่ได้ / ไฟล์ใหญ่เกิน

แก้ไข `php.ini`:
```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 120
```

บน Ubuntu ไฟล์อยู่ที่ `/etc/php/8.2/apache2/php.ini` แล้ว restart Apache:
```bash
sudo systemctl restart apache2
```

### ❌ LINE ส่งข้อความไม่ได้

1. ตรวจสอบว่ากรอก Channel Access Token ถูกต้องใน System Settings
2. ตรวจสอบว่า server มีการเชื่อมต่ออินเทอร์เน็ตออกได้ (curl ออก)
3. ตรวจสอบ log ที่ `logs/payment_reminder.log`

### ❌ Cron Job ไม่ทำงาน

```bash
# ตรวจสอบ log ของ cron
sudo tail -f /var/log/syslog | grep CRON

# ทดสอบรัน script ด้วยมือก่อน
php /var/www/html/dormitory_management-1/auto_generate_expenses.php
```

---

## 📞 ติดต่อ / รายงานปัญหา

หากพบปัญหาการใช้งาน สามารถเปิด Issue ได้ที่ GitHub Repository ของโปรเจกต์นี้

# 📱 LINE OAuth Setup Guide - สำคัญ!

## 🔴 ปัญหา: 400 Bad Request
**สาเหตุ:** `redirect_uri` ไม่ตรงกับที่ลงทะเบียนใน LINE Developers Console

---

## ✅ วิธีแก้ (ทำเป็นลำดับ)

### 📍 ขั้นที่ 1: ตรวจสอบ Redirect URI ที่ถูกต้อง

เปิด URL นี้ในเบราว์เซอร์:
```
http://project.3bbddns.com:36140/dormitory_management/debug_oauth.php
```

**หมายเหตุ:** ใช้ `http://` (ไม่ใช่ `https://`) เรื่องนี้เริ่มต้น เนื่องจากปัญหา SSL/TLS บนพอร์ต 36140
- ถ้ายังคงมีปัญหา → ดู [SSL_TROUBLESHOOTING.md](SSL_TROUBLESHOOTING.md)

**คุณจะเห็น:**
- ✅ Configuration Settings (ค่าที่ตั้งในระบบ)
- ✅ Server Information (ข้อมูล Server ปัจจุบัน)
- ✅ **LINE Login Callback URI** ← **คัดลอก URL นี้!**

---

### 📍 ขั้นที่ 2: แนวทาง A - ถ้าใช้ HTTPS Domain ที่มี Port

**แนวทาง:** ตั้งค่า HTTPS domain ใน config.php

1. เปิดไฟล์ `config.php` ด้วย VS Code
   - ค้นหา: `SITE_HOST` (บรรทัดประมาณ 8-18)

2. แก้ไขเป็น:
```php
define('SITE_HOST', 'project.3bbddns.com:36140');
define('SITE_PROTOCOL', 'https');
```

3. บันทึกไฟล์ (Ctrl+S)

4. ลบ Browser Cache:
   - ปิด Browser ทั้งหมด
   - หรือเปิด Private/Incognito Window

5. เปิดเว็บใหม่ และ refresh `debug_oauth.php`
   - ตรวจสอบว่า LINE Login Callback URI ถูกต้อง

---

### 📍 ขั้นที่ 2B: หรือใช้ HTTP (ถ้า HTTPS มีปัญหา)

ถ้าเจอ `ERR_SSL_PROTOCOL_ERROR` ให้ใช้ HTTP แทน:

```php
define('SITE_HOST', 'project.3bbddns.com:36140');
define('SITE_PROTOCOL', 'http');  // ← ใช้ HTTP แทน
```

ดู [SSL_TROUBLESHOOTING.md](SSL_TROUBLESHOOTING.md) สำหรับข้อมูลเพิ่มเติม

---

### 📍 ขั้นที่ 3: ลงทะเบียน Redirect URI ใน LINE Developers

**ขั้นตอน:**

1. ไปเข้า [LINE Developers Console](https://developers.line.biz/console/)
   
2. เลือก Channel ของคุณ
   
3. ไปที่ **Messaging API** หรือ **LINE Login** แล้วเลือก **Channel settings**
   
4. หา **OAuth2.0 settings** หรือ **Redirect URI**
   
5. ใส่ LINE Login Callback URI จาก debug_oauth.php ลงไป:
   ```
   https://project.3bbddns.com:36140/dormitory_management/line_callback.php
   ```
   
6. **บันทึก/Save**

---

### 📍 ขั้นที่ 4: ทดสอบ LINE Login

1. เปิด booking page:
   ```
   https://project.3bbddns.com:36140/dormitory_management/Public/booking.php
   ```

2. กด "ผูกบัญชี LINE" (LINE Link Button)

3. ควรจะ redirect ไป LINE OAuth โดยไม่มี 400 Error แล้ว ✅

---

## 🆘 ถ้ายังคงเกิด 400 Error

### ✓ ตรวจสอบตามนี้:

- [ ] HTTPS Certificate ถูกต้องหรือไม่? (บ้าง domain อาจใช้ Self-signed)
- [ ] ค่า redirect_uri ที่กำหนดใน LINE Developers ตรงกับ debug_oauth.php หรือไม่? (ต้องตรงทุกตัวอักษร)
- [ ] ค่าที่ใส่ใน config.php ถูกต้องหรือไม่?
- [ ] ลบ Browser Cache/Cookies แล้วหรือยัง?

---

## 📚 For Google OAuth (ถ้าใช้งาน)

ทำเดียวกันแต่ไปลงทะเบียนใน **Google Cloud Console**:
```
https://project.3bbddns.com:36140/dormitory_management/google_callback.php
```

---

## 🔧 Test Command

```bash
# ตรวจสอบว่า PHP สร้าง URL ถูกไหม
curl https://project.3bbddns.com:36140/dormitory_management/debug_oauth.php
```

---

**❓ ยังมีปัญหา?** Comment URL จาก debug_oauth.php ให้ฉันดูว่า redirect_uri ถูกต้องไหม

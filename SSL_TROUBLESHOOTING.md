# 🔧 SSL/TLS Certificate Issue - Troubleshooting

## 🔴 Problem: ERR_SSL_PROTOCOL_ERROR
```
เว็บไซต์ไม่สามารถอ่านข้อมูลจากเซิร์ฟเวอร์ได้ขณะเชื่อมต่อ
ERR_SSL_PROTOCOL_ERROR
```

**สาเหตุ:** Port 36140 ไม่สามารถใช้ SSL/TLS (HTTPS) ได้อย่างถูกต้อง

---

## ✅ Solution: ใช้ HTTP แทน HTTPS

### ขั้นที่ 1: ตั้งค่า config.php ✓ (Already Done)
```php
define('SITE_HOST', 'project.3bbddns.com:36140');
define('SITE_PROTOCOL', 'http');  // ← ใช้ HTTP แทน HTTPS
```

### ขั้นที่ 2: ล้าง Browser Cache
- ปิด Browser ทั้งหมด
- **หรือ** เปิด Private/Incognito Window

### ขั้นที่ 3: เข้า Debug Page ใหม่
```
http://project.3bbddns.com:36140/dormitory_management/debug_oauth.php
```

✅ ควรเข้าได้แล้ว - ตรวจสอบ LINE Login Callback URI

---

## 🔐 ถ้าต้องการใช้ HTTPS (Production)

### Option A: ติดตั้ง Valid SSL Certificate
```bash
# ใช้ Let's Encrypt (ฟรี)
sudo certbot certonly --standalone -d project.3bbddns.com
```

### Option B: ใช้ Reverse Proxy (Nginx/Apache) 
```
HTTP (port 36140) → HTTPS (reverse proxy)
```

### Option C: ถ้าใช้ Self-signed Certificate
```bash
# Browser: คลิก "Advanced" → "Proceed anyway"
# Postman/cURL: เพิ่ม flag -k (insecure)
```

---

## 📋 Redirect URI ที่ถูกต้อง (ปัจจุบัน)
```
LINE:   http://project.3bbddns.com:36140/dormitory_management/line_callback.php
Google: http://project.3bbddns.com:36140/dormitory_management/google_callback.php
```

✅ ต้องลงทะเบียน URI นี้ใน LINE/Google Developers Console!

---

## 🧪 Test Commands

```bash
# ตรวจสอบ SSL Certificate
openssl s_client -connect project.3bbddns.com:36140

# ตรวจสอบ HTTP port
curl -v http://project.3bbddns.com:36140/dormitory_management/debug_oauth.php

# ตรวจสอบ HTTPS port (ถ้ามี)
curl -k -v https://project.3bbddns.com:36140/dormitory_management/debug_oauth.php
```

---

## ⏭️ Next Steps

1. ✅ เข้า debug_oauth.php - ตรวจสอบ redirect_uri
2. ✅ ลงทะเบียน redirect_uri ใน LINE/Google Developers
3. ✅ ทดสอบ LINE Login ใหม่

**ถ้ายังมีปัญหา:** แบ่งปันข้อมูลจาก debug_oauth.php ให้ฉันดู

# Payment Reminder Setup

ระบบได้เพิ่มฟีเจอร์แจ้งเตือนชำระเงินแบบไม่ต้องให้ผู้เช่าเข้าหน้าเว็บก่อน โดยรองรับการส่งผ่าน Webhook และมี fallback เป็นการบันทึกคิวลง log

## สิ่งที่เพิ่มแล้ว

1. ปุ่มส่งแจ้งเตือนในหน้า `Reports/manage_expenses.php`
2. API สำหรับส่งแจ้งเตือนแบบ batch: `Manage/send_payment_reminders.php`
3. Service กลางสำหรับเตรียมข้อความ/ส่ง webhook/บันทึก log: `includes/payment_reminder_service.php`
4. สคริปต์สำหรับ cron: `Manage/send_payment_reminders_cron.php`

## ช่องทางส่งแจ้งเตือน

ช่องทางหลักคือ Webhook โดยตั้งค่าผ่าน environment variables:

- `PAYMENT_REMINDER_WEBHOOK_URL` URL ปลายทางสำหรับรับ JSON payload
- `PAYMENT_REMINDER_WEBHOOK_TOKEN` (ไม่บังคับ) Bearer token สำหรับ Authorization header
- `PAYMENT_REMINDER_SENDER_NAME` (ไม่บังคับ) ชื่อผู้ส่งในข้อความแจ้งเตือน
- `PAYMENT_REMINDER_INCLUDE_PORTAL_URL` (ไม่บังคับ) ใส่ `1` หากต้องการแนบลิงก์ Tenant Portal

หากไม่ตั้ง `PAYMENT_REMINDER_WEBHOOK_URL` ระบบจะบันทึกเป็นคิวลง log แทน

## Log การทำงาน

- ไฟล์ log: `logs/payment_reminder.log`

## ใช้งานจากหน้าเว็บ (Manual)

1. เปิดหน้า `Reports/manage_expenses.php`
2. กดปุ่ม `ส่งแจ้งเตือน`
3. ระบบจะส่งเฉพาะรายการสถานะที่ต้องจ่าย (`0`, `3`, `4`) ในหน้าปัจจุบัน

## ใช้งานผ่าน Cron

ตัวอย่างคำสั่ง:

```bash
/Applications/XAMPP/xamppfiles/bin/php Manage/send_payment_reminders_cron.php --month=2026-03 --limit=200
```

ทดสอบแบบไม่ส่งจริง:

```bash
/Applications/XAMPP/xamppfiles/bin/php Manage/send_payment_reminders_cron.php --month=2026-03 --dry-run
```

## โครงสร้าง payload (ย่อ)

Webhook จะได้รับข้อมูลสำคัญ เช่น:

- `event`
- `expense_id`
- `tenant` (ชื่อ, โทรศัพท์, อีเมลถ้ามี)
- `contract`
- `billing` (เดือน, วันครบกำหนด, ยอดคงค้าง)
- `bank`
- `message`
- `meta`

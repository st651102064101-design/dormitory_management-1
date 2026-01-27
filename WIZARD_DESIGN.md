# ออกแบบระบบ Wizard สำหรับจัดการผู้เช่า (5 ขั้นตอน)

## วัตถุประสงค์
สร้างระบบที่ทำให้ Admin จัดการผู้เช่าได้ง่ายที่สุด โดยแบ่งกระบวนการเป็น 5 ขั้นตอนชัดเจน

---

## Workflow ทั้งหมด

```
[1. ยืนยันจอง] → [2. ยืนยันชำระเงินจอง] → [3. สร้างสัญญา] → [4. เช็คอิน] → [5. เริ่มบิลรายเดือน]
     ↓                    ↓                       ↓                 ↓                  ↓
  ล็อกห้อง           ออกใบเสร็จ               สร้าง PDF          บันทึกมิเตอร์      สร้าง expense แรก
```

---

## ขั้นตอนที่ 1: ยืนยันจอง (Confirm Booking)

### จุดประสงค์:
- อนุมัติการจองของผู้เช่า
- ล็อกห้องไม่ให้คนอื่นจองซ้ำ
- สร้างยอดเงินจองที่ต้องชำระ

### การทำงาน:
1. Admin เลือกรายการจอง (booking) ที่มีสถานะ "รอการยืนยัน"
2. กดปุ่ม "ยืนยันจอง"
3. ระบบทำ:
   - อัปเดต `booking.bkg_status = 1` (ยืนยันแล้ว)
   - อัปเดต `room.room_status = 2` (จองแล้ว)
   - สร้างรายการใน `booking_payment` (เงินจองที่ต้องชำระ)
   - อัปเดต `tenant.tnt_status = 3` (จองห้อง)

### ข้อมูลที่เกี่ยวข้อง:
- **booking**: bkg_id, bkg_date, bkg_status, room_id, tnt_id
- **booking_payment**: bp_id, bp_amount, bp_status (0=รอชำระ, 1=ชำระแล้ว), bkg_id
- **room**: room_status (2 = จองแล้ว)
- **tenant**: tnt_status (3 = จองห้อง)

### SQL สำหรับตาราง booking_payment:
```sql
CREATE TABLE IF NOT EXISTS booking_payment (
    bp_id INT AUTO_INCREMENT PRIMARY KEY,
    bp_amount DECIMAL(10,2) NOT NULL DEFAULT 2000.00,
    bp_status ENUM('0','1') DEFAULT '0' COMMENT '0=รอชำระ, 1=ชำระแล้ว',
    bp_payment_date DATETIME NULL,
    bp_proof VARCHAR(255) NULL COMMENT 'หลักฐานการชำระเงิน',
    bp_receipt_no VARCHAR(50) NULL,
    bkg_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bkg_id) REFERENCES booking(bkg_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ขั้นตอนที่ 2: ยืนยันชำระเงินจอง (Confirm Payment)

### จุดประสงค์:
- ตรวจสอบหลักฐานการชำระเงินจอง
- ออกใบเสร็จ

### การทำงาน:
1. ผู้เช่าแนบหลักฐานการชำระเงินจองผ่าน Tenant Portal
2. Admin เห็นรายการรอตรวจสอบ
3. Admin ตรวจสอบหลักฐาน แล้วกด "อนุมัติ"
4. ระบบทำ:
   - อัปเดต `booking_payment.bp_status = 1`
   - อัปเดต `booking_payment.bp_payment_date = NOW()`
   - สร้างเลขใบเสร็จ `bp_receipt_no`

### ข้อมูลที่เกี่ยวข้อง:
- **booking_payment**: bp_proof (path to file), bp_status, bp_receipt_no

---

## ขั้นตอนที่ 3: สร้างสัญญา (Create Contract)

### จุดประสงค์:
- สร้างสัญญาเช่าอย่างเป็นทางการ
- สร้าง PDF สัญญา

### การทำงาน:
1. Admin กดปุ่ม "สร้างสัญญา" จากรายการที่ชำระเงินจองแล้ว
2. ระบบแสดงฟอร์ม:
   - วันเริ่มสัญญา (default = วันนี้)
   - ระยะเวลา (3, 6, 12 เดือน)
   - เงินประกัน (default = 2000 บาท)
3. กด "สร้างสัญญา"
4. ระบบทำ:
   - สร้าง record ใน `contract` (ctr_status = 0)
   - สร้าง PDF สัญญาและบันทึกลง `contract.contract_pdf_path`
   - อัปเดต `tenant.tnt_status = 2` (รอเข้าพัก)

### ข้อมูลที่เกี่ยวข้อง:
- **contract**: ctr_id, ctr_start, ctr_end, ctr_deposit, ctr_status, tnt_id, room_id, contract_pdf_path

### เพิ่มฟิลด์ใน contract:
```sql
ALTER TABLE contract
ADD COLUMN contract_pdf_path VARCHAR(255) NULL COMMENT 'path ไฟล์ PDF สัญญา',
ADD COLUMN contract_created_date DATETIME DEFAULT CURRENT_TIMESTAMP;
```

---

## ขั้นตอนที่ 4: เช็คอิน (Check-in)

### จุดประสงค์:
- บันทึกมิเตอร์น้ำ-ไฟเริ่มต้น
- ถ่ายรูปสภาพห้องก่อนเข้าพัก
- บันทึกการส่งมอบกุญแจ

### การทำงาน:
1. Admin กดปุ่ม "เช็คอิน" จากรายการที่มีสัญญาแล้ว
2. ระบบแสดงฟอร์ม:
   - มิเตอร์น้ำเริ่มต้น
   - มิเตอร์ไฟเริ่มต้น
   - อัปโหลดรูปสภาพห้อง (หลายรูป)
   - เลขกุญแจ
   - หมายเหตุ
3. กด "บันทึกเช็คอิน"
4. ระบบทำ:
   - สร้าง record ใน `checkin_record`
   - อัปเดต `tenant.tnt_status = 1` (พักอยู่)
   - อัปเดต `room.room_status = 1` (ไม่ว่าง)

### SQL สำหรับตาราง checkin_record:
```sql
CREATE TABLE IF NOT EXISTS checkin_record (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    checkin_date DATE NOT NULL,
    water_meter_start DECIMAL(10,2) NOT NULL,
    elec_meter_start DECIMAL(10,2) NOT NULL,
    room_images TEXT NULL COMMENT 'JSON array ของ path รูปภาพ',
    key_number VARCHAR(50) NULL,
    notes TEXT NULL,
    ctr_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ctr_id) REFERENCES contract(ctr_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ขั้นตอนที่ 5: เริ่มบิลรายเดือน (Start Monthly Billing)

### จุดประสงค์:
- สร้างบิลรายเดือนแรก
- ตั้งรอบบิล
- เปิดระบบแจ้งเตือน

### การทำงาน:
1. Admin กดปุ่ม "เริ่มบิลรายเดือน"
2. ระบบแสดงข้อมูล:
   - รอบบิล: เดือนถัดไป
   - ค่าห้อง: จาก roomtype
   - อัตราน้ำ-ไฟ: จาก rate table
3. กด "ยืนยัน"
4. ระบบทำ:
   - สร้าง record แรกใน `expense` (exp_month = เดือนถัดไป, exp_status = 0)
   - เปิดใช้งาน auto-generate expense สำหรับสัญญานี้

### ข้อมูลที่เกี่ยวข้อง:
- **expense**: exp_id, exp_month, exp_status, ctr_id
- **rate**: rate_water, rate_elec

---

## หน้า Wizard หลัก (`/Reports/tenant_wizard.php`)

### แสดงข้อมูล:
- ตารางแสดงรายการผู้เช่าทั้งหมดที่กำลังอยู่ในกระบวนการ
- แต่ละแถวแสดง:
  - ชื่อผู้เช่า
  - เลขห้อง
  - สถานะปัจจุบัน (step indicator: 1-5)
  - ปุ่มสำหรับขั้นตอนถัดไป

### ตัวอย่าง UI:
```
┌───────────────────────────────────────────────────────────────────────┐
│ จัดการผู้เช่า - Wizard                                                │
├───────────────────────────────────────────────────────────────────────┤
│ ชื่อผู้เช่า   │ ห้อง │ ① → ② → ③ → ④ → ⑤ │ ขั้นตอนถัดไป             │
├───────────────┼──────┼────────────────────┼──────────────────────────┤
│ นาย A         │ 101  │ ✓   ✓   ○   ○   ○  │ [สร้างสัญญา]              │
│ นาง B         │ 102  │ ✓   ○   ○   ○   ○  │ [ยืนยันชำระเงินจอง]       │
│ นางสาว C      │ 103  │ ○   ○   ○   ○   ○  │ [ยืนยันจอง]               │
└───────────────┴──────┴────────────────────┴──────────────────────────┘
```

---

## ตาราง Tracking สถานะ (`tenant_workflow`)

### SQL:
```sql
CREATE TABLE IF NOT EXISTS tenant_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tnt_id VARCHAR(20) NOT NULL,
    bkg_id INT NULL,
    step_1_confirmed BOOLEAN DEFAULT FALSE COMMENT 'ยืนยันจอง',
    step_1_date DATETIME NULL,
    step_2_confirmed BOOLEAN DEFAULT FALSE COMMENT 'ยืนยันชำระเงินจอง',
    step_2_date DATETIME NULL,
    step_3_confirmed BOOLEAN DEFAULT FALSE COMMENT 'สร้างสัญญา',
    step_3_date DATETIME NULL,
    step_4_confirmed BOOLEAN DEFAULT FALSE COMMENT 'เช็คอิน',
    step_4_date DATETIME NULL,
    step_5_confirmed BOOLEAN DEFAULT FALSE COMMENT 'เริ่มบิลรายเดือน',
    step_5_date DATETIME NULL,
    current_step INT DEFAULT 1 COMMENT 'ขั้นตอนปัจจุบัน (1-5)',
    completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tnt_id) REFERENCES tenant(tnt_id) ON DELETE CASCADE,
    FOREIGN KEY (bkg_id) REFERENCES booking(bkg_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ไฟล์ที่ต้องสร้าง

### 1. Frontend (หน้า UI):
- `/Reports/tenant_wizard.php` - หน้าหลักแสดง wizard
- `/Reports/wizard_step1.php` - ยืนยันจอง
- `/Reports/wizard_step2.php` - ยืนยันชำระเงินจอง
- `/Reports/wizard_step3.php` - สร้างสัญญา
- `/Reports/wizard_step4.php` - เช็คอิน
- `/Reports/wizard_step5.php` - เริ่มบิลรายเดือน

### 2. Backend (ประมวลผล):
- `/Manage/process_wizard_step1.php` - ประมวลผลยืนยันจอง
- `/Manage/process_wizard_step2.php` - ประมวลผลยืนยันชำระเงินจอง
- `/Manage/process_wizard_step3.php` - ประมวลผลสร้างสัญญา
- `/Manage/process_wizard_step4.php` - ประมวลผลเช็คอิน
- `/Manage/process_wizard_step5.php` - ประมวลผลเริ่มบิลรายเดือน

### 3. Database:
- `database_updates.sql` - SQL สำหรับสร้างตาราง/เพิ่มฟิลด์ใหม่

---

## การติดตั้ง

1. รัน `database_updates.sql` เพื่อสร้างตารางใหม่
2. เพิ่มเมนู "จัดการผู้เช่า (Wizard)" ใน sidebar
3. ทดสอบแต่ละขั้นตอน

---

## ข้อดี

✅ Admin ทำงานง่าย - ไม่ต้องจำขั้นตอน
✅ ไม่พลาดขั้นตอนสำคัญ
✅ ติดตามความคืบหน้าได้ชัดเจน
✅ ลดความผิดพลาด
✅ ประวัติการทำงานครบถ้วน

---

## การพัฒนาต่อ (Phase 2)

- แจ้งเตือน SMS/LINE เมื่อเสร็จแต่ละขั้นตอน
- Dashboard แสดงสถิติผู้เช่าแต่ละขั้นตอน
- Export รายงาน Excel/PDF
- Timeline view แสดงประวัติทั้งหมด

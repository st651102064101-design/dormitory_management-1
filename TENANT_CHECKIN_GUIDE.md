# Tenant Check-in Information Display System

## 📋 Overview
Tenants can now view the check-in information (meter readings, room photos, key number, and notes) that administrators record during the check-in process. This creates transparency and allows tenants to:
- See their starting meter readings for utilities
- Review the condition documentation of their room
- Know their assigned key number
- Understand the check-in date

---

## 🔄 Complete Data Flow

### 1. **Admin Records Check-in** (Admin Portal)
**Location:** [Reports/tenant_wizard.php](Reports/tenant_wizard.php)
- Admin opens the Tenant Wizard dashboard
- Finds the tenant's booking with Step 4 incomplete
- Clicks the Step 4 button to open the check-in modal
- Fills in:
  - Check-in date (วันที่เช็คอิน)
  - Water meter reading (มิเตอร์น้ำ)
  - Electric meter reading (มิเตอร์ไฟฟ้า)
  - Key number (เลขกุญแจ) - optional
  - Room photos (up to multiple images)
  - Additional notes (หมายเหตุ)
- Submits the form

### 2. **Data Saved to Database**
**Process:** [Manage/process_wizard_step4.php](Manage/process_wizard_step4.php)
- Images uploaded to: `/Public/Assets/Images/Checkin/`
- Data stored in `checkin_record` table:
  ```
  - checkin_id (auto)
  - checkin_date
  - water_meter_start
  - elec_meter_start
  - room_images (JSON array of paths)
  - key_number
  - notes
  - ctr_id (contract ID)
  - created_by (admin username)
  ```
- Updates workflow to Step 4 complete
- Marks tenant as "living" (tnt_status = 1)
- Marks room as "occupied" (room_status = 1)

### 3. **Tenant Accesses Check-in Info** (Tenant Portal)
**Location:** [Tenant/index.php](Tenant/index.php) → "ข้อมูลเช็คอิน" menu button

**Then views:** [Tenant/checkin.php](Tenant/checkin.php)

---

## 📱 Tenant Portal Menu

### Main Page Layout (`Tenant/index.php`)
```
┌─────────────────────────────────────┐
│         Header (Room Info)          │
├─────────────────────────────────────┤
│         Services Section            │
│  ┌─────────┬─────────┬─────────┐   │
│  │ Profile │ Check-in│ Repairs │   │ ← NEW: ข้อมูลเช็คอิน
│  ├─────────┼─────────┼─────────┤   │
│  │ Payment │Terminate│         │   │
│  └─────────┴─────────┴─────────┘   │
├─────────────────────────────────────┤
│       Reports Section               │
│  ┌─────────┬─────────┬─────────┐   │
│  │  Room   │  News   │  Bills  │   │
│  ├─────────┼─────────┼─────────┤   │
│  │Contract │ Utility │         │   │
│  └─────────┴─────────┴─────────┘   │
└─────────────────────────────────────┘
```

### Check-in Page Layout (`Tenant/checkin.php`)

```
┌─────────────────────────────────────┐
│  ← Back | Check-in Details          │
├─────────────────────────────────────┤
│  Room Info Card                     │
│  🏠 ห้อง 104                        │
│  👤 สมศรี สมหวัง                    │
│  📱 08-xxxx-xxxx                    │
│  💰 Single Room - 5,000 บาท/เดือน  │
│  ✅ เช็คอินแล้ว  OR  ⏳ ยังไม่เช็คอิน│
├─────────────────────────────────────┤
│  Contract Period                    │
│  📅 Start: 15/01/2024               │
│  📅 End:   14/01/2025               │
├─────────────────────────────────────┤
│  Check-in Date                      │
│  📆 20/01/2024                      │
├─────────────────────────────────────┤
│  Meter Readings (Starting Values)   │
│  ┌──────────────┬──────────────┐   │
│  │ 💧 Water     │ ⚡ Electric   │   │
│  │ 123.45 units │ 456.78 units │   │
│  └──────────────┴──────────────┘   │
├─────────────────────────────────────┤
│  Key Number                         │
│  🔑 K-204                           │
├─────────────────────────────────────┤
│  Room Photos (Condition Docs)       │
│  [Image 1] [Image 2] [Image 3]      │ ← Clickable for fullscreen
├─────────────────────────────────────┤
│  Additional Notes                   │
│  "ห้องในสภาพดี ไม่มีปัญหา"          │
└─────────────────────────────────────┘
```

---

## 🗄️ Database Schema

### `checkin_record` Table
```sql
CREATE TABLE checkin_record (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    checkin_date DATE NOT NULL,
    water_meter_start DECIMAL(10,2) NOT NULL,
    elec_meter_start DECIMAL(10,2) NOT NULL,
    room_images TEXT NULL,  -- JSON array: ["path1.jpg", "path2.jpg"]
    key_number VARCHAR(50) NULL,
    notes TEXT NULL,
    ctr_id INT NOT NULL,  -- Links to contract
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ctr_id (ctr_id),
    INDEX idx_checkin_date (checkin_date)
);
```

---

## 🔐 Access Control

### Tenant Access
- Access via **QR Code with token** (from contract's `access_token`)
- Token passed as URL parameter: `checkin.php?token=<access_token>`
- Only sees their own check-in data
- Can view photos in fullscreen modal

### Admin Access
- Full admin portal only
- Can view, create, edit check-in records
- Can see all tenants' check-in information

---

## 🎯 What Tenants See

### If Check-in NOT Yet Done:
- Shows "⏳ ยังไม่มีข้อมูลการเช็คอิน"
- Displays helpful message: "กรุณารอให้ผู้ดูแลหอพักทำการเช็คอินห้องของคุณ"
- Shows when they can expect the data

### If Check-in COMPLETED:
- ✅ Status badge shows "เช็คอินแล้ว"
- All sections visible:
  1. Check-in date
  2. Water meter start reading
  3. Electric meter start reading
  4. Key number (if assigned)
  5. Room photos (with fullscreen viewer)
  6. Admin notes

---

## 📸 Features

### Image Viewing
- Grid display of all check-in photos
- Click any image to view full-screen
- Close with button or Escape key
- Click outside modal to close
- Responsive design for mobile viewing

### Date Display
- All dates formatted as DD/MM/YYYY (Thai format)
- Contract start and end dates clearly shown
- Check-in date separate from contract dates

### Meter Display
- Water and electric meters side-by-side
- Formatted with 2 decimal places
- Shows units clearly
- Color-coded sections (cyan/teal)

---

## 🔧 Technical Stack

- **PHP 7.4+** (PDO database access)
- **MySQL 5.7+** (data storage)
- **HTML5 / CSS3** (dark theme responsive design)
- **Vanilla JavaScript** (modal functionality)
- **Responsive Design** (mobile-optimized)

---

## 📝 Implementation Summary

### Files Created:
1. **[Tenant/checkin.php](Tenant/checkin.php)** - Tenant-facing check-in details page

### Files Modified:
1. **[Tenant/index.php](Tenant/index.php)** - Added menu button for check-in info

### Related Files (Already Implemented):
- **[Manage/process_wizard_step4.php](Manage/process_wizard_step4.php)** - Saves check-in data
- **[Reports/tenant_wizard.php](Reports/tenant_wizard.php)** - Admin interface for recording check-in
- **Database table:** `checkin_record` - Stores all check-in information

---

## ✨ User Experience Flow

### For Tenant (Using QR Code):
1. Scans QR code from contract
2. Lands on tenant portal
3. Clicks "ข้อมูลเช็คอิน" button
4. Sees check-in details (or waiting message)
5. Can view meter readings and room photos
6. Understands starting utility meter values
7. Can reference conditions documented at check-in

### For Admin:
1. Opens Tenant Wizard dashboard
2. Filters incomplete bookings
3. Finds tenant's step 4
4. Records meter readings and photos
5. Adds key number and notes
6. Submits form
7. System confirms → Tenant can now see the data

---

## 🚀 Future Enhancements

Potential improvements:
- [ ] Meter reading history and graph trending
- [ ] Periodic meter check-ups at month-end
- [ ] Automatic utility charge calculation
- [ ] Export check-in report as PDF
- [ ] Move-out inspection comparison
- [ ] Photo upload from tenant side
- [ ] Digital signature for check-in acknowledgment


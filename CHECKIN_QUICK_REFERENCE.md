# Tenant Check-in Information - Quick Reference

## 🎯 The Question: "Where do tenants see check-in data that admin recorded?"

### Answer: Tenant Portal → "ข้อมูลเช็คอิน" Menu Item

---

## 📍 Location Path

```
Tenant Portal (accessed via QR code)
    ↓
Main Menu (index.php)
    ↓
Services Section
    ↓
[ข้อมูลเช็คอิน] ← NEW BUTTON
    ↓
Check-in Details Page (checkin.php)
```

---

## 🔄 Complete Workflow

### ADMIN SIDE:
```
1. Admin Dashboard
   ↓
2. Reports → Tenant Wizard
   ↓
3. Click tenant's Step 4 button
   ↓
4. Check-in Modal opens
   ↓
5. Fill:
   - วันที่เช็คอิน (date)
   - มิเตอร์น้ำ (water meter)
   - มิเตอร์ไฟฟ้า (electric meter)
   - เลขกุญแจ (key number)
   - รูปภาพห้อง (room photos)
   - หมายเหตุ (notes)
   ↓
6. Submit → Data saved to checkin_record table
```

### TENANT SIDE:
```
1. Scan QR Code from contract
   ↓
2. Access tenant portal
   ↓
3. Click "ข้อมูลเช็คอิน" button
   ↓
4. View check-in page showing:
   - ✅ Status: เช็คอินแล้ว or ⏳ ยังไม่เช็คอิน
   - 📅 Check-in date
   - 💧 Water meter start reading
   - ⚡ Electric meter start reading
   - 🔑 Key number
   - 📸 Room photos (clickable)
   - 📝 Admin notes
```

---

## 📊 Data Display by Status

### If Admin HAS recorded check-in:
✅ All sections appear with full information
```
- Check-in date: 20/01/2024
- Water: 123.45 units
- Electric: 456.78 units
- Key: K-204
- Photos: [Grid of images]
- Notes: "Room in good condition..."
```

### If Admin HAS NOT yet recorded check-in:
⏳ Empty state shown
```
"ยังไม่มีข้อมูลการเช็คอิน"
(No check-in data yet)

Please wait for the dormitory staff
to record your room's check-in data.
```

---

## 🎨 Visual Design

**Dark theme matching tenant portal**
- Blue gradient room info card
- Teal color for check-in section icon
- Cyan/blue meter reading boxes
- Green indicator when check-in complete
- Red indicator when check-in pending

**Responsive mobile layout**
- Full-width sections
- 2-column meter grid
- Auto-wrap image gallery
- Modal image viewer

---

## 🔐 Security

- **Token-based access**: Each tenant has unique `access_token` from contract
- **Session-based**: Token passed as URL parameter
- **Database security**: LEFT JOIN ensures only associated data shown
- **Status check**: Only shows contracts with status 0 or 2 (active)

---

## 📁 Files Involved

| File | Purpose |
|------|---------|
| `Tenant/index.php` | Main portal menu (added button) |
| `Tenant/checkin.php` | ✨ NEW - Check-in details display |
| `Manage/process_wizard_step4.php` | Saves check-in data (unchanged) |
| `Reports/tenant_wizard.php` | Admin check-in recording UI (unchanged) |
| `checkin_record` table | Database storage |

---

## 🚀 How to Test

1. **As Admin:**
   - Go to Reports → Tenant Wizard
   - Click a booking's Step 4 button
   - Fill in check-in details with test data
   - Submit (data saved)

2. **As Tenant:**
   - Use the access_token from that contract
   - Access: `Tenant/checkin.php?token=<access_token>`
   - See the check-in details you just recorded

---

## 📲 Example Tenant URL

```
http://localhost/dormitory_management/Tenant/checkin.php?token=abc123def456
```

Where `abc123def456` is the contract's `access_token`

---

## ✨ Key Features

✅ **Meter Reference** - Tenants see starting meter values
✅ **Photo Documentation** - Visual proof of room condition
✅ **Key Assignment** - Clear key number display
✅ **Admin Notes** - Any special instructions visible
✅ **Status Indicator** - Clear if check-in is done
✅ **Mobile Optimized** - Works on all device sizes
✅ **Photo Fullscreen** - Can zoom in on images
✅ **Thai Language** - All text in Thai

---

## 💡 What This Solves

**Before:**
- Tenants didn't know their starting meter values
- No way to see room condition documentation
- Unclear when key was assigned
- No transparency in check-in process

**After:**
- Tenants see exact meter starting values
- Can review room condition via photos
- Know their assigned key number
- Full transparency in onboarding process
- Reference for future utility billing disputes

---

## 🔄 Integration Point

The check-in data flows from:
```
Admin Records (Step 4 modal)
    ↓
process_wizard_step4.php saves to checkin_record
    ↓
Tenant views via checkin.php
    ↓
Tenants understand their starting point
```

Complete transparency in the tenant onboarding workflow! ✨


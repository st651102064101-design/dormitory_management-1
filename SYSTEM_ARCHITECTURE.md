# Tenant Check-in System - Visual Architecture

## 🏗️ System Architecture Diagram

```
┌────────────────────────────────────────────────────────────────┐
│                      DORMITORY SYSTEM                          │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌─────────────────────────┐    ┌─────────────────────────┐  │
│  │     ADMIN PORTAL        │    │    TENANT PORTAL        │  │
│  │   (Admin Dashboard)     │    │    (QR Code Access)     │  │
│  └──────────────┬──────────┘    └──────────────┬──────────┘  │
│                 │                              │             │
│                 │ Logs in                      │ Scans QR    │
│                 ↓                              ↓             │
│  ┌────────────────────────────┐  ┌───────────────────────┐  │
│  │ Reports/tenant_wizard.php  │  │ Tenant/index.php      │  │
│  │   (Wizard Dashboard)       │  │ (Main Portal)         │  │
│  └──────────────┬─────────────┘  └─────────┬─────────────┘  │
│                 │                          │               │
│                 │ Click Step 4             │ Click         │
│                 │ (Check-in)              │ "ข้อมูลเช็คอิน" │
│                 ↓                          ↓               │
│  ┌────────────────────────────┐  ┌──────────────────────┐   │
│  │ Check-in Modal Form        │  │ Tenant/checkin.php   │   │
│  │ ─────────────────────      │  │ (Check-in Details)   │   │
│  │ • วันที่เช็คอิน             │  │ ──────────────────  │   │
│  │ • มิเตอร์น้ำ               │  │ Displays:            │   │
│  │ • มิเตอร์ไฟฟ้า             │  │ ✅ Check-in Status  │   │
│  │ • เลขกุญแจ                 │  │ 📅 Check-in Date    │   │
│  │ • รูปภาพห้อง               │  │ 💧 Water Meter      │   │
│  │ • หมายเหตุ                 │  │ ⚡ Electric Meter   │   │
│  └──────────────┬──────────────┘  │ 🔑 Key Number      │   │
│                 │                 │ 📸 Room Photos     │   │
│                 │ Submit           │ 📝 Notes           │   │
│                 ↓                  └─────────┬──────────┘   │
│  ┌────────────────────────────┐            │               │
│  │ process_wizard_step4.php   │            │ Query         │
│  │ (Process Check-in)         │            │ Data          │
│  │ ─────────────────────────  │            │               │
│  │ • Validate data            │            ↓               │
│  │ • Upload images            │  ┌──────────────────────┐   │
│  │ • Save to database         │  │  checkin_record      │   │
│  │ • Update workflow          │  │  Table (DB)          │   │
│  │ • Update tenant status     │  │  ─────────────────  │   │
│  │ • Mark room occupied       │  │  ✅ Data Retrieved  │   │
│  └──────────────┬─────────────┘  └──────────────────────┘   │
│                 │                                            │
│                 ↓                                            │
│  ┌───────────────────────────────┐                          │
│  │   checkin_record Table        │                          │
│  │   ─────────────────────────  │                          │
│  │   • checkin_id               │                          │
│  │   • checkin_date             │                          │
│  │   • water_meter_start        │                          │
│  │   • elec_meter_start         │                          │
│  │   • room_images (JSON)       │                          │
│  │   • key_number               │                          │
│  │   • notes                    │                          │
│  │   • ctr_id (FK to contract)  │                          │
│  │   • created_by               │                          │
│  │   • timestamps               │                          │
│  └───────────────────────────────┘                          │
│                                                              │
└────────────────────────────────────────────────────────────┘
```

---

## 🔄 Complete User Journey

### Admin: Record Check-in
```
START
  ↓
[Login to Admin]
  ↓
[Go to Reports → Tenant Wizard]
  ↓
[See list of incomplete bookings]
  ↓
[Find tenant's Step 4 button]
  ↓
[Click button → Modal opens]
  ↓
[Fill in check-in details:
  - Date: 20/01/2024
  - Water: 123.45
  - Electric: 456.78
  - Key: K-204
  - Upload 3 photos
  - Notes: "Good condition"
]
  ↓
[Click Submit]
  ↓
[Images uploaded to /Public/Assets/Images/Checkin/]
  ↓
[Data saved to checkin_record table]
  ↓
[Workflow Step 4 marked complete]
  ↓
[Tenant marked as "living"]
  ↓
[Room marked as "occupied"]
  ↓
[Success message displayed]
  ↓
[Redirect to Tenant Wizard dashboard]
  ↓
END
```

### Tenant: View Check-in Details
```
START
  ↓
[Receive QR code from contract]
  ↓
[Scan QR with phone camera]
  ↓
[Browser opens with token in URL]
  ↓
[Lands on Tenant Portal]
  ↓
[See main menu with 6 service buttons]
  ↓
[Click "ข้อมูลเช็คอิน" button]
  ↓
[Request sent: GET Tenant/checkin.php?token=xxx]
  ↓
[Server validates token against contract]
  ↓
[Query: SELECT FROM checkin_record WHERE ctr_id = ?]
  ↓
[DECISION: Check-in exists?]
  │
  ├─ YES: Display all data
  │       • Status: ✅ เช็คอินแล้ว
  │       • Contract dates
  │       • Check-in date
  │       • Water meter: 123.45
  │       • Electric meter: 456.78
  │       • Key: K-204
  │       • 3 room photos (clickable)
  │       • Notes: "Good condition"
  │
  └─ NO: Display empty state
        • Status: ⏳ ยังไม่เช็คอิน
        • "Wait for admin to record check-in"
  ↓
[Tenant reviews information]
  ↓
[Can click photos for fullscreen view]
  ↓
[Can scroll and read admin notes]
  ↓
[Click back button to return to main menu]
  ↓
END
```

---

## 📊 Data Flow Sequence Diagram

```
┌─────────┐              ┌──────────────┐           ┌────────────┐
│ Admin   │              │   Database   │           │   Tenant   │
└────┬────┘              └──────┬───────┘           └─────┬──────┘
     │                          │                        │
     │ 1. Fill check-in form   │                        │
     │                          │                        │
     │ 2. POST to Step 4       │                        │
     ├─────────────────────────>│                        │
     │                          │                        │
     │                          │ 3. Validate           │
     │                          │                        │
     │                          │ 4. Save to            │
     │                          │    checkin_record     │
     │                          │                        │
     │ 5. Success message      │                        │
     │<─────────────────────────┤                        │
     │                          │                        │
     │ (Admin redirected)       │                        │
     │                          │                        │
     │                          │                        │
     │                          │   6. Scan QR Code     │
     │                          │   ──────────────────> │
     │                          │                        │
     │                          │   7. GET checkin.php  │
     │                          │   with token          │
     │                          │   ──────────────────> │
     │                          │                        │
     │                          │ 8. Validate token    │
     │                          │                        │
     │                          │ 9. Query check-in    │
     │                          │    data              │
     │    10. checkin_record   │                        │
     │<─────────────────────────┤                        │
     │                          │                        │
     │                          │ 11. Render HTML       │
     │                          │ with data             │
     │                          │                        │
     │                          │ 12. Display to Tenant │
     │                          │<─────────────────────>│
     │                          │                        │
     │                          │    13. Tenant views   │
     │                          │    meter readings,    │
     │                          │    photos, notes      │
     │                          │                        │
```

---

## 🗂️ File Structure

```
dormitory_management/
│
├── Tenant/
│   ├── index.php                    ← Modified: Added menu item
│   ├── checkin.php                  ← NEW: Displays check-in details
│   ├── contract.php
│   ├── profile.php
│   ├── repair.php
│   ├── payment.php
│   └── ... (other tenant pages)
│
├── Manage/
│   └── process_wizard_step4.php      ← Used: Saves check-in data
│
├── Reports/
│   └── tenant_wizard.php             ← Used: Admin check-in form
│
├── Public/Assets/Images/
│   └── Checkin/                      ← Directory: Stores check-in photos
│       ├── room_104_001.jpg
│       ├── room_104_002.jpg
│       └── room_104_003.jpg
│
├── TENANT_CHECKIN_GUIDE.md           ← Documentation
├── CHECKIN_QUICK_REFERENCE.md        ← Quick ref
└── IMPLEMENTATION_COMPLETE.md         ← This summary
```

---

## 🔐 Security Flow

```
┌────────────────────────────────────┐
│ Tenant Receives QR Code            │
│ Contains: Access Token             │
└────────────────┬───────────────────┘
                 │
                 ↓
       ┌─────────────────────┐
       │ Scans QR Code       │
       │ URL includes token  │
       └────────────┬────────┘
                    │
                    ↓
       ┌──────────────────────────┐
       │ checkin.php receives:    │
       │ $_GET['token'] = 'xxx'  │
       └────────────┬─────────────┘
                    │
                    ↓
       ┌──────────────────────────┐
       │ Validate token in DB     │
       │ Query: SELECT FROM       │
       │ contract WHERE           │
       │ access_token = ?         │
       └────────────┬─────────────┘
                    │
         ┌──────────┴──────────┐
         │                     │
         ↓                     ↓
    ┌─────────┐          ┌──────────┐
    │ VALID   │          │ INVALID  │
    │         │          │          │
    │ Proceed │          │ Error 403│
    │ Get data│          │ Exit     │
    └─────────┘          └──────────┘
```

---

## 📈 Status Flow

```
┌──────────────────┐
│ Tenant Created   │
└────────┬─────────┘
         │
         ↓
┌──────────────────────┐
│ Booking Created      │
│ Step 1: Auto-Complete│
└────────┬─────────────┘
         │
         ↓
┌──────────────────────┐
│ Step 2: Payment      │
│ (Before check-in)    │
└────────┬─────────────┘
         │
         ↓
┌──────────────────────┐
│ Step 3: Contract     │
│ (Before check-in)    │
└────────┬─────────────┘
         │
         ↓
┌──────────────────────────────┐
│ Step 4: Check-in             │
│ ┌────────────────────────┐   │
│ │ Admin Records:         │   │
│ │ • Date                 │   │
│ │ • Meter readings       │   │
│ │ • Photos               │   │
│ │ • Key number           │   │
│ │ • Notes                │   │
│ └────────────────────────┘   │
│ ✅ Tenant can now see all    │
│    this info in their portal │
└────────┬─────────────────────┘
         │
         ↓
┌──────────────────────┐
│ Step 5: Billing      │
│ Automatic monthly    │
└──────────────────────┘
```

---

## 🎯 Use Case Scenarios

### Scenario 1: First-time Tenant
```
Day 1: Tenant moves in
  → Admin records check-in with meter readings
  → Tenant receives access instructions

Day 2: Tenant accesses portal
  → Scans QR code
  → Opens tenant portal
  → Clicks "ข้อมูลเช็คอิน"
  → Sees meter starting values
  → Reviews room condition photos
  → Notes key number
  → Understands all initial conditions
```

### Scenario 2: Billing Dispute
```
Month 2: Water bill seems high
  → Tenant accesses check-in page
  → Sees water meter started at: 123.45
  → Current meter: 200.00
  → Usage: 76.55 units
  → Can verify charge is correct

Result: No disputes, transparency achieved
```

### Scenario 3: Move-out Inspection
```
12 months later: Tenant moving out
  → Admin does move-out inspection
  → Can reference move-in photos
  → Can compare room condition
  → Fair assessment of damages
  → Documented proof of original condition
```

---

## 📱 Mobile Responsive Layout

```
Desktop (1200px+):
┌────────────────────────────────────┐
│  Header  │  Room Card  │  Right    │
├────────────────────────────────────┤
│ Sections in 2-column layout        │
│ Images in 4-column grid            │
└────────────────────────────────────┘

Tablet (768px):
┌──────────────────────┐
│  Header              │
├──────────────────────┤
│  Room Card (full)    │
├──────────────────────┤
│  Sections            │
│  (2-column grid)     │
├──────────────────────┤
│  Images (3-column)   │
└──────────────────────┘

Mobile (320px):
┌──────────────┐
│  ← Header    │
├──────────────┤
│  Room Card   │
├──────────────┤
│  Section     │
├──────────────┤
│  Section     │
├──────────────┤
│  Images (1-2)│
└──────────────┘
```

---

## ✨ Summary

**The complete system enables:**

1. **Admins** to record detailed check-in data (meter readings, photos, notes)
2. **Data storage** in a structured database table
3. **Tenants** to view their complete check-in record with one click
4. **Transparency** in the onboarding process
5. **Documentation** of room condition at move-in
6. **Reference data** for future utility calculations

All with proper security, responsive design, and Thai language support. ✅


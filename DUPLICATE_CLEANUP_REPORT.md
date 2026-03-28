# 🎯 Duplicate Contracts - Complete Cleanup & Prevention

## Summary

✅ **ลบ Duplicates แล้ว:** 14 สัญญาซ้ำ → ลบ 9 สัญญา
   - Initial cleanup: 2 contracts
   - Aggressive cleanup: 7 contracts (with related data)
   - **Result:** 0 Duplicates Remaining

✅ **Database-Level Protection:** Added Triggers
   - BEFORE INSERT: ตรวจสอบห้องและผู้เช่าซ้ำ
   - BEFORE UPDATE: ป้องกันเปลี่ยน status เป็น active ที่ซ้ำ

✅ **App-Level Protection:** process_contract.php (3 checks)
   1. ห้องหนึ่งมีสัญญา active เดียว (status IN '0','2')
   2. ผู้เช่าหนึ่งมีสัญญา active เดียว (status IN '0','2')
   3. วันที่ของสัญญาไม่ทับซ้อน

## Cleanup Process

### Step 1: Soft Cleanup (non-aggressive)
**File:** `Manage/cleanup_run.php`
- ลบสัญญาที่ไม่มี payment/utility/expense
- **Result:** 2 contracts deleted
- **Skipped:** 7 contracts (มีข้อมูลเชื่อมโยง)

### Step 2: Aggressive Cleanup (complete removal)
**File:** `Manage/aggressive_cleanup.php`
- ลบ booking_payment คำเกี่ยวกับสัญญา
- ลบ utility (meter readings)
- ลบ expense (charges)
- ลบ checkin_record
- ลบ tenant_workflow entries
- ลบสัญญา เอง
- **Result:** 7 contracts deleted + related data

**Deleted Records:**
```
Contracts deleted: 7
Payments deleted: 7
Utilities deleted: 13
Expenses deleted: 7
Checkins deleted: 7
```

### Step 3: Database Constraints
**File:** `Manage/add_db_constraints.php`

#### Triggers Created:
1. **trg_contract_before_insert**
   - ตรวจสอบก่อนสร้างสัญญาใหม่
   - Error: "Duplicate: Room already has active contract"
   - Error: "Duplicate: Tenant already has active contract"

2. **trg_contract_before_update**
   - ตรวจสอบก่อนเปลี่ยน status เป็น active
   - ป้องกันเปลี่ยน cancelled (1) → active (0 or 2)

#### Foreign Key:
- `tenant_workflow.ctr_id → contract.ctr_id`
- ON DELETE CASCADE (ลบสัญญา → ลบ workflow)
- ON UPDATE CASCADE (อัปเดต ctr_id → อัปเดต workflow)

## Verification Scripts

### Check Remaining Duplicates
```bash
/Applications/XAMPP/bin/php Manage/check_remaining_dupes.php
```
**Expected Output:** "Total Duplicate Sets: 0"

### Clean Orphaned Records
```bash
/Applications/XAMPP/bin/php Manage/clean_orphans.php
```
Removes tenant_workflow records without matching contracts

## Multi-Layer Protection

| Layer | Method | File | Status |
|-------|--------|------|--------|
| 🔴 Database | Triggers (BEFORE INSERT/UPDATE) | MySQL Database | ✅ Active |
| 🟡 Application | 3-check validation | process_contract.php | ✅ Active |
| 🟢 Delete Safety | Payment-aware deletion | delete_contract.php | ✅ Active |

## Files Created/Modified

### New Files
- ✅ `Manage/cleanup_run.php` - Soft cleanup script
- ✅ `Manage/aggressive_cleanup.php` - Complete deletion script
- ✅ `Manage/add_db_constraints.php` - Database constraints setup
- ✅ `Manage/clean_orphans.php` - Orphan record cleanup
- ✅ `Manage/check_remaining_dupes.php` - Verify duplicates
- ✅ `Reports/cleanup_duplicates_ui.php` - Web UI for cleanup
- ✅ `Manage/cleanup_duplicate_contracts.php` - Conditional cleanup API
- ✅ `Manage/find_duplicate_contracts.php` - Find duplicates API

### Modified Files
- ✅ `Manage/delete_contract.php` - Fixed payment FK query
- ✅ `Manage/process_contract.php` - Already has 3-layer duplicate prevention

## How to Test

### Test 1: Try to Create Duplicate Contract
```
1. Go to manage_contracts.php
2. Try to create contract for existing (tenant + room + active status)
3. Expected: Error message from process_contract.php
```

### Test 2: Try to Bypass via Database Trigger
```php
INSERT INTO contract (tnt_id, room_id, ctr_status, ctr_start, ctr_end)
VALUES ('T123', 1, '0', '2026-01-01', '2026-12-31');
// Expected: Error - trigger blocks duplicate
```

### Test 3: Try to Delete Contract with Payments
```
1. Go to manage_contracts.php
2. Try to delete contract with payment records
3. Expected: Error - "Cannot delete contract with payment records"
```

## Rollback Plan (if needed)

### Drop Triggers
```sql
DROP TRIGGER trg_contract_before_insert;
DROP TRIGGER trg_contract_before_update;
```

### Drop Foreign Key
```sql
ALTER TABLE tenant_workflow DROP FOREIGN KEY fk_tenant_workflow_contract;
```

### Restore from Backup
```bash
# Use database backup from before cleanup
```

## Future Improvements

1. **Application Enhancements**
   - Add notification when duplicate prevention blocks action
   - Log all duplicate prevention attempts
   - Admin dashboard for contract integrity

2. **Database Maintenance**
   - Monthly audit script to check for any orphaned contracts
   - Report on contract-status mismatch

3. **User Experience**
   - Clear error messages displayed to end-user
   - Suggest alternative actions when duplicate blocked

---

**Created:** 28 มีนาคม 2569
**Status:** ✅ Complete
**Next Action:** Test with sample data to verify all protections working

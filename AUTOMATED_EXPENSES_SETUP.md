# Automated Expense Generation System

## Overview
This system automatically generates expense records for all active tenants on the 1st of each month. It creates a record with the current month, room price, and current utility rates (electricity and water). Utility units default to 0 and should be updated manually.

## Components

### 1. **trigger_expense_generation.php** (Recommended)
**Location:** `/dormitory_management/trigger_expense_generation.php`

- Web-accessible endpoint for manual triggering
- Can be accessed via browser or cron job
- Auto-runs on the 1st of each month
- Supports force trigger via GET parameter: `?force=true`

**Usage:**
```
# Manual trigger (works any day):
http://localhost/dormitory_management/trigger_expense_generation.php?force=true

# Auto-trigger (web based):
http://localhost/dormitory_management/trigger_expense_generation.php
```

### 2. **auto_generate_expenses.php** (CLI Only)
**Location:** `/dormitory_management/auto_generate_expenses.php`

- Command-line only script
- More efficient for cron jobs
- Better logging for debugging

**Usage:**
```bash
php /path/to/dormitory_management/auto_generate_expenses.php
```

## Setup Instructions

### Option A: Cron Job (Recommended)

**Step 1: Edit your crontab**
```bash
crontab -e
```

**Step 2: Add one of the following lines:**

**Using trigger_expense_generation.php (web-based):**
```bash
# Run at 00:00 on the 1st of every month
0 0 1 * * curl -s http://localhost/dormitory_management/trigger_expense_generation.php?force=true > /dev/null 2>&1
```

**Or using auto_generate_expenses.php (CLI):**
```bash
# Run at 00:00 on the 1st of every month
0 0 1 * * /usr/bin/php /path/to/dormitory_management/auto_generate_expenses.php >> /var/log/expense_generation.log 2>&1
```

**Step 3: Save and exit**
- Press `Ctrl+X` then `Y` to save (if using nano)
- Or use appropriate commands for your editor

### Option B: Manual Trigger (No Cron)

**Visit this URL in your browser:**
```
http://project.3bbddns.com:36140/dormitory_management/trigger_expense_generation.php
```

You can visit this URL any day to manually trigger the generation. Use `?force=true` to skip the 1st-of-month check.

### Option C: Scheduled Task (Windows)

**For Windows servers, use Task Scheduler:**

1. Open Task Scheduler
2. Create a new task
3. Set trigger: Daily at 00:00
4. Add condition: "On the 1st of each month"
5. Set action: Run program
   ```
   Program: C:\php\php.exe
   Arguments: C:\path\to\dormitory_management\auto_generate_expenses.php
   ```

## How It Works

1. **Checks Date:** Runs only on the 1st of month (unless forced)
2. **Gets Contracts:** Retrieves all active contracts with valid date ranges
3. **Validates:** 
   - Checks if contract is within its date range
   - Checks if expense already exists for the month
4. **Creates Record:** 
   - Sets room price from roomtype table
   - Gets latest electricity and water rates
   - Creates expense with 0 utility units
   - Sets status to '0' (unpaid)
5. **Logs Results:** Reports generated and skipped records

## Expense Record Structure

```
exp_month       : Year-Month-01 (e.g., 2025-01-01)
exp_elec_unit   : 0 (to be updated manually)
exp_water_unit  : 0 (to be updated manually)
rate_elec       : Current electricity rate from rate table
rate_water      : Current water rate from rate table
room_price      : Price from roomtype
exp_elec_chg    : 0 (calculated from units × rate)
exp_water       : 0 (calculated from units × rate)
exp_total       : room_price (updated when utilities added)
exp_status      : '0' (unpaid)
```

## Manual Updates After Generation

After the script generates expense records:

1. Go to **Reports > Manage Expenses**
2. View the generated records
3. Update electricity and water units as needed
4. System automatically calculates totals
5. Records ready for payment collection

## Troubleshooting

### Expenses Not Generating

1. **Check date:** System only runs on 1st of month (unless forced)
2. **Check contracts:** Ensure contracts are active (ctr_status = '0')
3. **Check date ranges:** Contract dates must include current month
4. **Check permissions:** Database user must have INSERT permissions
5. **Check rates:** Ensure rate table has at least one record

### Cron Not Running

1. **Verify crontab:** `crontab -l` to list your cron jobs
2. **Check cron service:** `sudo systemctl status cron` (Linux)
3. **Check logs:** Look in `/var/log/syslog` or cron logs
4. **Test manually:** Run the PHP script directly to verify it works
5. **Check PHP path:** Ensure `/usr/bin/php` is correct (run `which php`)

### Permission Issues

```bash
# Make script executable (optional)
chmod +x /path/to/auto_generate_expenses.php

# Check file ownership
ls -la /path/to/dormitory_management/

# Ensure web server has read permissions
chmod 644 *.php
chmod 755 /path/to/dormitory_management/
```

## Viewing Logs

**For curl-based cron:**
Add output to a log file:
```bash
0 0 1 * * curl -s "http://localhost/dormitory_management/trigger_expense_generation.php?force=true" >> /var/log/expense_generation.log 2>&1
```

**For PHP CLI:**
Check the log file:
```bash
tail -f /var/log/expense_generation.log
```

## API Response

**trigger_expense_generation.php** returns formatted output:
- Web: HTML formatted with emojis
- CLI: Plain text with timestamps
- Exit code: 0 (success) or 1 (error)

## Security Notes

1. **Authentication:** Web version checks admin login
2. **CLI Only:** auto_generate_expenses.php rejects web access
3. **Database:** Uses parameterized queries to prevent SQL injection
4. **Force Parameter:** Only use `?force=true` when needed

## Testing

**Test the system:**
```bash
# Visit with force parameter to test any day
http://localhost/dormitory_management/trigger_expense_generation.php?force=true

# Or run CLI version
php /path/to/auto_generate_expenses.php
```

**Expected output:**
```
✅ Contract 1: ห้อง 2 - เด็กดี ดีดี (ค่าห้อง ฿1500)
✅ Contract 2: ห้อง 3 - นางดี ดี (ค่าห้อง ฿1500)
...
==================================================
✅ สรุป: สร้างสำเร็จ 9 | ข้ามไป 0
```

## FAQ

**Q: Can I run this more than once per month?**
A: Yes, the system checks for existing records and skips them.

**Q: What if I need to regenerate for a past month?**
A: Manually delete the old records from the database, then trigger again with `?force=true`.

**Q: Are utility units automatically filled?**
A: No, they default to 0. Update them manually based on meter readings.

**Q: Can I customize the default rates?**
A: Yes, update the `rate` table in the database.

**Q: What happens to contracts that end mid-month?**
A: The system respects the contract end date and won't create records after it.

---

**Created:** 2025-01-26
**Last Updated:** 2025-01-26

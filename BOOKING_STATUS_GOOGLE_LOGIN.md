# Booking Status Google Login Integration

## Overview
When users log in via Google OAuth, they are now automatically redirected to the booking status page with their booking number displayed.

## Changes Made

### 1. **google_callback.php** - Line 377
**Change:** Redirect destination after tenant login
```php
// OLD: header('Location: /dormitory_management/index.php');
// NEW: header('Location: /dormitory_management/Public/booking_status.php?auto=1');
```
**Reason:** After Google OAuth authentication, tenants should go directly to their booking status page instead of the index page.

### 2. **booking_status.php** - Multiple Changes

#### a. Auto-fill booking number (Lines 167-215)
Added automatic booking number resolution and loading:
- If user is logged in via Google OAuth, the system resolves their `tenant_id` from email
- Fetches all active bookings for that tenant
- If only one booking exists, automatically sets `$bookingRef` to that booking ID
- Automatically loads and displays booking information without requiring form submission

#### b. Auto-submit form (Lines 707-720)
Added JavaScript to automatically submit the form when user has a single booking:
```javascript
// Auto-select first booking if only one exists
document.addEventListener('DOMContentLoaded', function() {
    const select = document.querySelector('select[name="booking_ref"]');
    if (select) {
        select.value = '<?php echo htmlspecialchars($tenantBookings[0]['bkg_id']); ?>';
        select.form.submit();
    }
});
```

#### c. Login status indicator (Lines 706-714)
Added visible confirmation message showing:
- Login success status
- Tenant name or email
- Only shows for logged-in users

#### d. Enhanced booking number display (Lines 749-769)
Added dedicated "🎫 เลขที่การจองของคุณ" section for logged-in users showing:
- Booking ID in large, blue, monospace font
- Clear separation from other information
- Visual indicator that user is logged in

## User Flow

### Google Login → Booking Status
1. User clicks "Login with Google"
2. Google OAuth verification completes
3. System finds tenant account linked to Google email
4. Session variables set:
   - `$_SESSION['tenant_id']`
   - `$_SESSION['tenant_name']`
   - `$_SESSION['tenant_phone']`
   - `$_SESSION['tenant_email']`
   - `$_SESSION['tenant_logged_in'] = true`
5. User redirected to: `/Public/booking_status.php?auto=1`
6. Booking status page:
   - Detects logged-in status
   - Fetches all active bookings for tenant
   - If single booking exists: automatically displays booking details
   - If multiple bookings exist: shows dropdown to select booking
   - Booking number displayed prominently at top

## Session Variables Used

When a user logs in via Google OAuth, these session variables are set:
```php
$_SESSION['tenant_id']           // Tenant ID (e.g., 'T177003915821')
$_SESSION['tenant_name']         // Tenant name
$_SESSION['tenant_phone']        // Tenant phone
$_SESSION['tenant_email']        // Google email used for login
$_SESSION['tenant_picture']      // Google profile picture URL
$_SESSION['tenant_logged_in']    // Boolean: true if logged in
```

## Booking Status Page Logic

### Priority of Information Sources
1. Check `$_SESSION['tenant_id']` - primary identifier
2. Check `$_SESSION['tenant_email']` - fallback to query `tenant_oauth` table
3. Resolve from `tenant_oauth` table by email
4. Attempt to find by tenant name (if available)
5. Attempt to find by phone (if available)

### Auto-loading Booking Information
When user is logged in (`$isTenantLoggedIn = true`):
1. Fetch all active bookings where `bkg_status IN ('1','2')`
2. Set `$bookingRef` to booking ID if single booking exists
3. Automatically load and display booking details
4. Show dropdown if multiple bookings exist

## Database Tables Used

### tenant_oauth
```sql
SELECT oauth_id, tnt_id, provider, provider_email 
FROM tenant_oauth 
WHERE provider = 'google' AND provider_email = ?
```

### booking
```sql
SELECT bkg_id, bkg_date, bkg_checkin_date, bkg_status
FROM booking
WHERE tnt_id = ? AND bkg_status IN ('1','2')
ORDER BY bkg_date DESC
```

## Testing Checklist

- [ ] User can log in with Google
- [ ] After login, redirected to booking_status.php?auto=1
- [ ] Single booking automatically displays
- [ ] Booking number visible at top of page
- [ ] Login status indicator shows
- [ ] Multiple bookings show dropdown
- [ ] Phone number auto-filled in non-logged-in form
- [ ] Booking status information loads correctly
- [ ] All payment information displays

## Browser Session Requirements

For this to work properly:
- PHP sessions must be enabled (`session_start()`)
- Cookies must be enabled in browser
- Same domain for both OAuth callback and booking status page
- Session timeout should be reasonable (typically 1-2 hours)

## Notes

- The booking number is displayed in a special "🎫 เลขที่การจองของคุณ" section only for logged-in users
- Non-logged-in users can still search for their booking by number + phone
- The auto-submit JavaScript handles the multi-booking dropdown case
- Booking status page provides dropdown for multi-booking users to select which one to view

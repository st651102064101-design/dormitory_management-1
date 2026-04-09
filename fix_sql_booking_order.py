import re

with open('Public/booking_status.php', 'r') as f:
    code = f.read()

# Replace all b.bkg_date DESC with b.bkg_id DESC
# This guarantees that if multiple bookings exist on the same day, the newest one is picked.
code = code.replace("ORDER BY b.bkg_date DESC", "ORDER BY b.bkg_id DESC")

with open('Public/booking_status.php', 'w') as f:
    f.write(code)

print("Done")

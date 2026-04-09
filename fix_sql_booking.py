import re

with open('Public/booking_status.php', 'r') as f:
    code = f.read()

# 1. Fix stmtTenantByName
code = re.sub(
    r"LEFT JOIN contract c ON t\.tnt_id = c\.tnt_id AND b\.room_id = c\.room_id\s*WHERE t\.tnt_name = \? AND b\.bkg_status IN \('1','2'\)\s*AND \(c\.ctr_status IS NULL OR c\.ctr_status <> '1'\)",
    r"WHERE t.tnt_name = ? AND b.bkg_status IN ('1','2')",
    code
)

# 2. Fix stmtBookings
code = re.sub(
    r"LEFT JOIN contract c ON b\.room_id = c\.room_id AND t\.tnt_id = c\.tnt_id\s*WHERE \(b\.tnt_id = \? OR t\.tnt_phone = \?\) AND b\.bkg_status IN \('1','2'\)\s*AND \(c\.ctr_status IS NULL OR c\.ctr_status <> '1'\)",
    r"WHERE (b.tnt_id = ? OR t.tnt_phone = ?) AND b.bkg_status IN ('1','2')",
    code
)

# Fix ALL cc.ctr_status = '1' JOINs and conditions
# This removes the line: LEFT JOIN contract cc ON t.tnt_id = cc.tnt_id AND cc.room_id = b.room_id AND cc.ctr_status = '1'
code = re.sub(
    r"\s*LEFT JOIN contract cc ON t\.tnt_id = cc\.tnt_id AND cc\.room_id = b\.room_id AND cc\.ctr_status = '1'",
    "",
    code
)

# Remove AND cc.ctr_id IS NULL logic
code = re.sub(r" AND cc\.ctr_id IS NULL", "", code)
# Make sure contract c matches by room_id
code = re.sub(r"LEFT JOIN contract c ON t\.tnt_id = c\.tnt_id AND c\.ctr_status = '0'", r"LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'", code)

# Also fix the last query to be INNER JOIN so it doesn't return empty bookings
code = re.sub(
    r"FROM tenant t\s*LEFT JOIN booking b ON t\.tnt_id = b\.tnt_id AND b\.bkg_status IN \('1', '2'\)([\s\S]*?)WHERE t\.tnt_phone \= \?\s*ORDER BY b\.bkg_date DESC",
    r"FROM tenant t\n                    INNER JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')\1WHERE t.tnt_phone = ?\n                    ORDER BY b.bkg_date DESC",
    code
)

with open('Public/booking_status.php', 'w') as f:
    f.write(code)

print("Done python script")

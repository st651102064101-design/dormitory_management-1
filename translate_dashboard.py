import re

thai_to_key = {
    'แดชบอร์ด': 'dashboard',
    'กิจกรรมวันนี้': 'today_activity',
    'จองห้องใหม่': 'new_bookings',
    'แจ้งซ่อมใหม่': 'new_repairs',
    'รับชำระเงิน': 'payments_received',
    'อัตราการเข้าพัก': 'occupancy_rate',
    'ห้อง': 'rooms',
    'ห้องว่าง': 'vacant_rooms',
    'จากห้องทั้งหมด': 'out_of_total_rooms',
    'รายได้รวมทั้งหมด': 'total_revenue',
    'จากค่าใช้จ่ายที่ชำระแล้ว': 'from_paid_expenses',
    'ผู้เช่าทั้งหมด': 'total_tenants',
    'สัญญาที่ใช้งาน': 'active_contracts',
    'สัญญา': 'contracts',
    'งานซ่อมที่รอ': 'pending_repairs',
    'ซ่อมเสร็จแล้ว': 'completed_repairs',
    'งาน': 'jobs',
    'สถิติภาพรวม': 'overview_statistics',
    'จองแล้ว': 'booked',
    'เข้าพัก': 'occupied',
    'ยกเลิก': 'cancelled',
    'สถานะห้องพัก': 'room_status',
    'รายงานการแจ้งซ่อม': 'repair_report',
    'รอดำเนินการ': 'pending',
    'กำลังดำเนินการ': 'in_progress',
    'เสร็จสิ้น': 'completed',
    'กราฟรายได้': 'revenue_chart',
    'บาท': 'baht'
}

with open('Reports/dashboard.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Make sure lang.php is included
if 'require_once __DIR__ . \'/../includes/lang.php\';' not in content:
    content = re.sub(r'(require_once __DIR__ \. \'/../ConnectDB.php\';)', r"\1\nrequire_once __DIR__ . '/../includes/lang.php';", content)

for thai, key in thai_to_key.items():
    # Replace in HTML texts: >Thai< to ><?php echo __('key'); ?><
    # Also replace in PHP strings
    # This regex handles cases where Thai text is surrounded by spaces or newlines
    pass

# A simpler approach: Just hardcode replacing the exact strings in the HTML structure
replacements = [
    (r"\$pageTitle\s*=\s*'แดชบอร์ด';", r"$pageTitle = __('dashboard');"),
    (r"กิจกรรมวันนี้\s*\(", r"<?php echo __('today_activity'); ?> ("),
    (r"จองห้องใหม่", r"<?php echo __('new_bookings'); ?>"),
    (r"แจ้งซ่อมใหม่", r"<?php echo __('new_repairs'); ?>"),
    (r"รับชำระเงิน", r"<?php echo __('payments_received'); ?>"),
    (r"อัตราการเข้าพัก", r"<?php echo __('occupancy_rate'); ?>"),
    (r"ห้องว่าง(.*?)ห้อง", r"<?php echo __('vacant_rooms'); ?>\1<?php echo __('rooms'); ?>"),
    (r"ห้องว่าง", r"<?php echo __('vacant_rooms'); ?>"),
    (r"/\s*30\s*ห้อง", r"/ 30 <?php echo __('rooms'); ?>"),
    (r"ห้อง<", r"<?php echo __('rooms'); ?><"),
    (r"รายได้รวมทั้งหมด", r"<?php echo __('total_revenue'); ?>"),
    (r"จากค่าใช้จ่ายที่ชำระแล้ว", r"<?php echo __('from_paid_expenses'); ?>"),
    (r"ผู้เช่าทั้งหมด", r"<?php echo __('total_tenants'); ?>"),
    (r"สัญญาที่ใช้งาน(.*?)สัญญา", r"<?php echo __('active_contracts'); ?>\1<?php echo __('contracts'); ?>"),
    (r"งานซ่อมที่รอ", r"<?php echo __('pending_repairs'); ?>"),
    (r"ซ่อมเสร็จแล้ว(.*?)งาน", r"<?php echo __('completed_repairs'); ?>\1<?php echo __('jobs'); ?>"),
    (r"สถานะห้องพัก", r"<?php echo __('room_status'); ?>"),
    (r"รายงานการแจ้งซ่อม", r"<?php echo __('repair_report'); ?>"),
    (r"กราฟรายได้", r"<?php echo __('revenue_chart'); ?>"),
    (r"รอดำเนินการ", r"<?php echo __('pending'); ?>"),
    (r"กำลังดำเนินการ", r"<?php echo __('in_progress'); ?>"),
    (r"เสร็จสิ้น", r"<?php echo __('completed'); ?>"),
    (r"สถิติภาพรวม", r"<?php echo __('overview_statistics'); ?>"),
    (r"จองแล้ว", r"<?php echo __('booked'); ?>"),
    (r"เข้าพัก", r"<?php echo __('occupied'); ?>"),
    (r"ยกเลิก", r"<?php echo __('cancelled'); ?>"),
    (r"ไม่มีข้อมูลรายได้ประจำเดือน", r"<?php echo __('no_revenue_data'); ?>")
]

for old, new in replacements:
    content = re.sub(old, new, content)

with open('Reports/dashboard.php', 'w', encoding='utf-8') as f:
    f.write(content)


import re

thai_to_key = {
    'ห้องที่ใช้': 'used_rooms',
    'การแจ้งซ่อม': 'repair_requests',
    'ข่าวประชาสัมพันธ์': 'news_announcements',
    'สถานะการแจ้งซ่อม': 'repair_status',
    'สถานะการชำระเงิน': 'payment_status',
    'รายได้รายเดือน': 'monthly_revenue',
    'ประเภทห้องพัก': 'room_types',
    'การใช้น้ำ-ไฟ': 'utility_usage',
    'ผู้เช่าเข้าใหม่': 'new_tenants',
    'ความสำคัญสูง': 'high_priority',
    'งานที่ควรตรวจทุกวัน': 'daily_review_tasks',
    'ใบแจ้งชำระเงิน': 'invoices',
    'รอตรวจสอบ': 'pending_verification',
    'ตรวจสอบแล้ว': 'verified',
    'ดูรายละเอียด →': 'view_details_arrow',
    'รอซ่อม': 'waiting_repair',
    'กำลังซ่อม': 'repairing',
    'เสร็จแล้ว': 'completed_already',
    'รายงานข้อมูลการ': 'occupancy_report',
    'แดชบอร์ด': 'dashboard',
    'ใหม่': 'new_suffix',
    'เดือนล่าสุด': 'last_months',
    'วันล่าสุด': 'last_days',
}

eng_dict = {
    'used_rooms': 'Used Rooms',
    'repair_requests': 'Repair Requests',
    'news_announcements': 'News & Announcements',
    'repair_status': 'Repair Status',
    'payment_status': 'Payment Status',
    'monthly_revenue': 'Monthly Revenue',
    'room_types': 'Room Types',
    'utility_usage': 'Utility Usage',
    'new_tenants': 'New Tenants',
    'high_priority': 'High Priority',
    'daily_review_tasks': 'Tasks to review daily',
    'invoices': 'Invoices',
    'pending_verification': 'Pending Verification',
    'verified': 'Verified',
    'view_details_arrow': 'View Details →',
    'waiting_repair': 'Waiting Repair',
    'repairing': 'Repairing',
    'completed_already': 'Completed',
    'occupancy_report': 'Occupancy Report',
    'new_suffix': 'New',
    'last_months': 'last months',
    'last_days': 'last days'
}

thai_dict = {
    'used_rooms': 'ห้องที่ใช้',
    'repair_requests': 'การแจ้งซ่อม',
    'news_announcements': 'ข่าวประชาสัมพันธ์',
    'repair_status': 'สถานะการแจ้งซ่อม',
    'payment_status': 'สถานะการชำระเงิน',
    'monthly_revenue': 'รายได้รายเดือน',
    'room_types': 'ประเภทห้องพัก',
    'utility_usage': 'การใช้น้ำ-ไฟ',
    'new_tenants': 'ผู้เช่าเข้าใหม่',
    'high_priority': 'ความสำคัญสูง',
    'daily_review_tasks': 'งานที่ควรตรวจทุกวัน',
    'invoices': 'ใบแจ้งชำระเงิน',
    'pending_verification': 'รอตรวจสอบ',
    'verified': 'ตรวจสอบแล้ว',
    'view_details_arrow': 'ดูรายละเอียด →',
    'waiting_repair': 'รอซ่อม',
    'repairing': 'กำลังซ่อม',
    'completed_already': 'เสร็จแล้ว',
    'occupancy_report': 'รายงานข้อมูลการเข้าพัก',
    'new_suffix': 'ใหม่',
    'last_months': 'เดือนล่าสุด',
    'last_days': 'วันล่าสุด'
}

replacements = [
    (r"ห้องที่ใช้", r"<?php echo __('used_rooms'); ?>"),
    (r"การแจ้งซ่อม\<\?php echo __\('pending'\); \?\>", r"<?php echo __('repair_requests'); ?> <?php echo __('pending'); ?>"),
    (r"การแจ้งซ่อม", r"<?php echo __('repair_requests'); ?>"),
    (r"ข่าวประชาสัมพันธ์", r"<?php echo __('news_announcements'); ?>"),
    (r"สถานะการแจ้งซ่อม", r"<?php echo __('repair_status'); ?>"),
    (r"สถานะการชำระเงิน", r"<?php echo __('payment_status'); ?>"),
    (r"รายได้รายเดือน", r"<?php echo __('monthly_revenue'); ?>"),
    (r"ประเภทห้องพัก", r"<?php echo __('room_types'); ?>"),
    (r"การใช้น้ำ-ไฟ \(6 เดือนล่าสุด\)", r"<?php echo __('utility_usage'); ?> (6 <?php echo __('last_months'); ?>)"),
    (r"ผู้เช่าเข้าใหม่ \(7 วันล่าสุด\)", r"<?php echo __('new_tenants'); ?> (7 <?php echo __('last_days'); ?>)"),
    (r"ความสำคัญสูง", r"<?php echo __('high_priority'); ?>"),
    (r"งานที่ควรตรวจทุกวัน", r"<?php echo __('daily_review_tasks'); ?>"),
    (r"ใบแจ้งชำระเงิน", r"<?php echo __('invoices'); ?>"),
    (r"รอตรวจสอบ", r"<?php echo __('pending_verification'); ?>"),
    (r"ตรวจสอบแล้ว", r"<?php echo __('verified'); ?>"),
    (r"ดูรายละเอียด →", r"<?php echo __('view_details_arrow'); ?>"),
    (r"รอซ่อม", r"<?php echo __('waiting_repair'); ?>"),
    (r"กำลังซ่อม", r"<?php echo __('repairing'); ?>"),
    (r"เสร็จแล้ว", r"<?php echo __('completed_already'); ?>"),
    (r"รายงานข้อมูลการ\<\?php echo __\('occupied'\); \?\>", r"<?php echo __('occupancy_report'); ?>"),
    (r"แดชบอร์ด", r"<?php echo __('dashboard'); ?>"),
    (r"\<\?php echo __\('occupied'\); \?\>แล้ว", r"<?php echo __('occupied'); ?>"),
]

with open('Reports/dashboard.php', 'r', encoding='utf-8') as f:
    content = f.read()

for old, new in replacements:
    content = re.sub(old, new, content)

with open('Reports/dashboard.php', 'w', encoding='utf-8') as f:
    f.write(content)

def inject_dict(filename, dct):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    new_keys = []
    for k, v in dct.items():
        if f"'{k}'" not in content:
            new_keys.append(f"    '{k}' => '{v}',")

    if new_keys:
        injection = "\n    // More dashboard additions\n" + "\n".join(new_keys) + "\n"
        content = re.sub(r'\];\s*$', injection + "];\n", content)
        with open(filename, 'w', encoding='utf-8') as f:
            f.write(content)

inject_dict('langs/th.php', thai_dict)
inject_dict('langs/en.php', eng_dict)

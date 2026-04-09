import re

thai_dict = {
    'today_activity': 'กิจกรรมวันนี้',
    'new_bookings': 'จองห้องใหม่',
    'new_repairs': 'แจ้งซ่อมใหม่',
    'payments_received': 'รับชำระเงิน',
    'occupancy_rate': 'อัตราการเข้าพัก',
    'rooms': 'ห้อง',
    'vacant_rooms': 'ห้องว่าง',
    'out_of_total_rooms': 'จากห้องทั้งหมด',
    'total_revenue': 'รายได้รวมทั้งหมด',
    'from_paid_expenses': 'จากค่าใช้จ่ายที่ชำระแล้ว',
    'total_tenants': 'ผู้เช่าทั้งหมด',
    'active_contracts': 'สัญญาที่ใช้งาน',
    'contracts': 'สัญญา',
    'pending_repairs': 'งานซ่อมที่รอ',
    'completed_repairs': 'ซ่อมเสร็จแล้ว',
    'jobs': 'งาน',
    'overview_statistics': 'สถิติภาพรวม',
    'booked': 'จองแล้ว',
    'occupied': 'เข้าพัก',
    'cancelled': 'ยกเลิก',
    'room_status': 'สถานะห้องพัก',
    'repair_report': 'รายงานการแจ้งซ่อม',
    'pending': 'รอดำเนินการ',
    'in_progress': 'กำลังดำเนินการ',
    'completed': 'เสร็จสิ้น',
    'revenue_chart': 'กราฟรายได้',
    'baht': 'บาท',
    'no_revenue_data': 'ไม่มีข้อมูลรายได้ประจำเดือน'
}

eng_dict = {
    'today_activity': "Today's Activity",
    'new_bookings': 'New Bookings',
    'new_repairs': 'New Repairs',
    'payments_received': 'Payments Received',
    'occupancy_rate': 'Occupancy Rate',
    'rooms': 'Rooms',
    'vacant_rooms': 'Vacant Rooms ',
    'out_of_total_rooms': 'of total rooms',
    'total_revenue': 'Total Revenue',
    'from_paid_expenses': 'From paid expenses',
    'total_tenants': 'Total Tenants',
    'active_contracts': 'Active Contracts ',
    'contracts': ' Contracts',
    'pending_repairs': 'Pending Repairs',
    'completed_repairs': 'Completed ',
    'jobs': ' Jobs',
    'overview_statistics': 'Overview Statistics',
    'booked': 'Booked',
    'occupied': 'Occupied',
    'cancelled': 'Cancelled',
    'room_status': 'Room Status',
    'repair_report': 'Repair Report',
    'pending': 'Pending',
    'in_progress': 'In Progress',
    'completed': 'Completed',
    'revenue_chart': 'Revenue Chart',
    'baht': 'THB',
    'no_revenue_data': 'No revenue data for this month'
}

def inject_dict(filename, dct):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # insert before "];" at the end of the file
    new_keys = []
    for k, v in dct.items():
        if f"'{k}'" not in content:
            new_keys.append(f"    '{k}' => '{v}',")
    
    if new_keys:
        injection = "\n    // Dashboard additions\n" + "\n".join(new_keys) + "\n"
        content = re.sub(r'\];\s*$', injection + "];\n", content)
        with open(filename, 'w', encoding='utf-8') as f:
            f.write(content)

inject_dict('langs/th.php', thai_dict)
inject_dict('langs/en.php', eng_dict)

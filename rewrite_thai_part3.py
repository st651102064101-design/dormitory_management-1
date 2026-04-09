import re

thai_to_key = {
    'จองอยู่': 'reserved',
    'ติดตามประจำ': 'regular_followup',
    'การเงินและการใช้ทรัพยากร': 'finance_and_resources',
    'ยอดชำระเงิน': 'payment_amount',
    'รายงานการชำระเงิน': 'payment_report',
    'ยอดชำระทั้งหมด': 'total_payment_amount',
    'รายรับ': 'income',
    'รายงานข้อมูลรายรับ': 'income_report',
    'รายรับทั้งหมด': 'total_income',
    'น้ำ-ไฟ': 'water_electric',
    'รายงานสรุปการใช้น้ำ-ไฟ': 'utility_summary_report',
    'มิเตอร์น้ำ': 'water_meter',
    'เดือนนี้รวม': 'this_month_total',
    'หน่วย': 'units',
    'vs เดือนก่อน': 'vs last month',
    'มิเตอร์ไฟ': 'electric_meter',
    'ข้อมูลอ้างอิง': 'reference_data',
    'ดูภาพรวมและเอกสารประกอบ': 'view_overview_and_docs',
    'ห้องพัก': 'rooms',
    'รายงานข้อมูลห้องพัก': 'room_info_report',
    'ห้องไม่ว่าง': 'occupied_rooms',
    'พิมพ์สัญญา': 'print_contract',
    'สัญญาที่ใช้': 'active_contracts',
    'สิ้นสุดแล้ว': 'ended',
    'พิมพ์สัญญา →': 'print_contract_arrow',
    'รายงานข้อมูล': 'data_report',
    'ข่าวทั้งหมด': 'all_news',
    'ซ่อมเสร็จ': 'completed_repair',
    'รายได้ \(บาท\)': 'revenue_baht',
    'ไม่มีข้อมูล': 'no_data',
    'ใช้งาน': 'active',
    'สิ้นสุด': 'ended_label',
    'ผู้เช่าเข้าใหม่': 'new_tenants',
    'ว่าง': 'vacant',
    'ไม่ว่าง': 'not_vacant',
    'น้ำ \(ยูนิต\)': 'water_units_label',
    'ไฟ \(ยูนิต\)': 'electric_units_label',
    'ยอดชำระ': 'payment_total_label',
    'น้ำ': 'water_label',
    'ไฟ': 'electric_label',
    'ข่าว': 'news_label'
}

eng_dict = {
    'reserved': 'Reserved',
    'regular_followup': 'Regular Follow-up',
    'finance_and_resources': 'Finance & Resources',
    'payment_amount': 'Payment Amount',
    'payment_report': 'Payment Report',
    'total_payment_amount': 'Total Payment',
    'income': 'Income',
    'income_report': 'Income Report',
    'total_income': 'Total Income',
    'water_electric': 'Water/Electric',
    'utility_summary_report': 'Utility Summary Report',
    'water_meter': 'Water Meter',
    'this_month_total': 'This month total',
    'units': 'units',
    'vs_last_month': 'vs last month',
    'electric_meter': 'Electric Meter',
    'reference_data': 'Reference Data',
    'view_overview_and_docs': 'View overview and documents',
    'room_info_report': 'Room Info Report',
    'occupied_rooms': 'Occupied Rooms',
    'print_contract': 'Print Contract',
    'ended': 'Ended',
    'print_contract_arrow': 'Print Contract →',
    'data_report': 'Data Report',
    'all_news': 'All News',
    'completed_repair': 'Completed',
    'revenue_baht': 'Revenue (THB)',
    'no_data': 'No Data',
    'active': 'Active',
    'ended_label': 'Ended',
    'vacant': 'Vacant',
    'not_vacant': 'Occupied',
    'water_units_label': 'Water (Units)',
    'electric_units_label': 'Electric (Units)',
    'payment_total_label': 'Payment Total',
    'water_label': 'Water',
    'electric_label': 'Electric',
    'news_label': 'News'
}

thai_dict = {
    'reserved': 'จองอยู่',
    'regular_followup': 'ติดตามประจำ',
    'finance_and_resources': 'การเงินและการใช้ทรัพยากร',
    'payment_amount': 'ยอดชำระเงิน',
    'payment_report': 'รายงานการชำระเงิน',
    'total_payment_amount': 'ยอดชำระทั้งหมด',
    'income': 'รายรับ',
    'income_report': 'รายงานข้อมูลรายรับ',
    'total_income': 'รายรับทั้งหมด',
    'water_electric': 'น้ำ-ไฟ',
    'utility_summary_report': 'รายงานสรุปการใช้น้ำ-ไฟ',
    'water_meter': 'มิเตอร์น้ำ',
    'this_month_total': 'เดือนนี้รวม',
    'units': 'หน่วย',
    'vs_last_month': 'vs เดือนก่อน',
    'electric_meter': 'มิเตอร์ไฟ',
    'reference_data': 'ข้อมูลอ้างอิง',
    'view_overview_and_docs': 'ดูภาพรวมและเอกสารประกอบ',
    'rooms': 'ห้องพัก',
    'room_info_report': 'รายงานข้อมูลห้องพัก',
    'occupied_rooms': 'ห้องไม่ว่าง',
    'print_contract': 'พิมพ์สัญญา',
    'active_contracts': 'สัญญาที่ใช้',
    'ended': 'สิ้นสุดแล้ว',
    'print_contract_arrow': 'พิมพ์สัญญา →',
    'data_report': 'รายงานข้อมูล',
    'all_news': 'ข่าวทั้งหมด',
    'completed_repair': 'ซ่อมเสร็จ',
    'revenue_baht': 'รายได้ (บาท)',
    'no_data': 'ไม่มีข้อมูล',
    'active': 'ใช้งาน',
    'ended_label': 'สิ้นสุด',
    'new_tenants': 'ผู้เช่าเข้าใหม่',
    'vacant': 'ว่าง',
    'not_vacant': 'ไม่ว่าง',
    'water_units_label': 'น้ำ (ยูนิต)',
    'electric_units_label': 'ไฟ (ยูนิต)',
    'payment_total_label': 'ยอดชำระ',
    'water_label': 'น้ำ',
    'electric_label': 'ไฟ',
    'news_label': 'ข่าว'
}

replacements = [
    (r"จองอยู่", r"<?php echo __('reserved'); ?>"),
    (r"ติดตามประจำ", r"<?php echo __('regular_followup'); ?>"),
    (r"การเงินและการใช้ทรัพยากร", r"<?php echo __('finance_and_resources'); ?>"),
    (r"ยอดชำระเงิน", r"<?php echo __('payment_amount'); ?>"),
    (r"รายงานการชำระเงิน", r"<?php echo __('payment_report'); ?>"),
    (r"ยอดชำระทั้งหมด", r"<?php echo __('total_payment_amount'); ?>"),
    (r"รายรับทั้งหมด", r"<?php echo __('total_income'); ?>"),
    (r"รายงานข้อมูลรายรับ", r"<?php echo __('income_report'); ?>"),
    (r"รายรับ", r"<?php echo __('income'); ?>"),
    (r"รายงานสรุปการใช้น้ำ-ไฟ", r"<?php echo __('utility_summary_report'); ?>"),
    (r"น้ำ-ไฟ", r"<?php echo __('water_electric'); ?>"),
    (r"มิเตอร์น้ำ", r"<?php echo __('water_meter'); ?>"),
    (r"เดือนนี้รวม", r"<?php echo __('this_month_total'); ?>"),
    (r"หน่วย", r"<?php echo __('units'); ?>"),
    (r"vs เดือนก่อน", r"<?php echo __('vs_last_month'); ?>"),
    (r"มิเตอร์ไฟ", r"<?php echo __('electric_meter'); ?>"),
    (r"ข้อมูลอ้างอิง", r"<?php echo __('reference_data'); ?>"),
    (r"ดูภาพรวมและเอกสารประกอบ", r"<?php echo __('view_overview_and_docs'); ?>"),
    (r"รายงานข้อมูลห้องพัก", r"<?php echo __('room_info_report'); ?>"),
    (r"ห้องพัก", r"<?php echo __('rooms'); ?>"),
    (r"ห้องไม่ว่าง", r"<?php echo __('occupied_rooms'); ?>"),
    (r"พิมพ์สัญญา →", r"<?php echo __('print_contract_arrow'); ?>"),
    (r"พิมพ์สัญญา", r"<?php echo __('print_contract'); ?>"),
    (r"สัญญาที่ใช้", r"<?php echo __('active_contracts'); ?>"),
    (r"สิ้นสุดแล้ว", r"<?php echo __('ended'); ?>"),
    (r"รายงานข้อมูล", r"<?php echo __('data_report'); ?>"),
    (r"ข่าวทั้งหมด", r"<?php echo __('all_news'); ?>"),
    (r"'ว่าง'", r"'<?php echo __('vacant'); ?>'"),
    (r"'ไม่ว่าง'", r"'<?php echo __('not_vacant'); ?>'"),
    (r"'ซ่อมเสร็จ'", r"'<?php echo __('completed_repair'); ?>'"),
    (r"'รายได้ \(บาท\)'", r"'<?php echo __('revenue_baht'); ?>'"),
    (r"'ไม่มีข้อมูล'", r"'<?php echo __('no_data'); ?>'"),
    (r"'ใช้งาน'", r"'<?php echo __('active'); ?>'"),
    (r"'สิ้นสุด'", r"'<?php echo __('ended_label'); ?>'"),
    (r"'น้ำ \(ยูนิต\)'", r"'<?php echo __('water_units_label'); ?>'"),
    (r"'ไฟ \(ยูนิต\)'", r"'<?php echo __('electric_units_label'); ?>'"),
    (r"'ผู้เช่าเข้าใหม่'", r"'<?php echo __('new_tenants'); ?>'"),
    (r"'ข่าว'", r"'<?php echo __('news_label'); ?>'"),
    (r"'ยอดชำระ'", r"'<?php echo __('payment_total_label'); ?>'"),
    (r"'น้ำ'", r"'<?php echo __('water_label'); ?>'"),
    (r"'ไฟ'", r"'<?php echo __('electric_label'); ?>'"),
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
        injection = "\n    // Even more dashboard additions\n" + "\n".join(new_keys) + "\n"
        content = re.sub(r'\];\s*$', injection + "];\n", content)
        with open(filename, 'w', encoding='utf-8') as f:
            f.write(content)

inject_dict('langs/th.php', thai_dict)
inject_dict('langs/en.php', eng_dict)

print("Done part 3!")

-- เพิ่มคอลัมน์สำหรับนัดหมายเวลาซ่อม
ALTER TABLE repair 
ADD COLUMN scheduled_date DATE DEFAULT NULL COMMENT 'วันที่นัดซ่อม',
ADD COLUMN scheduled_time_start TIME DEFAULT NULL COMMENT 'เวลาเริ่มต้น',
ADD COLUMN scheduled_time_end TIME DEFAULT NULL COMMENT 'เวลาสิ้นสุด',
ADD COLUMN technician_name VARCHAR(100) DEFAULT NULL COMMENT 'ชื่อช่างผู้รับผิดชอบ',
ADD COLUMN technician_phone VARCHAR(20) DEFAULT NULL COMMENT 'เบอร์โทรช่าง',
ADD COLUMN schedule_note TEXT DEFAULT NULL COMMENT 'หมายเหตุการนัดหมาย';

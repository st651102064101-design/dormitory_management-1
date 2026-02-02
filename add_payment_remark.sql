-- เพิ่มคอลัมน์หมายเหตุในตาราง payment และ booking_payment
-- สร้างเมื่อ: 2 กุมภาพันธ์ 2569

-- เพิ่มคอลัมน์ pay_remark ในตาราง payment
ALTER TABLE payment ADD COLUMN pay_remark VARCHAR(255) DEFAULT NULL COMMENT 'หมายเหตุการชำระเงิน';

-- เพิ่มคอลัมน์ bp_remark ในตาราง booking_payment
ALTER TABLE booking_payment ADD COLUMN bp_remark VARCHAR(255) DEFAULT NULL COMMENT 'หมายเหตุการชำระเงิน';

-- อัพเดทข้อมูลเก่าที่เป็นค่ามัดจำ (2000 บาท) ให้มีหมายเหตุ
UPDATE payment SET pay_remark = 'มัดจำ' WHERE pay_amount = 2000 AND pay_remark IS NULL;
UPDATE booking_payment SET bp_remark = 'มัดจำ' WHERE bp_amount = 2000.00 AND bp_remark IS NULL;

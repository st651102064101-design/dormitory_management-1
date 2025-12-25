-- เพิ่มคอลัมน์สำหรับเก็บไฟล์สำเนาบัตรประชาชนและทะเบียนบ้าน
-- รันคำสั่งนี้ใน phpMyAdmin หรือ MySQL CLI

ALTER TABLE `tenant` 
ADD COLUMN `tnt_idcard_copy` VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์สำเนาบัตรประชาชน' AFTER `tnt_parentsphone`,
ADD COLUMN `tnt_house_copy` VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์สำเนาทะเบียนบ้าน' AFTER `tnt_idcard_copy`;

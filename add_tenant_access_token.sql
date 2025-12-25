-- เพิ่มคอลัมน์ access_token สำหรับระบบ Tenant Portal
-- รันคำสั่งนี้ใน phpMyAdmin หรือ MySQL CLI

ALTER TABLE `contract` 
ADD COLUMN `access_token` VARCHAR(64) DEFAULT NULL COMMENT 'Token สำหรับเข้าถึง Tenant Portal' AFTER `room_id`,
ADD UNIQUE KEY `access_token_unique` (`access_token`);

-- อัพเดท token สำหรับสัญญาที่มีอยู่แล้ว (ที่ยังใช้งานอยู่)
UPDATE `contract` SET `access_token` = MD5(CONCAT(ctr_id, '-', tnt_id, '-', room_id, '-', NOW(), '-', RAND())) WHERE `access_token` IS NULL AND `ctr_status` IN ('0', '2');

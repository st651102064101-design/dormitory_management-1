-- Add document storage columns to tenant table
-- Migration: Add tnt_idcard_copy and tnt_house_copy columns

ALTER TABLE `tenant` 
ADD COLUMN `tnt_idcard_copy` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อไฟล์สำเนาบัตรประชาชน' AFTER `tnt_parentsphone`,
ADD COLUMN `tnt_house_copy` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อไฟล์สำเนาทะเบียนบ้าน' AFTER `tnt_idcard_copy`;

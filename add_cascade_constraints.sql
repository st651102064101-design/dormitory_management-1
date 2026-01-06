-- =====================================================
-- SQL Script: Add CASCADE DELETE Constraints
-- ลบข้อมูลในตารางหลัก ข้อมูลในตารางที่ reference FK จะหายไปด้วย
-- =====================================================
-- ความสัมพันธ์:
-- tenant (หลัก)
--   ├── booking (tnt_id FK → tenant)
--   └── contract (tnt_id FK → tenant)
--         └── expense (ctr_id FK → contract)
--               └── payment (exp_id FK → expense)
-- room (หลัก)
--   ├── booking (room_id FK → room)
--   └── contract (room_id FK → room)
-- =====================================================

-- 1. ลบ Foreign Key เดิมทั้งหมดก่อน
-- =====================================================

-- Drop payment FK
ALTER TABLE `payment` DROP FOREIGN KEY `payment_ibfk_1`;

-- Drop expense FK  
ALTER TABLE `expense` DROP FOREIGN KEY `expense_ibfk_1`;

-- Drop contract FKs
ALTER TABLE `contract` DROP FOREIGN KEY `contract_ibfk_1`;
ALTER TABLE `contract` DROP FOREIGN KEY `contract_ibfk_2`;

-- Drop booking FKs
ALTER TABLE `booking` DROP FOREIGN KEY `booking_ibfk_1`;

-- 2. เพิ่ม Foreign Key ใหม่พร้อม ON DELETE CASCADE
-- =====================================================

-- booking: เมื่อลบ room → ลบ booking ที่เกี่ยวข้อง
ALTER TABLE `booking` 
ADD CONSTRAINT `booking_ibfk_1` 
FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- booking: เมื่อลบ tenant → ลบ booking ที่เกี่ยวข้อง
ALTER TABLE `booking` 
ADD CONSTRAINT `booking_ibfk_2` 
FOREIGN KEY (`tnt_id`) REFERENCES `tenant` (`tnt_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- contract: เมื่อลบ tenant → ลบ contract ที่เกี่ยวข้อง
ALTER TABLE `contract` 
ADD CONSTRAINT `contract_ibfk_1` 
FOREIGN KEY (`tnt_id`) REFERENCES `tenant` (`tnt_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- contract: เมื่อลบ room → ลบ contract ที่เกี่ยวข้อง
ALTER TABLE `contract` 
ADD CONSTRAINT `contract_ibfk_2` 
FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- expense: เมื่อลบ contract → ลบ expense ที่เกี่ยวข้อง
ALTER TABLE `expense` 
ADD CONSTRAINT `expense_ibfk_1` 
FOREIGN KEY (`ctr_id`) REFERENCES `contract` (`ctr_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- payment: เมื่อลบ expense → ลบ payment ที่เกี่ยวข้อง
ALTER TABLE `payment` 
ADD CONSTRAINT `payment_ibfk_1` 
FOREIGN KEY (`exp_id`) REFERENCES `expense` (`exp_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- =====================================================
-- ตรวจสอบผลลัพธ์
-- =====================================================
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    information_schema.KEY_COLUMN_USAGE
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

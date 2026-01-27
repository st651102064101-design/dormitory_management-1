-- ===================================================================
-- SQL สำหรับอัปเดต Database เพื่อรองรับระบบ Wizard 5 ขั้นตอน (Final Version)
-- ===================================================================
-- วันที่สร้าง: 2026-01-27
-- แก้ไข: ใช้ utf8mb4_general_ci ให้ตรงกับตารางเดิม
-- ===================================================================

-- 1. สร้างตารางสำหรับเก็บข้อมูลการชำระเงินจอง
CREATE TABLE IF NOT EXISTS booking_payment (
    bp_id INT AUTO_INCREMENT PRIMARY KEY,
    bp_amount DECIMAL(10,2) NOT NULL DEFAULT 2000.00 COMMENT 'ยอดเงินจอง',
    bp_status ENUM('0','1') DEFAULT '0' COMMENT '0=รอชำระ, 1=ชำระแล้ว',
    bp_payment_date DATETIME NULL COMMENT 'วันที่ชำระเงิน',
    bp_proof VARCHAR(255) NULL COMMENT 'path ไปยังไฟล์หลักฐานการชำระเงิน',
    bp_receipt_no VARCHAR(50) NULL COMMENT 'เลขที่ใบเสร็จ',
    bkg_id INT NOT NULL COMMENT 'FK ไปยังตาราง booking',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bkg_id (bkg_id),
    INDEX idx_bp_status (bp_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตารางเก็บข้อมูลการชำระเงินจอง';

-- 2. สร้างตารางสำหรับเก็บข้อมูลการเช็คอิน
CREATE TABLE IF NOT EXISTS checkin_record (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    checkin_date DATE NOT NULL COMMENT 'วันที่เช็คอิน',
    water_meter_start DECIMAL(10,2) NOT NULL COMMENT 'เลขมิเตอร์น้ำเริ่มต้น',
    elec_meter_start DECIMAL(10,2) NOT NULL COMMENT 'เลขมิเตอร์ไฟเริ่มต้น',
    room_images TEXT NULL COMMENT 'JSON array ของ path รูปภาพสภาพห้อง',
    key_number VARCHAR(50) NULL COMMENT 'เลขกุญแจ',
    notes TEXT NULL COMMENT 'หมายเหตุเพิ่มเติม',
    ctr_id INT NOT NULL COMMENT 'FK ไปยังตาราง contract',
    created_by VARCHAR(100) NULL COMMENT 'ผู้บันทึก',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ctr_id (ctr_id),
    INDEX idx_checkin_date (checkin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตารางเก็บข้อมูลการเช็คอิน';

-- 3. สร้างตารางสำหรับติดตามสถานะ Workflow ของผู้เช่า
CREATE TABLE IF NOT EXISTS tenant_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tnt_id VARCHAR(20) NOT NULL COMMENT 'FK ไปยังตาราง tenant',
    bkg_id INT NULL COMMENT 'FK ไปยังตาราง booking',
    ctr_id INT NULL COMMENT 'FK ไปยังตาราง contract',

    -- สถานะแต่ละขั้นตอน
    step_1_confirmed TINYINT(1) DEFAULT 0 COMMENT 'Step 1: ยืนยันจอง',
    step_1_date DATETIME NULL COMMENT 'วันที่ทำ Step 1',
    step_1_by VARCHAR(100) NULL COMMENT 'ผู้ทำ Step 1',

    step_2_confirmed TINYINT(1) DEFAULT 0 COMMENT 'Step 2: ยืนยันชำระเงินจอง',
    step_2_date DATETIME NULL COMMENT 'วันที่ทำ Step 2',
    step_2_by VARCHAR(100) NULL COMMENT 'ผู้ทำ Step 2',

    step_3_confirmed TINYINT(1) DEFAULT 0 COMMENT 'Step 3: สร้างสัญญา',
    step_3_date DATETIME NULL COMMENT 'วันที่ทำ Step 3',
    step_3_by VARCHAR(100) NULL COMMENT 'ผู้ทำ Step 3',

    step_4_confirmed TINYINT(1) DEFAULT 0 COMMENT 'Step 4: เช็คอิน',
    step_4_date DATETIME NULL COMMENT 'วันที่ทำ Step 4',
    step_4_by VARCHAR(100) NULL COMMENT 'ผู้ทำ Step 4',

    step_5_confirmed TINYINT(1) DEFAULT 0 COMMENT 'Step 5: เริ่มบิลรายเดือน',
    step_5_date DATETIME NULL COMMENT 'วันที่ทำ Step 5',
    step_5_by VARCHAR(100) NULL COMMENT 'ผู้ทำ Step 5',

    current_step INT DEFAULT 1 COMMENT 'ขั้นตอนปัจจุบัน (1-5)',
    completed TINYINT(1) DEFAULT 0 COMMENT 'เสร็จสิ้นทุกขั้นตอนแล้ว',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tnt_id (tnt_id),
    INDEX idx_bkg_id (bkg_id),
    INDEX idx_ctr_id (ctr_id),
    INDEX idx_current_step (current_step),
    INDEX idx_completed (completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตารางติดตามสถานะ Workflow ของผู้เช่า';

-- 4. เพิ่มฟิลด์ใหม่ในตาราง contract (ถ้ายังไม่มี)
SET @dbname = DATABASE();
SET @tablename = 'contract';
SET @columnname1 = 'contract_pdf_path';
SET @columnname2 = 'contract_created_date';

SET @preparedStatement1 = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME=@columnname1) > 0,
  'SELECT 1',
  'ALTER TABLE contract ADD COLUMN contract_pdf_path VARCHAR(255) NULL COMMENT ''path ไปยังไฟล์ PDF สัญญา'''
));
PREPARE alterIfNotExists1 FROM @preparedStatement1;
EXECUTE alterIfNotExists1;
DEALLOCATE PREPARE alterIfNotExists1;

SET @preparedStatement2 = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME=@columnname2) > 0,
  'SELECT 1',
  'ALTER TABLE contract ADD COLUMN contract_created_date DATETIME NULL COMMENT ''วันที่สร้างสัญญา'''
));
PREPARE alterIfNotExists2 FROM @preparedStatement2;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- 5. สร้าง View สำหรับดูข้อมูล Wizard แบบรวม
DROP VIEW IF EXISTS vw_tenant_wizard;

CREATE VIEW vw_tenant_wizard AS
SELECT
    t.tnt_id,
    t.tnt_name,
    t.tnt_phone,
    t.tnt_status,
    b.bkg_id,
    b.bkg_date,
    b.bkg_status,
    r.room_id,
    r.room_number,
    rt.type_name,
    rt.type_price,
    c.ctr_id,
    c.ctr_start,
    c.ctr_end,
    c.ctr_status,
    tw.id as workflow_id,
    tw.current_step,
    tw.step_1_confirmed,
    tw.step_1_date,
    tw.step_2_confirmed,
    tw.step_2_date,
    tw.step_3_confirmed,
    tw.step_3_date,
    tw.step_4_confirmed,
    tw.step_4_date,
    tw.step_5_confirmed,
    tw.step_5_date,
    tw.completed,
    bp.bp_status AS booking_payment_status,
    bp.bp_receipt_no,
    cr.checkin_id,
    cr.checkin_date
FROM tenant t
LEFT JOIN tenant_workflow tw ON t.tnt_id COLLATE utf8mb4_general_ci = tw.tnt_id COLLATE utf8mb4_general_ci
LEFT JOIN booking b ON tw.bkg_id = b.bkg_id
LEFT JOIN room r ON b.room_id = r.room_id
LEFT JOIN roomtype rt ON r.type_id = rt.type_id
LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
LEFT JOIN booking_payment bp ON b.bkg_id = bp.bkg_id
LEFT JOIN checkin_record cr ON c.ctr_id = cr.ctr_id
WHERE tw.id IS NOT NULL
ORDER BY tw.current_step ASC, tw.updated_at DESC;

-- ===================================================================
-- จบการอัปเดต Database
-- ===================================================================

-- ตรวจสอบว่าสร้างตารางสำเร็จหรือไม่
SELECT 'สำเร็จ! ตารางถูกสร้างแล้ว' AS status;

SELECT
    'booking_payment' AS table_name,
    COUNT(*) AS record_count
FROM booking_payment
UNION ALL
SELECT
    'checkin_record' AS table_name,
    COUNT(*) AS record_count
FROM checkin_record
UNION ALL
SELECT
    'tenant_workflow' AS table_name,
    COUNT(*) AS record_count
FROM tenant_workflow;

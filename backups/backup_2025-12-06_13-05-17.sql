-- Backup: 2025-12-06 13:05:17
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `admin_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสผู้ดูแลระบบ',
  `admin_username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อผู้ใช้',
  `admin_password` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'รหัสผ่าน',
  `admin_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อ-นามสกุล',
  `admin_tel` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'เบอร์โทรศัพท์',
  `admin_line` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ไอดีไลน์ผู้ดูแลระบบ',
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin` (`admin_id`, `admin_username`, `admin_password`, `admin_name`, `admin_tel`, `admin_line`) VALUES ('1', 'admin01', '123456', 'กิตติศักดิ์ โชติวิจิตร', '0891234567', 'kittisak_admin');
INSERT INTO `admin` (`admin_id`, `admin_username`, `admin_password`, `admin_name`, `admin_tel`, `admin_line`) VALUES ('2', 'admin02', 'admin@2025', 'ชลิตา ทิพมณีพงศ์', '0819876543', 'chalita_line');

DROP TABLE IF EXISTS `booking`;
CREATE TABLE `booking` (
  `bkg_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสการจอง',
  `bkg_date` date DEFAULT NULL COMMENT 'วันที่จอง',
  `bkg_checkin_date` date DEFAULT NULL COMMENT 'วันที่เข้าพัก',
  `bkg_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะการจอง (1=จองแล้ว, 0=ยกเลิก, 2=เข้าพักแล้ว)',
  `room_id` int DEFAULT NULL COMMENT 'รหัสห้องพัก (FK)',
  PRIMARY KEY (`bkg_id`),
  KEY `booking_ibfk_1` (`room_id`),
  CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('5', '2025-12-10', '2025-12-11', '0', '2');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('6', '2025-12-11', '2025-12-17', '2', '1');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('7', '2025-12-18', '2025-12-05', '0', '5');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('8', '2025-12-04', '2025-12-31', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('9', '2025-12-17', '2026-01-02', '0', '6');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('10', '2025-12-05', '2026-01-01', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('11', '2025-12-05', '2026-01-01', '0', '6');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('12', '2025-12-05', '2025-12-24', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('13', '2025-12-05', '2025-12-10', '0', '3');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('14', '2025-12-05', '2025-12-18', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('15', '2025-12-05', '2025-12-17', '0', '4');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('16', '2025-12-05', '2025-12-23', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('17', '2025-12-05', '2025-12-11', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('18', '2025-12-05', '2025-12-10', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('19', '2025-12-05', '2025-12-17', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('20', '2025-12-05', '2025-12-11', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('21', '2025-12-05', '2025-12-23', '0', '38');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('22', '2025-12-05', '2025-12-16', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('23', '2025-12-05', '2026-01-01', '0', '38');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('24', '2025-12-05', '2025-12-05', '0', '38');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('25', '2025-12-05', '2025-12-17', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('26', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('27', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('28', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('29', '2025-12-05', '2025-12-05', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('30', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('31', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('32', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('33', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('34', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('35', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('36', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('37', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('38', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('39', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('40', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('41', '2025-12-05', '2025-12-10', '0', '37');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('42', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('43', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('44', '2025-12-05', '2025-12-05', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('45', '2025-12-06', '2025-12-06', '0', '7');
INSERT INTO `booking` (`bkg_id`, `bkg_date`, `bkg_checkin_date`, `bkg_status`, `room_id`) VALUES ('46', '2025-12-06', '2025-12-31', '0', '38');

DROP TABLE IF EXISTS `contract`;
CREATE TABLE `contract` (
  `ctr_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสสัญญา',
  `ctr_start` date DEFAULT NULL COMMENT 'วันที่เริ่มต้น',
  `ctr_end` date DEFAULT NULL COMMENT 'วันที่สิ้นสุด',
  `ctr_deposit` int DEFAULT NULL COMMENT 'เงินมัดจำ',
  `ctr_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะของสัญญา (0=ปกติ, 1=ยกเลิกสัญญา, 2=แจ้งยกเลิก)',
  `tnt_id` char(13) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'รหัสผู้เช่า (FK)',
  `room_id` int DEFAULT NULL COMMENT 'รหัสห้องพัก (FK)',
  PRIMARY KEY (`ctr_id`),
  KEY `contract_ibfk_1` (`tnt_id`),
  KEY `contract_ibfk_2` (`room_id`),
  CONSTRAINT `contract_ibfk_1` FOREIGN KEY (`tnt_id`) REFERENCES `tenant` (`tnt_id`),
  CONSTRAINT `contract_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('1', '2025-11-05', '2026-05-05', '2000', '1', '1103700890123', '1');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('2', '2025-11-07', '2026-05-07', '2000', '1', '1103700567290', '2');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('3', '2025-11-12', '2026-05-12', '2000', '1', '1103700890123', '3');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('4', '2025-12-01', '2026-06-01', '2000', '1', '1409912345678', '4');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('5', '2025-12-02', '2026-06-02', '2000', '1', '1990100000003', '6');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('6', '2025-12-05', '2025-12-06', '2000', '1', '1103700456789', '1');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('7', '2025-12-11', '2025-12-17', '2000', '2', '1103700456789', '5');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('8', '2025-12-05', '2026-01-06', '2000', '1', '1103700456789', '38');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('9', '2025-12-05', '2026-01-06', '2000', '1', '1103700456789', '2');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('10', '2025-12-05', '2026-01-06', '2000', '0', '1103700456789', '3');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('11', '2025-12-05', '2025-12-10', '2000', '0', '1103700456789', '4');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('12', '2025-12-05', '2026-01-06', '2000', '0', '1103700456789', '6');

DROP TABLE IF EXISTS `expense`;
CREATE TABLE `expense` (
  `exp_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสค่าใช้จ่าย',
  `exp_month` date DEFAULT NULL COMMENT 'วันเดือนปีที่เรียกเก็บ',
  `exp_elec_unit` int DEFAULT NULL COMMENT 'หน่วยไฟ',
  `exp_water_unit` int DEFAULT NULL COMMENT 'หน่วยน้ำ',
  `rate_elec` int DEFAULT NULL COMMENT 'อัตราค่าไฟ',
  `rate_water` int DEFAULT NULL COMMENT 'อัตราค่าน้ำ',
  `room_price` int DEFAULT NULL COMMENT 'ค่าห้องพัก',
  `exp_elec_chg` int DEFAULT NULL COMMENT 'ค่าไฟรวม',
  `exp_water` int DEFAULT NULL COMMENT 'ค่าน้ำ',
  `exp_total` int DEFAULT NULL COMMENT 'ยอดรวมทั้งหมด',
  `exp_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะการชำระเงิน (0=ยังไม่จ่าย, 1=จ่ายแล้ว)',
  `ctr_id` int DEFAULT NULL COMMENT 'รหัสสัญญา (FK)',
  PRIMARY KEY (`exp_id`),
  KEY `expense_ibfk_1` (`ctr_id`),
  CONSTRAINT `expense_ibfk_1` FOREIGN KEY (`ctr_id`) REFERENCES `contract` (`ctr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('3', '2025-11-30', '150', '20', '8', '18', '1600', '360', '3160', '5120', '1', '1');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('4', '2025-11-30', '200', '25', '8', '18', '1500', '450', '3550', '5500', '0', '2');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('5', '2025-11-30', '180', '22', '8', '18', '1600', '396', '3436', '5432', '0', '3');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('7', '2025-12-31', '190', '25', '8', '18', '1600', '1520', '450', '3570', '0', '4');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('8', '2025-12-31', '210', '28', '8', '18', '1600', '1680', '504', '3784', '0', '5');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('12', '2025-12-01', '0', '0', '700', '1500', '1500', '0', '0', '1500', '0', '1');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('13', '2026-01-01', '1', '1', '700', '1500', '1500', '7', '15', '1522', '0', '1');
INSERT INTO `expense` (`exp_id`, `exp_month`, `exp_elec_unit`, `exp_water_unit`, `rate_elec`, `rate_water`, `room_price`, `exp_elec_chg`, `exp_water`, `exp_total`, `exp_status`, `ctr_id`) VALUES ('14', '2026-05-01', '3', '3', '700', '1500', '1500', '21', '45', '1566', '0', '1');

DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `news_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสข่าว',
  `news_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'หัวข้อข่าว',
  `news_details` text COLLATE utf8mb4_general_ci COMMENT 'รายละเอียด',
  `news_date` date DEFAULT NULL COMMENT 'วันที่เผยแพร่',
  `news_by` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ผู้เผยแพร่',
  PRIMARY KEY (`news_id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `news` (`news_id`, `news_title`, `news_details`, `news_date`, `news_by`) VALUES ('1', 'งดปรับปรุงน้ำประปา', 'จะมีการปิดปรับปรุงน้ำประปาชั่วคราววันที่ 25 พ.ย. 2568', '2025-11-18', 'กิตติศักดิ์ โชติวิจิตร');
INSERT INTO `news` (`news_id`, `news_title`, `news_details`, `news_date`, `news_by`) VALUES ('2', 'ตรวจสอบไฟฟ้านอก', 'การตรวจสอบระบบไฟฟ้าวันที่ 28 พ.ย. 2568', '2025-11-25', 'ชลิตา ทิพมณีพงศ์');
INSERT INTO `news` (`news_id`, `news_title`, `news_details`, `news_date`, `news_by`) VALUES ('3', 'แจ้งเตือนวันชำระค่าเช่า', 'ค่าเช่าและค่าใช้จ่ายประจำเดือน ธ.ค. จะครบกำหนดชำระภายในวันที่ 5 ม.ค. 2569', '2025-12-28', 'ชนิดาติ พิมพ์พันธ์พงศ์');
INSERT INTO `news` (`news_id`, `news_title`, `news_details`, `news_date`, `news_by`) VALUES ('4', 'เปิดรับสมัครผู้เช่าใหม่', 'ผู้ที่สนใจเข้าพักในปีการศึกษาหน้า สามารถยื่นเรื่องจองห้องพักได้ตั้งแต่วันที่ 15 ม.ค. 2569 เป็นต้นไป', '2026-01-05', 'ชนิดาติ พิมพ์พันธ์พงศ์');

DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `pay_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสการแจ้งชำระเงิน',
  `pay_date` date DEFAULT NULL COMMENT 'วันที่แจ้งชำระเงิน',
  `pay_amount` int DEFAULT NULL COMMENT 'จำนวนเงินที่ชำระ',
  `pay_proof` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'หลักฐานการชำระเงิน',
  `pay_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะการตรวจสอบ (0=รอการตรวจสอบ, 1=ตรวจสอบแล้ว)',
  `exp_id` int DEFAULT NULL COMMENT 'รหัสค่าใช้จ่าย (FK)',
  PRIMARY KEY (`pay_id`),
  KEY `payment_ibfk_1` (`exp_id`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`exp_id`) REFERENCES `expense` (`exp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payment` (`pay_id`, `pay_date`, `pay_amount`, `pay_proof`, `pay_status`, `exp_id`) VALUES ('10', '2025-11-30', '3160', 'slip1.jpg', '0', '3');
INSERT INTO `payment` (`pay_id`, `pay_date`, `pay_amount`, `pay_proof`, `pay_status`, `exp_id`) VALUES ('11', '2025-11-30', '3550', 'slip2.jpg', '1', '4');
INSERT INTO `payment` (`pay_id`, `pay_date`, `pay_amount`, `pay_proof`, `pay_status`, `exp_id`) VALUES ('12', '2025-11-30', '3436', 'slip3.jpg', '0', '5');
INSERT INTO `payment` (`pay_id`, `pay_date`, `pay_amount`, `pay_proof`, `pay_status`, `exp_id`) VALUES ('13', '2025-12-31', '3570', 'slip4.jpg', '1', '7');
INSERT INTO `payment` (`pay_id`, `pay_date`, `pay_amount`, `pay_proof`, `pay_status`, `exp_id`) VALUES ('14', '2025-12-31', '3784', 'slip5.jpg', '1', '8');

DROP TABLE IF EXISTS `rate`;
CREATE TABLE `rate` (
  `rate_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัส rate',
  `rate_water` int DEFAULT NULL COMMENT 'อัตราค่าน้ำ (บาท/หน่วย)',
  `rate_elec` int DEFAULT NULL COMMENT 'อัตราค่าไฟ (บาท/หน่วย)',
  PRIMARY KEY (`rate_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rate` (`rate_id`, `rate_water`, `rate_elec`) VALUES ('2', '18', '8');
INSERT INTO `rate` (`rate_id`, `rate_water`, `rate_elec`) VALUES ('6', '5', '4');

DROP TABLE IF EXISTS `repair`;
CREATE TABLE `repair` (
  `repair_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสแจ้งซ่อม',
  `repair_desc` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'รายละเอียดการแจ้งซ่อม',
  `repair_date` datetime DEFAULT NULL COMMENT 'วันที่แจ้ง',
  `repair_time` time DEFAULT NULL COMMENT 'เวลาที่แจ้ง',
  `repair_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะการดำเนินการ (0=รอซ่อม, 1=กำลังซ่อม, 2=ซ่อมเสร็จ)',
  `repair_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'รูปภาพ',
  `ctr_id` int DEFAULT NULL COMMENT 'รหัสสัญญา (FK)',
  PRIMARY KEY (`repair_id`),
  KEY `repair_ibfk_1` (`ctr_id`),
  CONSTRAINT `repair_ibfk_1` FOREIGN KEY (`ctr_id`) REFERENCES `contract` (`ctr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `repair` (`repair_id`, `repair_desc`, `repair_date`, `repair_time`, `repair_status`, `repair_image`, `ctr_id`) VALUES ('74', 'ดฟไ', '2025-12-06 00:00:00', '02:44:28', '1', NULL, '1');
INSERT INTO `repair` (`repair_id`, `repair_desc`, `repair_date`, `repair_time`, `repair_status`, `repair_image`, `ctr_id`) VALUES ('75', 'ดฟไ', '2025-12-06 00:00:00', '02:46:53', '1', NULL, '2');
INSERT INTO `repair` (`repair_id`, `repair_desc`, `repair_date`, `repair_time`, `repair_status`, `repair_image`, `ctr_id`) VALUES ('76', 'ดฟไดไฟ', '2025-12-06 00:00:00', '02:47:07', '1', NULL, '3');
INSERT INTO `repair` (`repair_id`, `repair_desc`, `repair_date`, `repair_time`, `repair_status`, `repair_image`, `ctr_id`) VALUES ('77', 'ดไฟด', '2025-12-06 00:00:00', '02:47:40', '1', NULL, '4');
INSERT INTO `repair` (`repair_id`, `repair_desc`, `repair_date`, `repair_time`, `repair_status`, `repair_image`, `ctr_id`) VALUES ('78', 'ฟไดไฟด', '2025-12-06 00:00:00', '02:58:25', '0', NULL, '5');
INSERT INTO `repair` (`repair_id`, `repair_desc`, `repair_date`, `repair_time`, `repair_status`, `repair_image`, `ctr_id`) VALUES ('79', 'ดไ', '2025-12-06 00:00:00', '03:55:52', '0', NULL, '6');

DROP TABLE IF EXISTS `room`;
CREATE TABLE `room` (
  `room_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสห้องพัก',
  `room_number` varchar(2) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'หมายเลขห้อง',
  `room_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะห้องพัก (1=ว่าง, 0=ไม่ว่าง)',
  `room_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'รูปภาพ',
  `type_id` tinyint NOT NULL COMMENT 'รหัสประเภทห้องพัก (FK)',
  PRIMARY KEY (`room_id`),
  KEY `room_ibfk_1` (`type_id`),
  CONSTRAINT `room_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `roomtype` (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('1', '01', '0', 'room01.jpg', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('2', '02', '0', 'room02.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('3', '03', '1', 'room03.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('4', '04', '1', 'room04.jpg', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('5', '05', '1', 'room05.jpg', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('6', '06', '1', 'room06.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('7', '07', '0', 'room07.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('37', '08', '0', '', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('38', '09', '0', '', '1');

DROP TABLE IF EXISTS `roomtype`;
CREATE TABLE `roomtype` (
  `type_id` tinyint NOT NULL AUTO_INCREMENT COMMENT 'รหัสประเภทห้องพัก',
  `type_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อประเภทห้องพัก',
  `type_price` int DEFAULT NULL COMMENT 'ราคาห้องพัก',
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roomtype` (`type_id`, `type_name`, `type_price`) VALUES ('1', 'ฝั่งเก่า', '1500');
INSERT INTO `roomtype` (`type_id`, `type_name`, `type_price`) VALUES ('2', 'ฝั่งใหม่', '1600');

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_general_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=692 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('1', 'site_name', 'Sangthian Dormitory', '2025-12-06 18:53:51');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('2', 'theme_color', '#0f172a', '2025-12-06 19:05:09');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('3', 'font_size', '0.9', '2025-12-06 18:57:11');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('4', 'logo_filename', 'Logo.jpg', '2025-12-06 13:43:41');

DROP TABLE IF EXISTS `tenant`;
CREATE TABLE `tenant` (
  `tnt_id` char(13) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'เลขทะเบียนบัตรประจำตัวประชาชน',
  `tnt_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อ-สกุล',
  `tnt_age` tinyint DEFAULT NULL COMMENT 'อายุ',
  `tnt_address` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ที่อยู่',
  `tnt_phone` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'เบอร์โทรศัพท์',
  `tnt_education` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานที่ศึกษา',
  `tnt_faculty` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'คณะ',
  `tnt_year` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชั้นปี',
  `tnt_vehicle` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ทะเบียนรถ',
  `tnt_parent` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อ-สกุลผู้ปกครอง',
  `tnt_parentsphone` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'เบอร์โทรศัพท์ผู้ปกครอง',
  `tnt_status` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะผู้เช่า (1=พักอยู่, 0=ย้ายออก)',
  PRIMARY KEY (`tnt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`) VALUES ('1103700456789', 'นางสาวฟ้าใสน้ำตก', '20', '123 หมู่ 5 ต.เมือง อ.พิษณุโลก', '0912345678', 'มหาวิทยาลัยนเรศวร', 'วิศวกรรมไฟฟ้า', 'ปี2', 'กน 1234', 'นายสมชาย ศรีสวัสดิ์', '0811111111', '1');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`) VALUES ('1103700567290', 'นายเทพพิทักษ์ ทรงคมทอง', '21', '88 ม.10 ต.นาเฉลียง อ.หนองไผ่ จ.เพชรบูรณ์', '0987654321', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'วิทยาการคอมพิวเตอร์', 'ปี3', '1ขธ 7899', 'นางสมใจ บางนกแขวก', '0822222222', '1');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`) VALUES ('1103700890123', 'นางสาวอรชร วงษ์ศรี', '20', '78 ม.2 ต.ห้วยชัน จ.ลพบุรี', '0991112233', 'มหาวิทยาลัยนเรศวร', 'วิศวกรรมการเงิน', 'ปี1', '5ฐพ 5678', 'นางปราณี ประเสริฐ', '0833333333', '0');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`) VALUES ('1409912345678', 'นายวรวิทย์ ชัยชนะ', '22', '150 ถ.สามัคคีชัย ต.ในเมือง อ.เมือง จ.เพชรบูรณ์', '0876543210', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'คณะครุศาสตร์', 'ปี 4', 'สน 5555', 'นายปรีชา ชัยชนะ', '0871112222', '1');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`) VALUES ('1990100000003', 'นายธนาธิป สุขเกษม', '23', '199 ม.1 ถ.เลย-หล่มสัก อ.หล่มสัก จ.เพชรบูรณ์', '0634445555', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'คณะวิทยาศาสตร์ฯ', 'ปี 2 ', 'กม 7890', 'นางสาวเมตตา สุขเกษม', '0631234567', '1');

DROP TABLE IF EXISTS `termination`;
CREATE TABLE `termination` (
  `term_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสแจ้งยกเลิกสัญญา',
  `ctr_id` int DEFAULT NULL COMMENT 'รหัสสัญญา (FK)',
  `term_date` date DEFAULT NULL COMMENT 'วันที่ยกเลิก',
  PRIMARY KEY (`term_id`),
  KEY `termination_ibfk_1` (`ctr_id`),
  CONSTRAINT `termination_ibfk_1` FOREIGN KEY (`ctr_id`) REFERENCES `contract` (`ctr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `termination` (`term_id`, `ctr_id`, `term_date`) VALUES ('1', '3', '2025-11-15');

DROP TABLE IF EXISTS `utility`;
CREATE TABLE `utility` (
  `utl_id` int NOT NULL AUTO_INCREMENT COMMENT 'รหัสการใช้น้ำ-ไฟ',
  `utl_water_start` int DEFAULT NULL COMMENT 'เลขมิเตอร์น้ำเริ่มต้น',
  `utl_water_end` int DEFAULT NULL COMMENT 'เลขมิเตอร์น้ำสิ้นสุด',
  `utl_elec_start` int DEFAULT NULL COMMENT 'เลขมิเตอร์ไฟเริ่มต้น',
  `utl_elec_end` int DEFAULT NULL COMMENT 'เลขมิเตอร์ไฟสิ้นสุด',
  `utl_usage` int DEFAULT NULL COMMENT 'หน่วยที่ใช้ต่อเดือน',
  `utl_date` date DEFAULT NULL COMMENT 'วันทีจดมิเตอร์',
  `ctr_id` int DEFAULT NULL COMMENT 'รหัสสัญญา (FK)',
  PRIMARY KEY (`utl_id`),
  KEY `utility_ibfk_1` (`ctr_id`),
  CONSTRAINT `utility_ibfk_1` FOREIGN KEY (`ctr_id`) REFERENCES `contract` (`ctr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `utility` (`utl_id`, `utl_water_start`, `utl_water_end`, `utl_elec_start`, `utl_elec_end`, `utl_usage`, `utl_date`, `ctr_id`) VALUES ('1', '1200', '1220', '3500', '3650', '20', '2025-11-30', '1');
INSERT INTO `utility` (`utl_id`, `utl_water_start`, `utl_water_end`, `utl_elec_start`, `utl_elec_end`, `utl_usage`, `utl_date`, `ctr_id`) VALUES ('2', '800', '825', '2200', '2350', '25', '2025-11-30', '2');
INSERT INTO `utility` (`utl_id`, `utl_water_start`, `utl_water_end`, `utl_elec_start`, `utl_elec_end`, `utl_usage`, `utl_date`, `ctr_id`) VALUES ('3', '1500', '1522', '4100', '4250', '22', '2025-11-30', '3');
INSERT INTO `utility` (`utl_id`, `utl_water_start`, `utl_water_end`, `utl_elec_start`, `utl_elec_end`, `utl_usage`, `utl_date`, `ctr_id`) VALUES ('4', '1800', '1825', '4500', '4700', '225', '2025-12-31', '4');
INSERT INTO `utility` (`utl_id`, `utl_water_start`, `utl_water_end`, `utl_elec_start`, `utl_elec_end`, `utl_usage`, `utl_date`, `ctr_id`) VALUES ('5', '1550', '1578', '3800', '4020', '248', '2025-12-31', '5');

SET FOREIGN_KEY_CHECKS=1;

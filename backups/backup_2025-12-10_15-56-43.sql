-- Backup: 2025-12-10 15:56:43
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
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('3', '2025-11-12', '2026-05-12', '2000', '1', '1103700890123', '3');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('15', '2025-12-10', '2026-06-10', '2000', '0', '1100000000014', '4');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('16', '2025-12-10', '2026-06-10', '2000', '0', '1100000000012', '5');
INSERT INTO `contract` (`ctr_id`, `ctr_start`, `ctr_end`, `ctr_deposit`, `ctr_status`, `tnt_id`, `room_id`) VALUES ('17', '2025-12-10', '2026-06-10', '2000', '0', '1100000000010', '37');

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
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('1', '1', '0', 'room01.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('2', '2', '0', 'room02.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('3', '3', '0', 'room03.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('4', '4', '1', 'room04.jpg', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('5', '5', '1', 'room05.jpg', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('6', '6', '0', 'room06.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('7', '7', '0', 'room07.jpg', '2');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('37', '8', '1', '', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('38', '9', '0', '', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('71', '10', '0', '', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('72', '11', '0', '', '1');
INSERT INTO `room` (`room_id`, `room_number`, `room_status`, `room_image`, `type_id`) VALUES ('73', '12', '0', '1765283006_slip3.png', '1');

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
) ENGINE=InnoDB AUTO_INCREMENT=823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('1', 'site_name', 'Sangthian Dormitory', '2025-12-07 23:32:48');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('2', 'theme_color', '#0f172a', '2025-12-10 19:01:24');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('3', 'font_size', '1', '2025-12-09 16:46:06');
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
  `tnt_status` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'สถานะผู้เช่า (0=ย้ายออก, 1=พักอยู่, 2=รอการเข้าพัก, 3=จองห้อง, 4=ยกเลิกจองห้อง)',
  `tnt_ceatetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tnt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000001', 'นายสมชาย ใจดี', '20', '123/1 ถนนสุขุมวิท กรุงเทพมหานคร 10110', '0812345678', 'มหาวิทยาลัย A', 'วิศวกรรมศาสตร์', 'ปี 3', 'กข 1234', 'นายสมคิด ใจดี', '0987654321', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000002', 'นางสาวมาลี รักเรียน', '19', '456 ซอยลาดพร้าว 101 กรุงเทพมหานคร 10310', '0923456789', 'มหาวิทยาลัย B', 'บริหารธุรกิจ', 'ปี 2', NULL, 'นางสมศรี รักเรียน', '0876543210', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000003', 'นายณัฐพล เก่งกาจ', '21', '789 ถนนพหลโยธิน ปทุมธานี 12120', '0834567890', 'สถาบัน C', 'สถาปัตยกรรมศาสตร์', 'ปี 4', 'ศษ 5678', 'นายณรงค์ เก่งกาจ', '0998877665', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000004', 'นางสาวอารยา มีสุข', '18', '10/5 หมู่ 3 นนทบุรี 11000', '0945678901', 'มหาวิทยาลัย D', 'ศิลปกรรมศาสตร์', 'ปี 1', NULL, 'นางอารี มีสุข', '0865544332', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000005', 'นายวีระชัย พากเพียร', '22', '333/99 ถนนแจ้งวัฒนะ นนทบุรี 11120', '0856789012', 'มหาวิทยาลัย A', 'วิทยาศาสตร์คอมพิวเตอร์', 'ปี 4', 'บท 9012', 'นายวิชัย พากเพียร', '0954433221', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000006', 'นางสาวสุดารัตน์ อดทน', '20', '555/12 ถนนรัชดาภิเษก กรุงเทพมหานคร 10400', '0967890123', 'มหาวิทยาลัย B', 'นิติศาสตร์', 'ปี 3', NULL, 'นางสุภา อดทน', '0843322110', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000007', 'นายชลิต ศักดิ์สิทธิ์', '19', '99/88 หมู่บ้านกฤษณา ปทุมธานี 12000', '0878901234', 'สถาบัน C', 'การท่องเที่ยวและโรงแรม', 'ปี 2', 'ทท 3456', 'นายเฉลิม ศักดิ์สิทธิ์', '0932211009', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000008', 'นางสาวเพ็ญศรี พรมแดน', '23', '200 ถนนรามคำแหง กรุงเทพมหานคร 10240', '0989012345', 'มหาวิทยาลัย D', 'แพทยศาสตร์', 'ปี 6', NULL, 'นางพวงเพ็ญ พรมแดน', '0821100998', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000009', 'นายธนากร รุ่งเรือง', '18', '112/5 ซอยอุดมสุข กรุงเทพมหานคร 10260', '0990123456', 'มหาวิทยาลัย A', 'เศรษฐศาสตร์', 'ปี 1', 'มต 7890', 'นายธวัช รุ่งเรือง', '0910099887', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000010', 'นางสาวปิยนุช อิ่มเอม', '20', '44/7 ถนนกาญจนาภิเษก นนทบุรี 11110', '0801234567', 'มหาวิทยาลัย B', 'มนุษยศาสตร์', 'ปี 3', NULL, 'นางปรียา อิ่มเอม', '0909988776', '1', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000011', 'นายวิษณุ บุญมี', '21', '60/2 ถนนเพชรเกษม กรุงเทพมหานคร 10160', '0810987654', 'สถาบัน C', 'วิศวกรรมโยธา', 'ปี 4', 'ลอ 1122', 'นายบรรจบ บุญมี', '0898765432', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000012', 'นางสาวจันทร์จิรา สุขใจ', '19', '101/4 ซอยทองหล่อ กรุงเทพมหานคร 10110', '0921098765', 'มหาวิทยาลัย D', 'นิเทศศาสตร์', 'ปี 2', NULL, 'นางจันทนา สุขใจ', '0887654321', '1', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000013', 'นายพงศธร ล้ำเลิศ', '22', '33/3 ถนนวิภาวดีรังสิต ปทุมธานี 12130', '0832109876', 'มหาวิทยาลัย A', 'รัฐศาสตร์', 'ปี 4', 'ยย 3344', 'นายพงษ์ศักดิ์ ล้ำเลิศ', '0876543210', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000014', 'นางสาวกนกพร มั่นคง', '18', '77/1 ถนนพระราม 9 กรุงเทพมหานคร 10310', '0943210987', 'มหาวิทยาลัย B', 'เภสัชศาสตร์', 'ปี 1', NULL, 'นางกานดา มั่นคง', '0865432109', '1', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000015', 'นายเกียรติศักดิ์ เจริญพร', '20', '5/9 หมู่ 1 บางใหญ่ นนทบุรี 11140', '0854321098', 'สถาบัน C', 'เทคโนโลยีสารสนเทศ', 'ปี 3', 'กบ 5566', 'นายโกวิท เจริญพร', '0854321098', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000016', 'นางสาวพิมลพรรณ งามตา', '21', '18/2 ถนนสาทร กรุงเทพมหานคร 10120', '0965432109', 'มหาวิทยาลัย D', 'บัญชี', 'ปี 4', NULL, 'นางพวงทอง งามตา', '0843210987', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000017', 'นายไพศาล รักไทย', '19', '222/5 ถนนสุขสวัสดิ์ สมุทรปราการ 10290', '0876543210', 'มหาวิทยาลัย A', 'การจัดการ', 'ปี 2', 'ขก 7788', 'นายไพบูลย์ รักไทย', '0832109876', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000018', 'นางสาวรัชนีวรรณ ดวงดาว', '23', '40/10 ซอยอโศก กรุงเทพมหานคร 10110', '0987654321', 'มหาวิทยาลัย B', 'ทันตแพทยศาสตร์', 'ปี 5', NULL, 'นางราตรี ดวงดาว', '0821098765', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000019', 'นายจักรพงษ์ แก้วมณี', '18', '500 ถนนลาดกระบัง กรุงเทพมหานคร 10520', '0998765432', 'สถาบัน C', 'ครุศาสตร์', 'ปี 1', 'ดก 9900', 'นายจิระพงษ์ แก้วมณี', '0810987654', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1100000000020', 'นางสาวนันทิยา ชัยชนะ', '20', '15/3 ถนนบางนา-ตราด สมุทรปราการ 10540', '0809876543', 'มหาวิทยาลัย D', 'ภาษาศาสตร์', 'ปี 3', NULL, 'นางนงนุช ชัยชนะ', '0801234567', '2', '2025-12-10 04:32:12');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1103700456789', 'นางสาวฟ้าใส (อี) น้ำตก', '20', '123 หมู่ 5 ต.เมือง อ.พิษณุโลก', '0912345678', 'มหาวิทยาลัยนเรศวร', 'วิศวกรรมไฟฟ้า', 'ปี2', 'กน 1234', 'นายสมชาย ศรีสวัสดิ์', '0811111111', '2', '2025-12-10 03:22:06');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1103700567290', 'นายเทพพิทักษ์ (ดี) ทรงคมทอง', '21', '88 ม.10 ต.นาเฉลียง อ.หนองไผ่ จ.เพชรบูรณ์', '0987654321', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'วิทยาการคอมพิวเตอร์', 'ปี3', '1ขธ 7899', 'นางสมใจ บางนกแขวก', '0822222222', '2', '2025-12-10 03:22:06');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1103700890123', 'นางสาวอรชร (ซี) วงษ์ศรี', '20', '78 ม.2 ต.ห้วยชัน จ.ลพบุรี', '0991112233', 'มหาวิทยาลัยนเรศวร', 'วิศวกรรมการเงิน', 'ปี1', '5ฐพ 5678', 'นางปราณี ประเสริฐ', '0833333333', '0', '2025-12-10 03:22:06');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1409912345678', 'นายวรวิทย์ (บี) ชัยชนะ', '22', '150 ถ.สามัคคีชัย ต.ในเมือง อ.เมือง จ.เพชรบูรณ์', '0876543210', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'คณะครุศาสตร์', 'ปี 4', 'สน 5555', 'นายปรีชา ชัยชนะ', '0871112222', '2', '2025-12-10 03:22:06');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1412341242111', 'นางสาวฟ้าใส (อี) น้ำตก', NULL, NULL, NULL, 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'วิศวกรรมไฟฟ้า', NULL, NULL, NULL, NULL, '2', '2025-12-10 04:12:15');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1412341242112', 'นางสาวฟ้าใส (อี) น้ำตก', NULL, NULL, NULL, 'วิทยาลัยเทคนิคเพชรบูรณ์', 'ดดดดด', NULL, NULL, NULL, NULL, '2', '2025-12-10 04:13:36');
INSERT INTO `tenant` (`tnt_id`, `tnt_name`, `tnt_age`, `tnt_address`, `tnt_phone`, `tnt_education`, `tnt_faculty`, `tnt_year`, `tnt_vehicle`, `tnt_parent`, `tnt_parentsphone`, `tnt_status`, `tnt_ceatetime`) VALUES ('1990100000003', 'นายธนาธิป (เอ) สุขเกษม', '23', '199 ม.1 ถ.เลย-หล่มสัก อ.หล่มสัก จ.เพชรบูรณ์', '0634445555', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', 'คณะวิทยาศาสตร์ฯ', 'ปี 2 ', 'กม 7890', 'นางสาวเมตตา สุขเกษม', '0631234567', '2', '2025-12-10 03:22:06');

DROP TABLE IF EXISTS `tenant_custom_dropdowns`;
CREATE TABLE `tenant_custom_dropdowns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_value` (`type`,`value`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tenant_custom_dropdowns` (`id`, `type`, `value`, `created_at`) VALUES ('1', 'education', 'มหาวิทยาลัยราชภัฏเพชรบูรณ์', '2025-12-10 04:12:15');
INSERT INTO `tenant_custom_dropdowns` (`id`, `type`, `value`, `created_at`) VALUES ('2', 'faculty', 'วิทยาศาสตร์และเทคโนโลยี', '2025-12-10 04:13:36');

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


SET FOREIGN_KEY_CHECKS=1;

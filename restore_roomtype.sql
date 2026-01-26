-- Restore missing room_type table
DROP TABLE IF EXISTS `roomtype`;
CREATE TABLE `roomtype` (
  `type_id` tinyint NOT NULL AUTO_INCREMENT COMMENT 'รหัสประเภทห้องพัก',
  `type_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อประเภทห้องพัก',
  `type_price` int DEFAULT NULL COMMENT 'ราคาห้องพัก',
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roomtype` (`type_id`, `type_name`, `type_price`) VALUES ('1', 'ฝั่งเก่า', '1500');
INSERT INTO `roomtype` (`type_id`, `type_name`, `type_price`) VALUES ('2', 'ฝั่งใหม่', '1600');

<?php
/**
 * Restore missing roomtype table
 */
require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // Drop and recreate roomtype table
    $sql = "
    DROP TABLE IF EXISTS `roomtype`;
    CREATE TABLE `roomtype` (
      `type_id` tinyint NOT NULL AUTO_INCREMENT COMMENT 'รหัสประเภทห้องพัก',
      `type_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ชื่อประเภทห้องพัก',
      `type_price` int DEFAULT NULL COMMENT 'ราคาห้องพัก',
      PRIMARY KEY (`type_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($sql);
    
    // Insert data
    $inserts = [
        "INSERT INTO `roomtype` (`type_id`, `type_name`, `type_price`) VALUES ('1', 'ฝั่งเก่า', '1500')",
        "INSERT INTO `roomtype` (`type_id`, `type_name`, `type_price`) VALUES ('2', 'ฝั่งใหม่', '1600')"
    ];
    
    foreach ($inserts as $insert) {
        $pdo->exec($insert);
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    echo json_encode([
        'success' => true,
        'message' => 'roomtype table restored successfully'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

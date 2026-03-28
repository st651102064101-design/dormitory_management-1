<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// Check authorization
if (empty($_SESSION['admin_username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    // ค้นหาซ้ำ: ผู้เช่า + ห้อง ซ้ำกันมากกว่า 1 สัญญา
    $findDupesStmt = $pdo->query(
        "SELECT tnt_id, room_id, COUNT(*) as cnt, GROUP_CONCAT(ctr_id) as ctr_ids
         FROM contract
         WHERE ctr_status IN ('0','2')
         GROUP BY tnt_id, room_id
         HAVING cnt > 1
         ORDER BY cnt DESC"
    );
    
    $duplicates = $findDupesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo json_encode([
            'success' => true,
            'duplicates_found' => 0,
            'message' => 'ไม่มีสัญญาซ้ำ'
        ]);
        exit;
    }
    
    // แสดงรายการซ้ำ
    $result = [
        'success' => true,
        'duplicates_found' => count($duplicates),
        'duplicates' => []
    ];
    
    foreach ($duplicates as $dup) {
        $tntStmt = $pdo->prepare("SELECT tnt_name FROM tenant WHERE tnt_id = ?");
        $tntStmt->execute([$dup['tnt_id']]);
        $tnt = $tntStmt->fetch();
        
        $roomStmt = $pdo->prepare("SELECT room_number FROM room WHERE room_id = ?");
        $roomStmt->execute([$dup['room_id']]);
        $room = $roomStmt->fetch();
        
        $ctrIds = explode(',', $dup['ctr_ids']);
        sort($ctrIds);
        
        $result['duplicates'][] = [
            'tenant_id' => $dup['tnt_id'],
            'tenant_name' => $tnt['tnt_name'] ?? '-',
            'room_id' => $dup['room_id'],
            'room_number' => $room['room_number'] ?? '-',
            'count' => $dup['cnt'],
            'contract_ids' => $ctrIds,
            'keep_id' => $ctrIds[0],  // ตรวจสอบ: ให้เก็บ ID แรกสุด
            'delete_ids' => array_slice($ctrIds, 1)  // ลบ ID อื่นๆ
        ];
    }
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    exit;
}
?>

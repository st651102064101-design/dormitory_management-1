<?php
/**
 * API: Search existing tenants by name or phone
 * Returns tenant data for autocomplete
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../ConnectDB.php';

$pdo = connectDB();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'ต้องพิมพ์อย่างน้อย 2 ตัวอักษร']);
    exit;
}

try {
    // Search by name or phone number
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            t.tnt_id,
            t.tnt_name,
            t.tnt_phone,
            t.tnt_age,
            t.tnt_address,
            t.tnt_education,
            t.tnt_faculty,
            t.tnt_year,
            t.tnt_vehicle,
            t.tnt_parent,
            t.tnt_parentsphone,
            t.tnt_status
        FROM tenant t
        WHERE (t.tnt_name LIKE :query1 OR t.tnt_phone LIKE :query2)
        ORDER BY t.tnt_name ASC
        LIMIT 10
    ");
    
    $searchQuery = "%{$query}%";
    $stmt->execute([
        ':query1' => $searchQuery,
        ':query2' => $searchQuery
    ]);
    
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for response
    $results = [];
    foreach ($tenants as $tenant) {
        $statusText = match($tenant['tnt_status']) {
            '0' => 'ย้ายออก',
            '1' => 'พักอยู่',
            '2' => 'รอการเข้าพัก',
            '3' => 'จองห้อง',
            '4' => 'ยกเลิกจองห้อง',
            default => 'ไม่ทราบ'
        };
        
        $results[] = [
            'id' => $tenant['tnt_id'],
            'name' => $tenant['tnt_name'],
            'phone' => $tenant['tnt_phone'],
            'age' => $tenant['tnt_age'],
            'address' => $tenant['tnt_address'],
            'education' => $tenant['tnt_education'],
            'faculty' => $tenant['tnt_faculty'],
            'year' => $tenant['tnt_year'],
            'vehicle' => $tenant['tnt_vehicle'],
            'parent' => $tenant['tnt_parent'],
            'parentsphone' => $tenant['tnt_parentsphone'],
            'status' => $tenant['tnt_status'],
            'statusText' => $statusText,
            'label' => $tenant['tnt_name'] . ($tenant['tnt_phone'] ? ' (' . $tenant['tnt_phone'] . ')' : '')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'count' => count($results)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

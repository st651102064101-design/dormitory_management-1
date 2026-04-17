<?php
$content = file_get_contents('Reports/report_utility.php');

// Define our robust replacement logic
$newSqlLogic = <<<'PHP'
$filterMonthStr = str_pad((string)$filterMonth, 2, '0', STR_PAD_LEFT);
$filterYearStr  = (string)$filterYear;

// 1. Get all rooms and their active contract for the month
$roomSql = "
    SELECT r.room_id, r.room_number,
           c.ctr_id, t.tnt_name,
           (c.ctr_id IS NOT NULL) AS is_occupied
    FROM room r
    LEFT JOIN (
        SELECT room_id, MAX(ctr_id) AS ctr_id
        FROM contract
        WHERE ctr_status IN ('0','1') 
          AND ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
          AND (ctr_end IS NULL OR ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d'))
        GROUP BY room_id
    ) lc ON r.room_id = lc.room_id
    LEFT JOIN contract c ON lc.ctr_id = c.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
";
$roomStmt = $pdo->prepare($roomSql);
$roomStmt->execute([$filterYearStr, $filterMonthStr, $filterYearStr]);
$allRooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

$utilities = [];
foreach ($allRooms as $room) {
    $roomId = $room['room_id'];
    $isOccupied = (bool)$room['is_occupied'];
    $ctrId = $room['ctr_id'];

    // 2. See if there's a utility reading for this specific month
    $utilRow = null;
    $hasReading = false;
    
    if ($ctrId) {
        $uStmt = $pdo->prepare("SELECT * FROM utility WHERE MONTH(utl_date) = ? AND YEAR(utl_date) = ? AND ctr_id = ? ORDER BY utl_id DESC LIMIT 1");
        $uStmt->execute([$filterMonth, $filterYear, $ctrId]);
        $utilRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($utilRow && (
            ((int)$utilRow['utl_water_end'] !== (int)$utilRow['utl_water_start']) ||
            ((int)$utilRow['utl_elec_end'] !== (int)$utilRow['utl_elec_start'])
        )) {
            $hasReading = true;
        }
    }

    // 3. Fallback: if no reading this month, or room is vacant, get the LATEST EVER utility reading for this room BEFORE or ON this month
    $targetMonthEnd = date('Y-m-t', strtotime("$filterYear-$filterMonth-01"));
    $prevStmt = $pdo->prepare("
        SELECT u.utl_water_end, u.utl_elec_end 
        FROM utility u 
        INNER JOIN contract c2 ON u.ctr_id = c2.ctr_id
        WHERE c2.room_id = ? AND u.utl_date <= ?
        ORDER BY u.utl_date DESC, u.utl_id DESC LIMIT 1
    ");
    $prevStmt->execute([$roomId, $targetMonthEnd]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

    $oldWater = $prev ? (int)$prev['utl_water_end'] : 0;
    $oldElec  = $prev ? (int)$prev['utl_elec_end'] : 0;

    // Use current reading if exists
    $finalWaterOld = $utilRow ? (int)$utilRow['utl_water_start'] : $oldWater;
    $finalWaterNew = $utilRow ? (int)$utilRow['utl_water_end'] : ($hasReading ? 0 : $oldWater);
    $finalElecOld  = $utilRow ? (int)$utilRow['utl_elec_start'] : $oldElec;
    $finalElecNew  = $utilRow ? (int)$utilRow['utl_elec_end'] : ($hasReading ? 0 : $oldElec);

    $utilities[] = [
        'room_number' => $room['room_number'],
        'room_id'     => $room['room_id'],
        'tnt_name'    => $room['tnt_name'],
        'is_occupied' => $isOccupied,
        'has_reading' => $hasReading,
        'utl_water_start' => $finalWaterOld,
        'utl_water_end'   => $hasReading ? $finalWaterNew : '', // Leave empty if no reading
        'utl_elec_start'  => $finalElecOld,
        'utl_elec_end'    => $hasReading ? $finalElecNew : ''
    ];
}

// จัดกลุ่มตามชั้น
$floors = [];
foreach ($utilities as $util) {
    $num = (int)($util['room_number'] ?? 0);
    $floorNum = ($num >= 100) ? (int)floor($num / 100) : 1;
    $floors[$floorNum][] = $util;
}
ksort($floors);
PHP;

$pattern = '/\$sql\s*=\s*"\s*SELECT u\.\*,.*?ksort\(\$floors\);/s';
if (preg_match($pattern, $content)) {
    $content = preg_replace($pattern, $newSqlLogic, $content);
    file_put_contents('Reports/report_utility.php', $content);
    echo "Replaced successfully!\n";
} else {
    echo "Pattern not found.\n";
}

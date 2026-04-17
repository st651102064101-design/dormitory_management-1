import re

with open('Reports/report_utility.php', 'r') as f:
    content = f.read()

pattern = re.compile(r'\$sql = "\s*SELECT u\.\*,.*?ksort\(\$floors\);', re.DOTALL)

new_logic = """$filterMonthStr = str_pad((string)$filterMonth, 2, '0', STR_PAD_LEFT);
$filterYearStr  = (string)$filterYear;

$roomSql = "
    SELECT r.room_id, r.room_number,
           c.ctr_id, t.tnt_name,
           IF(c.ctr_id IS NOT NULL, 1, 0) AS is_occupied
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

    $targetMonthStart = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
    $prevStmt = $pdo->prepare("SELECT u.utl_water_end, u.utl_elec_end FROM utility u INNER JOIN contract c2 ON u.ctr_id = c2.ctr_id WHERE c2.room_id = ? AND u.utl_date < ? ORDER BY u.utl_date DESC, u.utl_id DESC LIMIT 1");
    $prevStmt->execute([$roomId, $targetMonthStart]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

    $oldWater = $prev ? (int)$prev['utl_water_end'] : 0;
    $oldElec  = $prev ? (int)$prev['utl_elec_end'] : 0;

    $finalWaterOld = $utilRow ? (int)$utilRow['utl_water_start'] : $oldWater;
    $finalWaterNew = $utilRow ? (int)$utilRow['utl_water_end'] : $oldWater;
    $finalElecOld  = $utilRow ? (int)$utilRow['utl_elec_start'] : $oldElec;
    $finalElecNew  = $utilRow ? (int)$utilRow['utl_elec_end'] : $oldElec;

    $utilities[] = [
        'room_number' => $room['room_number'],
        'room_id'     => $room['room_id'],
        'tnt_name'    => $room['tnt_name'],
        'is_occupied' => $isOccupied,
        'has_reading' => $hasReading,
        'utl_water_start' => $finalWaterOld,
        'utl_water_end'   => $finalWaterNew,
        'utl_elec_start'  => $finalElecOld,
        'utl_elec_end'    => $finalElecNew
    ];
}

$floors = [];
foreach ($utilities as $util) {
    if (!$util['is_occupied']) {
        $floors['vacant'][] = $util;
    } else {
        $floorNum = (int)floor(((int)$util['room_number']) / 100);
        $floorNum = $floorNum < 1 ? 1 : $floorNum;
        $floors['floor_'.$floorNum][] = $util;
    }
}
// Sort floors
$sortedFloors = [];
foreach ($floors as $k => $v) {
    if (strpos($k, 'floor_') === 0) {
        $sortedFloors[$k] = $v;
    }
}
ksort($sortedFloors);
if (isset($floors['vacant'])) {
    $sortedFloors['vacant'] = $floors['vacant'];
}
$floors = $sortedFloors;"""

res = pattern.sub(new_logic, content)
with open('Reports/report_utility.php', 'w') as f:
    f.write(res)
print("Replaced!")

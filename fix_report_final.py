import re

with open('Reports/report_utility.php', 'r') as f:
    text = f.read()

# 1) Replace SQL logic
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
text = re.sub(r'\$sql = "\s*SELECT u\.\*,.*?ksort\(\$floors\);', new_logic, text, flags=re.DOTALL)

# 2) Replace Floor Headers
def repl_header(m):
    return """                        <?php
                            if ($floorNum === 'vacant') {
                                $headerLabel = 'ห้องพักที่ไม่มีผู้เช่า';
                            } else {
                                $fn = str_replace('floor_', '', $floorNum);
                                $headerLabel = 'ชั้นที่ ' . $fn;
                            }
                        ?>
                        <div class="floor-header"><?php echo htmlspecialchars($headerLabel); ?></div>"""
text = re.sub(r'(\s*<div class="floor-header">ชั้นที่ <\?php echo \$floorNum; \?></div>)', repl_header, text)


# 3) Replace Water Table Body Row
water_row = """<tr>
                                <td class="room-num-cell" data-label="ห้อง"><?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></td>
                                <td class="status-icon" data-label="สถานะ">
                                    <?php if (!empty($util['tnt_name'])): ?>
                                    <svg viewBox="0 0 24 24" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($util['tnt_name']); ?>"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="prev-val" data-label="เลขมิเตอร์เดือนก่อนหน้า"><?php echo str_pad((string)(int)($util['utl_water_start'] ?? 0), 7, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="เลขมิเตอร์เดือนล่าสุด">
                                    <?php if (!$util['is_occupied']): ?>
                                        <span class="curr-val" style="color:#aaa;">-</span>
                                    <?php elseif (!$util['has_reading']): ?>
                                        <span class="badge badge-warning" style="background:#fed7aa; color:#9a3412; padding:0.25rem 0.5rem; border-radius:0.25rem; font-size:0.8rem; font-weight:600;">ยังไม่ได้จด</span>
                                    <?php else: ?>
                                        <span class="curr-val"><?php echo str_pad((string)(int)($util['utl_water_end'] ?? 0), 7, '0', STR_PAD_LEFT); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="usage-cell" data-label="หน่วยที่ใช้">
                                    <?php if (!$util['is_occupied'] || !$util['has_reading']): ?>
                                        -
                                    <?php else: ?>
                                        <?php echo number_format($waterUsage); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>"""

text = re.sub(r'<tr>\s*<td class="room-num-cell" data-label="ห้อง">.*?<td class="usage-cell" data-label="หน่วยที่ใช้"><\?php echo number_format\(\$waterUsage\); \?></td>\s*</tr>', water_row, text, count=1, flags=re.DOTALL)


# 4) Replace Electric Table Body Row
elec_row = """<tr>
                                <td class="room-num-cell" data-label="ห้อง"><?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></td>
                                <td class="status-icon" data-label="สถานะ">
                                    <?php if (!empty($util['tnt_name'])): ?>
                                    <svg viewBox="0 0 24 24" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($util['tnt_name']); ?>"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="prev-val" data-label="เลขมิเตอร์เดือนก่อนหน้า"><?php echo str_pad((string)(int)($util['utl_elec_start'] ?? 0), 5, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="เลขมิเตอร์เดือนล่าสุด">
                                    <?php if (!$util['is_occupied']): ?>
                                        <span class="curr-val elec-val" style="color:#aaa;">-</span>
                                    <?php elseif (!$util['has_reading']): ?>
                                        <span class="badge badge-warning" style="background:#fed7aa; color:#9a3412; padding:0.25rem 0.5rem; border-radius:0.25rem; font-size:0.8rem; font-weight:600;">ยังไม่ได้จด</span>
                                    <?php else: ?>
                                        <span class="curr-val elec-val"><?php echo str_pad((string)(int)($util['utl_elec_end'] ?? 0), 5, '0', STR_PAD_LEFT); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="usage-cell elec-usage" data-label="หน่วยที่ใช้">
                                    <?php if (!$util['is_occupied'] || !$util['has_reading']): ?>
                                        -
                                    <?php else: ?>
                                        <?php echo number_format($elecUsage); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>"""

text = re.sub(r'<tr>\s*<td class="room-num-cell" data-label="ห้อง">.*?<td class="usage-cell elec-usage" data-label="หน่วยที่ใช้"><\?php echo number_format\(\$elecUsage\); \?></td>\s*</tr>', elec_row, text, count=1, flags=re.DOTALL)


with open('Reports/report_utility.php', 'w') as f:
    f.write(text)
print("Done fixing completely")

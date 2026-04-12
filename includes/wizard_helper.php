<?php
/**
 * Helper Functions สำหรับระบบ Wizard
 * ใช้สำหรับจัดการและอัปเดตสถานะของ Workflow
 */

declare(strict_types=1);

/**
 * สร้าง workflow record ใหม่สำหรับผู้เช่า
 */
function createWorkflow(PDO $pdo, string $tnt_id, int $bkg_id): int {
    $stmt = $pdo->prepare("
        INSERT INTO tenant_workflow (tnt_id, bkg_id, current_step)
        VALUES (?, ?, 1)
    ");
    $stmt->execute([$tnt_id, $bkg_id]);
    return (int)$pdo->lastInsertId();
}

/**
 * อัปเดตสถานะ Step ใน Workflow
 */
function updateWorkflowStep(
    PDO $pdo,
    string $tnt_id,
    int $step,
    string $adminUsername,
    ?int $ctr_id = null
): bool {
    try {
        // อัปเดตสถานะ step ที่ระบุ
        $stepField = "step_{$step}_confirmed";
        $stepDateField = "step_{$step}_date";
        $stepByField = "step_{$step}_by";

        $sql = "UPDATE tenant_workflow
                SET {$stepField} = TRUE,
                    {$stepDateField} = NOW(),
                    {$stepByField} = ?,
                    current_step = ?";

        // ถ้ามี ctr_id ให้บันทึกด้วย
        if ($ctr_id !== null && $step >= 3) {
            $sql .= ", ctr_id = ?";
        }

        // ถ้าเป็น step 5 (สุดท้าย) ให้ทำเครื่องหมายว่าเสร็จสิ้น
        if ($step === 5) {
            $sql .= ", completed = TRUE";
        }

        $sql .= " WHERE id = (SELECT tmp.max_id FROM (SELECT MAX(id) AS max_id FROM tenant_workflow WHERE tnt_id = ?) AS tmp)";

        $stmt = $pdo->prepare($sql);

        $params = [$adminUsername, $step + 1];
        if ($ctr_id !== null && $step >= 3) {
            $params[] = $ctr_id;
        }
        $params[] = $tnt_id;

        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating workflow step: " . $e->getMessage());
        return false;
    }
}

/**
 * ดึงข้อมูล Workflow ของผู้เช่า
 */
function getWorkflow(PDO $pdo, string $tnt_id): ?array {
    $stmt = $pdo->prepare("
        SELECT * FROM tenant_workflow
        WHERE tnt_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$tnt_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * ตรวจสอบว่ามี workflow อยู่แล้วหรือไม่
 */
function workflowExists(PDO $pdo, string $tnt_id): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM tenant_workflow
        WHERE tnt_id = ?
    ");
    $stmt->execute([$tnt_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['count'] ?? 0) > 0;
}

/**
 * สร้างเลขที่ใบเสร็จ
 */
function generateReceiptNumber(): string {
    $date = date('Ymd');
    $random = str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return "RC{$date}{$random}";
}

/**
 * อัปโหลดไฟล์และคืนค่า path
 */
function uploadFile(array $file, string $uploadDir, array $allowedTypes = []): ?string {
    // ตรวจสอบว่ามีการอัปโหลดไฟล์
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }

    // ตรวจสอบ error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("File upload error: " . $file['error']);
        return null;
    }

    // ตรวจสอบประเภทไฟล์
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowedTypes) && !in_array($fileType, $allowedTypes)) {
        error_log("File type not allowed: " . $fileType);
        return null;
    }

    // สร้าง directory ถ้ายังไม่มี
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // สร้างชื่อไฟล์ใหม่
    $newFileName = uniqid() . '_' . time() . '.' . $fileType;
    $targetPath = $uploadDir . '/' . $newFileName;

    // ย้ายไฟล์
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }

    error_log("Failed to move uploaded file");
    return null;
}

/**
 * อัปโหลดหลายไฟล์และคืนค่า array ของ paths
 */
function uploadMultipleFiles(array $files, string $uploadDir, array $allowedTypes = []): array {
    $uploadedPaths = [];

    // จัดการกรณี $_FILES มีโครงสร้างพิเศษ
    if (isset($files['name']) && is_array($files['name'])) {
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($files['tmp_name'][$i])) {
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $path = uploadFile($file, $uploadDir, $allowedTypes);
            if ($path !== null) {
                $uploadedPaths[] = $path;
            }
        }
    }

    return $uploadedPaths;
}

/**
 * ดึงข้อมูลการจองพร้อมข้อมูลที่เกี่ยวข้อง
 */
function getBookingDetails(PDO $pdo, int $bkg_id): ?array {
    $stmt = $pdo->prepare("
        SELECT
            b.*,
            t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_status,
            r.room_id, r.room_number, r.room_status,
            rt.type_id, rt.type_name, rt.type_price
        FROM booking b
        LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE b.bkg_id = ?
    ");
    $stmt->execute([$bkg_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * ดึงข้อมูลสัญญาพร้อมข้อมูลที่เกี่ยวข้อง
 */
function getContractDetails(PDO $pdo, int $ctr_id): ?array {
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_status,
            r.room_id, r.room_number, r.room_status,
            rt.type_id, rt.type_name, rt.type_price
        FROM contract c
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN roomtype rt ON r.type_id = rt.type_id
        WHERE c.ctr_id = ?
    ");
    $stmt->execute([$ctr_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * ดึงอัตราค่าน้ำ-ไฟล่าสุด
 */
function getLatestRate(PDO $pdo): ?array {
    $stmt = $pdo->query("
        SELECT * FROM rate
        ORDER BY effective_date DESC, created_at DESC
        LIMIT 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * ดึงรายการ wizard ที่ยังไม่เสร็จ — ใช้ query เดียวกับ tenant_wizard.php
 * คืนค่า ['items' => array, 'count' => int]
 */
function getWizardItems(PDO $pdo): array
{
    $firstBillPaidCondition = "
        EXISTS (
            SELECT 1
            FROM expense e_first
            WHERE e_first.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                AND (
                    c.ctr_start IS NULL
                    OR DATE_FORMAT(e_first.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                )
                AND e_first.exp_month = (
                    SELECT MIN(e_min.exp_month)
                    FROM expense e_min
                    WHERE e_min.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                        AND (
                            c.ctr_start IS NULL
                            OR DATE_FORMAT(e_min.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m')
                        )
                )
                AND e_first.exp_total > 0
                AND COALESCE((
                    SELECT SUM(p.pay_amount)
                    FROM payment p
                    WHERE p.exp_id = e_first.exp_id
                      AND p.pay_status = '1'
                ), 0) >= e_first.exp_total - 0.00001
        )
    ";

    $meterRecordedCondition = "
        EXISTS (
            SELECT 1
            FROM utility u_meter
            WHERE u_meter.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
              AND u_meter.utl_water_end IS NOT NULL
        )
    ";

    $latestBillPaidCondition = "
        EXISTS (
            SELECT 1
            FROM expense e_latest
            WHERE e_latest.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                AND e_latest.exp_month = (
                    SELECT MAX(e_max.exp_month)
                    FROM expense e_max
                    WHERE e_max.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                )
                AND e_latest.exp_total > 0
                AND COALESCE((
                    SELECT SUM(p.pay_amount)
                    FROM payment p
                    WHERE p.exp_id = e_latest.exp_id
                      AND p.pay_status = '1'
                ), 0) >= e_latest.exp_total - 0.00001
        )
    ";

    $allStepsDoneCondition = "
        c.ctr_status = '0'
        AND cr.checkin_date IS NOT NULL
        AND cr.checkin_date <> '0000-00-00'
        AND $firstBillPaidCondition
        AND $latestBillPaidCondition
        AND $meterRecordedCondition
    ";

    // แสดงเฉพาะ wizard ที่ยังไม่ครบ 5 ขั้นตอน (เหมือน tenant_wizard.php default)
    $completionCondition = "AND NOT ($allStepsDoneCondition)";

    $sql = "
        SELECT
            b.bkg_id, b.bkg_date,
            t.tnt_name,
            r.room_id, r.room_number,
            COALESCE(tw.current_step, 0) AS current_step,
            COALESCE(tw.completed, 0) AS completed,
            c.ctr_id, c.ctr_status
        FROM booking b
        INNER JOIN tenant t ON b.tnt_id = t.tnt_id
        LEFT JOIN (
            SELECT tw1.*
            FROM tenant_workflow tw1
            INNER JOIN (
                SELECT bkg_id, MAX(id) AS latest_workflow_id
                FROM tenant_workflow
                GROUP BY bkg_id
            ) tw2 ON tw1.id = tw2.latest_workflow_id
        ) tw ON b.bkg_id = tw.bkg_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
        LEFT JOIN (
            SELECT cr1.*
            FROM checkin_record cr1
            INNER JOIN (
                SELECT ctr_id, MAX(checkin_id) AS latest_checkin_id
                FROM checkin_record
                GROUP BY ctr_id
            ) cr2 ON cr1.checkin_id = cr2.latest_checkin_id
        ) cr ON c.ctr_id = cr.ctr_id
        WHERE (tw.id IS NULL OR tw.completed = 0 OR tw.completed = 1)
            AND (c.ctr_id IS NULL OR c.ctr_status <> '1')
            AND NOT EXISTS (
                SELECT 1 FROM contract c3
                LEFT JOIN termination t3 ON c3.ctr_id = t3.ctr_id
                WHERE c3.room_id = b.room_id
                  AND (
                      (c3.ctr_status = '0' AND (c3.ctr_end IS NULL OR c3.ctr_end >= CURDATE()))
                      OR (c3.ctr_status = '2' AND (t3.term_date IS NULL OR t3.term_date >= CURDATE()))
                  )
                  AND COALESCE(c3.tnt_id, '') <> COALESCE(b.tnt_id, '')
            )
            $completionCondition
        ORDER BY CAST(r.room_number AS UNSIGNED) ASC
    ";

    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        return ['items' => [], 'count' => 0];
    }

    // Dedupe: เก็บ row ที่ current_step สูงสุดต่อ room
    $deduped = [];
    foreach ($rows as $row) {
        $roomKey = isset($row['room_id']) && $row['room_id'] !== null
            ? 'r' . (int)$row['room_id']
            : 'b' . (int)($row['bkg_id'] ?? 0);
        if (!isset($deduped[$roomKey])) {
            $deduped[$roomKey] = $row;
            continue;
        }
        $curStep = (int)($deduped[$roomKey]['current_step'] ?? 1);
        $newStep = (int)($row['current_step'] ?? 1);
        if ($newStep > $curStep) {
            $deduped[$roomKey] = $row;
        } elseif ($newStep === $curStep) {
            if (strtotime($row['bkg_date'] ?? '1970-01-01') > strtotime($deduped[$roomKey]['bkg_date'] ?? '1970-01-01')) {
                $deduped[$roomKey] = $row;
            }
        }
    }

    $items = array_values($deduped);
    return ['items' => $items, 'count' => count($items)];
}

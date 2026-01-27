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

        $sql .= " WHERE tnt_id = ?";

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

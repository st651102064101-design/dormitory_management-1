<?php
/**
 * ฟังก์ชันตรวจสอบและยกเลิกสัญญาที่หมดอายุอัตโนมัติ
 *
 * @param PDO $pdo Database connection
 * @return array ข้อมูลสัญญาที่ถูกยกเลิก
 */
function autoCancelExpiredContracts(PDO $pdo): array {
    $canceledContracts = [];

    try {
        // ค้นหาสัญญาที่หมดอายุแล้ว และยังคงอยู่ในสถานะปกติ (ctr_status = 0)
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT c.ctr_id, c.room_id, c.tnt_id, c.ctr_end,
                   t.tnt_name,
                   r.room_number
            FROM contract c
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN room r ON c.room_id = r.room_id
            WHERE c.ctr_status = '0'
            AND c.ctr_end < ?
        ");
        $stmt->execute([$today]);
        $expiredContracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($expiredContracts) > 0) {
            $pdo->beginTransaction();

            foreach ($expiredContracts as $contract) {
                // อัปเดตสถานะสัญญาเป็นยกเลิกแล้ว (1)
                $updateCtr = $pdo->prepare('UPDATE contract SET ctr_status = ? WHERE ctr_id = ?');
                $updateCtr->execute(['1', $contract['ctr_id']]);

                // อัปเดตสถานะห้องเป็นว่าง (0)
                $room_id = (int)$contract['room_id'];
                if ($room_id > 0) {
                    $pdo->prepare("UPDATE room SET room_status = '0' WHERE room_id = ?")->execute([$room_id]);
                }

                // อัปเดตสถานะผู้เช่าเป็นย้ายออก (0)
                $tnt_id = $contract['tnt_id'] ?? '';
                if ($tnt_id !== '') {
                    $pdo->prepare("UPDATE tenant SET tnt_status = '0' WHERE tnt_id = ?")->execute([$tnt_id]);
                }

                $canceledContracts[] = [
                    'ctr_id' => $contract['ctr_id'],
                    'tnt_name' => $contract['tnt_name'] ?? 'N/A',
                    'room_number' => $contract['room_number'] ?? 'N/A',
                    'ctr_end' => $contract['ctr_end']
                ];
            }

            $pdo->commit();
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Auto-cancel expired contracts error: " . $e->getMessage());
    }

    return $canceledContracts;
}

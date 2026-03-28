<?php
// Test error message translation

$testMessages = [
    "SQLSTATE[45000]: <<Unknown error>>: 1644 Duplicate: Room already has active contract",
    "SQLSTATE[45000]: <<Unknown error>>: 1644 Duplicate: Tenant already has active contract",
    "SQLSTATE[45000]: <<Unknown error>>: 1644 Some other error",
];

echo "🧪 Testing Error Message Translation\n";
echo "====================================\n\n";

foreach ($testMessages as $rawErrorMsg) {
    echo "Raw Error:\n";
    echo "  $rawErrorMsg\n\n";
    
    // Extract MESSAGE_TEXT from trigger (format: "1644 Message Text")
    if (preg_match('/\b\d+\s+(.+)$/i', $rawErrorMsg, $matches)) {
        $triggerMsg = trim($matches[1]);
    } else {
        $triggerMsg = $rawErrorMsg;
    }
    
    echo "Extracted:\n";
    echo "  $triggerMsg\n\n";
    
    // ตรวจสอบ trigger error messages และแปล
    $errorMsg = 'เกิดข้อผิดพลาด: ' . $rawErrorMsg;
    
    if (stripos($triggerMsg, 'Room already has active contract') !== false) {
        $errorMsg = '❌ ห้องนี้มีสัญญาที่ยังใช้อยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
    } elseif (stripos($triggerMsg, 'Tenant already has active contract') !== false) {
        $errorMsg = '❌ ผู้เช่าคนนี้มีสัญญาที่ยังใช้อยู่แล้ว - ไม่สามารถสร้างสัญญาซ้ำได้';
    } elseif (stripos($triggerMsg, 'Duplicate') !== false) {
        $errorMsg = '❌ สัญญาซ้ำ - ไม่สามารถสร้างสัญญาซ้ำสำหรับห้องหรือผู้เช่าเดียวกันได้';
    }
    
    echo "Translated:\n";
    echo "  $errorMsg\n";
    echo "\n" . str_repeat("-", 70) . "\n\n";
}
?>

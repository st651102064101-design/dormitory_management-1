<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$type = $_POST['type'] ?? ''; // 'education' or 'faculty'
$value = $_POST['value'] ?? '';

header('Content-Type: application/json');

if (!$type || !$value) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
}

$allowedTypes = ['education', 'faculty'];
$type = in_array($type, $allowedTypes, true) ? $type : '';
$value = trim($value);

if ($type === '' || $value === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
}

try {
        $customTable = 'tenant_custom_dropdowns';

        // Ensure table exists (idempotent) with a clearer name
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$customTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            value VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY type_value (type, value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Migrate data from legacy table if it exists
        $hasLegacy = $pdo->query("SHOW TABLES LIKE 'custom_options'")?->fetchColumn();
        if ($hasLegacy) {
                $pdo->exec("INSERT IGNORE INTO {$customTable} (type, value, created_at)
                    SELECT type, value, created_at FROM custom_options");
        }

        $stmt = $pdo->prepare("INSERT INTO {$customTable} (type, value) VALUES (:type, :value)
            ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute([
            ':type' => $type,
            ':value' => $value,
        ]);

        echo json_encode(['success' => true, 'message' => 'Option added successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

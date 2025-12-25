<?php
declare(strict_types=1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Bangkok');

echo "<pre>";
echo "=== TEST REPAIR UPLOAD ===\n";
echo "POST data:\n";
print_r($_POST);
echo "\nFILES data:\n";
print_r($_FILES);
echo "\nSESSION:\n";
print_r($_SESSION);
echo "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<hr><h3>Processing...</h3>";
    
    require_once __DIR__ . '/ConnectDB.php';
    
    try {
        $pdo = connectDB();
        
        $ctr_id = isset($_POST['ctr_id']) ? (int)$_POST['ctr_id'] : 0;
        $repair_desc = trim($_POST['repair_desc'] ?? '');
        
        echo "ctr_id = $ctr_id<br>";
        echo "repair_desc = $repair_desc<br>";
        
        if ($ctr_id <= 0) {
            echo "ERROR: Invalid ctr_id\n";
            exit;
        }
        
        if (empty($repair_desc)) {
            echo "ERROR: Empty repair_desc\n";
            exit;
        }
        
        // Check contract
        $stmt = $pdo->prepare('SELECT ctr_id FROM contract WHERE ctr_id = ?');
        $stmt->execute([$ctr_id]);
        if (!$stmt->fetchColumn()) {
            echo "ERROR: Contract not found\n";
            exit;
        }
        
        echo "SUCCESS: All validations passed!\n";
        
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}
?>
<form method="post" enctype="multipart/form-data">
    <input type="text" name="ctr_id" placeholder="ctr_id" value="9">
    <input type="text" name="repair_desc" placeholder="description" value="test">
    <input type="file" name="repair_image">
    <button type="submit">Test Submit</button>
</form>

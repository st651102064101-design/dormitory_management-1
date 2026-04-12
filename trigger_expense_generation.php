<?php
/**
 * Manual Trigger for Automated Expense Generation
 * Can be accessed via web browser or command line
 * Usage: http://localhost/dormitory_management/trigger_expense_generation.php
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/ConnectDB.php';

/**
 * @return array{force:bool,dry_run:bool,simulate_date:?string,month:?string}
 */
function parseCliOptions(array $argv): array
{
    $options = [
        'force' => false,
        'dry_run' => false,
        'simulate_date' => null,
        'month' => null,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--force') {
            $options['force'] = true;
            continue;
        }

        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }

        if (strpos($arg, '--simulate-date=') === 0) {
            $options['simulate_date'] = trim(substr($arg, 16));
            continue;
        }

        if (strpos($arg, '--month=') === 0) {
            $options['month'] = trim(substr($arg, 8));
        }
    }

    return $options;
}

function parseReferenceDate(?string $simulateDate, ?string $month): DateTime
{
    if ($month !== null && $month !== '') {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException("Invalid month format '$month'. Use YYYY-MM");
        }

        return new DateTime($month . '-01');
    }

    if ($simulateDate !== null && $simulateDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $simulateDate);
        $errors = DateTime::getLastErrors();
        if (!$date || !is_array($errors) || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            throw new InvalidArgumentException("Invalid simulate_date format '$simulateDate'. Use YYYY-MM-DD");
        }

        $date->setTime(0, 0, 0);
        return $date;
    }

    return new DateTime();
}

function isTruthy($value): bool
{
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

// Check if user is admin (for web access)
$isWebAccess = php_sapi_name() !== 'cli';
if ($isWebAccess && empty($_SESSION['admin_username'])) {
    header('Location: Login.php');
    exit;
}

try {
    $pdo = connectDB();

    $cliOptions = !$isWebAccess ? parseCliOptions($argv ?? []) : [
        'force' => false,
        'dry_run' => false,
        'simulate_date' => null,
        'month' => null,
    ];

    $simulateDateInput = $isWebAccess
        ? (isset($_GET['simulate_date']) ? trim((string)$_GET['simulate_date']) : null)
        : $cliOptions['simulate_date'];
    $monthInput = $isWebAccess
        ? (isset($_GET['month']) ? trim((string)$_GET['month']) : null)
        : $cliOptions['month'];

    $today = parseReferenceDate($simulateDateInput, $monthInput);

    $isManualTrigger = $isWebAccess
        ? (isset($_GET['force']) && isTruthy($_GET['force']))
        : $cliOptions['force'];
    $isDryRun = $isWebAccess
        ? (isset($_GET['dry_run']) && isTruthy($_GET['dry_run']))
        : $cliOptions['dry_run'];
    
    // Check if today is the 1st of the month
    $isDayOne = $today->format('d') === '01';
    
    if (!$isDayOne && !$isManualTrigger) {
        $message = "⏭️ Automatic expense generation only runs on the 1st of each month. Current date: " . $today->format('Y-m-d');
        if ($isWebAccess) {
            echo $message;
        } else {
            echo "[INFO] $message\n";
        }
        exit(0);
    }
    
    $currentMonth = $today->format('Y-m');
    
    if ($isWebAccess) {
        echo "<h2>Automated Expense Generation</h2>";
        echo "<p>Processing month: <strong>$currentMonth</strong></p>";
        echo "<p>Reference date: <strong>" . $today->format('Y-m-d') . "</strong></p>";
        if ($isDryRun) {
            echo "<p style='color:#d97706;'><strong>DRY RUN:</strong> Preview mode only, no data written.</p>";
        }
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace;'>";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Starting automated expense generation for month: $currentMonth";
        echo " (reference date: " . $today->format('Y-m-d') . ")";
        if ($isDryRun) {
            echo " [DRY RUN]";
        }
        echo "\n";
    }
    
    // Get all active contracts
    $contractsStmt = $pdo->query("SELECT ctr_id, ctr_start, ctr_end FROM contract WHERE ctr_status = '0'");
    $contracts = $contractsStmt ? $contractsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    echo "📋 Found " . count($contracts) . " active contracts\n";
    
    $generated = 0;
    $wouldGenerate = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($contracts as $contract) {
        $ctr_id = (int)$contract['ctr_id'];
        $ctr_start = (new DateTime($contract['ctr_start']))->format('Y-m');
        $ctr_end = (new DateTime($contract['ctr_end']))->format('Y-m');
        
        // Check if contract is valid for current month
        if ($currentMonth < $ctr_start || $currentMonth > $ctr_end) {
            echo "⏭️  Contract $ctr_id: Out of date range\n";
            $skipped++;
            continue;
        }
        
        // Check if expense already exists for this month
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
        $checkStmt->execute([$ctr_id, $currentMonth]);
        
        if ((int)$checkStmt->fetchColumn() > 0) {
            echo "⏭️  Contract $ctr_id: Already exists for $currentMonth\n";
            $skipped++;
            continue;
        }
        
        try {
            // Get room and tenant info
            $contractStmt = $pdo->prepare("
                SELECT c.*, r.room_number, rt.type_price, t.tnt_name
                FROM contract c 
                LEFT JOIN room r ON c.room_id = r.room_id 
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
                WHERE c.ctr_id = ?
            ");
            $contractStmt->execute([$ctr_id]);
            $contractInfo = $contractStmt->fetch(PDO::FETCH_ASSOC);
            
            $room_number = $contractInfo['room_number'] ?? 'N/A';
            $tenant_name = $contractInfo['tnt_name'] ?? 'Unknown';
            $room_price = (int)($contractInfo['type_price'] ?? 0);
            
            // Get latest rates
            $rateStmt = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1");
            $rateRow = $rateStmt ? $rateStmt->fetch(PDO::FETCH_ASSOC) : null;
            $rate_elec = (int)($rateRow['rate_elec'] ?? 8);
            $rate_water = (int)($rateRow['rate_water'] ?? 18);
            
            $exp_total = $room_price;

            if ($isDryRun) {
                echo "🧪 Contract $ctr_id: ห้อง $room_number - $tenant_name (จะสร้างบิล ฿$room_price)\n";
                $wouldGenerate++;
                continue;
            }

            // Create new expense record with 0 units
            $insertStmt = $pdo->prepare(" 
                INSERT INTO expense (
                    exp_month, 
                    exp_elec_unit, 
                    exp_water_unit, 
                    rate_elec, 
                    rate_water, 
                    room_price, 
                    exp_elec_chg, 
                    exp_water, 
                    exp_total, 
                    exp_status, 
                    ctr_id
                ) VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '0', ?)
            ");

            $insertStmt->execute([
                $currentMonth . '-01',
                $rate_elec,
                $rate_water,
                $room_price,
                $exp_total,
                $ctr_id
            ]);

            echo "✅ Contract $ctr_id: ห้อง $room_number - $tenant_name (ค่าห้อง ฿$room_price)\n";
            $generated++;
        } catch (Exception $e) {
            echo "❌ Contract $ctr_id: " . $e->getMessage() . "\n";
            $errors[] = "Contract $ctr_id: " . $e->getMessage();
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    if ($isDryRun) {
        echo "🧪 สรุป (DRY RUN): จะสร้าง $wouldGenerate | ข้ามไป $skipped\n";
    } else {
        echo "✅ สรุป: สร้างสำเร็จ $generated | ข้ามไป $skipped\n";
    }
    
    if (!empty($errors)) {
        echo "\n⚠️ Errors (" . count($errors) . "):\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    if ($isWebAccess) {
        echo "</pre>";
        echo "<p><a href='Reports/manage_expenses.php' style='color: #007bff; text-decoration: none;'>📊 ดูรายการค่าใช้จ่าย</a></p>";
    }
    
    exit(0);
    
} catch (Exception $e) {
    $message = "❌ Error: " . $e->getMessage();
    if ($isWebAccess) {
        echo "<p style='color: red;'>$message</p>";
    } else {
        echo "[ERROR] $message\n";
    }
    exit(1);
}
?>

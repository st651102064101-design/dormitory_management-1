<?php
session_start();
require_once '../ConnectDB.php';

// ตรวจสอบการ login
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}

$admin_name = $_SESSION['admin_username'];

try {
    $pdo = connectDB();
    
    // ดึงข้อมูล theme_color จาก system_settings (key-value format)
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_color' LIMIT 1");
    $themeColor = $stmt->fetchColumn() ?: '#0f172a';
    
    // คำนวณความสว่างของสี
    $hex = ltrim($themeColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    $isLight = $brightness > 155;
    
    // 1. รายงานข้อมูลการเข้าพัก
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = 2");
    $booking_count = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM booking WHERE bkg_status = 1");
    $booking_pending = $stmt->fetch()['total'] ?? 0;
    
    // 2. รายงานข้อมูลข่าวประชาสัมพันธ์
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM news");
    $news_count = $stmt->fetch()['total'] ?? 0;
    
    // 3. รายงานการแจ้งซ่อมอุปกรณ์
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 0");
    $repair_waiting = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 1");
    $repair_processing = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair WHERE repair_status = 2");
    $repair_completed = $stmt->fetch()['total'] ?? 0;
    
    // 4. ใบแจ้งชำระเงิน
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payment WHERE pay_status = 0");
    $payment_pending = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM payment WHERE pay_status = 1");
    $payment_verified = $stmt->fetch()['total'] ?? 0;
    
    // 5. รายงานการชำระเงิน
    $stmt = $pdo->query("SELECT SUM(pay_amount) as total FROM payment");
    $total_payment = $stmt->fetch()['total'] ?? 0;
    
    // 6. รายงานข้อมูลห้องพัก
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 1");
    $room_available = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 0");
    $room_occupied = $stmt->fetch()['total'] ?? 0;
    
    // 7. รายงานสรุปการใช้น้ำ-ไฟ
    $stmt = $pdo->query("SELECT AVG(utl_water_end - utl_water_start) as avg_water, AVG(utl_elec_end - utl_elec_start) as avg_elec FROM utility");
    $utility_avg = $stmt->fetch() ?? ['avg_water' => 0, 'avg_elec' => 0];
    $avg_water = round($utility_avg['avg_water'] ?? 0, 2);
    $avg_elec = round($utility_avg['avg_elec'] ?? 0, 2);
    
    // 8. รายงานข้อมูลรายรับ
    $stmt = $pdo->query("SELECT SUM(exp_total) as total_revenue FROM expense WHERE exp_status = 1");
    $total_revenue = $stmt->fetch()['total_revenue'] ?? 0;
    
    // 9. ข้อมูลสัญญา
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 0");
    $contract_active = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contract WHERE ctr_status = 1");
    $contract_cancelled = $stmt->fetch()['total'] ?? 0;
    
    // ดึงข้อมูล Tenant
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenant WHERE tnt_status = 1");
    $tenant_active = $stmt->fetch()['total'] ?? 0;
    
    // ดึงค่าตั้งค่าระบบ
    $siteName = 'Sangthian Dormitory';
    $logoFilename = 'Logo.jpg';
    try {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
            if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        }
    } catch (PDOException $e) {}
    
    // ข้อมูลรายได้รายเดือน
    $stmt = $pdo->query("SELECT DATE_FORMAT(exp_month, '%Y-%m') as month, SUM(exp_total) as total 
            FROM expense 
            WHERE exp_status = 1 
            GROUP BY DATE_FORMAT(exp_month, '%Y-%m')
            ORDER BY DATE_FORMAT(exp_month, '%Y-%m') DESC
            LIMIT 12");
    $monthly_revenue = array_reverse($stmt->fetchAll());
    
} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - แดชบอร์ด</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="../Assets/Css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            <?php if ($isLight): ?>
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(20,30,48,0.95), rgba(8,14,28,0.95));
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        <?php if (!$isLight): ?>
        .stat-card.danger { box-shadow: 0 10px 30px rgba(220,53,69,0.25); }
        .stat-card.success { box-shadow: 0 10px 30px rgba(40,167,69,0.22); }
        .stat-card.warning { box-shadow: 0 10px 30px rgba(255,193,7,0.22); }
        .stat-card.info { box-shadow: 0 10px 30px rgba(23,162,184,0.22); }
        <?php endif; ?>

        .stat-card h3 {
            font-size: 14px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(255,255,255,0.7);
            <?php endif; ?>
            margin-bottom: 10px;
            font-weight: normal;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
        }

        .chart-container {
            <?php if ($isLight): ?>
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(20,30,48,0.92), rgba(8,14,28,0.95));
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .chart-container h3 {
            margin-top: 0;
            <?php if ($isLight): ?>
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            <?php else: ?>
            color: #f5f8ff;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            <?php endif; ?>
            padding-bottom: 15px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .report-section {
            <?php if ($isLight): ?>
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(20,30,48,0.92), rgba(8,14,28,0.95));
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .report-section h3 {
            margin-top: 0;
            <?php if ($isLight): ?>
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            <?php else: ?>
            color: #f5f8ff;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            <?php endif; ?>
            padding-bottom: 15px;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .report-item {
            <?php if ($isLight): ?>
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            <?php else: ?>
            background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.95));
            border: 1px solid rgba(255,255,255,0.05);
            <?php endif; ?>
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .report-item label {
            display: block;
            font-size: 12px;
            <?php if ($isLight): ?>
            color: #6b7280;
            <?php else: ?>
            color: rgba(255,255,255,0.65);
            <?php endif; ?>
            margin-bottom: 8px;
        }

        .report-item .value {
            font-size: 24px;
            font-weight: bold;
            <?php if ($isLight): ?>
            color: #111827;
            <?php else: ?>
            color: #f5f8ff;
            <?php endif; ?>
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .stat-card .number {
                font-size: 24px;
            }

            .charts-row {
                grid-template-columns: 1fr;
            }

            .chart-wrapper {
                height: 250px;
            }
        }
    </style>
</head>
<body class="reports-page">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <div>
                <?php $pageTitle = 'แดชบอร์ด'; include __DIR__ . '/../includes/page_header.php'; ?>

            <!-- สรุปข้อมูล Overview -->
            <div class="dashboard-grid">
                <div class="stat-card info">
                    <h3>ผู้เช่าทั้งหมด</h3>
                    <div class="number"><?php echo $tenant_active; ?></div>
                </div>
                <div class="stat-card success">
                    <h3>ห้องว่าง</h3>
                    <div class="number"><?php echo $room_available; ?></div>
                </div>
                <div class="stat-card danger">
                    <h3>ห้องที่ใช้</h3>
                    <div class="number"><?php echo $room_occupied; ?></div>
                </div>
                <div class="stat-card warning">
                    <h3>สัญญาที่ใช้งาน</h3>
                    <div class="number"><?php echo $contract_active; ?></div>
                </div>
                <div class="stat-card danger">
                    <h3>การแจ้งซ่อมรอดำเนินการ</h3>
                    <div class="number"><?php echo $repair_waiting; ?></div>
                </div>
                <div class="stat-card info">
                    <h3>ข่าวประชาสัมพันธ์</h3>
                    <div class="number"><?php echo $news_count; ?></div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-row">
                <div class="chart-container">
                    <h3>📈 สถานะห้องพัก</h3>
                    <div class="chart-wrapper">
                        <canvas id="roomStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>🔧 สถานะการแจ้งซ่อม</h3>
                    <div class="chart-wrapper">
                        <canvas id="repairStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>💰 สถานะการชำระเงิน</h3>
                    <div class="chart-wrapper">
                        <canvas id="paymentStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- รายได้รายเดือน -->
            <div class="chart-container">
                <h3>📊 รายได้รายเดือน</h3>
                <div class="chart-wrapper" style="height: 250px;">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>

            <!-- รายงานรายละเอียด -->
            <div class="report-section">
                <h3>1️⃣ รายงานข้อมูลการเข้าพัก</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>เข้าพักแล้ว</label>
                        <div class="value"><?php echo $booking_count; ?></div>
                    </div>
                    <div class="report-item">
                        <label>จองอยู่</label>
                        <div class="value"><?php echo $booking_pending; ?></div>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>2️⃣ รายงานข้อมูลข่าวประชาสัมพันธ์</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>ข่าวทั้งหมด</label>
                        <div class="value"><?php echo $news_count; ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="manage_news.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>3️⃣ รายงานการแจ้งซ่อมอุปกรณ์ภายในห้อง</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>รอซ่อม</label>
                        <div class="value"><?php echo $repair_waiting; ?></div>
                    </div>
                    <div class="report-item">
                        <label>กำลังซ่อม</label>
                        <div class="value"><?php echo $repair_processing; ?></div>
                    </div>
                    <div class="report-item">
                        <label>ซ่อมเสร็จ</label>
                        <div class="value"><?php echo $repair_completed; ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="report_repairs.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>4️⃣ ใบแจ้งชำระเงิน</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>รอตรวจสอบ</label>
                        <div class="value"><?php echo $payment_pending; ?></div>
                    </div>
                    <div class="report-item">
                        <label>ตรวจสอบแล้ว</label>
                        <div class="value"><?php echo $payment_verified; ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="report_invoice.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>5️⃣ รายงานการชำระเงิน</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>ยอดชำระทั้งหมด</label>
                        <div class="value">฿<?php echo number_format($total_payment, 0); ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="report_payments.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>6️⃣ รายงานข้อมูลห้องพัก</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>ห้องว่าง</label>
                        <div class="value"><?php echo $room_available; ?></div>
                    </div>
                    <div class="report-item">
                        <label>ห้องไม่ว่าง</label>
                        <div class="value"><?php echo $room_occupied; ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="manage_rooms.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>7️⃣ รายงานสรุปการใช้น้ำ-ไฟ</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>เฉลี่ยน้ำ/เดือน</label>
                        <div class="value"><?php echo $avg_water; ?></div>
                    </div>
                    <div class="report-item">
                        <label>เฉลี่ยไฟ/เดือน</label>
                        <div class="value"><?php echo $avg_elec; ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="manage_utility.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>8️⃣ รายงานข้อมูลรายรับ</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>รายรับทั้งหมด</label>
                        <div class="value">฿<?php echo number_format($total_revenue, 0); ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="manage_revenue.php" style="color: #007bff; text-decoration: none;">ดูรายละเอียด →</a></label>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h3>9️⃣ พิมพ์สัญญา</h3>
                <div class="report-grid">
                    <div class="report-item">
                        <label>สัญญาที่ใช้</label>
                        <div class="value"><?php echo $contract_active; ?></div>
                    </div>
                    <div class="report-item">
                        <label><a href="print_contract.php" style="color: #007bff; text-decoration: none;">พิมพ์สัญญา →</a></label>
                    </div>
                </div>
            </div>

            </div>
        </main>
    </div>

    <script src="../Assets/Javascript/main.js" defer></script>
    <script src="../Assets/Javascript/animate-ui.js" defer></script>

    <script>
        // สีสำหรับ Charts
        const colors = {
            primary: 'rgba(0, 123, 255, 0.7)',
            primaryBorder: 'rgb(0, 123, 255)',
            success: 'rgba(40, 167, 69, 0.7)',
            successBorder: 'rgb(40, 167, 69)',
            danger: 'rgba(220, 53, 69, 0.7)',
            dangerBorder: 'rgb(220, 53, 69)',
            warning: 'rgba(255, 193, 7, 0.7)',
            warningBorder: 'rgb(255, 193, 7)',
            info: 'rgba(23, 162, 184, 0.7)',
            infoBorder: 'rgb(23, 162, 184)'
        };

        // Chart: สถานะห้องพัก
        const roomStatusCtx = document.getElementById('roomStatusChart').getContext('2d');
        new Chart(roomStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['ว่าง', 'ไม่ว่าง'],
                datasets: [{
                    data: [<?php echo $room_available; ?>, <?php echo $room_occupied; ?>],
                    backgroundColor: [colors.success, colors.danger],
                    borderColor: [colors.successBorder, colors.dangerBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        // Chart: สถานะการแจ้งซ่อม
        const repairStatusCtx = document.getElementById('repairStatusChart').getContext('2d');
        new Chart(repairStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอซ่อม', 'กำลังซ่อม', 'ซ่อมเสร็จ'],
                datasets: [{
                    data: [<?php echo $repair_waiting; ?>, <?php echo $repair_processing; ?>, <?php echo $repair_completed; ?>],
                    backgroundColor: [colors.danger, colors.warning, colors.success],
                    borderColor: [colors.dangerBorder, colors.warningBorder, colors.successBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        // Chart: สถานะการชำระเงิน
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอตรวจสอบ', 'ตรวจสอบแล้ว'],
                datasets: [{
                    data: [<?php echo $payment_pending; ?>, <?php echo $payment_verified; ?>],
                    backgroundColor: [colors.warning, colors.success],
                    borderColor: [colors.warningBorder, colors.successBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        // Chart: รายได้รายเดือน
        const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        new Chart(monthlyRevenueCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($monthly_revenue as $data) {
                        $date = new DateTime($data['month']);
                        echo "'" . $date->format('M Y') . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: [
                        <?php 
                        foreach ($monthly_revenue as $data) {
                            echo $data['total'] . ",";
                        }
                        ?>
                    ],
                    borderColor: colors.primaryBorder,
                    backgroundColor: colors.primary,
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: colors.primaryBorder,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { font: { size: 14 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '฿' + value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>

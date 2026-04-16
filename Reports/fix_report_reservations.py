import sys

file_path = "/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/report_reservations.php"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Find the end of the PHP section
end_php_idx = content.find("?>\n<!doctype html>")
if end_php_idx == -1:
    end_php_idx = content.find("?>\n<!DOCTYPE html>")

if end_php_idx == -1:
    print("Could not find the end of PHP block")
    sys.exit(1)

php_part = content[:end_php_idx + 3]

html_part = """<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการจอง</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; padding: 0; }
        .saas-card { background: #ffffff; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid rgba(226, 232, 240, 0.8); transition: all 0.2s ease; cursor: pointer; }
        .saas-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border-color: rgba(203, 213, 225, 1); }
        .saas-card.no-hover { cursor: default; }
        .saas-card.no-hover:hover { transform: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-color: rgba(226, 232, 240, 0.8); }
        .app-main { background: #f8fafc !important; }
        
        /* Table Styles for Light Theme */
        .saas-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .saas-table th { padding: 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #64748b; background: rgba(248, 250, 252, 0.8); border-bottom: 2px solid #e2e8f0; }
        .saas-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 0.9rem; }
        .saas-table tr:hover td { background: #f8fafc; }
        .saas-table tbody tr:last-child td { border-bottom: none; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* View transitions */
        .view-content { transition: opacity 0.3s ease; }
        .hidden-view { display: none !important; }
    </style>
</head>
<body class="reports-page live-light text-slate-800 antialiased">
    <div class="app-shell">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main flex-1 p-4 sm:p-8 lg:p-10 w-full overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-8 pb-12">
                
                <!-- Layout Header -->
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mt-2">
                    <div class="flex items-center gap-4">
                        <button id="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar" class="sidebar-toggle-btn p-2 bg-white border border-slate-200 rounded-lg shadow-sm hover:bg-slate-50 transition text-slate-600 flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="18" x2="21" y2="18"></line>
                            </svg>
                        </button>
                        <div>
                            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                                <span class="p-2 bg-blue-100 text-blue-600 rounded-xl">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                </span>
                                รายงานการจอง
                            </h1>
                            <p class="text-slate-500 mt-2 text-base">สรุปข้อมูลมัดจำ การจอง และผู้รอเข้าพักทั้งหมด</p>
                        </div>
                    </div>
                </div>

                <!-- Mini Stats Overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="saas-card no-hover p-6 border-l-4 border-l-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">จองแล้ว</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo $bookingConfirmed; ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saas-card no-hover p-6 border-l-4 border-l-emerald-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">เข้าพักแล้ว</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo $bookingCompleted; ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saas-card no-hover p-6 border-l-4 border-l-rose-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">ยกเลิก</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo $bookingCancelled; ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Controls -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm saas-card no-hover">
                    <div class="flex gap-2 p-1 w-full sm:w-auto overflow-x-auto">
                        <a href="report_reservations.php" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo !isset($_GET['status']) ? 'bg-slate-800 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100'; ?>">ทั้งหมด</a>
                        <a href="report_reservations.php?status=1" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'bg-blue-500 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">จองแล้ว</a>
                        <a href="report_reservations.php?status=2" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo isset($_GET['status']) && $_GET['status'] === '2' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">เข้าพักแล้ว</a>
                        <a href="report_reservations.php?status=0" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo isset($_GET['status']) && $_GET['status'] === '0' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">ยกเลิก</a>
                    </div>
                    <div class="flex gap-2 p-2 sm:p-1 items-center bg-slate-50 rounded-xl mr-1 w-full sm:w-auto border border-slate-200">
                        <button type="button" class="view-toggle-btn px-4 py-2 rounded-lg font-medium text-sm transition-all flex items-center gap-2" data-view="card" onclick="switchView('card')">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            การ์ด
                        </button>
                        <button type="button" class="view-toggle-btn px-4 py-2 rounded-lg font-medium text-sm transition-all flex items-center gap-2" data-view="table" onclick="switchView('table')">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            ตาราง
                        </button>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="relative">
                    
                    <!-- Empty State Helper -->
                    <?php if (count($rows) === 0): ?>
                    <div class="saas-card no-hover p-16 text-center">
                        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-slate-50 mb-6">
                            <svg class="w-12 h-12 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">ไม่พบข้อมูลการจอง</h3>
                        <p class="text-slate-500">ยังไม่มีประวัติการจองในสถานะนี้</p>
                    </div>
                    <?php else: ?>

                    <!-- Card View -->
                    <div id="card-view" class="view-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($rows as $r): 
                            $bgStatusIcon = match($r['bkg_status']) {
                                '0' => 'bg-rose-100 text-rose-700',
                                '1' => 'bg-blue-100 text-blue-700',
                                '2' => 'bg-emerald-100 text-emerald-700',
                                default => 'bg-amber-100 text-amber-700'
                            };
                            $badgeStatus = match($r['bkg_status']) {
                                '0' => 'bg-rose-50 text-rose-600 border border-rose-200',
                                '1' => 'bg-blue-50 text-blue-600 border border-blue-200',
                                '2' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
                                default => 'bg-amber-50 text-amber-600 border border-amber-200'
                            };
                            $statusLabel = $statusLabels[$r['bkg_status']] ?? 'ไม่ทราบ';
                        ?>
                        <div class="saas-card no-hover flex flex-col h-full overflow-hidden group">
                            <!-- Top Decorator -->
                            <div class="h-2 w-full <?php echo match($r['bkg_status']) { '0' => 'bg-rose-500', '1' => 'bg-blue-500', '2' => 'bg-emerald-500', default => 'bg-amber-500' }; ?>"></div>
                            
                            <div class="p-6 flex-grow flex flex-col">
                                <div class="flex justify-between items-start mb-6">
                                    <div class="inline-flex items-center justify-center p-3 rounded-2xl <?php echo $bgStatusIcon; ?>">
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $badgeStatus; ?>"><?php echo $statusLabel; ?></span>
                                </div>
                                
                                <div class="space-y-4 flex-grow">
                                    <div>
                                        <p class="text-xs uppercase tracking-widest text-slate-400 font-bold mb-1">รหัสอ้างอิง</p>
                                        <h4 class="text-xl font-extrabold text-slate-800">#<?php echo renderField((string)$r['bkg_id'], 'ไม่ระบุ'); ?></h4>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                        <div>
                                            <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">ห้องพัก</p>
                                            <p class="font-bold text-slate-800"><?php echo renderField($r['room_number'], '—'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">ผู้เช่า</p>
                                            <p class="font-semibold text-slate-700 truncate" title="<?php echo renderField($r['tnt_name'] ?? '', '—'); ?>"><?php echo renderField($r['tnt_name'] ?? '', '—'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-2">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                                <span class="text-xs font-semibold text-slate-500">จองเมื่อ</span>
                                                <span class="text-sm font-semibold text-slate-800"><?php echo getRelativeTime($r['bkg_date']); ?></span>
                                            </div>
                                            <div class="flex justify-between items-center pb-1">
                                                <span class="text-xs font-semibold text-slate-500">กำหนดเข้าพัก</span>
                                                <span class="text-sm font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md"><?php echo getRelativeTime($r['bkg_checkin_date']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Table View -->
                    <div id="table-view" class="view-content hidden-view">
                        <div class="saas-card no-hover overflow-hidden border border-slate-200">
                            <table id="table-reservations" class="saas-table">
                                <thead>
                                    <tr>
                                        <th>รหัสพนักงาน</th>
                                        <th>ผู้เช่า</th>
                                        <th>ห้องพัก</th>
                                        <th>วันที่ทำรายการ</th>
                                        <th>กำหนดเข้าพัก</th>
                                        <th>สถานะผู้เช่า</th>
                                        <th>สถานะการจอง</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): 
                                        $badgeStatus = match($r['bkg_status']) {
                                            '0' => 'bg-rose-50 text-rose-600 border border-rose-200',
                                            '1' => 'bg-blue-50 text-blue-600 border border-blue-200',
                                            '2' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
                                            default => 'bg-amber-50 text-amber-600 border border-amber-200'
                                        };
                                        $statusLabel = $statusLabels[$r['bkg_status']] ?? 'ไม่ทราบ';
                                        
                                        $tenantStatusLabels = [
                                            '0' => 'ย้ายออก', '1' => 'พักอยู่', '2' => 'รอซักพัก', '3' => 'จองห้อง', '4' => 'ยกเลิก'
                                        ];
                                        $tenantStatus = $tenantStatusLabels[$r['tnt_status'] ?? ''] ?? 'ไม่ทราบ';
                                        $tenantStatusBadge = ($r['tnt_status'] === '2') 
                                            ? 'bg-purple-50 text-purple-600 border border-purple-200' 
                                            : 'bg-slate-50 text-slate-600 border border-slate-200';
                                    ?>
                                    <tr class="transition-colors group cursor-pointer hover:bg-slate-50">
                                        <td class="font-bold text-slate-800">#<?php echo renderField((string)$r['bkg_id'], '—'); ?></td>
                                        <td class="font-medium text-slate-700">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs uppercase">
                                                    <?php echo mb_substr(renderField($r['tnt_name'] ?? 'U', 'U'), 0, 2); ?>
                                                </div>
                                                <span class="truncate max-w-[150px]"><?php echo renderField($r['tnt_name'] ?? '', '—'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-bold px-3 py-1 bg-slate-100 text-slate-700 rounded-lg"><?php echo renderField($r['room_number'], '—'); ?></span>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-semibold text-slate-800"><?php echo getRelativeTime($r['bkg_date']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-sm font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-md"><?php echo getRelativeTime($r['bkg_checkin_date']); ?></span>
                                        </td>
                                        <td>
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $tenantStatusBadge; ?>"><?php echo $tenantStatus; ?></span>
                                        </td>
                                        <td>
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $badgeStatus; ?>"><?php echo $statusLabel; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script>
        function switchView(view) {
            const cardView = document.getElementById('card-view');
            const tableView = document.getElementById('table-view');
            const buttons = document.querySelectorAll('.view-toggle-btn');
            
            if (!cardView || !tableView) return;
            
            buttons.forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
                btn.classList.add('bg-transparent', 'text-slate-600', 'hover:bg-slate-100');
            });
            
            const activeBtn = document.querySelector(`.view-toggle-btn[data-view="${view}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-transparent', 'text-slate-600', 'hover:bg-slate-100');
                activeBtn.classList.add('bg-blue-600', 'text-white', 'shadow-md');
            }
            
            if (view === 'card') {
                cardView.classList.remove('hidden-view');
                tableView.classList.add('hidden-view');
                localStorage.setItem('reservationViewMode', 'card');
            } else {
                cardView.classList.add('hidden-view');
                tableView.classList.remove('hidden-view');
                localStorage.setItem('reservationViewMode', 'table');
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const dbDefaultView = '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>';
            const savedView = localStorage.getItem('reservationViewMode') || dbDefaultView;
            switchView(savedView);
            
            const tableEl = document.getElementById('table-reservations');
            if (tableEl && typeof simpleDatatables !== 'undefined') {
                new simpleDatatables.DataTable(tableEl, {
                    searchable: true,
                    fixedHeight: false,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "ค้นหาข้อมูลการจอง...",
                        perPage: "รายการต่อหน้า",
                        noRows: "ไม่พบข้อมูล",
                        info: "แสดง {start} ถึง {end} จากทั้งหมด {rows} รายการ"
                    }
                });
            }
        });
    </script>
</body>
</html>
"""

with open(file_path, "w", encoding="utf-8") as f:
    f.write(php_part + html_part)

print("Patch applied successfully.")

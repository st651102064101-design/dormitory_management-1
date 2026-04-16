import sys
import re

file_path = "/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/manage_stay.php"

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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานการเข้าพัก</title>
    <?php include __DIR__ . '/../includes/sidebar_toggle.php'; ?>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css">
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; margin: 0; padding: 0; }
        .saas-card { background: #ffffff; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid rgba(226, 232, 240, 0.8); transition: all 0.2s ease; cursor: pointer; }
        .saas-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border-color: rgba(203, 213, 225, 1); }
        .saas-card.no-hover { cursor: default; }
        .saas-card.no-hover:hover { transform: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-color: rgba(226, 232, 240, 0.8); }
        .app-main { background: #f8fafc !important; }
        
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
                                <span class="p-2 bg-indigo-100 text-indigo-600 rounded-xl">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                </span>
                                รายงานการเข้าพัก
                            </h1>
                            <p class="text-slate-500 mt-2 text-base">สรุปจำนวนห้องว่าง และห้องที่มีผู้เช่า ณ วันนี้</p>
                        </div>
                    </div>
                </div>

                <!-- Mini Stats Overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="saas-card no-hover p-6 border-l-4 border-l-slate-400">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">ห้องทั้งหมด</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format($stats['total']); ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saas-card no-hover p-6 border-l-4 border-l-emerald-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">ห้องว่าง</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format($stats['vacant']); ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saas-card no-hover p-6 border-l-4 border-l-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">ห้องมีผู้เช่า</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format($stats['occupied']); ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Controls -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm saas-card no-hover">
                    <div class="flex gap-2 p-1 w-full sm:w-auto overflow-x-auto">
                        <a href="manage_stay.php" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo !isset($_GET['status']) ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">ทั้งหมด</a>
                        <a href="manage_stay.php?status=1" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo isset($_GET['status']) && $_GET['status'] === '1' ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">มีผู้เช่าอยู่</a>
                        <a href="manage_stay.php?status=0" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo isset($_GET['status']) && $_GET['status'] === '0' ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">ห้องว่าง</a>
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
                    <?php if (count($rooms) === 0): ?>
                    <div class="saas-card no-hover p-16 text-center">
                        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-slate-50 mb-6">
                            <svg class="w-12 h-12 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">ไม่พบข้อมูลห้องพัก</h3>
                        <p class="text-slate-500">ยังไม่มีข้อมูลในระบบ หรือจากการกรองข้อมูล</p>
                    </div>
                    <?php else: ?>

                    <!-- Card View -->
                    <div id="card-view" class="view-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($rooms as $r): 
                            $statusLabel = $statusLabels[(string)$r['room_status']] ?? 'ไม่ทราบ';
                            $bgStatusIcon = match((string)$r['room_status']) {
                                '0' => 'bg-emerald-100 text-emerald-700',
                                '1' => 'bg-blue-100 text-blue-700',
                                default => 'bg-slate-100 text-slate-700'
                            };
                            $badgeStatus = match((string)$r['room_status']) {
                                '0' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
                                '1' => 'bg-blue-50 text-blue-600 border border-blue-200',
                                default => 'bg-slate-50 text-slate-600 border border-slate-200'
                            };
                            // สำหรับการแสดงสัญญาใช้งาน 
                            // $r['active_contract_id'] คือ id สัญญา ถ้ามี
                            $hasContract = !empty($r['active_contract_id']);
                            $tnt_name = $hasContract ? ($r['tnt_name'] ?? 'ไม่ระบุชื่อ') : '—';
                        ?>
                        <div class="saas-card no-hover flex flex-col h-full overflow-hidden group">
                            <!-- Top Decorator -->
                            <div class="h-2 w-full <?php echo match((string)$r['room_status']) { '0' => 'bg-emerald-500', '1' => 'bg-blue-500', default => 'bg-slate-500' }; ?>"></div>
                            
                            <div class="p-6 flex-grow flex flex-col">
                                <div class="flex justify-between items-start mb-6">
                                    <div class="inline-flex items-center justify-center p-3 rounded-2xl <?php echo $bgStatusIcon; ?>">
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap <?php echo $badgeStatus; ?>"><?php echo $statusLabel; ?></span>
                                </div>
                                
                                <div class="space-y-4 flex-grow">
                                    <div>
                                        <p class="text-xs uppercase tracking-widest text-slate-400 font-bold mb-1">หมายเลขห้อง</p>
                                        <h4 class="text-2xl font-extrabold text-slate-800"><?php echo renderField($r['room_number'], 'ไม่ระบุ'); ?></h4>
                                    </div>
                                    
                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col gap-2">
                                        <p class="text-[10px] uppercase font-bold text-slate-400">ประเภทห้อง</p>
                                        <p class="font-bold text-slate-700 truncate" title="<?php echo renderField($r['type_name'], 'ไม่ระบุ'); ?>"><?php echo renderField($r['type_name'], 'ไม่ระบุ'); ?></p>
                                    </div>
                                    
                                    <div class="pt-2">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                                <span class="text-xs font-semibold text-slate-500">ผู้เช่าปัจจุบัน</span>
                                                <span class="text-sm font-semibold text-slate-800 truncate pl-2 max-w-[120px] text-right" title="<?php echo htmlspecialchars($tnt_name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tnt_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                            <div class="flex justify-between items-center pb-1">
                                                <span class="text-xs font-semibold text-slate-500">ราคา/เดือน (฿)</span>
                                                <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md"><?php echo number_format((float)($r['type_price'] ?? 0)); ?></span>
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
                            <table id="table-stay">
                                <thead>
                                    <tr>
                                        <th>ห้องพัก</th>
                                        <th>ประเภทห้อง</th>
                                        <th>ราคา (บาท)</th>
                                        <th>ผู้เช่าปัจจุบัน</th>
                                        <th>สถานะห้อง</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rooms as $r): 
                                        $badgeStatus = match((string)$r['room_status']) {
                                            '0' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
                                            '1' => 'bg-blue-50 text-blue-600 border border-blue-200',
                                            default => 'bg-slate-50 text-slate-600 border border-slate-200'
                                        };
                                        $statusLabel = $statusLabels[(string)$r['room_status']] ?? 'ไม่ทราบ';
                                        $hasContract = !empty($r['active_contract_id']);
                                        $tnt_name = $hasContract ? ($r['tnt_name'] ?? 'ไม่ระบุชื่อ') : '—';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="font-bold px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-lg"><?php echo renderField($r['room_number'], '—'); ?></span>
                                        </td>
                                        <td class="font-medium text-slate-700">
                                            <?php echo renderField($r['type_name'], '—'); ?>
                                        </td>
                                        <td>
                                            <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md"><?php echo number_format((float)($r['type_price'] ?? 0)); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($hasContract): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs uppercase shadow-sm border border-slate-200">
                                                    <?php echo mb_substr(renderField($tnt_name, 'U'), 0, 2); ?>
                                                </div>
                                                <span class="truncate max-w-[200px] font-semibold text-slate-700"><?php echo renderField($tnt_name, '—'); ?></span>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-slate-400 italic">ว่าง</span>
                                            <?php endif; ?>
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
                localStorage.setItem('stayViewMode', 'card');
            } else {
                cardView.classList.add('hidden-view');
                tableView.classList.remove('hidden-view');
                localStorage.setItem('stayViewMode', 'table');
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const dbDefaultView = '<?php echo $defaultViewMode === "list" ? "table" : "card"; ?>';
            const savedView = localStorage.getItem('stayViewMode') || dbDefaultView;
            switchView(savedView);
            
            const tableEl = document.getElementById('table-stay');
            if (tableEl && typeof simpleDatatables !== 'undefined') {
                new simpleDatatables.DataTable(tableEl, {
                    searchable: true,
                    fixedHeight: false,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "ค้นหาข้อมูลห้องพัก...",
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

print("HTML rewrite patch applied to manage_stay.")

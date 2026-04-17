import sys

file_path = "/tmp/manage_stay.php"
target_path = "/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/manage_stay.php"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

end_php_idx = content.find("?>\n<!doctype html>")
if end_php_idx == -1:
    end_php_idx = content.find("?>\n<!DOCTYPE html>")

php_part = content[:end_php_idx + 3]

html_part = """<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - รายงานข้อมูลการเข้าพัก</title>
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
                                รายงานข้อมูลการเข้าพัก
                            </h1>
                            <p class="text-slate-500 mt-2 text-base">ตรวจสอบข้อมูลผู้เช่า สิทธิ์การใช้ห้องพัก และสถานะสัญญา</p>
                        </div>
                    </div>
                </div>

                <!-- Mini Stats Overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="saas-card no-hover p-6 border-l-4 border-l-emerald-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">กำลังเข้าพัก</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format($contractsActive); ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saas-card no-hover p-6 border-l-4 border-l-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">ยกเลิกสัญญา</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format($contractsCancelled); ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-red-50 flex items-center justify-center text-red-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="saas-card no-hover p-6 border-l-4 border-l-amber-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">แจ้งยกเลิก</h3>
                                <div class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format($contractsPendingCancel); ?></div>
                            </div>
                            <div class="h-12 w-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters & Controls -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm saas-card no-hover">
                    <div class="flex gap-2 p-1 w-full sm:w-auto overflow-x-auto">
                        <a href="manage_stay.php?status=all" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo $selectedStatus === 'all' ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">ทั้งหมด</a>
                        <a href="manage_stay.php?status=1" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo $selectedStatus === '1' ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">ยกเลิกสัญญาแล้ว</a>
                        <a href="manage_stay.php?status=2" class="px-5 py-2.5 rounded-xl font-medium text-sm transition-all whitespace-nowrap <?php echo $selectedStatus === '2' ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>">รายการที่แจ้งยกเลิก</a>
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
                            <svg class="w-12 h-12 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">ไม่มีข้อมูลการเข้าพัก</h3>
                        <p class="text-slate-500">ยังไม่มีข้อมูลในระบบ หรือจากการกรองข้อมูล</p>
                    </div>
                    <?php else: ?>

                    <!-- Card View -->
                    <div id="card-view" class="view-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($rows as $r): 
                            $statusLabel = $statusLabels[(string)$r['ctr_status']] ?? 'ไม่ทราบ';
                            $bgStatusIcon = match((string)$r['ctr_status']) {
                                '0' => 'bg-emerald-100 text-emerald-700',
                                '1' => 'bg-red-100 text-red-700',
                                '2' => 'bg-amber-100 text-amber-700',
                                default => 'bg-slate-100 text-slate-700'
                            };
                            $badgeStatus = match((string)$r['ctr_status']) {
                                '0' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
                                '1' => 'bg-red-50 text-red-600 border border-red-200',
                                '2' => 'bg-amber-50 text-amber-600 border border-amber-200',
                                default => 'bg-slate-50 text-slate-600 border border-slate-200'
                            };
                        ?>
                        <div class="saas-card no-hover flex flex-col h-full overflow-hidden group">
                            <!-- Top Decorator -->
                            <div class="h-2 w-full <?php echo match((string)$r['ctr_status']) { '0' => 'bg-emerald-500', '1' => 'bg-red-500', '2' => 'bg-amber-500', default => 'bg-slate-500' }; ?>"></div>
                            
                            <div class="p-6 flex-grow flex flex-col">
                                <div class="flex justify-between items-start mb-6">
                                    <div class="inline-flex items-center justify-center p-3 rounded-2xl <?php echo $bgStatusIcon; ?>">
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <?php if((string)$r['ctr_status'] === '1'): ?>
                                            <path d="M18 6L6 18M6 6l12 12"/>
                                            <?php elseif((string)$r['ctr_status'] === '2'): ?>
                                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                            <?php else: ?>
                                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                                            <?php endif; ?>
                                        </svg>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap <?php echo $badgeStatus; ?>"><?php echo $statusLabel; ?></span>
                                </div>
                                
                                <div class="space-y-4 flex-grow">
                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">หมายเลขห้อง</p>
                                            <span class="text-[10px] text-slate-400 uppercase font-medium">สัญญา #<?php echo renderField((string)$r['ctr_id'], '—'); ?></span>
                                        </div>
                                        <h4 class="text-2xl font-extrabold text-slate-800"><?php echo renderField($r['room_number'], 'ไม่ระบุ'); ?></h4>
                                    </div>
                                    
                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col gap-2">
                                        <p class="text-[10px] uppercase font-bold text-slate-400">ผู้เช่า</p>
                                        <p class="font-bold text-slate-700 truncate" title="<?php echo renderField($r['tnt_name'], '—'); ?>"><?php echo renderField($r['tnt_name'], '—'); ?></p>
                                    </div>
                                    
                                    <div class="pt-2">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                                <span class="text-xs font-semibold text-slate-500">ช่วงเข้าพัก</span>
                                                <span class="text-[11px] font-semibold text-slate-800 text-right">
                                                    <?php echo renderField($r['ctr_start'], '—'); ?> <br/>
                                                    <span class="text-slate-400">ถึง</span> <?php echo renderField($r['ctr_end'], '—'); ?>
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center pb-1">
                                                <span class="text-xs font-semibold text-slate-500">มัดจำ (฿)</span>
                                                <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md"><?php echo number_format((float)($r['ctr_deposit'] ?? 0)); ?></span>
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
                            <table class="datatable-modern">
                                <thead>
                                    <tr>
                                        <th>รหัสสัญญา</th>
                                        <th>ผู้เช่า</th>
                                        <th>ห้อง</th>
                                        <th>ช่วงเข้าพัก</th>
                                        <th>มัดจำ (บาท)</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): 
                                        $badgeStatus = match((string)$r['ctr_status']) {
                                            '0' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
                                            '1' => 'bg-red-50 text-red-600 border border-red-200',
                                            '2' => 'bg-amber-50 text-amber-600 border border-amber-200',
                                            default => 'bg-slate-50 text-slate-600 border border-slate-200'
                                        };
                                        $statusLabel = $statusLabels[(string)$r['ctr_status']] ?? 'ไม่ทราบ';
                                    ?>
                                    <tr>
                                        <td class="font-medium text-slate-500">
                                            #<?php echo renderField((string)$r['ctr_id'], '—'); ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs uppercase shadow-sm border border-slate-200">
                                                    <?php echo mb_substr(renderField($r['tnt_name'], 'U'), 0, 2); ?>
                                                </div>
                                                <span class="truncate max-w-[200px] font-semibold text-slate-700"><?php echo renderField($r['tnt_name'], '—'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-bold px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-lg"><?php echo renderField($r['room_number'], '—'); ?></span>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-semibold text-slate-700"><?php echo renderField($r['ctr_start'], '—'); ?></span>
                                                <span class="text-xs text-slate-400">ถึง <?php echo renderField($r['ctr_end'], '—'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md"><?php echo number_format((float)($r['ctr_deposit'] ?? 0)); ?></span>
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
            
            const tableEls = document.querySelectorAll('.datatable-modern');
            tableEls.forEach(tableEl => {
                if (typeof simpleDatatables !== 'undefined') {
                    new simpleDatatables.DataTable(tableEl, {
                        searchable: true,
                        fixedHeight: false,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "ค้นหาข้อมูล...",
                            perPage: "รายการต่อหน้า",
                            noRows: "ไม่พบข้อมูล",
                            info: "แสดง {start} ถึง {end} จากทั้งหมด {rows} รายการ"
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
"""

with open(target_path, "w", encoding="utf-8") as f:
    f.write(php_part + html_part)

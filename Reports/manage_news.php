<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// ดึงข้อมูลข่าว (เรียงตาม ID ใหม่สุดก่อน)
$stmt = $pdo->query("SELECT * FROM news ORDER BY news_id DESC");
$newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนข่าว
$totalNews = count($newsList);
$recentNews = 0;
$oneMonthAgo = date('Y-m-d', strtotime('-1 month'));
foreach ($newsList as $news) {
    if ($news['news_date'] >= $oneMonthAgo) {
        $recentNews++;
    }
}

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
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการข่าวประชาสัมพันธ์</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <style>
        /* Force-hide animate-ui modal overlays on this page */
        .animate-ui-modal, .animate-ui-modal-overlay { display: none !important; visibility: hidden !important; opacity: 0 !important; }
      .news-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .news-stat-card {
        background: linear-gradient(135deg, rgba(18,24,40,0.85), rgba(7,13,26,0.95));
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255,255,255,0.08);
        color: #f5f8ff;
        box-shadow: 0 15px 35px rgba(3,7,18,0.4);
      }
      .news-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.7);
      }
      .news-stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
        color: #60a5fa;
      }
      .news-form {
        display: grid;
        gap: 1rem;
        margin-top: 1.5rem;
      }
      .news-form-group label {
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        display: block;
        margin-bottom: 0.4rem;
      }
      .news-form-group input,
      .news-form-group textarea {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(8,12,24,0.85);
        color: #f5f8ff;
        font-family: inherit;
      }
      .news-form-group textarea {
        min-height: 120px;
        resize: vertical;
      }
      .news-form-group input:focus,
      .news-form-group textarea:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      .news-form-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
      }
      .news-card {
        background: linear-gradient(135deg, rgba(30,41,59,0.6), rgba(15,23,42,0.8));
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
      }
      .news-card:hover {
        border-color: rgba(96,165,250,0.4);
        box-shadow: 0 8px 24px rgba(96,165,250,0.15);
      }
      .news-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
      }
      .news-card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #f5f8ff;
        margin: 0;
      }
      .news-card-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: #94a3b8;
        margin-bottom: 0.75rem;
      }
      .news-card-meta span {
        display: flex;
        align-items: center;
        gap: 0.3rem;
      }
      .news-card-content {
        color: #cbd5e1;
        line-height: 1.6;
        margin-bottom: 1rem;
      }
      .news-card-actions {
        display: flex;
        gap: 0.5rem;
      }
      .news-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: #64748b;
      }
      .news-empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
      }
      /* Edit modal layout */
      #editModal {
        display: none;
        position: fixed;
        inset: 0;
        padding: 1.5rem;
        background: rgba(8,15,30,0.7);
        backdrop-filter: blur(6px);
        align-items: center;
        justify-content: center;
        z-index: 20000;
      }
      #editModal.is-open { display: flex; }
      #editModal .booking-modal-content {
        width: min(720px, 100%);
        background: linear-gradient(145deg, #0f172a, #111827);
        border-radius: 16px;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 20px 60px rgba(0,0,0,0.45);
        padding: 1.8rem;
        color: #e2e8f0;
      }
      #editModal h2 {
        margin-top: 0;
        margin-bottom: 1rem;
        color: #f8fafc;
        text-align: center;
      }
      #editModal .booking-form-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin-bottom: 1rem;
      }
      #editModal label {
        font-weight: 600;
        color: rgba(255,255,255,0.85);
      }
      #editModal input,
      #editModal textarea {
        width: 100%;
        padding: 0.75rem 0.9rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(15,23,42,0.85);
        color: #f8fafc;
        font-family: inherit;
      }
      #editModal textarea { min-height: 140px; resize: vertical; }
      #editModal input:focus,
      #editModal textarea:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      #editModal .booking-form-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        margin-top: 1rem;
      }
      #editModal .btn-submit {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #0b1727;
        border: none;
        border-radius: 10px;
        padding: 0.75rem 1.4rem;
        font-weight: 700;
        cursor: pointer;
      }
      #editModal .btn-cancel {
        background: rgba(248,250,252,0.1);
        color: #e2e8f0;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 0.75rem 1.1rem;
        font-weight: 600;
        cursor: pointer;
      }
      #submitNewsBtn:hover {
        background: #31A74F !important;
        opacity: 0.9;
      }
      #submitNewsBtn:active {
        opacity: 0.8;
      }
        .reports-page .manage-panel { margin-top: 1.4rem; margin-bottom: 1.4rem; margin-right: 1.4rem; background: #0f172a; border: 1px solid rgba(148,163,184,0.2); box-shadow: 0 12px 30px rgba(0,0,0,0.2); }
        .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
    </style>
  </head>
  <body class="reports-page" data-disable-edit-modal="true">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการข่าวประชาสัมพันธ์';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <?php if (isset($_SESSION['success'])): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showSuccessToast('<?php echo addslashes($_SESSION['success']); ?>');
              });
            </script>
            <?php unset($_SESSION['success']); ?>
          <?php endif; ?>
          <?php if (isset($_SESSION['error'])): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showErrorToast('<?php echo addslashes($_SESSION['error']); ?>');
              });
            </script>
            <?php unset($_SESSION['error']); ?>
          <?php endif; ?>

          <section class="manage-panel">
            <div class="news-stats">
              <div class="news-stat-card">
                <h3>ข่าวทั้งหมด</h3>
                <div class="stat-value" id="totalNewsCount"><?php echo number_format($totalNews); ?></div>
              </div>
              <div class="news-stat-card">
                <h3>ข่าวใหม่ (30 วัน)</h3>
                <div class="stat-value" id="recentNewsCount" style="color:#22c55e;"><?php echo number_format($recentNews); ?></div>
              </div>
            </div>
          </section>

          <section class="manage-panel" style="background:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95)); color:#f8fafc;">
            <div class="section-header">
              <div>
                <h1>เพิ่มข่าวประชาสัมพันธ์ใหม่</h1>
                <p style="margin-top:0.25rem;color:rgba(255,255,255,0.7);">เผยแพร่ข่าวสารและประกาศสำคัญ</p>
              </div>
            </div>
            <form action="../Manage/process_news.php" method="post" id="newsForm">
              <div class="news-form">
                <div class="news-form-group">
                  <label for="news_title">หัวข้อข่าว <span style="color:#f87171;">*</span></label>
                  <input type="text" id="news_title" name="news_title" required maxlength="255" placeholder="ระบุหัวข้อข่าว" />
                </div>
                <div class="news-form-group">
                  <label for="news_details">รายละเอียด <span style="color:#f87171;">*</span></label>
                  <textarea id="news_details" name="news_details" required placeholder="เขียนรายละเอียดข่าว..."></textarea>
                </div>
                <div class="news-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                  <div>
                    <label for="news_date">วันที่เผยแพร่ <span style="color:#f87171;">*</span></label>
                    <input type="date" id="news_date" name="news_date" required value="<?php echo date('Y-m-d'); ?>" />
                  </div>
                  <div>
                    <label for="news_by">ผู้เผยแพร่</label>
                    <input type="text" id="news_by" name="news_by" maxlength="100" placeholder="ชื่อผู้เผยแพร่" value="<?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ''); ?>" readonly style="background: rgba(8,12,24,0.5); cursor: not-allowed; color: rgba(255,255,255,0.6);" />
                  </div>
                </div>
                <div class="news-form-actions">
                  <button type="submit" id="submitNewsBtn" style="flex:1; background: #34C759; color: white; padding: 0.85rem 1.5rem; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease; font-size: 1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    เผยแพร่ข่าว
                  </button>
                  <button type="reset" style="flex:1; background: #FF3B30; color: white; padding: 0.85rem 1.5rem; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease; font-size: 1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    ล้างข้อมูล
                  </button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header">
              <div>
                <h1>รายการข่าวทั้งหมด</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">ข่าวประชาสัมพันธ์และประกาศต่างๆ</p>
              </div>
            </div>
            
            <?php if (empty($newsList)): ?>
              <div class="news-empty">
                <div class="news-empty-icon">📰</div>
                <h3>ยังไม่มีข่าวประชาสัมพันธ์</h3>
                <p>เริ่มต้นเพิ่มข่าวใหม่จากฟอร์มด้านบน</p>
              </div>
            <?php else: ?>
              <div style="margin-top:1rem;" id="newsContainer">
                <?php 
                $displayLimit = 6;
                $totalNews = count($newsList);
                foreach ($newsList as $index => $news): 
                  $isHidden = $index >= $displayLimit ? ' style="display:none;"' : '';
                ?>
                  <div class="news-card news-item" data-news-id="<?php echo $news['news_id']; ?>"<?php echo $isHidden; ?>>
                    <div class="news-card-header">
                      <h3 class="news-card-title"><?php echo htmlspecialchars($news['news_title']); ?></h3>
                    </div>
                    <div class="news-card-meta">
                      <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo $news['news_date'] ? date('d/m/Y', strtotime($news['news_date'])) : '-'; ?>
                      </span>
                      <?php if (!empty($news['news_by'])): ?>
                        <span>
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                          <?php echo htmlspecialchars($news['news_by']); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="news-card-content">
                      <?php echo nl2br(htmlspecialchars($news['news_details'])); ?>
                    </div>
                    <div class="news-card-actions">
                      <button type="button" class="animate-ui-action-btn edit" data-no-modal="true" data-animate-ui-skip="true" data-news-id="<?php echo $news['news_id']; ?>" onclick="editNews(<?php echo $news['news_id']; ?>)">แก้ไข</button>
                      <button type="button" class="animate-ui-action-btn delete" onclick="deleteNews(<?php echo $news['news_id']; ?>, '<?php echo htmlspecialchars(addslashes($news['news_title'])); ?>')">ลบ</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if ($totalNews > $displayLimit): ?>
                <div style="text-align:center; margin-top:1.5rem;">
                  <button type="button" id="showMoreBtn" onclick="showMoreNews()" style="background:#007AFF; color:#fff; border:none; border-radius:10px; padding:0.75rem 2rem; font-weight:600; cursor:pointer; transition:all 0.3s;">
                    ดูเพิ่มเติม (<?php echo $totalNews - $displayLimit; ?> รายการ)
                  </button>
                  <button type="button" id="showLessBtn" onclick="showLessNews()" style="display:none; background:#FF9500; color:#fff; border:none; border-radius:10px; padding:0.75rem 2rem; font-weight:600; cursor:pointer; transition:all 0.3s;">
                    แสดงน้อยลง
                  </button>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>

    <!-- Edit Modal -->
    <div class="booking-modal" id="editModal" style="display:none;">
      <div class="booking-modal-content">
        <h2>แก้ไขข่าวประชาสัมพันธ์</h2>
        <form id="editForm" method="POST" action="../Manage/update_news.php">
          <input type="hidden" name="news_id" id="edit_news_id">
          
          <div class="booking-form-group">
            <label>หัวข้อข่าว: <span style="color: red;">*</span></label>
            <input type="text" name="news_title" id="edit_news_title" required maxlength="255">
          </div>
          
          <div class="booking-form-group">
            <label>รายละเอียด: <span style="color: red;">*</span></label>
            <textarea name="news_details" id="edit_news_details" required style="min-height:150px;"></textarea>
          </div>
          
          <div class="booking-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <label>วันที่เผยแพร่: <span style="color: red;">*</span></label>
              <input type="date" name="news_date" id="edit_news_date" required>
            </div>
            <div>
              <label>ผู้เผยแพร่:</label>
              <input type="text" name="news_by" id="edit_news_by" maxlength="100">
            </div>
          </div>
          
          <div class="booking-form-actions">
            <button type="submit" class="btn-submit">บันทึกการแก้ไข</button>
            <button type="button" class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>

    <script src="../Assets/Javascript/animate-ui.js" defer></script>
    <script src="../Assets/Javascript/main.js" defer></script>
    <script>
      const newsData = <?php echo json_encode($newsList); ?>;
      
      // ฟังก์ชันอัพเดทสถิติข่าว
      function updateStats(action) {
        const totalEl = document.getElementById('totalNewsCount');
        const recentEl = document.getElementById('recentNewsCount');
        
        if (!totalEl || !recentEl) return;
        
        let totalCount = parseInt(totalEl.textContent.replace(/,/g, ''));
        let recentCount = parseInt(recentEl.textContent.replace(/,/g, ''));
        
        if (action === 'add') {
          totalCount++;
          recentCount++; // ข่าวใหม่ที่เพิ่มจะอยู่ในช่วง 30 วันเสมอ
        } else if (action === 'delete') {
          totalCount = Math.max(0, totalCount - 1);
          recentCount = Math.max(0, recentCount - 1); // สมมุติว่าเป็นข่าวใหม่
        }
        
        totalEl.textContent = totalCount.toLocaleString();
        recentEl.textContent = recentCount.toLocaleString();
        
        // เอฟเฟกต์เล็กๆ
        [totalEl, recentEl].forEach(el => {
          el.style.transition = 'transform 0.3s ease';
          el.style.transform = 'scale(1.15)';
          setTimeout(() => { el.style.transform = 'scale(1)'; }, 300);
        });
      }
      
      // Hard block animate-ui modal for edit buttons on this page
      document.addEventListener('DOMContentLoaded', () => {
        // Disable animate-ui openModal globally on this page
        window.openModal = function() { return; };

        // Capture-phase delegation: open our edit modal and stop animate-ui
        document.body.addEventListener('click', (e) => {
          const editBtn = e.target.closest('.animate-ui-action-btn.edit');
          if (editBtn) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const id = editBtn.dataset.newsId || editBtn.getAttribute('data-news-id');
            if (id) {
              editNews(id);
            }
          }
        }, true);
      });

      // AJAX submit สำหรับ editForm
      document.addEventListener('DOMContentLoaded', function() {
        const editForm = document.getElementById('editForm');
        if (editForm) {
          editForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const formData = new FormData(editForm);
            
            try {
              console.log('Submitting edit form...', Object.fromEntries(formData));
              
              const response = await fetch('../Manage/update_news_ajax.php', {
                method: 'POST',
                body: formData,
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                }
              });
              
              console.log('Response status:', response.status);
              
              if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
              }
              
              const text = await response.text();
              console.log('Raw response:', text);
              
              let result;
              try {
                result = JSON.parse(text);
              } catch (e) {
                console.error('Failed to parse JSON:', e);
                showErrorToast('เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ');
                return;
              }
              
              console.log('Parsed result:', result);
              
              if (result.success) {
                showSuccessToast(result.message || 'แก้ไขข่าวสำเร็จ');
                closeEditModal();
                
                // อัพเดท card ใน DOM
                const newsId = formData.get('news_id');
                const newsCard = document.querySelector(`[data-news-id="${newsId}"]`);
                if (newsCard) {
                  const title = formData.get('news_title');
                  const details = formData.get('news_details');
                  const date = formData.get('news_date');
                  const by = formData.get('news_by');
                  
                  // Format date
                  const dateObj = new Date(date);
                  const formattedDate = String(dateObj.getDate()).padStart(2, '0') + '/' + 
                                       String(dateObj.getMonth() + 1).padStart(2, '0') + '/' + 
                                       dateObj.getFullYear();
                  
                  // อัพเดท card content - ตรวจสอบว่า element มีอยู่
                  const titleEl = newsCard.querySelector('.news-card-title');
                  if (titleEl) titleEl.textContent = title;
                  
                  const contentEl = newsCard.querySelector('.news-card-content p');
                  if (contentEl) contentEl.textContent = details;
                  
                  const metaSpans = newsCard.querySelectorAll('.news-card-meta span');
                  if (metaSpans.length > 0) {
                    metaSpans[0].innerHTML = `
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      ${formattedDate}
                    `;
                  }
                  
                  if (by && metaSpans.length > 1) {
                    metaSpans[1].innerHTML = `
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                      ${escapeHtml(by)}
                    `;
                  }
                  
                  // อัพเดท newsData array
                  const newsIndex = newsData.findIndex(n => n.news_id == newsId);
                  if (newsIndex !== -1) {
                    newsData[newsIndex].news_title = title;
                    newsData[newsIndex].news_details = details;
                    newsData[newsIndex].news_date = date;
                    newsData[newsIndex].news_by = by;
                  }
                }
              } else {
                showErrorToast(result.error || 'เกิดข้อผิดพลาดในการแก้ไขข่าว');
              }
            } catch (error) {
              console.error('Update error:', error);
              showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
          }, true);
        }
      });
      
      // Block animate-ui from intercepting the submit button
      document.addEventListener('DOMContentLoaded', function() {
        const submitBtn = document.getElementById('submitNewsBtn');
        const newsForm = document.getElementById('newsForm');
        
        if (submitBtn && newsForm) {
          // Prevent default form submission and handle via AJAX
          submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            if (!newsForm.checkValidity()) {
              newsForm.reportValidity();
              return false;
            }
            
            // Collect form data
            const formData = new FormData(newsForm);
            
            // Submit via AJAX
            fetch('../Manage/process_news.php', {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            })
            .then(response => response.json())
            .then(result => {
              if (result.success) {
                showSuccessToast(result.message || 'เผยแพร่ข่าวสำเร็จ');
                
                // เก็บข้อมูลก่อน reset
                const newsInfo = {
                  id: result.news_id,
                  title: newsForm.querySelector('[name="news_title"]').value,
                  details: newsForm.querySelector('[name="news_details"]').value,
                  date: newsForm.querySelector('[name="news_date"]').value,
                  by: newsForm.querySelector('[name="news_by"]').value
                };
                
                newsForm.reset();
                
                // คืนค่า news_by กลับมาหลัง reset
                newsForm.querySelector('[name="news_by"]').value = newsInfo.by;
                
                // เพิ่มข่าวใหม่ลงใน DOM แทนการ reload
                if (result.news_id) {
                  addNewsToList(newsInfo);
                  updateStats('add'); // อัพเดทสถิติ
                }
              } else {
                showErrorToast(result.error || 'เกิดข้อผิดพลาดในการเผยแพร่ข่าว');
              }
            })
            .catch(error => {
              console.error(error);
              showErrorToast('เกิดข้อผิดพลาดในการส่งข้อมูล');
            });
            
            return false;
          }, true); // Capture phase
        }
      });
      
      function editNews(newsId) {
        const news = newsData.find(n => n.news_id == newsId);
        console.log('เปิด modal แก้ไขข่าว', newsId, news);
        if (!news) {
          showErrorToast('ไม่พบข้อมูลข่าว');
          return;
        }
        document.getElementById('edit_news_id').value = news.news_id;
        document.getElementById('edit_news_title').value = news.news_title;
        document.getElementById('edit_news_details').value = news.news_details;
        document.getElementById('edit_news_date').value = news.news_date;
        document.getElementById('edit_news_by').value = news.news_by || '';
        const modal = document.getElementById('editModal');
        modal.classList.add('is-open');
        modal.style.display = 'flex';
      }
      
      function closeEditModal() {
        console.log('Closing edit modal...');
        const modal = document.getElementById('editModal');
        if (modal) {
          modal.style.display = 'none';
          const form = document.getElementById('editForm');
          if (form) {
            form.reset();
          }
          console.log('Modal closed successfully');
        } else {
          console.error('Modal not found!');
        }
      }
      
      async function deleteNews(newsId, newsTitle) {
        const confirmed = await showConfirmDialog(
          'ยืนยันการลบข่าว',
          `คุณต้องการลบข่าว <strong>"${escapeHtml(newsTitle)}"</strong> หรือไม่?<br><br>การดำเนินการนี้ไม่สามารถย้อนกลับได้`
        );
        
        if (!confirmed) return;
        
        try {
          const formData = new FormData();
          formData.append('news_id', newsId);
          
          const response = await fetch('../Manage/delete_news.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          const result = await response.json();
          
          if (result.success) {
            showSuccessToast(result.message);
            // ลบ card ออกจาก DOM
            const newsCard = document.querySelector(`[data-news-id="${newsId}"]`);
            if (newsCard) {
              newsCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
              newsCard.style.opacity = '0';
              newsCard.style.transform = 'scale(0.9)';
              setTimeout(() => {
                newsCard.remove();
                updateStats('delete'); // อัพเดทสถิติหลังลบ card
              }, 300);
            }
          } else {
            showErrorToast(result.error || 'เกิดข้อผิดพลาดในการลบข่าว');
          }
        } catch (error) {
          console.error('Delete error:', error);
          showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
      }
      
      // ฟังก์ชันเพิ่มข่าวใหม่ลงใน DOM
      function addNewsToList(newsInfo) {
        const { id: newsId, title, details, date, by } = newsInfo;
        
        // เพิ่มข้อมูลลงใน newsData array เพื่อให้ editNews ใช้ได้
        newsData.unshift({
          news_id: newsId,
          news_title: title,
          news_details: details,
          news_date: date,
          news_by: by
        });
        
        // Format date เป็น dd/mm/yyyy
        const dateObj = new Date(date);
        const formattedDate = String(dateObj.getDate()).padStart(2, '0') + '/' + 
                             String(dateObj.getMonth() + 1).padStart(2, '0') + '/' + 
                             dateObj.getFullYear();
        
        const newsHTML = `
          <div class="news-card news-item" data-news-id="${newsId}" style="opacity: 0; transform: scale(0.95);">
            <div class="news-card-header">
              <h3 class="news-card-title">${escapeHtml(title)}</h3>
            </div>
            <div class="news-card-meta">
              <span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                ${formattedDate}
              </span>
              ${by ? `<span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                ${escapeHtml(by)}
              </span>` : ''}
            </div>
            <div class="news-card-content">
              <p>${escapeHtml(details)}</p>
            </div>
            <div class="news-card-actions">
              <button type="button" class="animate-ui-action-btn edit" data-news-id="${newsId}">แก้ไข</button>
              <button type="button" class="animate-ui-action-btn delete" onclick="deleteNews(${newsId}, '${escapeHtml(title).replace(/'/g, "\\'")}')">ลบ</button>
            </div>
          </div>
        `;
        
        const newsContainer = document.getElementById('newsContainer');
        if (newsContainer) {
          // เพิ่มข่าวใหม่ที่ตำแหน่งแรกสุด (บนสุด)
          newsContainer.insertAdjacentHTML('afterbegin', newsHTML);
          
          // Animate fade in
          const newCard = newsContainer.querySelector(`[data-news-id="${newsId}"]`);
          setTimeout(() => {
            newCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            newCard.style.opacity = '1';
            newCard.style.transform = 'scale(1)';
          }, 10);
        }
      }
      
      // Helper function เพื่อ escape HTML
      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
      
      // Close modal when clicking outside
      document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
      });

      // Show more/less news functions
      function showMoreNews() {
        const hiddenItems = document.querySelectorAll('.news-item[style*="display:none"]');
        hiddenItems.forEach(item => {
          item.style.display = '';
        });
        document.getElementById('showMoreBtn').style.display = 'none';
        document.getElementById('showLessBtn').style.display = 'inline-block';
      }

      function showLessNews() {
        const allItems = document.querySelectorAll('.news-item');
        allItems.forEach((item, index) => {
          if (index >= 6) {
            item.style.display = 'none';
          }
        });
        document.getElementById('showMoreBtn').style.display = 'inline-block';
        document.getElementById('showLessBtn').style.display = 'none';
        // Scroll to news section
        document.getElementById('newsContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    </script>
    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
  </body>
</html>

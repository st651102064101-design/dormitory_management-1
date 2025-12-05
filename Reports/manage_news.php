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

// ดึงข้อมูลข่าว
$stmt = $pdo->query("SELECT * FROM news ORDER BY news_date DESC, news_id DESC");
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
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการข่าวประชาสัมพันธ์</title>
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <style>
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
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการข่าวประชาสัมพันธ์';
            include __DIR__ . '/../includes/page_header.php'; 
          ?>

          <?php if (isset($_SESSION['success'])): ?>
            <div style="padding: 1rem; margin-bottom: 1rem; background: #22c55e; color: #0f172a; border-radius: 10px; font-weight:600;">
              <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
          <?php endif; ?>
          <?php if (isset($_SESSION['error'])): ?>
            <div style="padding: 1rem; margin-bottom: 1rem; background: #ef4444; color: #fff; border-radius: 10px; font-weight:600;">
              <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
          <?php endif; ?>

          <section class="manage-panel">
            <div class="news-stats">
              <div class="news-stat-card">
                <h3>ข่าวทั้งหมด</h3>
                <div class="stat-value"><?php echo number_format($totalNews); ?></div>
              </div>
              <div class="news-stat-card">
                <h3>ข่าวใหม่ (30 วัน)</h3>
                <div class="stat-value" style="color:#22c55e;"><?php echo number_format($recentNews); ?></div>
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
            <form action="../Manage/process_news.php" method="post">
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
                    <input type="text" id="news_by" name="news_by" maxlength="100" placeholder="ชื่อผู้เผยแพร่" value="<?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ''); ?>" />
                  </div>
                </div>
                <div class="news-form-actions">
                  <button type="submit" class="animate-ui-add-btn" style="flex:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    เผยแพร่ข่าว
                  </button>
                  <button type="reset" class="animate-ui-action-btn delete" style="flex:1;">ล้างข้อมูล</button>
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
              <div style="margin-top:1rem;">
                <?php foreach ($newsList as $news): ?>
                  <div class="news-card">
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
                      <button type="button" class="animate-ui-action-btn edit" onclick="editNews(<?php echo $news['news_id']; ?>)">แก้ไข</button>
                      <button type="button" class="animate-ui-action-btn delete" onclick="deleteNews(<?php echo $news['news_id']; ?>, '<?php echo htmlspecialchars(addslashes($news['news_title'])); ?>')">ลบ</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>

    <!-- Edit Modal -->
    <div class="booking-modal" id="editModal" style="display:none;">
      <div class="booking-modal-content" style="max-width:600px;">
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
      
      function editNews(newsId) {
        const news = newsData.find(n => n.news_id == newsId);
        if (!news) return;
        
        document.getElementById('edit_news_id').value = news.news_id;
        document.getElementById('edit_news_title').value = news.news_title;
        document.getElementById('edit_news_details').value = news.news_details;
        document.getElementById('edit_news_date').value = news.news_date;
        document.getElementById('edit_news_by').value = news.news_by || '';
        
        document.getElementById('editModal').style.display = 'flex';
      }
      
      function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('editForm').reset();
      }
      
      function deleteNews(newsId, newsTitle) {
        if (!confirm(`ยืนยันการลบข่าว "${newsTitle}"?`)) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../Manage/delete_news.php';
        
        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'news_id';
        idField.value = newsId;
        
        form.appendChild(idField);
        document.body.appendChild(form);
        form.submit();
      }
      
      // Close modal when clicking outside
      document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
      });
    </script>
  </body>
</html>

<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/thai_date_helper.php';
require_once __DIR__ . '/../includes/lang.php';
$pdo = connectDB();
$currentLang = getLang();

// รับค่า sort จาก query parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'news_id DESC';

switch ($sortBy) {
  case 'oldest':
    $orderBy = 'news_id ASC';
    break;
  case 'title':
    $orderBy = 'news_title ASC';
    break;
  case 'newest':
  default:
    $orderBy = 'news_id DESC';
}

// ดึงข้อมูลข่าว (เรียงตาม ID ใหม่สุดก่อน)
$stmt = $pdo->query("SELECT * FROM news ORDER BY $orderBy");
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
$themeColor = '#0f172a';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename', 'theme_color')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
        if ($row['setting_key'] === 'logo_filename') $logoFilename = $row['setting_value'];
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
    }
} catch (PDOException $e) { error_log("PDOException in " . __FILE__ . " on line " . __LINE__ . ": " . $e->getMessage()); }

// ตรวจสอบว่าเป็น light theme หรือไม่
$isLightTheme = false;
$lightThemeClass = '';
if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $themeColor)) {
    $hex = ltrim($themeColor, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    if ($brightness > 180) {
        $isLightTheme = true;
        $lightThemeClass = 'light-theme';
    }
}

$newsManageTitle = __('news_manage_title');

if (!function_exists('formatNewsDateForManageNews')) {
  function formatNewsDateForManageNews(?string $dateValue, string $lang): string {
    if (empty($dateValue)) {
      return '-';
    }

    $timestamp = strtotime($dateValue);
    if ($timestamp === false) {
      return $dateValue;
    }

    if ($lang === 'en') {
      return date('j M Y', $timestamp);
    }

    return thaiDate(date('Y-m-d', $timestamp));
  }
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $lightThemeClass; ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($newsManageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/jpeg" href="/dormitory_management/Public/Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/main.css" />
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/particle-effects.css">
    <script src="/dormitory_management/Public/Assets/Javascript/particle-effects.js"></script>
    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/confirm-modal.css" />
    <style>
        /* Force-hide animate-ui modal overlays on this page */
        .animate-ui-modal, .animate-ui-modal-overlay { display: none !important; visibility: hidden !important; opacity: 0 !important; }

      /* ===== Light Theme Overrides ===== */
      html.light-theme .page-title,
      html.light-theme .page-subtitle,
      html.light-theme .news-card-title,
      html.light-theme .news-card-content,
      html.light-theme .news-card-content p,
      html.light-theme .news-card h3,
      html.light-theme .news-card p,
      html.light-theme .news-meta,
      html.light-theme .news-date,
      html.light-theme .news-author,
      html.light-theme .news-card-meta,
      html.light-theme .news-card-meta span,
      html.light-theme label,
      html.light-theme select {
        color: #111827 !important;
      }

      html.light-theme .news-card {
        background: rgba(255,255,255,0.9) !important;
        border-color: rgba(0,0,0,0.1) !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08) !important;
      }
      html.light-theme .news-card svg {
        stroke: #374151 !important;
      }

      html.light-theme .news-stat-card {
        background: linear-gradient(135deg, rgba(243,244,246,0.95), rgba(229,231,235,0.9)) !important;
        border-color: rgba(0,0,0,0.08) !important;
      }
      html.light-theme .news-stat-card h3 {
        color: #6b7280 !important;
      }
      html.light-theme .news-stat-card .stat-value {
        color: #2563eb !important;
      }

      /* Buttons - keep white text on colored backgrounds */
      html.light-theme .btn-edit,
      html.light-theme .btn-delete,
      html.light-theme .news-card-actions button,
      html.light-theme .news-card button[style*="background"] {
        color: #ffffff !important;
      }
      html.light-theme .news-card-actions button svg,
      html.light-theme .news-card button svg {
        stroke: #ffffff !important;
      }

      /* Sort select */
      html.light-theme .sort-select,
      html.light-theme select {
        background: rgba(0,0,0,0.05) !important;
        border-color: rgba(0,0,0,0.12) !important;
        color: #111827 !important;
      }

      .news-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .news-stat-card {
        background: linear-gradient(135deg, #ffffff, #f8fafc);
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(15,23,42,0.08);
        color: #0f172a;
        box-shadow: 0 10px 24px rgba(15,23,42,0.08);
      }
      .news-stat-card h3 {
        margin: 0;
        font-size: 0.95rem;
        color: #475569;
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
        color: #334155;
        font-weight: 600;
        display: block;
        margin-bottom: 0.4rem;
      }
      .news-form-group input,
      .news-form-group textarea {
        width: 100%;
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(15,23,42,0.14);
        background: #ffffff;
        color: #0f172a;
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
        background: #ffffff;
        border: 1px solid rgba(15,23,42,0.1);
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
        color: #0f172a;
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
        color: #334155;
        line-height: 1.6;
        margin-bottom: 1rem;
      }
      .news-card-actions {
        display: flex;
        gap: 0.5rem;
      }
      .news-card-actions .animate-ui-action-btn.edit,
      .news-card-actions .animate-ui-action-btn.delete {
        color: #ffffff !important;
      }
      .reports-page .news-card-actions .animate-ui-action-btn.edit {
        background: #007AFF !important;
        border: 1px solid #007AFF !important;
        color: #ffffff !important;
      }
      .reports-page .news-card-actions .animate-ui-action-btn.delete {
        background: #FF3B30 !important;
        border: 1px solid #FF3B30 !important;
        color: #ffffff !important;
      }
      .reports-page .news-card-actions .animate-ui-action-btn.edit:hover {
        background: #0A66DB !important;
        border-color: #0A66DB !important;
      }
      .reports-page .news-card-actions .animate-ui-action-btn.delete:hover {
        background: #E63D32 !important;
        border-color: #E63D32 !important;
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
        background: rgba(15,23,42,0.35);
        backdrop-filter: blur(6px);
        align-items: center;
        justify-content: center;
        z-index: 20000;
      }
      #editModal.is-open { display: flex; }
      #editModal .booking-modal-content {
        width: min(720px, 100%);
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid rgba(15,23,42,0.12);
        box-shadow: 0 20px 60px rgba(15,23,42,0.2);
        padding: 1.8rem;
        color: #1e293b;
      }
      #editModal h2 {
        margin-top: 0;
        margin-bottom: 1rem;
        color: #0f172a;
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
        color: #334155;
      }
      #editModal input,
      #editModal textarea {
        width: 100%;
        padding: 0.75rem 0.9rem;
        border-radius: 10px;
        border: 1px solid rgba(15,23,42,0.14);
        background: #ffffff;
        color: #0f172a;
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
        background: #f8fafc;
        color: #334155;
        border: 1px solid rgba(15,23,42,0.14);
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
      .page-header-bar {
        margin-top: 1rem !important;
      }
      .reports-page .manage-panel {
        margin: 0 !important;
        background: #ffffff;
        border: 1px solid rgba(15,23,42,0.1);
        box-shadow: 0 10px 26px rgba(15,23,42,0.08);
      }
    </style>
  </head>
  <body class="reports-page" data-disable-edit-modal="true">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = $newsManageTitle;
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
              <div class="news-stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <h3><?php echo __('news_total_label'); ?></h3>
                <div class="stat-value" id="totalNewsCount"><?php echo number_format($totalNews); ?></div>
              </div>
              <div class="news-stat-card particle-wrapper">
                <div class="particle-container" data-particles="3"></div>
                <h3><?php echo __('news_recent_30_days_label'); ?></h3>
                <div class="stat-value" id="recentNewsCount" style="color:#22c55e;"><?php echo number_format($recentNews); ?></div>
              </div>
            </div>
          </section>

          <!-- Toggle button for news form -->
          <div style="margin:1.5rem 0;">
            <button type="button" id="toggleNewsFormBtn" style="white-space:nowrap;padding:0.8rem 1.5rem;cursor:pointer;font-size:1rem;background:#ffffff;border:1px solid rgba(15,23,42,0.16);color:#1e293b;border-radius:8px;transition:all 0.2s;box-shadow:0 2px 4px rgba(15,23,42,0.08);" onclick="toggleNewsForm()" onmouseover="this.style.background='#f8fafc';this.style.borderColor='rgba(15,23,42,0.24)'" onmouseout="this.style.background='#ffffff';this.style.borderColor='rgba(15,23,42,0.16)'">
              <span id="toggleNewsFormIcon">▼</span> <span id="toggleNewsFormText"><?php echo __('toggle_hide_form'); ?></span>
            </button>
          </div>

          <section class="manage-panel" style="background:#ffffff; color:#0f172a;" id="addNewsSection">
            <div class="section-header">
              <div>
                <h1><?php echo __('news_add_new_title'); ?></h1>
                <p style="margin-top:0.25rem;color:#64748b;"><?php echo __('news_add_new_subtitle'); ?></p>
              </div>
            </div>
            <form action="../Manage/process_news.php" method="post" id="newsForm">
              <div class="news-form">
                <div class="news-form-group">
                  <label for="news_title"><?php echo __('news_title'); ?> <span style="color:#f87171;">*</span></label>
                  <input type="text" id="news_title" name="news_title" required maxlength="255" placeholder="<?php echo htmlspecialchars(__('news_title_placeholder'), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="news-form-group">
                  <label for="news_details"><?php echo __('news_content'); ?> <span style="color:#f87171;">*</span></label>
                  <textarea id="news_details" name="news_details" required placeholder="<?php echo htmlspecialchars(__('news_content_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                </div>
                <div class="news-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                  <div>
                    <label for="news_date"><?php echo __('news_date'); ?> <span style="color:#f87171;">*</span></label>
                    <input type="date" id="news_date" name="news_date" required value="<?php echo date('Y-m-d'); ?>" />
                  </div>
                  <div>
                    <label for="news_by"><?php echo __('news_publisher'); ?></label>
                    <input type="text" id="news_by" name="news_by" maxlength="100" placeholder="<?php echo htmlspecialchars(__('news_publisher_placeholder'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ''); ?>" readonly style="background:#f8fafc; border-color:rgba(15,23,42,0.12); cursor:not-allowed; color:#64748b;" />
                  </div>
                </div>
                <div class="news-form-actions">
                  <button type="submit" id="submitNewsBtn" style="flex:1; background: #34C759; color: white; padding: 0.85rem 1.5rem; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease; font-size: 1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                    <?php echo __('news_publish_button'); ?>
                  </button>
                  <button type="reset" style="flex:1; background: #FF3B30; color: white; padding: 0.85rem 1.5rem; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease; font-size: 1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    <?php echo __('clear_data'); ?>
                  </button>
                </div>
              </div>
            </form>
          </section>

          <section class="manage-panel">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
              <div>
                <h1><?php echo __('news_list_title'); ?></h1>
                <p style="color:#64748b;margin-top:0.2rem;"><?php echo __('news_list_subtitle'); ?></p>
              </div>
              <select id="sortSelect" onchange="changeSortBy(this.value)" style="padding:0.6rem 0.85rem;border-radius:8px;border:1px solid rgba(15,23,42,0.16);background:#ffffff;color:#0f172a;font-size:0.95rem;cursor:pointer;">
                <option value="newest" <?php echo ($sortBy === 'newest' ? 'selected' : ''); ?>><?php echo __('sort_newest'); ?></option>
                <option value="oldest" <?php echo ($sortBy === 'oldest' ? 'selected' : ''); ?>><?php echo __('sort_oldest'); ?></option>
                <option value="title" <?php echo ($sortBy === 'title' ? 'selected' : ''); ?>><?php echo __('sort_title'); ?></option>
              </select>
            </div>
            
            <?php if (empty($newsList)): ?>
              <div class="news-empty">
                <div class="news-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:56px;height:56px;"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg></div>
                <h3><?php echo __('news_empty_title'); ?></h3>
                <p><?php echo __('news_empty_hint'); ?></p>
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
                        <?php echo formatNewsDateForManageNews($news['news_date'] ?? null, $currentLang); ?>
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
                      <button type="button" class="animate-ui-action-btn edit" data-no-modal="true" data-animate-ui-skip="true" data-news-id="<?php echo $news['news_id']; ?>" onclick="editNews(<?php echo $news['news_id']; ?>)"><?php echo __('edit'); ?></button>
                      <button type="button" class="animate-ui-action-btn delete" onclick="deleteNews(<?php echo $news['news_id']; ?>, '<?php echo htmlspecialchars(addslashes($news['news_title'])); ?>')"><?php echo __('delete'); ?></button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if ($totalNews > $displayLimit): ?>
                <div style="text-align:center; margin-top:1.5rem;">
                  <button type="button" id="showMoreBtn" onclick="showMoreNews()" style="background:#007AFF; color:#fff; border:none; border-radius:10px; padding:0.75rem 2rem; font-weight:600; cursor:pointer; transition:all 0.3s;">
                    <?php echo __('show_more_items', ['count' => $totalNews - $displayLimit]); ?>
                  </button>
                  <button type="button" id="showLessBtn" onclick="showLessNews()" style="display:none; background:#FF9500; color:#fff; border:none; border-radius:10px; padding:0.75rem 2rem; font-weight:600; cursor:pointer; transition:all 0.3s;">
                    <?php echo __('show_less'); ?>
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
        <h2><?php echo __('edit_news'); ?></h2>
        <form id="editForm" method="POST" action="../Manage/update_news.php">
          <input type="hidden" name="news_id" id="edit_news_id">
          
          <div class="booking-form-group">
            <label><?php echo __('news_title'); ?>: <span style="color: red;">*</span></label>
            <input type="text" name="news_title" id="edit_news_title" required maxlength="255">
          </div>
          
          <div class="booking-form-group">
            <label><?php echo __('news_content'); ?>: <span style="color: red;">*</span></label>
            <textarea name="news_details" id="edit_news_details" required style="min-height:150px;"></textarea>
          </div>
          
          <div class="booking-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <label><?php echo __('news_date'); ?>: <span style="color: red;">*</span></label>
              <input type="date" name="news_date" id="edit_news_date" required>
            </div>
            <div>
              <label><?php echo __('news_publisher'); ?>:</label>
              <input type="text" name="news_by" id="edit_news_by" maxlength="100">
            </div>
          </div>
          
          <div class="booking-form-actions">
            <button type="submit" class="btn-submit"><?php echo __('save_changes'); ?></button>
            <button type="button" class="btn-cancel" onclick="closeEditModal()"><?php echo __('cancel'); ?></button>
          </div>
        </form>
      </div>
    </div>

    <script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js" defer></script>
    <script src="/dormitory_management/Public/Assets/Javascript/main.js" defer></script>
    <script>
      const currentNewsLang = <?php echo json_encode($currentLang); ?>;
      const newsI18n = {
        hideForm: <?php echo json_encode(__('toggle_hide_form'), JSON_UNESCAPED_UNICODE); ?>,
        showForm: <?php echo json_encode(__('toggle_show_form'), JSON_UNESCAPED_UNICODE); ?>,
        invalidResponse: <?php echo json_encode(__('news_invalid_response'), JSON_UNESCAPED_UNICODE); ?>,
        updateSuccess: <?php echo json_encode(__('news_update_success'), JSON_UNESCAPED_UNICODE); ?>,
        updateError: <?php echo json_encode(__('news_update_error'), JSON_UNESCAPED_UNICODE); ?>,
        updateConnectionError: <?php echo json_encode(__('news_connection_error'), JSON_UNESCAPED_UNICODE); ?>,
        publishSuccess: <?php echo json_encode(__('news_publish_success'), JSON_UNESCAPED_UNICODE); ?>,
        publishError: <?php echo json_encode(__('news_publish_error'), JSON_UNESCAPED_UNICODE); ?>,
        submitError: <?php echo json_encode(__('news_submit_error'), JSON_UNESCAPED_UNICODE); ?>,
        notFound: <?php echo json_encode(__('news_not_found'), JSON_UNESCAPED_UNICODE); ?>,
        deleteConfirmTitle: <?php echo json_encode(__('news_delete_confirm_title'), JSON_UNESCAPED_UNICODE); ?>,
        deleteConfirmStart: <?php echo json_encode(__('news_delete_confirm_start'), JSON_UNESCAPED_UNICODE); ?>,
        deleteConfirmEnd: <?php echo json_encode(__('news_delete_confirm_end'), JSON_UNESCAPED_UNICODE); ?>,
        cannotUndo: <?php echo json_encode(__('action_cannot_undo'), JSON_UNESCAPED_UNICODE); ?>,
        deleteSuccess: <?php echo json_encode(__('deleted_successfully'), JSON_UNESCAPED_UNICODE); ?>,
        deleteError: <?php echo json_encode(__('news_delete_error'), JSON_UNESCAPED_UNICODE); ?>,
        deleteConnectionError: <?php echo json_encode(__('news_delete_connection_error'), JSON_UNESCAPED_UNICODE); ?>,
        edit: <?php echo json_encode(__('edit'), JSON_UNESCAPED_UNICODE); ?>,
        delete: <?php echo json_encode(__('delete'), JSON_UNESCAPED_UNICODE); ?>
      };

      const newsData = <?php echo json_encode($newsList); ?>;

      function formatNewsDateClient(dateValue) {
        const parsed = new Date(dateValue);
        if (Number.isNaN(parsed.getTime())) {
          return dateValue || '';
        }

        if (currentNewsLang === 'en') {
          return parsed.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
          });
        }

        return parsed.toLocaleDateString('th-TH', {
          day: 'numeric',
          month: 'short',
          year: 'numeric'
        });
      }
      
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

      // Toggle news form visibility
      function toggleNewsForm() {
        const section = document.getElementById('addNewsSection');
        const icon = document.getElementById('toggleNewsFormIcon');
        const text = document.getElementById('toggleNewsFormText');
        const isHidden = section.style.display === 'none';
        
        if (isHidden) {
          section.style.display = '';
          icon.textContent = '▼';
          text.textContent = newsI18n.hideForm;
          localStorage.setItem('newsFormVisible', 'true');
        } else {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = newsI18n.showForm;
          localStorage.setItem('newsFormVisible', 'false');
        }
      }

      // AJAX submit สำหรับ editForm
      document.addEventListener('DOMContentLoaded', function() {
        // Restore form visibility from localStorage
        const isFormVisible = localStorage.getItem('newsFormVisible') !== 'false';
        const section = document.getElementById('addNewsSection');
        const icon = document.getElementById('toggleNewsFormIcon');
        const text = document.getElementById('toggleNewsFormText');
        if (!isFormVisible) {
          section.style.display = 'none';
          icon.textContent = '▶';
          text.textContent = newsI18n.showForm;
        }
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
                showErrorToast(newsI18n.invalidResponse);
                return;
              }
              
              console.log('Parsed result:', result);
              
              if (result.success) {
                showSuccessToast(newsI18n.updateSuccess);
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
                  const formattedDate = formatNewsDateClient(date);
                  
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
                showErrorToast(result.error || newsI18n.updateError);
              }
            } catch (error) {
              console.error('Update error:', error);
              showErrorToast(newsI18n.updateConnectionError);
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
                showSuccessToast(newsI18n.publishSuccess);
                
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
                showErrorToast(result.error || newsI18n.publishError);
              }
            })
            .catch(error => {
              console.error(error);
              showErrorToast(newsI18n.submitError);
            });
            
            return false;
          }, true); // Capture phase
        }
      });
      
      function editNews(newsId) {
        const news = newsData.find(n => n.news_id == newsId);
        console.log('เปิด modal แก้ไขข่าว', newsId, news);
        if (!news) {
          showErrorToast(newsI18n.notFound);
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
          newsI18n.deleteConfirmTitle,
          `${newsI18n.deleteConfirmStart} <strong>"${escapeHtml(newsTitle)}"</strong> ${newsI18n.deleteConfirmEnd}<br><br>${newsI18n.cannotUndo}`
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
            showSuccessToast(newsI18n.deleteSuccess);
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
            showErrorToast(result.error || newsI18n.deleteError);
          }
        } catch (error) {
          console.error('Delete error:', error);
          showErrorToast(newsI18n.deleteConnectionError);
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
        
        const formattedDate = formatNewsDateClient(date);
        
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
              <button type="button" class="animate-ui-action-btn edit" data-news-id="${newsId}">${newsI18n.edit}</button>
              <button type="button" class="animate-ui-action-btn delete" onclick="deleteNews(${newsId}, '${escapeHtml(title).replace(/'/g, "\\'")}')">${newsI18n.delete}</button>
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
    <script src="/dormitory_management/Public/Assets/Javascript/toast-notification.js"></script>
    <script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js"></script>
    <script>
      function changeSortBy(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
      }
    </script>
  </body>
</html>

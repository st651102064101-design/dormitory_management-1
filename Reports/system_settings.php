<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    header('Location: ../Login.php');
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// สร้าง table ก่อน ถ้าไม่มี
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Ignore if table creation fails
}

// ค่า default
$siteName = 'Sangthian Dormitory';
$themeColor = '#0f172a';
$fontSize = '1';
$logoFilename = 'Logo.jpg';

// ดึงค่าตั้งค่าระบบจาก database
try {
    $settingsStmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key IN ('site_name', 'theme_color', 'font_size', 'logo_filename')");
    $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rawSettings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }

    // อัพเดท default values ด้วยค่าจาก database
    $siteName = $settings['site_name'] ?? $siteName;
    $themeColor = $settings['theme_color'] ?? $themeColor;
    $fontSize = $settings['font_size'] ?? $fontSize;
    $logoFilename = $settings['logo_filename'] ?? $logoFilename;

    // ถ้า table ว่าง ให้ insert default
    $checkStmt = $pdo->query("SELECT COUNT(*) as cnt FROM system_settings");
    if ((int)$checkStmt->fetchColumn() === 0) {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $insertStmt->execute(['site_name', $siteName]);
        $insertStmt->execute(['theme_color', $themeColor]);
        $insertStmt->execute(['font_size', $fontSize]);
        $insertStmt->execute(['logo_filename', $logoFilename]);
    }
} catch (PDOException $e) {
    // Use default values if query fails
}
?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> - จัดการระบบ</title>
    <link rel="icon" type="image/jpeg" href="../Assets/Images/<?php echo htmlspecialchars($logoFilename, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="../Assets/Css/animate-ui.css" />
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="stylesheet" href="../Assets/Css/confirm-modal.css" />
    <style>
      :root {
        --theme-bg-color: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
      }
      html, body {
        overflow-y: auto;
        overflow-x: hidden;
        background: var(--theme-bg-color) !important;
      }
      .app-shell {
        background: var(--theme-bg-color) !important;
      }
      .app-main {
        background: var(--theme-bg-color) !important;
      }
      .reports-page {
        background: var(--theme-bg-color) !important;
      }
      .system-settings-container {
        display: grid;
        grid-template-columns: repeat(3, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
        padding-right: 0.75rem;
      }
      .logo-card { grid-column: 1; }
      .col-2 { grid-column: 2; }
      .col-3 { grid-column: 3; }
      .settings-column {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
      }
      @media (max-width: 1400px) {
        .system-settings-container {
          grid-template-columns: repeat(2, minmax(280px, 1fr));
        }
        .logo-card { grid-column: 1 / -1; }
        .col-2, .col-3 { grid-column: auto; }
      }
      @media (max-width: 900px) {
        .system-settings-container {
          grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }
        .logo-card { grid-column: 1 / -1; }
        .col-2, .col-3 { grid-column: auto; }
      }
      .settings-card {
        background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95));
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 1.75rem;
        color: #f5f8ff;
        box-shadow: 0 12px 30px rgba(0,0,0,0.35);
        margin-right: 1.5rem;
      }
      .settings-card h3 {
        margin: 0 0 1.2rem 0;
        font-size: 1.1rem;
        color: #f5f8ff;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .settings-card h3 span {
        font-size: 1.3rem;
      }
      .form-group {
        margin-bottom: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }
      .form-group label {
        font-weight: 600;
        color: rgba(255,255,255,0.85);
        font-size: 0.9rem;
      }
      .form-group input,
      .form-group select,
      .form-group textarea {
        padding: 0.75rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(8,12,24,0.85);
        color: #f5f8ff;
        font-size: 0.95rem;
        transition: all 0.2s ease;
      }
      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.25);
      }
      .color-picker-wrapper {
        display: flex;
        gap: 0.75rem;
        align-items: center;
      }
      .color-picker-wrapper input[type="color"] {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        cursor: pointer;
        border: 1px solid rgba(255,255,255,0.15);
      }
      .color-preview {
        flex: 1;
        padding: 0.75rem;
        border-radius: 8px;
        text-align: center;
        font-size: 0.85rem;
        color: #fff;
        font-weight: 600;
      }
      .font-size-preview {
        padding: 1rem;
        border-radius: 8px;
        background: rgba(59,130,246,0.1);
        border: 1px solid rgba(96,165,250,0.3);
        text-align: center;
        color: #60a5fa;
        margin-top: 0.5rem;
      }
      .logo-upload-area {
        border: 2px dashed rgba(96,165,250,0.5);
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: rgba(59,130,246,0.05);
      }
      .logo-upload-area:hover {
        border-color: rgba(96,165,250,0.8);
        background: rgba(59,130,246,0.1);
      }
      .logo-upload-area input[type="file"] {
        display: none;
      }
      .logo-preview {
        margin-top: 1rem;
        text-align: center;
      }
      .logo-preview img {
        max-width: 150px;
        max-height: 150px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      }
      .btn-save {
        width: 100%;
        padding: 0.85rem;
        margin-top: 1rem;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        outline: none;
      }
      .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(59,130,246,0.4);
      }
      .btn-save:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
      }
      .btn-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
      }
      .quick-color {
        padding: 0.6rem;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(8,12,24,0.85);
        color: #f5f8ff;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.85rem;
        outline: none;
      }
      .quick-color:hover {
        background: rgba(59,130,246,0.2);
        border-color: rgba(96,165,250,0.5);
      }
      .quick-color:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
      }
      .status-badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(34,197,94,0.15);
        color: #86efac;
        margin-top: 0.5rem;
      }
      .reports-page .manage-panel { 
        margin-top: 1.4rem; 
        margin-bottom: 1.4rem; 
        margin-right: 1rem;
        margin-left: 0.75rem;
        background: #0f172a; 
        border: 1px solid rgba(148,163,184,0.2); 
        box-shadow: 0 12px 30px rgba(0,0,0,0.2); 
        width: auto;
        max-width: calc(100% - 1.75rem);
        box-sizing: border-box;
      }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
      .logo-card { margin-right: 1.5rem; }
      @media (max-width: 768px) {
        .reports-page .manage-panel { margin-right: 0.5rem; margin-left: 0.5rem; max-width: calc(100% - 1rem); }
        .settings-card { margin-right: 0.5rem; }
        .logo-card { margin-right: 0.5rem; margin-left: 0; }
      }
    </style>
  </head>
  <body class="reports-page">
    <div class="app-shell">
      <?php include __DIR__ . '/../includes/sidebar.php'; ?>
      <main class="app-main">
        <div>
          <?php 
            $pageTitle = 'จัดการระบบ';
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
            <div class="section-header">
              <div>
                <h1>⚙️ ตั้งค่าระบบจัดการหอพัก</h1>
                <p style="color:#94a3b8;margin-top:0.2rem;">จัดการรูป, สี, และการตั้งค่าอื่น ๆ ของระบบ</p>
              </div>
            </div>

            <div class="system-settings-container">
              <!-- Logo Settings -->
              <div class="settings-card logo-card">
                <h3><span>🎨</span> จัดการ Logo</h3>
                <form id="logoForm" enctype="multipart/form-data">
                  <div class="form-group">
                    <label>รูป Logo ปัจจุบัน</label>
                    <div class="logo-preview" id="logoPreview" style="margin-bottom: 1rem; text-align: center;">
                      <img src="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" alt="Logo" style="max-width: 200px; max-height: 200px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);" />
                    </div>
                    <a href="../Assets/Images/<?php echo htmlspecialchars($logoFilename); ?>" download class="btn-save" style="display:inline-flex; align-items:center; gap:0.5rem; background: rgba(96,165,250,0.5); box-shadow:none; padding:0.6rem 1rem;">
                      📥 ดาวน์โหลดรูปปัจจุบัน
                    </a>
                  </div>

                  <div class="form-group">
                    <label>โหลดรูปเก่าจากระบบ</label>
                    <select id="oldLogoSelect" style="width: 100%; padding: 0.75rem 0.85rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); background: rgba(8,12,24,0.85); color: #f5f8ff; font-size: 0.95rem;">
                      <option value="">-- เลือกรูปเก่า --</option>
                      <?php
                        $logoDir = __DIR__ . '/../Assets/Images/';
                        if (is_dir($logoDir)) {
                          $files = scandir($logoDir);
                          foreach ($files as $file) {
                            if ($file === '.' || $file === '..') continue;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png'])) continue;
                            if (stripos($file, 'logo') === false && !preg_match('/^\d+\.(jpg|jpeg|png)$/i', $file)) continue;
                            echo '<option value="' . htmlspecialchars($file) . '">' . htmlspecialchars($file) . '</option>';
                          }
                        }
                      ?>
                    </select>
                    <div id="oldLogoPreview" style="margin-top: 0.75rem;"></div>
                    <button type="button" id="loadOldLogoBtn" class="btn-save" style="margin-top: 0.75rem; background: rgba(96,165,250,0.5); box-shadow: none;">📂 โหลดรูปที่เลือก</button>
                  </div>

                  <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 1.5rem 0;">

                  <div class="form-group">
                    <label>อัพโหลด Logo ใหม่ (JPG, PNG)</label>
                    <div class="logo-upload-area" onclick="document.getElementById('logoInput').click()">
                      <div style="font-size: 2rem; margin-bottom: 0.5rem;">📸</div>
                      <p>คลิกหรือลากไฟล์มาที่นี่</p>
                      <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png" />
                    </div>
                    <div id="newLogoPreview" style="margin-top: 1rem;"></div>
                  </div>
                  <button type="submit" class="btn-save">💾 บันทึก Logo</button>
                  <div class="status-badge" id="logoStatus"></div>
                </form>
              </div>
              
              <div class="settings-column col-2">
                <!-- Site Name Settings -->
                <div class="settings-card">
                  <h3><span>🏢</span> ชื่อสถานที่</h3>
                  <form id="siteNameForm">
                    <div class="form-group">
                      <label>ชื่อหอพัก</label>
                      <input type="text" id="siteName" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>" maxlength="100" required />
                      <small style="color: #94a3b8;">ชื่อที่จะแสดงในระบบ</small>
                    </div>
                    <button type="submit" class="btn-save">💾 บันทึกชื่อ</button>
                    <div class="status-badge" id="siteNameStatus"></div>
                  </form>
                </div>

                <!-- Theme Color Settings -->
                <div class="settings-card">
                  <h3><span>🎨</span> สีพื้นหลังระบบ</h3>
                  <form id="themeColorForm">
                    <div class="form-group">
                      <label>เลือกสี</label>
                      <div class="color-picker-wrapper">
                        <input type="color" id="themeColor" name="theme_color" value="<?php echo htmlspecialchars($themeColor); ?>" />
                        <div class="color-preview" id="colorPreview" style="background: <?php echo htmlspecialchars($themeColor); ?>;">
                          <?php echo htmlspecialchars($themeColor); ?>
                        </div>
                      </div>
                      <small style="color: #94a3b8; margin-top: 0.5rem;">เลือกสีสำหรับพื้นหลังระบบ</small>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem;">
                      <button type="button" class="quick-color" data-color="#0f172a" title="Dark Blue">🌙 Dark</button>
                      <button type="button" class="quick-color" data-color="#ffffff" title="White">☀️ White</button>
                      <button type="button" class="quick-color" data-color="#1e293b" title="Slate">⚪ Slate</button>
                    </div>
                    <button type="submit" class="btn-save">💾 บันทึกสี</button>
                    <div class="status-badge" id="colorStatus"></div>
                  </form>
                </div>

                <!-- Font Size Settings -->
                <div class="settings-card">
                  <h3><span>📝</span> ขนาดข้อความ</h3>
                  <form id="fontSizeForm">
                    <div class="form-group">
                      <label>ขนาด</label>
                      <select id="fontSize" name="font_size">
                        <option value="0.9" <?php echo $fontSize === '0.9' ? 'selected' : ''; ?>>เล็ก (0.9)</option>
                        <option value="1" <?php echo $fontSize === '1' ? 'selected' : ''; ?>>ปกติ (1.0) ✓</option>
                        <option value="1.1" <?php echo $fontSize === '1.1' ? 'selected' : ''; ?>>ใหญ่ (1.1)</option>
                        <option value="1.25" <?php echo $fontSize === '1.25' ? 'selected' : ''; ?>>ใหญ่มาก (1.25)</option>
                      </select>
                      <div class="font-size-preview" style="font-size: calc(1rem * <?php echo htmlspecialchars($fontSize); ?>);">
                        ตัวอย่างข้อความ - นี่คือขนาดที่คุณเลือก
                      </div>
                    </div>
                    <button type="submit" class="btn-save">💾 บันทึกขนาด</button>
                    <div class="status-badge" id="fontStatus"></div>
                  </form>
                </div>
              </div>

              <div class="settings-column col-3">
                <!-- System Info -->
                <div class="settings-card">
                  <h3><span>ℹ️</span> ข้อมูลระบบ</h3>
                  <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div>
                      <div style="color: #94a3b8; font-size: 0.9rem;">เวอร์ชัน PHP</div>
                      <div style="color: #f5f8ff; font-weight: 600;"><?php echo phpversion(); ?></div>
                    </div>
                    <div>
                      <div style="color: #94a3b8; font-size: 0.9rem;">ฐานข้อมูล</div>
                      <div style="color: #f5f8ff; font-weight: 600;">MySQL</div>
                    </div>
                    <div>
                      <div style="color: #94a3b8; font-size: 0.9rem;">สถานะระบบ</div>
                      <div style="color: #86efac; font-weight: 600;">✓ ทำงานปกติ</div>
                    </div>
                    <div>
                      <div style="color: #94a3b8; font-size: 0.9rem;">วันที่อัพเดทล่าสุด</div>
                      <div style="color: #f5f8ff; font-weight: 600;"><?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                  </div>
                </div>

                <!-- Database Backup -->
                <div class="settings-card">
                  <h3><span>💾</span> สำรองข้อมูล</h3>
                  <form id="backupForm">
                    <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1rem;">
                      สำรองฐานข้อมูลของคุณเพื่อป้องกันการสูญเสียข้อมูล
                    </p>
                    <button type="button" class="btn-save" id="backupBtn" style="margin-bottom: 0.5rem;">💾 สำรองข้อมูล</button>
                    <div class="status-badge" id="backupStatus"></div>
                  </form>
                </div>
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>

    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/system-settings.js"></script>
    <script src="../Assets/Javascript/animate-ui.js"></script>
  </body>
</html>

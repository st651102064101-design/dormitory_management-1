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
$bgFilename = 'bg.jpg';
$contactPhone = '0895656083';
$contactEmail = 'test@gmail.com';
$publicTheme = 'dark';

// ดึงค่าตั้งค่าระบบจาก database
try {
    $settingsStmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key IN ('site_name', 'theme_color', 'font_size', 'logo_filename', 'bg_filename', 'contact_phone', 'contact_email', 'public_theme')");
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
    $bgFilename = $settings['bg_filename'] ?? $bgFilename;
    $contactPhone = $settings['contact_phone'] ?? $contactPhone;
    $contactEmail = $settings['contact_email'] ?? $contactEmail;
    $publicTheme = $settings['public_theme'] ?? $publicTheme;

    // ถ้า table ว่าง ให้ insert default
    $checkStmt = $pdo->query("SELECT COUNT(*) as cnt FROM system_settings");
    if ((int)$checkStmt->fetchColumn() === 0) {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $insertStmt->execute(['site_name', $siteName]);
        $insertStmt->execute(['theme_color', $themeColor]);
        $insertStmt->execute(['font_size', $fontSize]);
        $insertStmt->execute(['logo_filename', $logoFilename]);
        $insertStmt->execute(['bg_filename', $bgFilename]);
        $insertStmt->execute(['contact_phone', $contactPhone]);
        $insertStmt->execute(['contact_email', $contactEmail]);
    }
} catch (PDOException $e) {
    // Use default values if query fails
}

// ดึงอัตราค่าน้ำค่าไฟ (ล่าสุด)
$waterRate = 18;
$electricRate = 8;
$currentRateDate = date('Y-m-d');
$allRates = [];
try {
    // ดึงอัตราล่าสุด
    $rateStmt = $pdo->query("SELECT rate_id, rate_water, rate_elec, effective_date FROM rate ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    if ($rate) {
        $waterRate = (int)$rate['rate_water'];
        $electricRate = (int)$rate['rate_elec'];
        $currentRateDate = $rate['effective_date'] ?? date('Y-m-d');
    }
    
    // ดึงประวัติทั้งหมด
    $allRatesStmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC, rate_id DESC");
    $allRates = $allRatesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
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
      /* ======================================
         Apple Settings Style - iOS/macOS
      ====================================== */
      :root {
        --apple-bg: #f2f2f7;
        --apple-card: #ffffff;
        --apple-separator: rgba(60, 60, 67, 0.12);
        --apple-text: #000000;
        --apple-text-secondary: #8e8e93;
        --apple-blue: #007aff;
        --apple-green: #34c759;
        --apple-orange: #ff9500;
        --apple-red: #ff3b30;
        --apple-purple: #af52de;
        --apple-pink: #ff2d55;
        --apple-teal: #5ac8fa;
        --apple-indigo: #5856d6;
        --apple-yellow: #ffcc00;
        --apple-radius: 12px;
        --apple-radius-lg: 20px;
        --theme-bg-color: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
      }
      
      html, body {
        background: var(--apple-bg) !important;
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'SF Pro Text', 'Helvetica Neue', sans-serif;
      }
      
      body {
        overflow-y: auto;
        overflow-x: hidden;
      }
      
      .app-shell, .app-main, .reports-page {
        background: var(--apple-bg) !important;
      }
      
      /* Override sidebar for Apple style */
      .reports-page .manage-panel {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
      }
      
      .reports-page .manage-panel .section-header {
        display: none;
      }
      
      /* Apple Settings Container */
      .apple-settings-wrapper {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
      }
      
      .apple-settings-header {
        text-align: center;
        margin-bottom: 24px;
      }
      
      .apple-settings-header h1 {
        font-size: 34px;
        font-weight: 700;
        color: var(--apple-text);
        margin: 0;
        letter-spacing: -0.5px;
      }
      
      .apple-settings-header p {
        font-size: 15px;
        color: var(--apple-text-secondary);
        margin: 8px 0 0;
      }
      
      /* Settings Groups */
      .apple-settings-group {
        background: var(--apple-card);
        border-radius: var(--apple-radius);
        margin-bottom: 35px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      }
      
      .apple-group-title {
        font-size: 13px;
        font-weight: 400;
        color: var(--apple-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 20px;
        margin-bottom: 0;
        background: transparent;
      }
      
      /* Settings Items */
      .apple-settings-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        background: var(--apple-card);
        border-bottom: 0.5px solid var(--apple-separator);
        cursor: pointer;
        transition: background 0.15s ease;
        min-height: 44px;
      }
      
      .apple-settings-item:last-child {
        border-bottom: none;
      }
      
      .apple-settings-item:hover {
        background: rgba(0,0,0,0.02);
      }
      
      .apple-settings-item:active {
        background: rgba(0,0,0,0.05);
      }
      
      .apple-icon {
        width: 29px;
        height: 29px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        font-size: 17px;
        flex-shrink: 0;
      }
      
      .apple-icon.blue { background: var(--apple-blue); color: white; }
      .apple-icon.green { background: var(--apple-green); color: white; }
      .apple-icon.orange { background: var(--apple-orange); color: white; }
      .apple-icon.red { background: var(--apple-red); color: white; }
      .apple-icon.purple { background: var(--apple-purple); color: white; }
      .apple-icon.pink { background: var(--apple-pink); color: white; }
      .apple-icon.teal { background: var(--apple-teal); color: white; }
      .apple-icon.indigo { background: var(--apple-indigo); color: white; }
      .apple-icon.yellow { background: var(--apple-yellow); color: #000; }
      .apple-icon.gray { background: #8e8e93; color: white; }
      
      .apple-item-content {
        flex: 1;
        min-width: 0;
      }
      
      .apple-item-title {
        font-size: 17px;
        font-weight: 400;
        color: var(--apple-text);
        margin: 0;
        line-height: 1.3;
      }
      
      .apple-item-subtitle {
        font-size: 13px;
        color: var(--apple-text-secondary);
        margin: 2px 0 0;
      }
      
      .apple-item-value {
        font-size: 17px;
        color: var(--apple-text-secondary);
        margin-right: 8px;
      }
      
      .apple-chevron {
        color: rgba(60, 60, 67, 0.3);
        font-size: 14px;
        font-weight: 600;
      }
      
      /* Apple Toggle Switch */
      .apple-toggle {
        width: 51px;
        height: 31px;
        background: rgba(120, 120, 128, 0.16);
        border-radius: 16px;
        position: relative;
        cursor: pointer;
        transition: background 0.3s ease;
        flex-shrink: 0;
      }
      
      .apple-toggle.active {
        background: var(--apple-green);
      }
      
      .apple-toggle::after {
        content: '';
        position: absolute;
        width: 27px;
        height: 27px;
        background: white;
        border-radius: 50%;
        top: 2px;
        left: 2px;
        transition: transform 0.3s ease;
        box-shadow: 0 3px 8px rgba(0,0,0,0.15), 0 3px 1px rgba(0,0,0,0.06);
      }
      
      .apple-toggle.active::after {
        transform: translateX(20px);
      }
      
      /* Apple Input */
      .apple-input {
        width: 100%;
        padding: 11px 16px;
        font-size: 17px;
        border: none;
        border-radius: 10px;
        background: rgba(118, 118, 128, 0.12);
        color: var(--apple-text);
        outline: none;
        transition: all 0.2s ease;
        -webkit-appearance: none;
      }
      
      .apple-input:focus {
        background: rgba(118, 118, 128, 0.18);
        box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.2);
      }
      
      .apple-input::placeholder {
        color: rgba(60, 60, 67, 0.3);
      }
      
      /* Apple Button */
      .apple-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 20px;
        font-size: 17px;
        font-weight: 500;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        gap: 8px;
      }
      
      .apple-button.primary {
        background: var(--apple-blue);
        color: white;
      }
      
      .apple-button.primary:hover {
        background: #0066d6;
        transform: scale(1.02);
      }
      
      .apple-button.primary:active {
        transform: scale(0.98);
      }
      
      .apple-button.secondary {
        background: rgba(118, 118, 128, 0.12);
        color: var(--apple-blue);
      }
      
      .apple-button.destructive {
        background: rgba(255, 59, 48, 0.1);
        color: var(--apple-red);
      }
      
      /* Apple Modal / Sheet */
      .apple-sheet-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 1000;
        display: none;
        align-items: flex-end;
        justify-content: center;
        padding: 10px;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
      }
      
      .apple-sheet-overlay.active {
        display: flex;
        animation: fadeIn 0.2s ease;
      }
      
      .apple-sheet {
        background: var(--apple-card);
        border-radius: var(--apple-radius-lg) var(--apple-radius-lg) 0 0;
        width: 100%;
        max-width: 500px;
        max-height: 85vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
      }
      
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      
      @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
      }
      
      .apple-sheet-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 0.5px solid var(--apple-separator);
        position: sticky;
        top: 0;
        background: var(--apple-card);
        z-index: 10;
      }
      
      .apple-sheet-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--apple-text);
        margin: 0;
      }
      
      .apple-sheet-close {
        width: 30px;
        height: 30px;
        background: rgba(118, 118, 128, 0.12);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
        color: var(--apple-text-secondary);
      }
      
      .apple-sheet-body {
        padding: 20px;
      }
      
      .apple-form-group {
        margin-bottom: 20px;
      }
      
      .apple-form-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: var(--apple-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        padding-left: 4px;
      }
      
      /* Profile Card */
      .apple-profile-card {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        background: var(--apple-card);
        border-radius: var(--apple-radius);
        margin-bottom: 35px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      }
      
      .apple-profile-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 16px;
        background: linear-gradient(135deg, #667eea, #764ba2);
      }
      
      .apple-profile-info {
        flex: 1;
      }
      
      .apple-profile-name {
        font-size: 20px;
        font-weight: 600;
        color: var(--apple-text);
        margin: 0;
      }
      
      .apple-profile-detail {
        font-size: 14px;
        color: var(--apple-text-secondary);
        margin: 2px 0 0;
      }
      
      /* Theme Selector - Apple Style */
      .apple-theme-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding: 16px;
      }
      
      .apple-theme-option {
        text-align: center;
        cursor: pointer;
        padding: 12px 8px;
        border-radius: 12px;
        transition: all 0.2s ease;
        border: 2px solid transparent;
      }
      
      .apple-theme-option:hover {
        background: rgba(0,0,0,0.02);
      }
      
      .apple-theme-option.active {
        border-color: var(--apple-blue);
        background: rgba(0, 122, 255, 0.05);
      }
      
      .apple-theme-preview {
        width: 70px;
        height: 50px;
        border-radius: 8px;
        margin: 0 auto 8px;
        border: 1px solid var(--apple-separator);
        overflow: hidden;
      }
      
      .apple-theme-name {
        font-size: 12px;
        font-weight: 500;
        color: var(--apple-text);
      }
      
      .apple-theme-option.active .apple-theme-name {
        color: var(--apple-blue);
      }
      
      /* Color Picker */
      .apple-color-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
        padding: 16px;
      }
      
      .apple-color-option {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        cursor: pointer;
        transition: transform 0.2s ease;
        border: 3px solid transparent;
        position: relative;
      }
      
      .apple-color-option:hover {
        transform: scale(1.1);
      }
      
      .apple-color-option.active {
        border-color: var(--apple-blue);
      }
      
      .apple-color-option.active::after {
        content: '✓';
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
      }
      
      /* Current Rate Display */
      .apple-rate-display {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 16px;
      }
      
      .apple-rate-box {
        background: rgba(118, 118, 128, 0.08);
        border-radius: 12px;
        padding: 16px;
        text-align: center;
      }
      
      .apple-rate-icon {
        font-size: 28px;
        margin-bottom: 4px;
      }
      
      .apple-rate-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--apple-text);
      }
      
      .apple-rate-unit {
        font-size: 13px;
        color: var(--apple-text-secondary);
      }
      
      /* History Table */
      .apple-table {
        width: 100%;
        border-collapse: collapse;
      }
      
      .apple-table th {
        font-size: 12px;
        font-weight: 500;
        color: var(--apple-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 16px;
        text-align: left;
        background: rgba(118, 118, 128, 0.08);
      }
      
      .apple-table td {
        padding: 12px 16px;
        font-size: 15px;
        color: var(--apple-text);
        border-bottom: 0.5px solid var(--apple-separator);
      }
      
      .apple-table tr:last-child td {
        border-bottom: none;
      }
      
      .apple-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
      }
      
      .apple-badge.active {
        background: rgba(52, 199, 89, 0.15);
        color: var(--apple-green);
      }
      
      /* File Upload Area */
      .apple-upload-area {
        border: 2px dashed var(--apple-separator);
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: rgba(118, 118, 128, 0.04);
      }
      
      .apple-upload-area:hover {
        border-color: var(--apple-blue);
        background: rgba(0, 122, 255, 0.04);
      }
      
      .apple-upload-area input[type="file"] {
        display: none;
      }
      
      .apple-upload-icon {
        font-size: 40px;
        margin-bottom: 8px;
      }
      
      .apple-upload-text {
        font-size: 15px;
        color: var(--apple-text-secondary);
      }
      
      .apple-upload-hint {
        font-size: 13px;
        color: rgba(60, 60, 67, 0.4);
        margin-top: 4px;
      }
      
      /* Image Preview */
      .apple-image-preview {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: rgba(118, 118, 128, 0.08);
        border-radius: 12px;
        margin-bottom: 12px;
      }
      
      .apple-image-preview img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
      }
      
      .apple-image-info {
        flex: 1;
      }
      
      .apple-image-name {
        font-size: 15px;
        font-weight: 500;
        color: var(--apple-text);
      }
      
      .apple-image-size {
        font-size: 13px;
        color: var(--apple-text-secondary);
      }
      
      /* Status Toast */
      .apple-toast {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(-100px);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 14px 24px;
        border-radius: 14px;
        font-size: 15px;
        font-weight: 500;
        z-index: 9999;
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
      }
      
      .apple-toast.show {
        transform: translateX(-50%) translateY(0);
      }
      
      .apple-toast.success {
        background: rgba(52, 199, 89, 0.95);
      }
      
      .apple-toast.error {
        background: rgba(255, 59, 48, 0.95);
      }
      
      /* System Info Cards */
      .apple-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 16px;
      }
      
      .apple-info-item {
        background: rgba(118, 118, 128, 0.08);
        border-radius: 10px;
        padding: 12px;
      }
      
      .apple-info-label {
        font-size: 12px;
        color: var(--apple-text-secondary);
        margin-bottom: 4px;
      }
      
      .apple-info-value {
        font-size: 17px;
        font-weight: 600;
        color: var(--apple-text);
      }
      
      .apple-info-value.success {
        color: var(--apple-green);
      }
      
      /* Responsive */
      @media (max-width: 600px) {
        .apple-settings-wrapper {
          padding: 12px;
        }
        
        .apple-settings-header h1 {
          font-size: 28px;
        }
        
        .apple-theme-grid {
          grid-template-columns: repeat(3, 1fr);
        }
        
        .apple-color-grid {
          grid-template-columns: repeat(5, 1fr);
        }
        
        .apple-info-grid {
          grid-template-columns: 1fr;
        }
      }
      
      @media (max-width: 768px) {
        .system-settings-container {
          grid-template-columns: 1fr;
        }
        .utility-rates-card {
          grid-column: span 1;
        }
      }
      .rate-form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
      }
      @media (max-width: 600px) {
        .rate-form-grid {
          grid-template-columns: 1fr;
        }
      }
      .settings-card {
        background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(2,6,23,0.95));
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 1.75rem;
        color: #f5f8ff;
        box-shadow: 0 12px 30px rgba(0,0,0,0.35);
        display: flex;
        flex-direction: column;
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
        background: var(--theme-bg-color); 
        border: 1px solid rgba(148,163,184,0.2); 
        box-shadow: 0 12px 30px rgba(0,0,0,0.2); 
        width: auto;
        max-width: calc(100% - 1.75rem);
        box-sizing: border-box;
      }
      .reports-page .manage-panel:first-of-type { margin-top: 0.2rem; }
      .logo-card { margin-right: 1.5rem; }

      /* Theme Selector Styles */
      .theme-selector {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      .theme-option {
        position: relative;
        cursor: pointer;
        border: 2px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s ease;
        background: rgba(0,0,0,0.2);
      }
      .theme-option:hover {
        border-color: rgba(96,165,250,0.5);
        background: rgba(96,165,250,0.05);
      }
      .theme-option.active {
        border-color: #22c55e;
        background: rgba(34,197,94,0.1);
        box-shadow: 0 0 20px rgba(34,197,94,0.2);
      }
      .theme-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
      }
      .theme-preview {
        width: 100%;
        height: 80px;
        border-radius: 8px;
        margin-bottom: 0.75rem;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
      }
      .dark-preview {
        background: linear-gradient(135deg, #0f172a, #1e293b);
      }
      .dark-preview .preview-header {
        height: 20px;
        background: linear-gradient(135deg, #1d4ed8, #3b82f6);
      }
      .dark-preview .preview-content {
        padding: 8px;
        display: flex;
        gap: 6px;
      }
      .dark-preview .preview-card {
        flex: 1;
        height: 40px;
        background: rgba(255,255,255,0.08);
        border-radius: 4px;
      }
      .light-preview {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
      }
      .light-preview .preview-header {
        height: 20px;
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
      }
      .light-preview .preview-content {
        padding: 8px;
        display: flex;
        gap: 6px;
      }
      .light-preview .preview-card {
        flex: 1;
        height: 40px;
        background: rgba(0,0,0,0.08);
        border-radius: 4px;
      }
      .theme-name {
        display: block;
        font-weight: 600;
        font-size: 0.95rem;
        color: #f5f8ff;
        margin-bottom: 0.25rem;
      }
      .theme-desc {
        display: block;
        font-size: 0.75rem;
        color: #94a3b8;
      }
      .theme-option.active .theme-name {
        color: #22c55e;
      }
      @media (max-width: 480px) {
        .theme-selector {
          grid-template-columns: 1fr;
        }
      }

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
              <div class="settings-card">
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
                    <select id="oldLogoSelect" style="width: 100%; padding: 0.75rem 0.85rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); background: rgba(8,12,24,0.85); color: #f5f8ff; font-size: 0.95rem; margin-bottom: 0.5rem;">
                      <option value="">-- เลือกรูปเก่า --</option>
                      <?php
                        $logoDir = realpath(__DIR__ . '/../Assets/Images/');
                        if ($logoDir && is_dir($logoDir)) {
                          $files = scandir($logoDir);
                          foreach ($files as $file) {
                            if ($file === '.' || $file === '..') continue;
                            if (is_dir($logoDir . '/' . $file)) continue;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            // รองรับไฟล์ jpg, jpeg, png เท่านั้น
                            if (!in_array($ext, ['jpg','jpeg','png'])) continue;
                            // แสดงไฟล์ทั้งหมดที่เป็นรูป (ไม่ต้องมี "logo" ในชื่อ)
                            echo '<option value="' . htmlspecialchars($file) . '">' . htmlspecialchars($file) . '</option>';
                          }
                        }
                      ?>
                    </select>
                    <div id="oldLogoPreview" style="margin-top: 0.75rem; margin-bottom: 0.75rem; display: flex; align-items: flex-start; gap: 1rem;"></div>
                    <div id="deleteLogoContainer" style="display: none;"></div>
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

              <!-- Background Image Settings -->
              <div class="settings-card">
                <h3><span>🖼️</span> จัดการภาพพื้นหลัง (หน้าแรก)</h3>
                <form id="bgForm" enctype="multipart/form-data">
                  <div class="form-group">
                    <label>ภาพพื้นหลังปัจจุบัน</label>
                    <div class="logo-preview" id="bgPreview" style="margin-bottom: 1rem; text-align: center;">
                      <img src="../Assets/Images/<?php echo htmlspecialchars($bgFilename); ?>" alt="Background" style="max-width: 300px; max-height: 180px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); object-fit: cover;" />
                    </div>
                    <small style="color: #94a3b8;">ภาพนี้จะแสดงเป็นพื้นหลังของหน้าแรก (Hero Section)</small>
                  </div>

                  <div class="form-group">
                    <label>เลือกภาพพื้นหลังจากระบบ</label>
                    <select id="bgSelect" style="width: 100%; padding: 0.75rem 0.85rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); background: rgba(8,12,24,0.85); color: #f5f8ff; font-size: 0.95rem; margin-bottom: 0.5rem;">
                      <option value="">-- เลือกภาพ --</option>
                      <?php
                        if ($logoDir && is_dir($logoDir)) {
                          foreach ($files as $file) {
                            if ($file === '.' || $file === '..') continue;
                            if (is_dir($logoDir . '/' . $file)) continue;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                            $selected = ($file === $bgFilename) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($file) . '" ' . $selected . '>' . htmlspecialchars($file) . '</option>';
                          }
                        }
                      ?>
                    </select>
                    <div id="bgSelectPreview" style="margin-top: 0.75rem; margin-bottom: 0.75rem;"></div>
                  </div>

                  <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 1.5rem 0;">

                  <div class="form-group">
                    <label>อัพโหลดภาพพื้นหลังใหม่ (JPG, PNG, WebP)</label>
                    <div class="logo-upload-area" onclick="document.getElementById('bgInput').click()">
                      <div style="font-size: 2rem; margin-bottom: 0.5rem;">🖼️</div>
                      <p>คลิกหรือลากไฟล์มาที่นี่</p>
                      <input type="file" id="bgInput" name="bg" accept="image/jpeg,image/png,image/webp" />
                    </div>
                    <div id="newBgPreview" style="margin-top: 1rem;"></div>
                  </div>
                  <button type="submit" class="btn-save">💾 บันทึกภาพพื้นหลัง</button>
                  <div class="status-badge" id="bgStatus"></div>
                </form>
              </div>
              
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

              <!-- Contact Information Settings -->
              <div class="settings-card">
                <h3><span>📞</span> ข้อมูลติดต่อ</h3>
                <div style="display: grid; gap: 1.5rem;">
                  <form id="phoneForm">
                    <div class="form-group">
                      <label>เบอร์โทรศัพท์</label>
                      <input type="tel" id="contactPhone" name="contact_phone" value="<?php echo htmlspecialchars($contactPhone); ?>" pattern="[0-9\-\+\s()]{8,20}" maxlength="20" required />
                      <small style="color: #94a3b8;">เช่น 0895656083 หรือ 089-565-6083</small>
                    </div>
                    <button type="submit" class="btn-save">💾 บันทึกเบอร์โทร</button>
                    <div class="status-badge" id="phoneStatus"></div>
                  </form>

                  <form id="emailForm">
                    <div class="form-group">
                      <label>อีเมลติดต่อ</label>
                      <input type="email" id="contactEmail" name="contact_email" value="<?php echo htmlspecialchars($contactEmail); ?>" maxlength="100" required />
                      <small style="color: #94a3b8;">เช่น test@gmail.com</small>
                    </div>
                    <button type="submit" class="btn-save">💾 บันทึกอีเมล</button>
                    <div class="status-badge" id="emailStatus"></div>
                  </form>
                </div>
              </div>

              <!-- Public Theme Settings -->
              <div class="settings-card">
                <h3><span>🌐</span> ธีมหน้าสาธารณะ</h3>
                <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.25rem;">เลือกธีมสำหรับหน้าแรก, หน้าจองห้อง, หน้าข่าวสาร และหน้าห้องพัก</p>
                <form id="publicThemeForm">
                  <div class="theme-selector" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <label class="theme-option <?php echo $publicTheme === 'dark' ? 'active' : ''; ?>" data-theme="dark">
                      <input type="radio" name="public_theme" value="dark" <?php echo $publicTheme === 'dark' ? 'checked' : ''; ?> />
                      <div class="theme-preview dark-preview">
                        <div class="preview-header"></div>
                        <div class="preview-content">
                          <div class="preview-card"></div>
                          <div class="preview-card"></div>
                        </div>
                      </div>
                      <span class="theme-name">🌙 ธีมมืด</span>
                      <span class="theme-desc">สีเข้ม สวยงาม ล้ำสมัย</span>
                    </label>
                    <label class="theme-option <?php echo $publicTheme === 'light' ? 'active' : ''; ?>" data-theme="light">
                      <input type="radio" name="public_theme" value="light" <?php echo $publicTheme === 'light' ? 'checked' : ''; ?> />
                      <div class="theme-preview light-preview">
                        <div class="preview-header"></div>
                        <div class="preview-content">
                          <div class="preview-card"></div>
                          <div class="preview-card"></div>
                        </div>
                      </div>
                      <span class="theme-name">☀️ ธีมสว่าง</span>
                      <span class="theme-desc">สีขาว สะอาดตา อ่านง่าย</span>
                    </label>
                    <label class="theme-option <?php echo $publicTheme === 'auto' ? 'active' : ''; ?>" data-theme="auto">
                      <input type="radio" name="public_theme" value="auto" <?php echo $publicTheme === 'auto' ? 'checked' : ''; ?> />
                      <div class="theme-preview auto-preview">
                        <div class="preview-header" style="background: linear-gradient(90deg, #1e293b 50%, #e2e8f0 50%);"></div>
                        <div class="preview-content" style="background: linear-gradient(90deg, #0f172a 50%, #f8fafc 50%);">
                          <div class="preview-card" style="background: linear-gradient(90deg, #1e3a5f 50%, #fff 50%);"></div>
                          <div class="preview-card" style="background: linear-gradient(90deg, #1e3a5f 50%, #fff 50%);"></div>
                        </div>
                      </div>
                      <span class="theme-name">🔄 อัตโนมัติ</span>
                      <span class="theme-desc">ปรับตามเวลา กลางวัน/กลางคืน</span>
                    </label>
                  </div>
                  <button type="submit" class="btn-save" style="margin-top: 1rem;">💾 บันทึกธีม</button>
                  <div class="status-badge" id="publicThemeStatus"></div>
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
                  <small style="color: #f97316; margin-top: 0.75rem; display: block; padding: 0.5rem; background: rgba(249, 115, 22, 0.1); border-radius: 4px;">
                    💡 หากเปลี่ยนไป Dark Mode ไม่สำเร็จ โปรดรีเฟรชหน้า (Cmd+R หรือ F5)
                  </small>
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
                    <div class="status-badge" id="fontStatus"></div>
                  </div>
                </form>
              </div>

              <!-- Utility Rates Settings -->
              <div class="settings-card utility-rates-card">
                <h3><span>💡</span> อัตราค่าน้ำค่าไฟ</h3>
                
                <!-- Current Rate Display -->
                <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                  <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.75rem;">📌 อัตราปัจจุบัน (ใช้ตั้งแต่ <?php echo date('d/m/Y', strtotime($currentRateDate)); ?>)</div>
                  <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div style="text-align: center;">
                      <div style="font-size: 2rem; color: #60a5fa;">💧</div>
                      <div style="font-size: 1.5rem; font-weight: 700; color: #22c55e;">฿<?php echo number_format($waterRate); ?></div>
                      <div style="font-size: 0.8rem; color: #94a3b8;">บาท/หน่วย</div>
                    </div>
                    <div style="text-align: center;">
                      <div style="font-size: 2rem; color: #fbbf24;">⚡</div>
                      <div style="font-size: 1.5rem; font-weight: 700; color: #22c55e;">฿<?php echo number_format($electricRate); ?></div>
                      <div style="font-size: 0.8rem; color: #94a3b8;">บาท/หน่วย</div>
                    </div>
                  </div>
                </div>
                
                <!-- Add New Rate Form -->
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                  <div style="font-size: 0.9rem; font-weight: 600; color: #60a5fa; margin-bottom: 1rem;">➕ เพิ่มอัตราใหม่</div>
                  <form id="utilityRatesForm">
                    <div class="rate-form-grid">
                      <div class="form-group" style="margin-bottom: 0;">
                        <label>💧 ค่าน้ำ (บาท/หน่วย)</label>
                        <input type="number" id="waterRate" name="water_rate" value="<?php echo $waterRate; ?>" min="0" step="1" required />
                      </div>
                      <div class="form-group" style="margin-bottom: 0;">
                        <label>⚡ ค่าไฟ (บาท/หน่วย)</label>
                        <input type="number" id="electricRate" name="electric_rate" value="<?php echo $electricRate; ?>" min="0" step="1" required />
                      </div>
                      <div class="form-group" style="margin-bottom: 0;">
                        <label>📅 วันที่เริ่มใช้</label>
                        <input type="date" id="effectiveDate" name="effective_date" value="<?php echo date('Y-m-d'); ?>" required />
                      </div>
                    </div>
                    <button type="button" class="btn-save" onclick="saveUtilityRates()" style="margin-top: 0;">💾 บันทึกอัตราใหม่</button>
                    <div class="status-badge" id="rateStatus" style="margin-top: 0.5rem; display: none;"></div>
                  </form>
                </div>
                
                <!-- Rate History -->
                <div>
                  <div style="font-size: 0.9rem; font-weight: 600; color: #94a3b8; margin-bottom: 0.75rem;">📋 ประวัติอัตราค่าน้ำค่าไฟ</div>
                  <div style="max-height: 250px; overflow-y: auto; border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                      <thead>
                        <tr style="background: rgba(15, 23, 42, 0.8); position: sticky; top: 0;">
                          <th style="padding: 0.6rem; text-align: left; color: #94a3b8; font-weight: 600;">วันที่เริ่มใช้</th>
                          <th style="padding: 0.6rem; text-align: center; color: #60a5fa;">💧 ค่าน้ำ</th>
                          <th style="padding: 0.6rem; text-align: center; color: #fbbf24;">⚡ ค่าไฟ</th>
                          <th style="padding: 0.6rem; text-align: center; color: #94a3b8;">จัดการ</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($allRates)): ?>
                        <tr>
                          <td colspan="4" style="padding: 1rem; text-align: center; color: #64748b;">ยังไม่มีข้อมูล</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($allRates as $i => $r): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); <?php echo $i === 0 ? 'background: rgba(34, 197, 94, 0.1);' : ''; ?>">
                          <td style="padding: 0.6rem;">
                            <?php echo date('d/m/Y', strtotime($r['effective_date'] ?? '2025-01-01')); ?>
                            <?php if ($i === 0): ?>
                            <span style="background: #22c55e; color: white; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.7rem; margin-left: 0.5rem;">ใช้งาน</span>
                            <?php endif; ?>
                          </td>
                          <td style="padding: 0.6rem; text-align: center; color: #60a5fa; font-weight: 600;">฿<?php echo number_format($r['rate_water']); ?></td>
                          <td style="padding: 0.6rem; text-align: center; color: #fbbf24; font-weight: 600;">฿<?php echo number_format($r['rate_elec']); ?></td>
                          <td style="padding: 0.6rem; text-align: center;">
                            <?php if ($i !== 0): ?>
                            <button type="button" onclick="deleteRate(<?php echo $r['rate_id']; ?>)" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.75rem;">🗑️ ลบ</button>
                            <?php else: ?>
                            <span style="color: #64748b; font-size: 0.75rem;">-</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

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
          </section>
        </div>
      </main>
    </div>

    <script src="../Assets/Javascript/toast-notification.js"></script>
    <script src="../Assets/Javascript/confirm-modal.js"></script>
    <script src="../Assets/Javascript/system-settings.js"></script>
    <script src="../Assets/Javascript/animate-ui.js"></script>
    <script>
    // Handle Phone Form
    document.getElementById('phoneForm')?.addEventListener('submit', async function(e) {
      e.preventDefault();
      const phone = document.getElementById('contactPhone').value.trim();
      const statusEl = document.getElementById('phoneStatus');
      
      if (!phone || !/^[0-9\-\+\s()]{8,20}$/.test(phone)) {
        showErrorToast('รูปแบบเบอร์โทรไม่ถูกต้อง');
        return;
      }
      
      try {
        const response = await fetch('../Manage/save_system_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `contact_phone=${encodeURIComponent(phone)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          statusEl.textContent = '✓ ' + result.message;
          statusEl.style.background = 'rgba(34, 197, 94, 0.2)';
          statusEl.style.color = '#22c55e';
          statusEl.style.display = 'block';
          showSuccessToast(result.message);
        } else {
          throw new Error(result.error || 'เกิดข้อผิดพลาด');
        }
      } catch (error) {
        statusEl.textContent = '✗ ' + error.message;
        statusEl.style.background = 'rgba(239, 68, 68, 0.2)';
        statusEl.style.color = '#ef4444';
        statusEl.style.display = 'block';
        showErrorToast(error.message);
      }
      
      setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
    });

    // Handle Email Form
    document.getElementById('emailForm')?.addEventListener('submit', async function(e) {
      e.preventDefault();
      const email = document.getElementById('contactEmail').value.trim();
      const statusEl = document.getElementById('emailStatus');
      
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showErrorToast('รูปแบบอีเมลไม่ถูกต้อง');
        return;
      }
      
      try {
        const response = await fetch('../Manage/save_system_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `contact_email=${encodeURIComponent(email)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          statusEl.textContent = '✓ ' + result.message;
          statusEl.style.background = 'rgba(34, 197, 94, 0.2)';
          statusEl.style.color = '#22c55e';
          statusEl.style.display = 'block';
          showSuccessToast(result.message);
        } else {
          throw new Error(result.error || 'เกิดข้อผิดพลาด');
        }
      } catch (error) {
        statusEl.textContent = '✗ ' + error.message;
        statusEl.style.background = 'rgba(239, 68, 68, 0.2)';
        statusEl.style.color = '#ef4444';
        statusEl.style.display = 'block';
        showErrorToast(error.message);
      }
      
      setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
    });

    // Handle Public Theme Form
    document.getElementById('publicThemeForm')?.addEventListener('submit', async function(e) {
      e.preventDefault();
      const theme = document.querySelector('input[name="public_theme"]:checked')?.value;
      const statusEl = document.getElementById('publicThemeStatus');
      
      if (!theme) {
        showErrorToast('กรุณาเลือกธีม');
        return;
      }
      
      try {
        const response = await fetch('../Manage/save_public_theme.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `theme=${encodeURIComponent(theme)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          statusEl.textContent = '✓ ' + result.message;
          statusEl.style.background = 'rgba(34, 197, 94, 0.2)';
          statusEl.style.color = '#22c55e';
          statusEl.style.display = 'block';
          showSuccessToast(result.message);
          
          // Update active state
          document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('active'));
          document.querySelector(`.theme-option[data-theme="${theme}"]`)?.classList.add('active');
        } else {
          throw new Error(result.error || 'เกิดข้อผิดพลาด');
        }
      } catch (error) {
        statusEl.textContent = '✗ ' + error.message;
        statusEl.style.background = 'rgba(239, 68, 68, 0.2)';
        statusEl.style.color = '#ef4444';
        statusEl.style.display = 'block';
        showErrorToast(error.message);
      }
      
      setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
    });

    // Theme option click handler
    document.querySelectorAll('.theme-option').forEach(option => {
      option.addEventListener('click', function() {
        document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        this.querySelector('input[type="radio"]').checked = true;
      });
    });

    // Update example calculation on input change
    document.getElementById('waterRate')?.addEventListener('input', function() {
      document.getElementById('waterExample').textContent = '= ฿' + (parseInt(this.value) * 10).toLocaleString();
    });
    document.getElementById('electricRate')?.addEventListener('input', function() {
      document.getElementById('electricExample').textContent = '฿' + (parseInt(this.value) * 100).toLocaleString();
    });

    // Save utility rates
    async function saveUtilityRates() {
      const waterRate = document.getElementById('waterRate').value;
      const electricRate = document.getElementById('electricRate').value;
      const effectiveDate = document.getElementById('effectiveDate').value;
      const statusEl = document.getElementById('rateStatus');
      
      if (!waterRate || !electricRate || !effectiveDate) {
        showErrorToast('กรุณากรอกข้อมูลให้ครบ');
        return;
      }
      
      try {
        const response = await fetch('../Manage/add_rate.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `rate_water=${waterRate}&rate_elec=${electricRate}&effective_date=${effectiveDate}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          statusEl.textContent = '✓ บันทึกสำเร็จ!';
          statusEl.style.background = 'rgba(34, 197, 94, 0.2)';
          statusEl.style.color = '#22c55e';
          statusEl.style.display = 'block';
          showSuccessToast('บันทึกอัตราค่าน้ำค่าไฟสำเร็จ!');
          setTimeout(() => location.reload(), 1000);
        } else {
          throw new Error(result.message || result.error || 'เกิดข้อผิดพลาด');
        }
      } catch (error) {
        statusEl.textContent = '✗ ' + error.message;
        statusEl.style.background = 'rgba(239, 68, 68, 0.2)';
        statusEl.style.color = '#ef4444';
        statusEl.style.display = 'block';
        showErrorToast(error.message);
      }
      
      setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
    }

    // Delete rate
    async function deleteRate(rateId) {
      if (!confirm('ต้องการลบอัตรานี้หรือไม่?')) return;
      
      try {
        const response = await fetch('../Manage/delete_rate.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `rate_id=${rateId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          showSuccessToast('ลบอัตราสำเร็จ!');
          location.reload();
        } else {
          throw new Error(result.message || 'เกิดข้อผิดพลาด');
        }
      } catch (error) {
        showErrorToast(error.message);
      }
    }
    </script>
  </body>
</html>

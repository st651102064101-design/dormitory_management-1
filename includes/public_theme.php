<?php
// Shared public theme settings: background image toggle and CSS
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$themeColor = '#1e40af';
$useBgImage = '0';
$bgFilename = '';

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('theme_color','use_bg_image','bg_filename')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'theme_color') $themeColor = $row['setting_value'];
        if ($row['setting_key'] === 'use_bg_image') $useBgImage = $row['setting_value'];
        if ($row['setting_key'] === 'bg_filename') $bgFilename = $row['setting_value'];
    }
} catch (PDOException $e) {}

$bgStyle = '';
if (!empty($useBgImage) && $useBgImage === '1' && !empty($bgFilename)) {
    $bgUrl = 'Assets/Images/' . htmlspecialchars($bgFilename, ENT_QUOTES, 'UTF-8');
    $bgStyle = "background-image: url('{$bgUrl}'); background-attachment: fixed; background-size: cover; background-position: center;";
}
?>
<style>
:root {
  --primary: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>;
}
body {
  <?php echo $bgStyle; ?>
}
/* Public scrollbar consistency */
html, body {
  scrollbar-width: thin;
  scrollbar-color: #8b5cf6 transparent;
}
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #a78bfa, #8b5cf6);
  border-radius: 999px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
}
::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #c4b5fd, #a78bfa); }
</style>

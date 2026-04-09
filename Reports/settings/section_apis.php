<?php declare(strict_types=1);

// Load API settings from database
$apiSettings = [];
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('openweathermap_api_key', 'openweathermap_city', 'google_maps_embed')");
    if ($stmt) {
        $apiSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
$hasOwm = !empty($apiSettings['openweathermap_api_key']);
$hasOwmCity = !empty($apiSettings['openweathermap_city']);
$hasMaps = !empty($apiSettings['google_maps_embed']);
?>

<!-- Section: API Configuration -->
<div class="apple-section-group">
  <h2 class="apple-section-title">API Configuration (พยากรณ์อากาศและแผนที่หอพัก)</h2>
  <div class="apple-section-card">
    
    <!-- OpenWeatherMap Configuration Status -->
    <div class="apple-settings-row">
      <div class="apple-row-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <circle cx="12" cy="12" r="5" />
          <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">ปลั๊กอินพยากรณ์อากาศ</p>
        <p class="apple-row-sublabel"><?php echo ($hasOwm && $hasOwmCity) ? 'เปิดใช้งานอยู่ (แสดงฝั่งผู้เช่า)' : 'ยังไม่ได้เชื่อมต่อ'; ?></p>
      </div>
      <span class="apple-row-badge <?php echo ($hasOwm && $hasOwmCity)  ? 'success' : 'warning'; ?>">
        <?php echo ($hasOwm && $hasOwmCity) ? 'พร้อมใช้งาน' : 'ตั้งค่าเพิ่มเติม'; ?>
      </span>
    </div>

    <!-- OpenWeatherMap API Key -->
    <div class="apple-settings-row" data-sheet="sheet-openweathermap-key">
      <div class="apple-row-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M19.5 13.572v3.914a2 2 0 0 1-2 2h-15a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2h15a2 2 0 0 1 2 2v.428"/></svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">OpenWeather API Key</p>
        <p class="apple-row-sublabel">คีย์สำหรับดึงข้อมูลสภาพอากาศ</p>
      </div>
      <span class="apple-row-value" data-display="owm-key"><?php echo $hasOwm ? '••••••••' : 'Not Set'; ?></span>
      <span class="apple-row-chevron">›</span>
    </div>

    <!-- OpenWeatherMap City -->
    <div class="apple-settings-row" data-sheet="sheet-openweathermap-city">
      <div class="apple-row-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">ตำแหน่ง/อำเภอ (City)</p>
        <p class="apple-row-sublabel">เช่น Bangkok, Nonthaburi, Hat Yai</p>
      </div>
      <span class="apple-row-value" data-display="owm-city"><?php echo htmlspecialchars($apiSettings['openweathermap_city'] ?? 'ยังไม่ได้ระบุ', ENT_QUOTES, 'UTF-8'); ?></span>
      <span class="apple-row-chevron">›</span>
    </div>

    <div style="height: 1px; background: rgba(0,0,0,0.05); margin: 0.5rem 1rem;"></div>

    <!-- Google Maps Configuration Status -->
    <div class="apple-settings-row">
      <div class="apple-row-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated">
          <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line>
        </svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">แผนที่หอพัก (Google Maps)</p>
        <p class="apple-row-sublabel"><?php echo $hasMaps ? 'เปิดใช้งานอยู่ (แสดงฝั่งผู้เช่า)' : 'ยังไม่ได้เชื่อมต่อ'; ?></p>
      </div>
      <span class="apple-row-badge <?php echo $hasMaps ? 'success' : 'warning'; ?>">
        <?php echo $hasMaps ? 'พร้อมใช้งาน' : 'ตั้งค่าเพิ่มเติม'; ?>
      </span>
    </div>

    <!-- Google Maps Embed -->
    <div class="apple-settings-row" data-sheet="sheet-google-maps">
      <div class="apple-row-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/></svg>
      </div>
      <div class="apple-row-content">
        <p class="apple-row-label">Google Maps Embed URL</p>
        <p class="apple-row-sublabel">ลิงก์ฝังแผนที่ จาก Google Maps (src)</p>
      </div>
      <span class="apple-row-value" data-display="maps-url"><?php echo $hasMaps ? 'ตั้งค่าแล้ว' : 'ยังไม่ได้ระบุ'; ?></span>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: OpenWeatherMap API Key -->
<div class="apple-sheet-overlay" id="sheet-openweathermap-key">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-openweathermap-key">ปิด</button>
      <h3 class="apple-sheet-title">OpenWeather API Key</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form method="post" action="../Manage/save_system_settings.php">
        <div class="apple-input-group">
          <label class="apple-input-label">กรอก API Key</label>
          <input type="password" name="openweathermap_api_key" class="apple-input" value="<?php echo htmlspecialchars($apiSettings['openweathermap_api_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" placeholder="เช่น: 1a2b3c4d5e6f...">
          <p class="apple-input-hint">สมัครรับคีย์ฟรีได้ที่ <a href="https://home.openweathermap.org/users/sign_up" target="_blank" style="color:#0066cc;">openweathermap.org</a></p>
        </div>
        <button type="submit" class="apple-button primary">บันทึกข้อมูล</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: OpenWeatherMap City -->
<div class="apple-sheet-overlay" id="sheet-openweathermap-city">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-openweathermap-city">ปิด</button>
      <h3 class="apple-sheet-title">เป้าหมายที่ตั้ง (City)</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form method="post" action="../Manage/save_system_settings.php">
        <div class="apple-input-group">
          <label class="apple-input-label">ชื่อเมือง หรือ อำเภอ (ภาษาอังกฤษ)</label>
          <input type="text" name="openweathermap_city" class="apple-input" value="<?php echo htmlspecialchars($apiSettings['openweathermap_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" placeholder="เช่น: Bangkok, Hat Yai, Pattaya">
          <p class="apple-input-hint">แนะนำใช้ชื่อเมืองภาษาอังกฤษ เพื่อความแม่นยำของพยากรณ์อากาศ</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึกข้อมูล</button>
      </form>
    </div>
  </div>
</div>

<!-- Sheet: Google Maps Embed -->
<div class="apple-sheet-overlay" id="sheet-google-maps">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-google-maps">ปิด</button>
      <h3 class="apple-sheet-title">แผนที่หอพัก</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <form method="post" action="../Manage/save_system_settings.php">
        <div class="apple-input-group">
          <label class="apple-input-label">Google Maps Embed URL</label>
          <textarea name="google_maps_embed" class="apple-input" maxlength="2048" rows="4" placeholder='https://www.google.com/maps/embed?pb=!1m...'><?php echo htmlspecialchars($apiSettings['google_maps_embed'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          <p class="apple-input-hint">ไปที่ Google Maps > เลือกสถานที่ > แชร์ > ฝังแผนที่ (Embed) > ก๊อปปี้เฉพาะลิงก์ที่อยู่ใน "src=" (https://www.google.com/maps/embed?...)</p>
        </div>
        <button type="submit" class="apple-button primary">บันทึกข้อมูล</button>
      </form>
    </div>
  </div>
</div>

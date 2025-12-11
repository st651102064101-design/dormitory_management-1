<!-- Section: Utility Rates -->
<div class="apple-section-group">
  <h2 class="apple-section-title">ค่าใช้จ่าย</h2>
  <div class="apple-section-card">
    <!-- Current Rates Display -->
    <div class="apple-settings-row" data-sheet="sheet-rates" style="padding: 16px;">
      <div style="display: flex; gap: 20px; width: 100%;">
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 28px; color: #3b82f6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32" class="icon-animated"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
          <div id="currentWaterRate" style="font-size: 24px; font-weight: 700; color: var(--apple-blue);">฿<?php echo number_format($waterRate); ?></div>
          <div style="font-size: 12px; color: var(--apple-text-secondary);">บาท/หน่วย</div>
        </div>
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 28px; color: #f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32" class="icon-animated"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
          <div id="currentElecRate" style="font-size: 24px; font-weight: 700; color: var(--apple-orange);">฿<?php echo number_format($electricRate); ?></div>
          <div style="font-size: 12px; color: var(--apple-text-secondary);">บาท/หน่วย</div>
        </div>
      </div>
    </div>
    
    <!-- Manage Rates -->
    <div class="apple-settings-row" data-sheet="sheet-rates">
      <div class="apple-row-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">จัดการอัตราค่าน้ำค่าไฟ</p>
        <p class="apple-row-sublabel" id="currentRateDateLabel">เริ่มใช้: <?php echo date('d/m/Y', strtotime($currentRateDate)); ?></p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
  </div>
</div>

<!-- Sheet: Rates -->
<div class="apple-sheet-overlay" id="sheet-rates">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-rates">เสร็จ</button>
      <h3 class="apple-sheet-title">อัตราค่าน้ำค่าไฟ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <!-- Current Rate -->
      <div class="apple-rate-display">
        <div class="apple-rate-item">
          <div class="apple-rate-icon" style="color: #3b82f6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
          <div class="apple-rate-value" id="sheetWaterRate">฿<?php echo number_format($waterRate); ?></div>
          <div class="apple-rate-unit">บาท/หน่วย</div>
        </div>
        <div class="apple-rate-item">
          <div class="apple-rate-icon" style="color: #f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
          <div class="apple-rate-value" id="sheetElecRate">฿<?php echo number_format($electricRate); ?></div>
          <div class="apple-rate-unit">บาท/หน่วย</div>
        </div>
      </div>
      
      <p id="sheetRateDateLabel" style="font-size: 13px; color: var(--apple-text-secondary); text-align: center; margin-bottom: 20px;">
        📌 อัตราปัจจุบัน (ใช้ตั้งแต่ <?php echo date('d/m/Y', strtotime($currentRateDate)); ?>)
      </p>
      
      <!-- Add New Rate -->
      <div style="background: rgba(0, 122, 255, 0.05); padding: 16px; border-radius: 14px; margin-bottom: 20px;">
        <h4 style="font-size: 15px; font-weight: 600; color: var(--apple-blue); margin: 0 0 16px;">➕ เพิ่มอัตราใหม่</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="apple-input-group" style="margin-bottom: 0;">
            <label class="apple-input-label">💧 ค่าน้ำ</label>
            <input type="number" id="waterRate" class="apple-input" value="<?php echo $waterRate; ?>" min="0" step="1">
          </div>
          <div class="apple-input-group" style="margin-bottom: 0;">
            <label class="apple-input-label">⚡ ค่าไฟ</label>
            <input type="number" id="electricRate" class="apple-input" value="<?php echo $electricRate; ?>" min="0" step="1">
          </div>
        </div>
        
        <div class="apple-input-group" style="margin-bottom: 12px;">
          <label class="apple-input-label">📅 วันที่เริ่มใช้</label>
          <input type="date" id="effectiveDate" class="apple-input" value="<?php echo date('Y-m-d'); ?>">
        </div>
        
        <button type="button" class="apple-button primary" onclick="saveUtilityRates()">บันทึกอัตราใหม่</button>
      </div>
      
      <!-- Rate History -->
      <h4 style="font-size: 13px; font-weight: 600; color: var(--apple-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">📋 ประวัติอัตรา</h4>
      
      <div style="background: var(--apple-card); border-radius: 14px; overflow: hidden;">
        <table class="apple-rate-table">
          <thead>
            <tr>
              <th>วันที่</th>
              <th style="text-align: center;">💧</th>
              <th style="text-align: center;">⚡</th>
              <th style="text-align: center;">สถานะ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allRates)): ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--apple-text-secondary);">ยังไม่มีข้อมูล</td>
            </tr>
            <?php else: ?>
            <?php foreach ($allRates as $i => $r): 
              $rateKey = $r['rate_water'] . '_' . $r['rate_elec'];
              $usage = $rateUsage[$rateKey] ?? null;
              $isUsed = !empty($usage);
              $isActive = ($i === 0);
            ?>
            <tr id="rate-row-<?php echo $r['rate_id']; ?>" class="<?php echo $isActive ? 'current-rate' : ''; ?>" data-rate-id="<?php echo $r['rate_id']; ?>" data-water="<?php echo $r['rate_water']; ?>" data-elec="<?php echo $r['rate_elec']; ?>">
              <td>
                <?php echo date('d/m/Y', strtotime($r['effective_date'] ?? '2025-01-01')); ?>
              </td>
              <td style="text-align: center; color: var(--apple-blue); font-weight: 600;">฿<?php echo number_format($r['rate_water']); ?></td>
              <td style="text-align: center; color: var(--apple-orange); font-weight: 600;">฿<?php echo number_format($r['rate_elec']); ?></td>
              <td style="text-align: center;">
                <?php if ($isActive): ?>
                <span class="apple-badge green rate-active-badge" style="font-size: 10px;">✓ ใช้งานอยู่</span>
                <?php elseif ($isUsed): ?>
                <div class="rate-usage-info" onclick="showRateUsage('<?php echo htmlspecialchars(json_encode($usage)); ?>')" style="cursor: pointer;">
                  <span class="apple-badge blue" style="font-size: 10px;" title="ใช้ใน <?php echo (int)$usage['expense_count']; ?> บิล, <?php echo (int)$usage['room_count']; ?> ห้อง">
                    📊 <?php echo (int)$usage['expense_count']; ?> บิล
                  </span>
                </div>
                <?php else: ?>
                <span style="font-size: 11px; color: var(--apple-text-secondary);">ยังไม่ถูกใช้</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <?php if (!$isActive): ?>
                <button type="button" class="apple-use-btn" onclick="useRate(<?php echo $r['rate_id']; ?>)" title="ใช้อัตรานี้">ใช้</button>
                <?php if (!$isUsed): ?>
                <button type="button" class="apple-delete-btn" onclick="deleteRate(<?php echo $r['rate_id']; ?>)">ลบ</button>
                <?php else: ?>
                <button type="button" class="apple-delete-btn" disabled title="ไม่สามารถลบได้ เพราะมีบิลใช้อัตรานี้อยู่" style="opacity: 0.4; cursor: not-allowed;">ลบ</button>
                <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <p style="font-size: 12px; color: var(--apple-text-secondary); margin-top: 12px; text-align: center;">
        💡 คลิกที่ "📊 x บิล" เพื่อดูรายละเอียดห้องที่ใช้อัตรานี้
      </p>
    </div>
  </div>
</div>

<!-- Modal: Rate Usage Info -->
<div id="rateUsageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10001; justify-content: center; align-items: center;">
  <div style="background: var(--apple-card); border-radius: 16px; padding: 24px; max-width: 400px; width: 90%; margin: 20px;">
    <h4 style="margin: 0 0 16px; color: var(--apple-text); font-size: 18px;">📊 รายละเอียดการใช้อัตรานี้</h4>
    <div id="rateUsageContent"></div>
    <button type="button" class="apple-button" style="width: 100%; margin-top: 16px;" onclick="closeRateUsageModal()">ปิด</button>
  </div>
</div>

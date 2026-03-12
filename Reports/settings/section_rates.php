<!-- Section: Utility Rates -->
<div class="apple-section-group">
  <h2 class="apple-section-title">ค่าใช้จ่าย</h2>
  <div class="apple-section-card">
    <!-- Payment Due Day Setting -->
    <div class="apple-settings-row" data-sheet="sheet-payment-due">
      <div class="apple-row-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label">กำหนดชำระค่าห้อง</p>
        <p class="apple-row-sublabel">ทุกวันที่ <?php echo (int)$paymentDueDay; ?> ของเดือน</p>
      </div>
      <span class="apple-row-chevron">›</span>
    </div>
    <!-- Current Rates Display -->
    <?php
      // Prefer base-unit/pricing values from the current/latest rate (if available),
      // otherwise fall back to system_settings (with sensible defaults).
      $waterBaseUnits = null;
      $waterBasePrice = null;
      $waterExcessRate = null;

      if (!empty($allRates) && isset($allRates[0]) && is_array($allRates[0])) {
        $currentRateRow = $allRates[0];
        if (isset($currentRateRow['water_base_units'])) {
          $waterBaseUnits = (int)$currentRateRow['water_base_units'];
        }
        if (isset($currentRateRow['water_base_price'])) {
          $waterBasePrice = (int)$currentRateRow['water_base_price'];
        }
        if (isset($currentRateRow['water_excess_rate'])) {
          $waterExcessRate = (int)$currentRateRow['water_excess_rate'];
        }
      }

      if ($waterBaseUnits === null || $waterBasePrice === null || $waterExcessRate === null) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        if ($waterBaseUnits === null) {
          $stmt->execute(['water_base_units']);
          $waterBaseUnits = (int)($stmt->fetchColumn() ?: 10);
        }
        if ($waterBasePrice === null) {
          $stmt->execute(['water_base_price']);
          $waterBasePrice = (int)($stmt->fetchColumn() ?: 200);
        }
        if ($waterExcessRate === null) {
          $stmt->execute(['water_excess_rate']);
          $waterExcessRate = (int)($stmt->fetchColumn() ?: 25);
        }
      }
  ?>
  <div class="apple-settings-row" data-sheet="sheet-rates" style="padding: 16px;">
      <div style="display: flex; gap: 20px; width: 100%;">
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 28px; color: #3b82f6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32" class="icon-animated"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
          <div id="currentWaterRate" style="font-size: 24px; font-weight: 700; color: var(--apple-blue);">฿<?php echo number_format($waterRate); ?></div>
          <div id="currentWaterUnit" style="font-size: 12px; color: var(--apple-text-secondary);">เหมาจ่าย ฿<?php echo number_format($waterBasePrice); ?> ≤<?php echo $waterBaseUnits;?> หน่วย</div>
          <div id="currentWaterExcess" style="font-size: 11px; color: var(--apple-text-secondary);">เกินหน่วยละ ฿<?php echo $waterExcessRate;?></div>
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
          <div class="apple-rate-unit">เหมาจ่าย ฿<?php echo number_format($waterBasePrice); ?> ≤<?php echo $waterBaseUnits;?> หน่วย<br><span style="font-size:11px;">เกินหน่วยละ ฿<?php echo $waterExcessRate;?></span></div>
        </div>
        <div class="apple-rate-item">
          <div class="apple-rate-icon" style="color: #f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
          <div class="apple-rate-value" id="sheetElecRate">฿<?php echo number_format($electricRate); ?></div>
          <div class="apple-rate-unit">บาท/หน่วย</div>
        </div>
      </div>
      
      <p id="sheetRateDateLabel" style="font-size: 13px; color: var(--apple-text-secondary); text-align: center; margin-bottom: 20px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>อัตราปัจจุบัน (ใช้ตั้งแต่ <?php echo date('d/m/Y', strtotime($currentRateDate)); ?>)
      </p>
      
      <!-- Add New Rate -->
      <div style="background: rgba(0, 122, 255, 0.05); padding: 16px; border-radius: 14px; margin-bottom: 20px;">
        <h4 style="font-size: 15px; font-weight: 600; color: var(--apple-blue); margin: 0 0 16px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>เพิ่มอัตราใหม่</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="apple-input-group" style="margin-bottom: 0;">
            <label class="apple-input-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>ราคาเหมาจ่าย</label>
            <input type="number" id="waterRate" class="apple-input" value="<?php echo $waterRate; ?>" min="0" step="1">
          </div>
          <div class="apple-input-group" style="margin-bottom: 0;">
            <label class="apple-input-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>หน่วยฐาน (เหมาจ่าย)</label>
            <input type="number" id="waterBaseUnits" class="apple-input" value="<?php echo $waterBaseUnits; ?>" min="0" step="1">
          </div>
          <div class="apple-input-group" style="margin-bottom: 0;">
            <label class="apple-input-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>ค่าเกินหน่วย</label>
            <input type="number" id="waterExcessRate" class="apple-input" value="<?php echo $waterExcessRate; ?>" min="0" step="1">
          </div>
          <div class="apple-input-group" style="margin-bottom: 0;">
            <label class="apple-input-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>ค่าไฟ</label>
            <input type="number" id="electricRate" class="apple-input" value="<?php echo $electricRate; ?>" min="0" step="1">
          </div>
        </div>
        
        <div class="apple-input-group" style="margin-bottom: 12px;">
          <label class="apple-input-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>วันที่เริ่มใช้</label>
          <input type="text" id="effectiveDate" class="apple-input" value="<?php echo date('Y-m-d'); ?>" placeholder="YYYY-MM-DD">
          <div style="font-size:11px;color:var(--apple-text-secondary);margin-top:4px;">
            พิมพ์ yyyy-mm-dd หรือ dd/mm/yyyy แล้วระบบจะปรับให้โดยอัตโนมัติ
          </div>
        </div>
        
        <button type="button" class="apple-button primary" onclick="saveUtilityRates()">บันทึกอัตราใหม่</button>
      </div>
      
      <!-- Rate History -->
      <h4 style="font-size: 13px; font-weight: 600; color: var(--apple-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><rect x="9" y="2" width="6" height="4" rx="1"/><rect x="4" y="4" width="16" height="18" rx="2"/><line x1="9" y1="11" x2="15" y2="11"/><line x1="9" y1="15" x2="15" y2="15"/></svg>ประวัติอัตรา</h4>
      
      <div style="background: var(--apple-card); border-radius: 14px; overflow: hidden;">
        <table class="apple-rate-table">
          <colgroup>
            <col style="width: 16%;">
            <col style="width: 13%;">
            <col style="width: 13%;">
            <col style="width: 13%;">
            <col style="width: 11%;">
            <col style="width: 16%;">
            <col style="width: 18%;">
          </colgroup>
          <thead>
            <tr>
              <th>วันที่</th>
              <th style="text-align: center;" colspan="3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg> น้ำ</th>
              <th style="text-align: center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> ไฟ</th>
              <th style="text-align: center;">สถานะ</th>
              <th></th>
            </tr>
            <tr class="apple-rate-subheader">
              <th></th>
              <th style="text-align: center;">เหมาจ่าย</th>
              <th style="text-align: center;">หน่วยฐาน</th>
              <th style="text-align: center;">เกิน/หน่วย</th>
              <th style="text-align: center;">บาท/หน่วย</th>
              <th></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allRates)): ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--apple-text-secondary);">ยังไม่มีข้อมูล</td>
            </tr>
            <?php else: ?>
            <?php foreach ($allRates as $i => $r):
              $rateKey = $r['rate_water'] . '_' . $r['rate_elec'];
              $usage = $rateUsage[$rateKey] ?? null;
              $isUsed = !empty($usage);
              $isActive = ($i === 0);
            ?>
            <?php
            $baseUnits = isset($r['water_base_units']) ? (int)$r['water_base_units'] : '';
            $basePrice = isset($r['water_base_price']) ? (int)$r['water_base_price'] : '';
            $excessRate = isset($r['water_excess_rate']) ? (int)$r['water_excess_rate'] : '';
            ?>
            <tr id="rate-row-<?php echo $r['rate_id']; ?>" class="<?php echo $isActive ? 'current-rate' : ''; ?>" data-rate-id="<?php echo $r['rate_id']; ?>" data-water="<?php echo $r['rate_water']; ?>" data-elec="<?php echo $r['rate_elec']; ?>" data-base-units="<?php echo $baseUnits; ?>" data-base-price="<?php echo $basePrice; ?>" data-excess-rate="<?php echo $excessRate; ?>">
              <td data-label="วันที่">
                <?php echo date('d/m/Y', strtotime($r['effective_date'] ?? '2025-01-01')); ?>
              </td>
              <td data-label="เหมาจ่าย" style="text-align: center; color: var(--apple-blue); font-weight: 600;">
                ฿<?php echo ($basePrice !== '') ? number_format($basePrice) : number_format($r['rate_water']); ?>
              </td>
              <td data-label="หน่วยฐาน" style="text-align: center; color: var(--apple-blue);">
                <?php echo ($baseUnits !== '') ? '≤' . $baseUnits . ' หน่วย' : '-'; ?>
              </td>
              <td data-label="เกิน/หน่วย" style="text-align: center; color: var(--apple-blue);">
                <?php echo ($excessRate !== '') ? '฿' . number_format($excessRate) : '-'; ?>
              </td>
              <td data-label="ค่าไฟ" style="text-align: center; color: var(--apple-orange); font-weight: 600;">฿<?php echo number_format($r['rate_elec']); ?></td>
              <td data-label="สถานะ" style="text-align: center;">
                <?php if ($isActive): ?>
                <span class="apple-badge green rate-active-badge" style="font-size: 10px;">✓ ใช้งานอยู่</span>
                <?php elseif ($isUsed): ?>
                <div class="rate-usage-info" onclick="showRateUsage('<?php echo htmlspecialchars(json_encode($usage)); ?>')" style="cursor: pointer;">
                  <span class="apple-badge blue" style="font-size: 10px;" title="ใช้ใน <?php echo (int)$usage['expense_count']; ?> บิล, <?php echo (int)$usage['room_count']; ?> ห้อง">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;vertical-align:-1px;margin-right:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo (int)$usage['expense_count']; ?> บิล
                  </span>
                </div>
                <?php else: ?>
                <span style="font-size: 11px; color: var(--apple-text-secondary);">ยังไม่ถูกใช้</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center; white-space: nowrap;">
                <?php if ($isActive): ?>
                <button type="button" class="apple-use-btn" disabled title="อัตรานี้ใช้งานอยู่แล้ว" style="opacity: 0.4; cursor: not-allowed;">ใช้</button>
                <button type="button" class="apple-delete-btn" disabled title="ไม่สามารถลบอัตราที่ใช้งานอยู่ได้" style="opacity: 0.4; cursor: not-allowed;">ลบ</button>
                <?php else: ?>
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
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>คลิกที่ "x บิล" เพื่อดูรายละเอียดห้องที่ใช้อัตรานี้
      </p>
    </div>
  </div>
</div>

<!-- Modal: Rate Usage Info -->
<div id="rateUsageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10001; justify-content: center; align-items: center;">
  <div style="background: var(--apple-card); border-radius: 16px; padding: 24px; max-width: 400px; width: 90%; margin: 20px;">
    <h4 style="margin: 0 0 16px; color: var(--apple-text); font-size: 18px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;vertical-align:-4px;margin-right:6px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>รายละเอียดการใช้อัตรานี้</h4>
    <div id="rateUsageContent"></div>
    <button type="button" class="apple-button" style="width: 100%; margin-top: 16px;" onclick="closeRateUsageModal()">ปิด</button>
  </div>
</div>

<!-- Sheet: Payment Due Day -->
<div class="apple-sheet-overlay" id="sheet-payment-due">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button class="apple-sheet-action" data-close-sheet="sheet-payment-due">เสร็จ</button>
      <h3 class="apple-sheet-title">กำหนดชำระค่าห้อง</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">
      <div style="text-align: center; margin-bottom: 20px;">
        <div style="font-size: 48px; color: #ef4444; margin-bottom: 8px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </div>
        <p style="font-size: 14px; color: var(--apple-text-secondary); margin: 0;">กำหนดวันที่ต้องชำระค่าห้องทุกเดือน<br>หากเลยกำหนดสถานะจะเปลี่ยนเป็น "<strong style="color:#ef4444;">ค้างชำระ</strong>" อัตโนมัติ</p>
      </div>

      <div class="apple-input-group" style="margin-bottom: 16px;">
        <label class="apple-input-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>วันที่ครบกำหนดชำระ (1-28)
        </label>
        <input type="number" id="paymentDueDay" class="apple-input" value="<?php echo (int)$paymentDueDay; ?>" min="1" max="28" step="1">
        <p style="font-size: 12px; color: var(--apple-text-secondary); margin-top: 6px;">
          เช่น ตั้งเป็น 5 หมายความว่า ต้องชำระภายในวันที่ 5 ของทุกเดือน
        </p>
      </div>

      <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 14px; margin-bottom: 20px;">
        <div style="display: flex; align-items: flex-start; gap: 10px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" width="20" height="20" style="flex-shrink:0; margin-top: 2px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <div style="font-size: 13px; color: var(--apple-text-secondary); line-height: 1.5;">
            <strong style="color: var(--apple-text);">ตัวอย่าง:</strong> ถ้าตั้งเป็นวันที่ <strong id="dueDayExample"><?php echo (int)$paymentDueDay; ?></strong><br>
            บิลเดือน มี.ค. 2569 → ต้องชำระภายใน <strong id="dueDateExample"><?php echo (int)$paymentDueDay; ?> มี.ค. 2569</strong><br>
            หากยังไม่ชำระภายในกำหนด → สถานะจะเป็น <span style="color:#ef4444; font-weight:600;">ค้างชำระ</span>
          </div>
        </div>
      </div>

      <button type="button" class="apple-button primary" onclick="savePaymentDueDay()" style="width: 100%;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:4px;">
          <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
          <polyline points="17 21 17 13 7 13 7 21"/>
          <polyline points="7 3 7 8 15 8"/>
        </svg>บันทึก
      </button>
    </div>
  </div>
</div>

<script>
// Payment Due Day
document.getElementById('paymentDueDay')?.addEventListener('input', function() {
  const val = Math.max(1, Math.min(28, parseInt(this.value) || 5));
  const exEl = document.getElementById('dueDayExample');
  const dateEl = document.getElementById('dueDateExample');
  if (exEl) exEl.textContent = val;
  if (dateEl) dateEl.textContent = val + ' มี.ค. 2569';
});

function savePaymentDueDay() {
  const val = Math.max(1, Math.min(28, parseInt(document.getElementById('paymentDueDay').value) || 5));
  const formData = new FormData();
  formData.append('payment_due_day', val);
  
  fetch('../Manage/save_system_settings.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      // Update the label on main settings page
      const sublabel = document.querySelector('[data-sheet="sheet-payment-due"] .apple-row-sublabel');
      if (sublabel) sublabel.textContent = 'ทุกวันที่ ' + val + ' ของเดือน';
      
      if (typeof appleToast === 'function') {
        appleToast('บันทึกวันครบกำหนดชำระสำเร็จ', 'success');
      } else {
        alert('บันทึกสำเร็จ');
      }
    } else {
      alert(data.error || 'เกิดข้อผิดพลาด');
    }
  })
  .catch(() => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
}
</script>

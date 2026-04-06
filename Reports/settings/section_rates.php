<!-- Section: Utility Rates -->
<div class="apple-section-group">
  <h2 id="expensesSectionTitle" class="apple-section-title"><?php echo __('expenses_section'); ?></h2>
  <div class="apple-section-card">
    <script>
    if (typeof window.openRatesSheetFromRow !== 'function') {
      window.openRatesSheetFromRow = function(event, rowId) {
        if (event && typeof event.preventDefault === 'function') {
          event.preventDefault();
        }

        var context = {
          source: 'section_rates.bootstrapFallback',
          rowId: rowId || '',
          sheetId: 'sheet-rates'
        };

        if (window.appleSettings && typeof window.appleSettings.openSheet === 'function') {
          try {
            if (window.appleSettings.openSheet('sheet-rates', context) === true) {
              return true;
            }
          } catch (error) {}
        }

        var overlay = document.getElementById('sheet-rates');
        if (!overlay) {
          return false;
        }

        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        return true;
      };
    }

    if (typeof window.handleRatesRowKeydown !== 'function') {
      window.handleRatesRowKeydown = function(event, rowId) {
        if (!event || (event.key !== 'Enter' && event.key !== ' ')) {
          return true;
        }

        return window.openRatesSheetFromRow(event, rowId) ? false : true;
      };
    }
    </script>

    <!-- Billing Schedule Setting (combined: generate day + payment due day) -->
    <div class="apple-settings-row" id="billingScheduleRow" data-sheet="sheet-billing-schedule" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-billing-schedule">
      <div class="apple-row-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label" id="billingScheduleRowLabel"><?php echo __('billing_schedule_label'); ?></p>
        <p class="apple-row-sublabel" id="billingScheduleSublabel"><?php echo __('billing_generate_day_prefix'); ?> <?php echo (int)$billingGenerateDay; ?> · <?php echo __('billing_due_day_prefix'); ?> <?php echo (int)$paymentDueDay; ?></p>
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
    <div class="apple-settings-row" id="currentRatesRow" data-sheet="sheet-rates" style="padding: 16px; cursor: pointer;" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-rates" onclick="openRatesSheetFromRow(event, 'currentRatesRow')" onkeydown="return handleRatesRowKeydown(event, 'currentRatesRow')">
      <div style="display: flex; gap: 20px; width: 100%; pointer-events: none;">
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
    <div class="apple-settings-row" id="manageRatesRow" data-sheet="sheet-rates" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="sheet-rates" style="cursor: pointer;" onclick="openRatesSheetFromRow(event, 'manageRatesRow')" onkeydown="return handleRatesRowKeydown(event, 'manageRatesRow')">
      <div class="apple-row-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-animated"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></div>
      <div class="apple-row-content">
        <p class="apple-row-label" id="manageRatesRowLabel"><?php echo __('manage_rates_label'); ?></p>
        <p class="apple-row-sublabel" id="currentRateDateLabel"><?php echo __('effective_from'); ?> <?php echo thaiDate($currentRateDate); ?></p>
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
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-rates">เสร็จ</button>
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
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>อัตราปัจจุบัน (ใช้ตั้งแต่ <?php echo thaiDate($currentRateDate); ?>)
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

      <!-- Update All Bills to Current Rate -->
      <div style="background: rgba(52, 199, 89, 0.08); border: 1px solid rgba(52, 199, 89, 0.2); padding: 16px; border-radius: 14px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="#34c759" stroke-width="2" style="width:20px;height:20px;flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <div>
            <h4 style="font-size: 15px; font-weight: 600; color: #34c759; margin: 0;">อัปเดตบิลทั้งหมดให้ใช้อัตราปัจจุบัน</h4>
            <p style="font-size: 12px; color: var(--apple-text-secondary); margin: 4px 0 0;">เปลี่ยนอัตราค่าน้ำค่าไฟในบิลทุกใบให้เป็นอัตราล่าสุด (฿<?php echo number_format($waterRate); ?> เหมาจ่าย, ≤<?php echo $waterBaseUnits; ?> หน่วย, เกิน ฿<?php echo $waterExcessRate; ?>/หน่วย, ไฟ ฿<?php echo number_format($electricRate); ?>/หน่วย)</p>
          </div>
        </div>
        <button type="button" class="apple-button" onclick="updateAllBillsRate()" style="width: 100%; background: #34c759; color: #fff; border: none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
          อัปเดตทุกบิลให้ใช้อัตราปัจจุบัน
        </button>
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
                <?php echo thaiDate($r['effective_date'] ?? '2025-01-01'); ?>
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
                <?php if ($isUsed): ?>
                <div class="rate-usage-info" onclick="showRateUsage('<?php echo htmlspecialchars(json_encode($usage)); ?>')" style="cursor: pointer; margin-top: 4px;">
                  <span class="apple-badge blue" style="font-size: 10px;" title="ใช้ใน <?php echo (int)$usage['room_count']; ?> ห้อง (<?php echo (int)$usage['expense_count']; ?> บิล)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;vertical-align:-1px;margin-right:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo (int)$usage['room_count']; ?> ห้อง
                  </span>
                </div>
                <?php endif; ?>
                <?php elseif ($isUsed): ?>
                <div class="rate-usage-info" onclick="showRateUsage('<?php echo htmlspecialchars(json_encode($usage)); ?>')" style="cursor: pointer;">
                  <span class="apple-badge blue" style="font-size: 10px;" title="ใช้ใน <?php echo (int)$usage['room_count']; ?> ห้อง (<?php echo (int)$usage['expense_count']; ?> บิล)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;vertical-align:-1px;margin-right:2px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><?php echo (int)$usage['room_count']; ?> ห้อง
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

<!-- Sheet: Billing Schedule (combined: generate day + payment due day) -->
<div class="apple-sheet-overlay" id="sheet-billing-schedule">
  <div class="apple-sheet">
    <div class="apple-sheet-handle"></div>
    <div class="apple-sheet-header">
      <button type="button" class="apple-sheet-action" data-close-sheet="sheet-billing-schedule">เสร็จ</button>
      <h3 class="apple-sheet-title">รอบบิลและกำหนดชำระ</h3>
      <div style="width: 50px;"></div>
    </div>
    <div class="apple-sheet-body">

      <!-- Section 1: Billing Generate Day -->
      <div style="margin-bottom: 28px;">
        <div style="text-align: center; margin-bottom: 16px;">
          <div style="font-size: 48px; color: #6366f1; margin-bottom: 8px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
              <polyline points="10 9 9 9 8 9"/>
            </svg>
          </div>
          <p style="font-size: 14px; color: var(--apple-text-secondary); margin: 0;">
            กำหนดวันที่ระบบจะ<strong style="color:var(--apple-text);">สร้างบิลรายเดือน</strong>ให้ผู้เช่าโดยอัตโนมัติ<br>
            บิลจะไม่ถูกสร้างก่อนถึงวันที่กำหนดในแต่ละเดือน
          </p>
        </div>

        <div class="apple-input-group" style="margin-bottom: 16px;">
          <label class="apple-input-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:3px;">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>วันที่ออกบิลของเดือน (1-28)
          </label>
          <input type="number" id="billingGenerateDay" class="apple-input" value="<?php echo (int)$billingGenerateDay; ?>" min="1" max="28" step="1">
          <p style="font-size: 12px; color: var(--apple-text-secondary); margin-top: 6px;">
            เช่น ตั้งเป็น 25 หมายความว่า บิลเดือนนี้จะถูกสร้างในวันที่ 25 เป็นต้นไป
          </p>
        </div>

        <div style="background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.25); border-radius: 12px; padding: 14px;">
          <div style="display: flex; align-items: flex-start; gap: 10px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" width="20" height="20" style="flex-shrink:0; margin-top: 2px;">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div style="font-size: 13px; color: var(--apple-text-secondary); line-height: 1.6;">
              <strong style="color: var(--apple-text);">ตัวอย่าง:</strong> ถ้าตั้งวันออกบิลเป็นวันที่ <strong id="genDayExample"><?php echo (int)$billingGenerateDay; ?></strong><br>
              → บิล เม.ย. 2569 จะถูกสร้างในวันที่ <strong id="genDateExample"><?php echo (int)$billingGenerateDay; ?> เม.ย. 2569</strong><br>
              → ก่อนวันที่ <strong id="genDayBefore"><?php echo (int)$billingGenerateDay; ?></strong> ของเดือน ระบบจะ<span style="color:#ef4444;font-weight:600;">ยังไม่สร้างบิล</span>เดือนปัจจุบัน
            </div>
          </div>
        </div>
      </div>

      <!-- Divider -->
      <div style="height: 1px; background: var(--apple-separator, rgba(0,0,0,0.1)); margin: 0 -20px 28px;"></div>

      <!-- Section 2: Payment Due Day -->
      <div style="margin-bottom: 24px;">
        <div style="text-align: center; margin-bottom: 16px;">
          <div style="font-size: 48px; color: #ef4444; margin-bottom: 8px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <p style="font-size: 14px; color: var(--apple-text-secondary); margin: 0;">
            กำหนดวันที่ต้องชำระค่าห้องทุกเดือน<br>
            หากเลยกำหนดสถานะจะเปลี่ยนเป็น "<strong style="color:#ef4444;">ค้างชำระ</strong>" อัตโนมัติ
          </p>
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

        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 14px;">
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
      </div>

      <button type="button" class="apple-button primary" onclick="saveBillingSchedule()" style="width: 100%; background: linear-gradient(135deg,#6366f1,#8b5cf6);">
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
const ratesSheetI18n = {
  title: <?php echo json_encode(__('manage_rates_label'), JSON_UNESCAPED_UNICODE); ?>,
  done: <?php echo json_encode(__('done'), JSON_UNESCAPED_UNICODE); ?>
};

function closeSheetOverlayByElement(overlay) {
  if (!overlay) {
    return;
  }

  overlay.classList.remove('active');
  if (!document.querySelector('.apple-sheet-overlay.active')) {
    document.body.style.overflow = '';
  }
}

function bindSheetHandleDragClose(sheetId) {
  var overlay = document.getElementById(sheetId);
  if (!overlay) {
    return;
  }

  var handle = overlay.querySelector('.apple-sheet-handle');
  var sheet = overlay.querySelector('.apple-sheet');
  if (!handle || !sheet || handle.dataset.dragCloseFallbackBound === '1') {
    return;
  }

  handle.dataset.dragCloseFallbackBound = '1';
  handle.style.touchAction = 'none';
  handle.style.cursor = 'ns-resize';

  var startY = 0;
  var deltaY = 0;
  var dragging = false;

  function getCloseThreshold() {
    var height = sheet.getBoundingClientRect().height || sheet.offsetHeight || 0;
    return Math.max(72, Math.round(height * 0.25));
  }

  function beginDrag(clientY) {
    if (!overlay.classList.contains('active')) {
      return false;
    }

    startY = clientY;
    deltaY = 0;
    dragging = true;
    sheet.style.transition = 'none';
    sheet.style.willChange = 'transform';
    return true;
  }

  function updateDrag(clientY) {
    if (!dragging) {
      return;
    }

    deltaY = Math.max(0, clientY - startY);
    sheet.style.transform = 'translateY(' + deltaY + 'px)';
  }

  function finishDrag() {
    if (!dragging) {
      return;
    }

    var shouldClose = deltaY >= getCloseThreshold();
    dragging = false;
    sheet.style.willChange = '';
    sheet.style.transition = 'transform 0.22s cubic-bezier(0.32, 0.72, 0, 1)';
    sheet.style.transform = '';

    if (shouldClose) {
      closeSheetOverlayByElement(overlay);
    }
  }

  handle.addEventListener('pointerdown', function(event) {
    if (event.button !== 0) {
      return;
    }
    if (!beginDrag(event.clientY)) {
      return;
    }

    event.preventDefault();
    try {
      handle.setPointerCapture(event.pointerId);
    } catch (captureError) {}
  });

  handle.addEventListener('pointermove', function(event) {
    updateDrag(event.clientY);
  });

  handle.addEventListener('pointerup', function() {
    finishDrag();
  });

  handle.addEventListener('pointercancel', function() {
    finishDrag();
  });

  var onMouseMove = function(event) {
    updateDrag(event.clientY);
  };
  var onMouseUp = function() {
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
    finishDrag();
  };

  handle.addEventListener('mousedown', function(event) {
    if (event.button !== 0) {
      return;
    }
    if (!beginDrag(event.clientY)) {
      return;
    }

    event.preventDefault();
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
  });

  handle.addEventListener('touchstart', function(event) {
    if (!event.touches || !event.touches.length) {
      return;
    }
    if (!beginDrag(event.touches[0].clientY)) {
      return;
    }

    event.preventDefault();
  }, { passive: false });

  handle.addEventListener('touchmove', function(event) {
    if (!event.touches || !event.touches.length) {
      return;
    }

    event.preventDefault();
    updateDrag(event.touches[0].clientY);
  }, { passive: false });

  handle.addEventListener('touchend', function() {
    finishDrag();
  });

  handle.addEventListener('touchcancel', function() {
    finishDrag();
  });
}

function refreshSheetHandleDragBindings() {
  bindSheetHandleDragClose('sheet-rates');
  bindSheetHandleDragClose('sheet-billing-schedule');

  if (window.appleSheetComponent && typeof window.appleSheetComponent.refresh === 'function') {
    window.appleSheetComponent.refresh();
  }
}

window.refreshSheetHandleDragBindings = refreshSheetHandleDragBindings;

function ensureRatesSheetFallback() {
  var existingOverlay = document.getElementById('sheet-rates');
  if (existingOverlay) {
    refreshSheetHandleDragBindings();
    return existingOverlay;
  }

  var overlay = document.createElement('div');
  overlay.className = 'apple-sheet-overlay';
  overlay.id = 'sheet-rates';
  overlay.innerHTML = `
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
      <div class="apple-sheet-header">
        <button type="button" class="apple-sheet-action" data-close-sheet="sheet-rates">${escapeBillingSheetText(ratesSheetI18n.done)}</button>
        <h3 class="apple-sheet-title">${escapeBillingSheetText(ratesSheetI18n.title)}</h3>
        <div style="width: 50px;"></div>
      </div>
      <div class="apple-sheet-body">
        <p style="font-size: 14px; color: var(--apple-text-secondary); margin: 0;">ไม่พบข้อมูลอัตราค่าน้ำค่าไฟของหน้านี้ กรุณารีเฟรชหน้าอีกครั้ง</p>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  refreshSheetHandleDragBindings();
  console.warn('[SheetDebug] Injected fallback overlay for missing sheet-rates');
  return overlay;
}

function openRatesSheetFromRow(event, rowId) {
  refreshSheetHandleDragBindings();

  if (event) {
    var target = event.target;
    if (target && target.closest && target.closest('button, input, select, textarea, a, [data-close-sheet]')) {
      return true;
    }
    if (event.type === 'keydown' && event.key && event.key !== 'Enter' && event.key !== ' ') {
      return true;
    }
    if (typeof event.preventDefault === 'function') {
      event.preventDefault();
    }
  }

  var context = {
    source: 'section_rates.inlineRowFallback',
    rowId: rowId || '',
    sheetId: 'sheet-rates'
  };

  var opened = false;
  if (window.appleSettings && typeof window.appleSettings.openSheet === 'function') {
    opened = window.appleSettings.openSheet('sheet-rates', context) === true;
  }

  if (!opened) {
    var overlay = document.getElementById('sheet-rates');
    if (!overlay) {
      overlay = ensureRatesSheetFallback();
    }

    if (overlay) {
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      opened = true;
    }
  }

  if (!opened) {
    console.error('[SheetDebug] Unable to open sheet-rates from row fallback', context);
  }

  if (opened) {
    refreshSheetHandleDragBindings();
  }

  return opened;
}

function handleRatesRowKeydown(event, rowId) {
  if (!event || (event.key !== 'Enter' && event.key !== ' ')) {
    return true;
  }

  return openRatesSheetFromRow(event, rowId) ? false : true;
}

window.openRatesSheetFromRow = openRatesSheetFromRow;
window.handleRatesRowKeydown = handleRatesRowKeydown;

(function bindRatesRowFailSafe() {
  function bindRow(rowId) {
    var row = document.getElementById(rowId);
    if (!row || row.dataset.ratesRowFailSafeBound === '1') {
      return;
    }

    row.dataset.ratesRowFailSafeBound = '1';

    row.addEventListener('click', function(event) {
      if (event.defaultPrevented) {
        return;
      }
      openRatesSheetFromRow(event, rowId);
    }, true);

    row.addEventListener('keydown', function(event) {
      handleRatesRowKeydown(event, rowId);
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      refreshSheetHandleDragBindings();
      bindRow('currentRatesRow');
      bindRow('manageRatesRow');
    });
  } else {
    refreshSheetHandleDragBindings();
    bindRow('currentRatesRow');
    bindRow('manageRatesRow');
  }
})();

const billingScheduleI18n = {
  title: <?php echo json_encode(__('billing_schedule_label'), JSON_UNESCAPED_UNICODE); ?>,
  generatePrefix: <?php echo json_encode(__('billing_generate_day_prefix'), JSON_UNESCAPED_UNICODE); ?>,
  duePrefix: <?php echo json_encode(__('billing_due_day_prefix'), JSON_UNESCAPED_UNICODE); ?>,
  done: <?php echo json_encode(__('done'), JSON_UNESCAPED_UNICODE); ?>,
  save: <?php echo json_encode(__('save'), JSON_UNESCAPED_UNICODE); ?>,
  savedSuccess: <?php echo json_encode(__('billing_schedule_saved_success'), JSON_UNESCAPED_UNICODE); ?>,
  saveError: <?php echo json_encode(__('error_occurred'), JSON_UNESCAPED_UNICODE); ?>
};

const billingScheduleDefaults = {
  generateDay: <?php echo (int)$billingGenerateDay; ?>,
  dueDay: <?php echo (int)$paymentDueDay; ?>
};

function escapeBillingSheetText(value) {
  return String(value || '').replace(/[&<>"']/g, function(ch) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[ch] || ch;
  });
}

function ensureBillingScheduleSheetFallback() {
  var existingOverlay = document.getElementById('sheet-billing-schedule');
  if (existingOverlay) {
    refreshSheetHandleDragBindings();
    return existingOverlay;
  }

  var overlay = document.createElement('div');
  overlay.className = 'apple-sheet-overlay';
  overlay.id = 'sheet-billing-schedule';
  overlay.innerHTML = `
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
      <div class="apple-sheet-header">
        <button type="button" class="apple-sheet-action" data-close-sheet="sheet-billing-schedule">${escapeBillingSheetText(billingScheduleI18n.done)}</button>
        <h3 class="apple-sheet-title">${escapeBillingSheetText(billingScheduleI18n.title)}</h3>
        <div style="width: 50px;"></div>
      </div>
      <div class="apple-sheet-body">
        <div class="apple-input-group" style="margin-bottom: 12px;">
          <label class="apple-input-label">${escapeBillingSheetText(billingScheduleI18n.generatePrefix)} (1-28)</label>
          <input type="number" id="billingGenerateDay" class="apple-input" value="${billingScheduleDefaults.generateDay}" min="1" max="28" step="1">
        </div>
        <div class="apple-input-group" style="margin-bottom: 16px;">
          <label class="apple-input-label">${escapeBillingSheetText(billingScheduleI18n.duePrefix)} (1-28)</label>
          <input type="number" id="paymentDueDay" class="apple-input" value="${billingScheduleDefaults.dueDay}" min="1" max="28" step="1">
        </div>
        <button type="button" class="apple-button primary" style="width: 100%;" onclick="saveBillingSchedule()">${escapeBillingSheetText(billingScheduleI18n.save)}</button>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  refreshSheetHandleDragBindings();
  console.warn('[SheetDebug] Injected fallback overlay for missing sheet-billing-schedule');

  return overlay;
}

window.ensureBillingScheduleSheetFallback = ensureBillingScheduleSheetFallback;

if (!document.getElementById('sheet-billing-schedule')) {
  ensureBillingScheduleSheetFallback();
}

// Billing Generate Day - live preview
document.getElementById('billingGenerateDay')?.addEventListener('input', function() {
  const val = Math.max(1, Math.min(28, parseInt(this.value) || 1));
  const exEl = document.getElementById('genDayExample');
  const dateEl = document.getElementById('genDateExample');
  const beforeEl = document.getElementById('genDayBefore');
  if (exEl) exEl.textContent = val;
  if (dateEl) dateEl.textContent = val + ' เม.ย. 2569';
  if (beforeEl) beforeEl.textContent = val;
});

// Payment Due Day - live preview
document.getElementById('paymentDueDay')?.addEventListener('input', function() {
  const val = Math.max(1, Math.min(28, parseInt(this.value) || 5));
  const exEl = document.getElementById('dueDayExample');
  const dateEl = document.getElementById('dueDateExample');
  if (exEl) exEl.textContent = val;
  if (dateEl) dateEl.textContent = val + ' มี.ค. 2569';
});

// Save both settings at once
function saveBillingSchedule() {
  const genDay = Math.max(1, Math.min(28, parseInt(document.getElementById('billingGenerateDay').value) || 1));
  const dueDay = Math.max(1, Math.min(28, parseInt(document.getElementById('paymentDueDay').value) || 5));

  // Save billing_generate_day
  const fd1 = new FormData();
  fd1.append('billing_generate_day', genDay);

  // Save payment_due_day
  const fd2 = new FormData();
  fd2.append('payment_due_day', dueDay);

  Promise.all([
    fetch('../Manage/save_system_settings.php', { method: 'POST', body: fd1 }).then(r => r.json()),
    fetch('../Manage/save_system_settings.php', { method: 'POST', body: fd2 }).then(r => r.json())
  ])
  .then(([res1, res2]) => {
    if (res1.success && res2.success) {
      // Update sublabel on main settings page
      const sublabel = document.getElementById('billingScheduleSublabel');
      if (sublabel) {
        sublabel.textContent = `${billingScheduleI18n.generatePrefix} ${genDay} · ${billingScheduleI18n.duePrefix} ${dueDay}`;
      }

      if (typeof appleToast === 'function') {
        appleToast(billingScheduleI18n.savedSuccess, 'success');
      } else {
        alert(billingScheduleI18n.savedSuccess);
      }
    } else {
      alert(res1.error || res2.error || billingScheduleI18n.saveError);
    }
  })
  .catch(() => alert(billingScheduleI18n.saveError));
}
</script>

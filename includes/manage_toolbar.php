<div class="manage-toolbar">
  <button type="button" class="animate-ui-action-btn animate-ui-add-btn crud-action" data-entity="<?php echo htmlspecialchars($entityName ?? 'รายการ', ENT_QUOTES, 'UTF-8'); ?>" data-fields="<?php echo htmlspecialchars($entityFields ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="(function(b){ if(window.animateUIOpen){ window.animateUIOpen({ title: 'เพิ่ม ' + (b.dataset.entity||'รายการ'), fields: (b.dataset.fields||'').split(',').map(f=>f.trim()).filter(Boolean) }); } else { b.click(); } })(this);">
    <span aria-hidden="true" style="font-size:18px;">➕</span>
    <span>เพิ่ม</span>
  </button>
</div>

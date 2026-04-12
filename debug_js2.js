    function openCheckinModal(ctrId, tntId, tntName, roomNumber, ctrStart, ctrEnd, checkinDate = '', waterMeter = '', elecMeter = '', readOnly = false) {
        document.getElementById('modal_ctr_id').value = ctrId;
        document.getElementById('modal_tnt_id').value = tntId;

        const normalizeDateInput = (rawDate) => {
            if (!rawDate) return '';
            const dateStr = String(rawDate).trim();
            if (!dateStr) return '';
            const yyyyMmDd = dateStr.slice(0, 10);
            if (/^\d{4}-\d{2}-\d{2}$/.test(yyyyMmDd)) {
                return yyyyMmDd;
            }
            const parsed = new Date(dateStr);
            if (Number.isNaN(parsed.getTime())) {
                return '';
            }
            const y = parsed.getFullYear();
            const m = String(parsed.getMonth() + 1).padStart(2, '0');
            const d = String(parsed.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const form = document.getElementById('checkinForm');
        const normalizedCheckinDate = normalizeDateInput(checkinDate);
        const today = new Date();
        const todayValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

        form.checkin_date.value = normalizedCheckinDate || todayValue;

        const closeBtn = document.getElementById('checkinCloseBtn');
        const submitBtn = document.getElementById('checkinSubmitBtn');
        closeBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        submitBtn.style.display = readOnly ? 'none' : 'inline-block';

        // โหมดดูอย่างเดียว: ปิดการแก้ไขทุก field ยกเว้น hidden
        form.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'hidden') return;
            field.disabled = readOnly;
        });

        // Format dates to Thai format
        const formatDate = (dateStr) => {
            const date = new Date(dateStr);
            const months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear() + 543; // Thai Buddhist year
            return `${day} ${month} ${year}`;
        };
        
        document.getElementById('tenantInfo').innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; color: #e2e8f0;">
                <div>
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.25rem;">👤 ชื่อผู้เช่า</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #60a5fa;">${tntName}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.25rem;">🚪 เลขห้อง</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #60a5fa;">${roomNumber}</div>
                </div>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(96, 165, 250, 0.3);">
                <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.25rem;">📋 ระยะเวลาสัญญา</div>
                <div style="font-size: 0.95rem; color: #cbd5e1;">
                    <span style="color: #4ade80;">✓ ${ctrStart}</span> 
                    <span style="color: #94a3b8;"> ถึง </span>
                    <span style="color: #f87171;">${ctrEnd}</span>
                </div>
            </div>
        `;
        
        document.getElementById('checkinModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function validateAndSubmitCheckin() {
        const form = document.getElementById('checkinForm');
        const errorContainer = document.getElementById('validationError');
        const errorList = document.getElementById('errorList');
        const errors = [];

        // Validate วันที่เช็คอิน
        const checkinDate = form.checkin_date.value.trim();
        if (!checkinDate) {
            errors.push('กรุณาระบุวันที่เช็คอิน');
        } else {
            const date = new Date(checkinDate);
            if (isNaN(date.getTime())) {
                errors.push('วันที่เช็คอิน ไม่ถูกต้อง');
            }
        }

        // Display errors or submit
        if (errors.length > 0) {
            errorList.innerHTML = errors.map(err => `<li>${err}</li>`).join('');
            errorContainer.style.display = 'block';
            // Scroll to error
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            errorContainer.style.display = 'none';
            form.submit();
        }
    }

    function closeCheckinModal() {
        document.getElementById('checkinModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        const form = document.getElementById('checkinForm');
        form.reset();
        form.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'hidden') return;
            field.disabled = false;
        });
        document.getElementById('checkinCloseBtn').textContent = 'ยกเลิก';
        document.getElementById('checkinSubmitBtn').style.display = 'inline-block';
        document.getElementById('validationError').style.display = 'none';
    }

    // ปิด modal เมื่อคลิกนอก modal
    document.getElementById('checkinModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCheckinModal();
        }
    });

    document.getElementById('contractModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeContractModal();
        }
    });

    document.getElementById('billingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBillingModal();
        }
    });

    // ปิด modal เมื่อกด ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeContractModal();
            closeCheckinModal();
            closeBillingModal();
        }
    });

    // Functions สำหรับ Booking Modal
    function openBookingModal(bkgId, tntId, roomId, tntName, tntPhone, roomNumber, typeName, typePrice, bkgDate, readOnly = false) {
        document.getElementById('modal_bkg_id').value = bkgId;
        document.getElementById('modal_booking_tnt_id').value = tntId;
        document.getElementById('modal_room_id').value = roomId;

        const bookingSubmitBtn = document.getElementById('bookingSubmitBtn');
        const bookingCloseBtn = document.getElementById('bookingCloseBtn');
        bookingSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
        bookingCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        
        document.getElementById('bookingInfo').innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ผู้เช่า</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${tntName}</div>
                    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">${tntPhone}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ห้องพัก</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${roomNumber}</div>
                    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">${typeName}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ราคา</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #3b82f6;">฿${Number(typePrice).toLocaleString()}/เดือน</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">วันที่จอง</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${bkgDate}</div>
                </div>
            </div>
        `;
        
        document.getElementById('bookingModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closeBookingModal() {
        document.getElementById('bookingModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
    }

    // Functions สำหรับ Contract Modal (Step 3)
    function showNotSignedToast() {
        var existing = document.getElementById('_notSignedToast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = '_notSignedToast';
        el.textContent = '🔒 ผู้เช่ายังไม่ได้เซ็นสัญญา กรุณาให้ผู้เช่าเซ็นสัญญาก่อนทำการเช็คอิน';
        el.style.cssText = 'position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);' +
            'background:#ef4444;color:#fff;padding:0.75rem 1.25rem;border-radius:10px;font-size:0.9rem;' +
            'font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;' +
            'max-width:90vw;text-align:center;opacity:0;transition:opacity 0.25s;pointer-events:none;';
        document.body.appendChild(el);
        requestAnimationFrame(function() { el.style.opacity = '1'; });
        setTimeout(function() {
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 300);
        }, 3500);
    }

    function openContractModal(tntId, roomId, bkgId, tntName, roomNumber, typeName, typePrice, bkgCheckinDate, ctrStart, ctrEnd, bookingAmount, ctrId = 0, hasSigned = false, readOnly = false) {
        document.getElementById('modal_contract_tnt_id').value = tntId;
        document.getElementById('modal_contract_room_id').value = roomId;
        document.getElementById('modal_contract_bkg_id').value = bkgId;

        const toDateInputValue = (rawDate) => {
            if (!rawDate) return '';
            const dateStr = String(rawDate).trim();
            if (!dateStr) return '';

            const isValidYyyyMmDd = (value) => {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
                if (value === '0000-00-00') return false;
                const parsed = new Date(`${value}T00:00:00`);
                if (Number.isNaN(parsed.getTime())) return false;
                const y = parsed.getFullYear();
                const m = String(parsed.getMonth() + 1).padStart(2, '0');
                const d = String(parsed.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}` === value;
            };

            // รองรับทั้งรูปแบบ YYYY-MM-DD และ YYYY-MM-DD HH:MM:SS
            const yyyyMmDd = dateStr.slice(0, 10);
            if (isValidYyyyMmDd(yyyyMmDd)) {
                return yyyyMmDd;
            }

            const parsed = new Date(dateStr);
            if (Number.isNaN(parsed.getTime())) {
                return '';
            }

            const y = parsed.getFullYear();
            const m = String(parsed.getMonth() + 1).padStart(2, '0');
            const d = String(parsed.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const contractSubmitBtn = document.getElementById('contractSubmitBtn');
        const contractCloseBtn = document.getElementById('contractCloseBtn');
        const contractStartInput = document.getElementById('modal_contract_start');
        const contractDurationInput = document.getElementById('modal_contract_duration');

        contractSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
        contractCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        contractStartInput.disabled = false;
        contractStartInput.readOnly = readOnly;
        contractStartInput.style.pointerEvents = readOnly ? 'none' : '';
        contractDurationInput.disabled = readOnly;

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const defaultStart = `${yyyy}-${mm}-${dd}`;

        // ถ้ามีสัญญาอยู่แล้ว (ctrStart) ให้ใช้วันที่จากสัญญาเสมอ
        // ถ้ายังไม่มีสัญญา ให้ใช้ bkgCheckinDate เป็นค่าแนะนำ
        const startDate = toDateInputValue(ctrStart) || toDateInputValue(bkgCheckinDate) || defaultStart;
        document.getElementById('modal_contract_start').value = startDate;

        let durationMonths = 6;
        if (ctrStart && ctrEnd) {
            const start = new Date(ctrStart);
            const end = new Date(ctrEnd);
            if (!isNaN(start) && !isNaN(end)) {
                const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
                if (months > 0) durationMonths = months;
            }
        }
        const durationSelect = document.getElementById('modal_contract_duration');
        if ([3, 6, 12].includes(durationMonths)) {
            durationSelect.value = String(durationMonths);
        } else {
            durationSelect.value = '6';
        }

        const depositValue = Number(bookingAmount) > 0 ? Number(bookingAmount) : 2000;
        document.getElementById('modal_contract_deposit').value = depositValue;

        document.getElementById('contractInfo').innerHTML = `
            <p><strong style="color: #a78bfa;">ผู้เช่า:</strong> ${tntName}</p>
            <p><strong style="color: #a78bfa;">ห้อง:</strong> ${roomNumber} (${typeName})</p>
            <p><strong style="color: #a78bfa;">ค่าห้อง:</strong> ฿${Number(typePrice).toLocaleString()}/เดือน</p>
        `;

        // แสดงส่วน signature ถ้ามี ctrId
        const sigSection = document.getElementById('contractSignatureSection');
        if (ctrId > 0) {
            let sigHtml;
            if (hasSigned) {
                sigHtml = '<div style="padding:0.75rem 1rem;border-radius:10px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);display:flex;align-items:center;gap:0.6rem;">'
                    + '<span style="font-size:1.2rem;">\u2705</span>'
                    + '<div>'
                    + '<div style="color:#22c55e;font-weight:600;font-size:0.9rem;">\u0e1c\u0e39\u0e49\u0e40\u0e0a\u0e48\u0e32\u0e40\u0e0b\u0e47\u0e19\u0e2a\u0e31\u0e0d\u0e0d\u0e32\u0e41\u0e25\u0e49\u0e27</div>'
                    + '<div style="color:#64748b;font-size:0.8rem;">\u0e2a\u0e32\u0e21\u0e32\u0e23\u0e16\u0e14\u0e33\u0e40\u0e19\u0e34\u0e19\u0e01\u0e32\u0e23\u0e40\u0e0a\u0e47\u0e04\u0e2d\u0e34\u0e19\u0e44\u0e14\u0e49</div>'
                    + '</div>'
                    + '<a href="print_contract.php?ctr_id=' + ctrId + '" target="_blank" style="margin-left:auto;font-size:0.82rem;color:#38bdf8;text-decoration:none;">\ud83d\udcc4 \u0e14\u0e39\u0e2a\u0e31\u0e0d\u0e0d\u0e32</a>'
                    + '</div>';
            } else {
                sigHtml = '<div style="padding:0.8rem 1rem;border-radius:10px;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.3);">'
                    + '<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.45rem;">'
                    + '<span style="font-size:1.1rem;">\u270d\ufe0f</span>'
                    + '<span style="color:#fbbf24;font-weight:600;font-size:0.9rem;">\u0e23\u0e2d\u0e1c\u0e39\u0e49\u0e40\u0e0a\u0e48\u0e32\u0e40\u0e0b\u0e47\u0e19\u0e2a\u0e31\u0e0d\u0e0d\u0e32</span>'
                    + '</div>'
                    + '<div style="font-size:0.82rem;color:#94a3b8;margin-bottom:0.65rem;">'
                    + '\u0e43\u0e2b\u0e49\u0e1c\u0e39\u0e49\u0e40\u0e0a\u0e48\u0e32\u0e40\u0e1b\u0e34\u0e14\u0e25\u0e34\u0e07\u0e01\u0e4c\u0e14\u0e49\u0e32\u0e19\u0e25\u0e48\u0e32\u0e07\u0e41\u0e25\u0e30\u0e40\u0e0b\u0e47\u0e19\u0e0a\u0e37\u0e48\u0e2d \u0e08\u0e36\u0e07\u0e08\u0e30\u0e2a\u0e32\u0e21\u0e32\u0e23\u0e16\u0e40\u0e0a\u0e47\u0e04\u0e2d\u0e34\u0e19\u0e44\u0e14\u0e49'
                    + '</div>'
                    + '<a href="print_contract.php?ctr_id=' + ctrId + '" target="_blank" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 1rem;border-radius:7px;background:rgba(139,92,246,0.18);border:1px solid rgba(139,92,246,0.45);color:#c4b5fd;font-size:0.85rem;font-weight:500;text-decoration:none;">'
                    + '\ud83d\udcc4 \u0e40\u0e1b\u0e34\u0e14\u0e2a\u0e31\u0e0d\u0e0d\u0e32\u0e2a\u0e33\u0e2b\u0e23\u0e31\u0e1a\u0e40\u0e0b\u0e47\u0e19</a>'
                    + '</div>';
            }
            sigSection.innerHTML = sigHtml;
            sigSection.style.display = 'block';
        } else {
            sigSection.style.display = 'none';
        }

        document.getElementById('contractModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
        updateContractEndDate();
    }

    function updateContractEndDate() {
        const startVal = document.getElementById('modal_contract_start').value;
        const durationVal = parseInt(document.getElementById('modal_contract_duration').value) || 0;
        const endDisplay = document.getElementById('modal_contract_end_display');
        if (!startVal || !durationVal) { endDisplay.textContent = '-'; return; }
        const start = new Date(startVal + 'T00:00:00');
        if (isNaN(start.getTime())) { endDisplay.textContent = '-'; return; }
        const end = new Date(start);
        end.setMonth(end.getMonth() + durationVal);
        endDisplay.textContent = end.toLocaleDateString('th-TH', {year:'numeric', month:'long', day:'numeric'});
    }

    function closeContractModal() {
        document.getElementById('contractModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        const contractStartInput = document.getElementById('modal_contract_start');
        contractStartInput.readOnly = false;
        contractStartInput.style.pointerEvents = '';
        document.getElementById('modal_contract_duration').disabled = false;
        document.getElementById('contractCloseBtn').textContent = 'ยกเลิก';
        document.getElementById('contractSubmitBtn').style.display = 'inline-block';
        document.getElementById('contractForm').reset();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatMonthDisplay(dateValue) {
        if (!dateValue) return '-';
        const date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) return '-';
        const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        return `${monthNames[date.getMonth()]} ${date.getFullYear() + 543}`;
    }

    function getBillRemarkText(rawRemark, monthText, fallbackPrefix = 'ชำระบิล') {
        const remark = String(rawRemark || '').trim();
        if (remark !== '') {
            return escapeHtml(remark);
        }
        return escapeHtml(`${fallbackPrefix} (${monthText})`);
    }

    function renderBillSection(containerId, title, billPayload, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const allowReviewAction = options.allowReviewAction === true;
        const emptyHint = options.emptyHint || 'ยังไม่มีข้อมูล';
        const monthText = formatMonthDisplay(billPayload?.bill_month || '');

        if (!billPayload?.has_expense) {
            container.innerHTML = `
                <div style="padding:1rem; background:rgba(255,255,255,0.04); border-radius:10px; border:1px solid rgba(255,255,255,0.08); text-align:center; color:rgba(148,163,184,0.8); font-size:0.88rem;">
                    ${escapeHtml(emptyHint)}
                </div>`;
            return;
        }

        const expenseTotal   = Number(billPayload.expense_total   || 0);
        const approvedAmount = Number(billPayload.approved_amount  || 0);
        const pendingAmount  = Number(billPayload.pending_amount   || 0);
        const remainAmount   = Math.max(expenseTotal - approvedAmount, 0);
        const expenseId      = Number(billPayload.expense_id       || 0);
        const payments       = Array.isArray(billPayload.payments) ? billPayload.payments : [];

        // เลือกสีสถานะบิล
        const statusText = billPayload?.expense_status_text || '-';
        const statusClr  = billPayload?.expense_status === '1' ? '#4ade80'
                         : billPayload?.expense_status === '2' ? '#fbbf24'
                         : billPayload?.expense_status === '3' ? '#f97316'
                         : billPayload?.expense_status === '4' ? '#ef4444'
                         : '#94a3b8';

        // progress bar
        const pct = expenseTotal > 0 ? Math.min((approvedAmount / expenseTotal) * 100, 100) : 0;
        const barColor = pct >= 100 ? '#4ade80' : pct > 0 ? '#fbbf24' : '#475569';

        // payment rows
        const paymentRows = payments.length
            ? payments.map((pay) => {
                const payId    = Number(pay.pay_id    || 0);
                const amount   = Number(pay.pay_amount || 0);
                const payStatus = String(pay.pay_status || '0');
                const proofFilename = String(pay.pay_proof || '').trim();
                const canReview = allowReviewAction && payId > 0 && payStatus === '0';
                const statusBadge = payStatus === '1'
                    ? `<span style="display:inline-block;padding:0.2rem 0.55rem;border-radius:20px;background:rgba(34,197,94,0.15);color:#4ade80;font-size:0.78rem;font-weight:600;">✓ อนุมัติแล้ว</span>`
                    : payStatus === '2'
                        ? `<span style="display:inline-block;padding:0.2rem 0.55rem;border-radius:20px;background:rgba(239,68,68,0.15);color:#f87171;font-size:0.78rem;font-weight:600;">✕ ตีกลับ</span>`
                        : canReview
                            ? `<button type="button" onclick="openSlipReview(${payId},${expenseId},${JSON.stringify(proofFilename).replace(/"/g, '&quot;')},${JSON.stringify(pay.pay_date_display||'-').replace(/"/g, '&quot;')},${amount})" title="คลิกเพื่อตรวจสอบสลิป" style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.55rem;border-radius:20px;background:rgba(245,158,11,0.18);color:#fbbf24;font-size:0.78rem;font-weight:600;border:1px solid rgba(245,158,11,0.4);cursor:pointer;transition:background 0.15s,transform 0.1s;" onmouseover="this.style.background='rgba(245,158,11,0.32)'" onmouseout="this.style.background='rgba(245,158,11,0.18)'">🔍 ตรวจสอบ</button>`
                            : `<span style="display:inline-block;padding:0.2rem 0.55rem;border-radius:20px;background:rgba(245,158,11,0.15);color:#fbbf24;font-size:0.78rem;font-weight:600;">⏳ รอตรวจสอบ</span>`;
                const purpose  = getBillRemarkText(pay.pay_remark, monthText, `ชำระ${title}`);
                const slipThumb = proofFilename
                    ? (() => {
                        const url = '/dormitory_management/Public/Assets/Images/Payments/' + encodeURIComponent(proofFilename);
                        return `<a href="${url}" target="_blank" title="ดูสลิป" style="flex-shrink:0;">
                            <img src="${url}" alt="สลิป" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.15);cursor:pointer;transition:transform 0.15s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'" onerror="this.parentElement.style.display='none'">
                        </a>`;
                    })()
                    : `<div style="width:44px;height:44px;border-radius:6px;border:1px dashed rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;" title="ไม่มีสลิป"><span style="font-size:1.1rem;opacity:0.3;">🖼</span></div>`;
                return `<div style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0.75rem;border-radius:8px;background:rgba(15,23,42,0.3);margin-bottom:0.4rem;flex-wrap:wrap;">
                    ${slipThumb}
                    <div style="flex:1;min-width:80px;">
                        <div style="font-size:0.78rem;color:rgba(148,163,184,0.8);">${escapeHtml(pay.pay_date_display || '-')}</div>
                        <div style="font-weight:700;color:#f8fafc;font-size:0.95rem;">฿${amount.toLocaleString()}</div>
                    </div>
                    <div style="flex:2;min-width:100px;font-size:0.8rem;color:rgba(226,232,240,0.75);">${purpose}</div>
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0;">${statusBadge}</div>
                </div>`;
            }).join('')
            : `<div style="padding:0.85rem;text-align:center;color:rgba(148,163,184,0.7);font-size:0.85rem;">ยังไม่มีรายการชำระ</div>`;

        container.innerHTML = `
            <div style="padding:1rem; background:rgba(255,255,255,0.04); border-radius:12px; border:1px solid rgba(255,255,255,0.09);">
                <!-- header row -->
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.9rem;">
                    <div>
                        <span style="font-weight:700;color:#93c5fd;font-size:0.95rem;">${escapeHtml(title)}</span>
                        <span style="margin-left:0.5rem;font-size:0.82rem;color:rgba(148,163,184,0.7);">${escapeHtml(monthText)}</span>
                    </div>
                    <span style="padding:0.2rem 0.65rem;border-radius:20px;background:rgba(255,255,255,0.06);font-size:0.78rem;color:${statusClr};font-weight:600;">${escapeHtml(statusText)}</span>
                </div>
                <!-- amount summary -->
                <div class="billing-summary-grid">
                    ${[
                        {label:'ยอดบิล',    val:expenseTotal,   clr:'#f8fafc',  bdr:'rgba(148,163,184,0.25)'},
                        {label:'ชำระแล้ว',  val:approvedAmount, clr:'#4ade80',  bdr:'rgba(34,197,94,0.3)'},
                        {label:'รอตรวจ',   val:pendingAmount,  clr:'#fbbf24',  bdr:'rgba(245,158,11,0.3)'},
                        {label:'คงเหลือ',   val:remainAmount,   clr:'#f87171',  bdr:'rgba(239,68,68,0.3)'},
                    ].map(c=>`<div style="background:rgba(15,23,42,0.4);border:1px solid ${c.bdr};border-radius:8px;padding:0.5rem 0.6rem;text-align:center;">
                        <div style="font-size:0.72rem;color:rgba(226,232,240,0.65);">${c.label}</div>
                        <div style="font-weight:700;font-size:0.9rem;color:${c.clr};">฿${c.val.toLocaleString()}</div>
                    </div>`).join('')}
                </div>
                <!-- progress bar — แสดงเฉพาะเมื่อมีการชำระบางส่วนแล้ว -->
                ${pct > 0 ? `<div style="height:5px;background:rgba(255,255,255,0.08);border-radius:99px;margin-bottom:0.9rem;overflow:hidden;">
                    <div style="height:100%;width:${pct.toFixed(1)}%;background:${barColor};border-radius:99px;transition:width 0.4s;"></div>
                </div>` : ''}
                <!-- payments -->
                ${paymentRows}
            </div>`;
    }

    function safeShowSuccessToast(message) {
        if (typeof showSuccessToast === 'function') {
            showSuccessToast(message);
            return;
        }
        alert(message);
    }

    function refreshBillingPayments(ctrId) {
        const firstBillPaymentsSection  = document.getElementById('firstBillPaymentsSection');
        const latestBillPaymentsSection = document.getElementById('latestBillPaymentsSection');
        const loadingHtml = `<div style="padding:1rem; text-align:center; color:rgba(148,163,184,0.8); font-size:0.88rem;">
            <svg style="width:20px;height:20px;animation:waitSpin 1s linear infinite;vertical-align:middle;margin-right:6px;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#60a5fa" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/></svg>
            กำลังโหลด...
        </div>`;
        firstBillPaymentsSection.innerHTML  = loadingHtml;
        latestBillPaymentsSection.innerHTML = loadingHtml;

        // First refresh session to prevent timeout errors
        fetch('../Manage/session_refresh.php', { method: 'POST', credentials: 'include' })
            .catch(() => {}) // Ignore refresh errors, continue anyway
            .then(() => {
                // Now fetch billing payments with valid session
                return fetch(`../Manage/get_first_bill_payments.php?ctr_id=${encodeURIComponent(ctrId)}`, { credentials: 'include' });
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load bill payments');
                }
                return response.json();
            })
            .then(data => {
                const firstBill  = data?.first_bill  || {};
                const latestBill = data?.latest_bill || {};
                const allBills   = data?.all_bills   || [];
                const firstBillMonth = firstBill?.bill_month || '';
                if (firstBillMonth) {
                    document.getElementById('nextMonthDisplay').textContent = formatMonthDisplay(firstBillMonth);
                }

                // Check if latest bill is fully paid (for meter disable logic)
                const lastBillIdx = allBills.length - 1;
                const billToCheck = allBills.length > 0 ? allBills[lastBillIdx] : firstBill;
                const billTotal = Number(billToCheck?.expense_total || 0);
                const billApproved = Number(billToCheck?.approved_amount || 0);
                const billSubmitted = Number(billToCheck?.submitted_amount || 0); // Include pending amount
                // ซ่อนฟอร์มมิเตอร์เมื่อชำระบิลแล้ว ไม่ว่าจะแค่อนุมัติแล้วหรือเพิ่งส่งสลิปรอตรวจสอบก็ตาม
                const isFirstBillFullyPaid = billTotal > 0 && (billApproved >= billTotal || billSubmitted >= billTotal);

                // Disable update meter button if latest bill is fully paid
                const moSaveBtn = document.getElementById('moSaveBtn');
                const saveMeterBtn = document.getElementById('saveMeterBtn');
                const meterBody = document.getElementById('meterBody');
                if (isFirstBillFullyPaid) {
                    if (moSaveBtn) { moSaveBtn.style.display = 'none'; moSaveBtn.disabled = true; }
                    if (saveMeterBtn) { saveMeterBtn.style.display = 'none'; saveMeterBtn.disabled = true; }
                    if (meterBody) { meterBody.style.display = 'none'; }
                    const meterNoticeBlock = document.getElementById('meterNoticeBlock');
                    if (meterNoticeBlock) {
                        meterNoticeBlock.innerHTML = '<span class="billing-inline-icon" style="color:#4ade80;"><svg class="billing-svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14M1 4.5L8.5 12 15 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg><span>ชำระแล้ว — ไม่สามารถอัปเดตมิเตอร์ได้</span></span>';
                        meterNoticeBlock.style.background = 'rgba(34, 197, 94, 0.08)';
                        meterNoticeBlock.style.borderColor = 'rgba(34, 197, 94, 0.25)';
                        meterNoticeBlock.style.color = '#4ade80';
                        meterNoticeBlock.style.display = '';
                    }
                } else {
                    if (meterBody) { meterBody.style.display = ''; }
                }

                // แสดงบิลทุกเดือนจาก all_bills
                firstBillPaymentsSection.style.display = 'none';
                firstBillPaymentsSection.innerHTML = '';
                latestBillPaymentsSection.innerHTML = '';

                if (allBills.length === 0) {
                    latestBillPaymentsSection.innerHTML = '<div style="color:rgba(148,163,184,0.7);font-size:0.88rem;padding:0.5rem 0;">ยังไม่มีบิลในระบบ</div>';
                } else {
                    // สร้าง container สำหรับทุกบิลใน latestBillPaymentsSection (เดือนล่าสุดขึ้นก่อน)
                    const billsReversed = [...allBills].reverse();
                    billsReversed.forEach((bill, idx) => {
                        const isLast = idx === 0; // isLast = bill ล่าสุด (ซึ่งอยู่ index 0 หลัง reverse)
                        const isFirst = idx === billsReversed.length - 1;
                        let title = '';
                        if (billsReversed.length === 1) {
                            title = 'รายการชำระเดือนแรก (บิลปัจจุบัน)';
                        } else if (isFirst) {
                            title = 'รายการชำระเดือนแรก';
                        } else if (isLast) {
                            title = 'บิลล่าสุดที่ต้องจัดการ';
                        } else {
                            // เดือนกลาง
                            const bm = bill.bill_month || '';
                            title = 'บิล ' + (bm ? formatMonthDisplay(bm) : '');
                        }
                        // สร้าง div container ชั่วคราวใน latestBillPaymentsSection
                        const divId = 'billSection_' + idx;
                        const divEl = document.createElement('div');
                        divEl.id = divId;
                        if (idx > 0) divEl.style.marginTop = '0.85rem';
                        latestBillPaymentsSection.appendChild(divEl);
                        renderBillSection(divId, title, bill, {
                            allowReviewAction: true,
                            emptyHint: 'ยังไม่มีรายการชำระ',
                        });
                    });
                }

            })
            .catch((e) => {
                console.error('[refreshBillingPayments] Error loading or rendering data:', e);
                firstBillPaymentsSection.innerHTML = `
                    <div style="font-weight: 700; color: #93c5fd; margin-bottom: 0.5rem;">รายการชำระเดือนแรก</div>
                    <div style="color: #fca5a5;">ไม่สามารถโหลดข้อมูลการชำระจากระบบได้</div>
                `;
                latestBillPaymentsSection.innerHTML = `
                    <div style="font-weight: 700; color: #93c5fd; margin-bottom: 0.5rem;">บิลล่าสุดที่ต้องจัดการ</div>
                    <div style="color: #fca5a5;">ไม่สามารถโหลดข้อมูลบิลล่าสุดจากระบบได้</div>
                `;
            });
    }

    function resetSlipReviewActionButtons() {
        const approveBtn = document.getElementById('slipReviewApproveBtn');
        const rejectBtn = document.getElementById('slipReviewRejectBtn');

        if (approveBtn) {
            approveBtn.disabled = false;
            approveBtn.textContent = '✓ อนุมัติการชำระ';
            approveBtn.style.opacity = '1';
            approveBtn.style.cursor = 'pointer';
        }

        if (rejectBtn) {
            rejectBtn.disabled = false;
            rejectBtn.textContent = '✕ ตีกลับ';
            rejectBtn.style.opacity = '1';
            rejectBtn.style.cursor = 'pointer';
        }
    }

    function setSlipReviewActionPending(actionType) {
        const approveBtn = document.getElementById('slipReviewApproveBtn');
        const rejectBtn = document.getElementById('slipReviewRejectBtn');

        if (!approveBtn || !rejectBtn) {
            return;
        }

        const isApproveAction = actionType === 'approve';
        approveBtn.disabled = true;
        rejectBtn.disabled = true;

        approveBtn.textContent = isApproveAction ? 'กำลังดำเนินการ...' : '✓ อนุมัติการชำระ';
        rejectBtn.textContent = isApproveAction ? '✕ ตีกลับ' : 'กำลังดำเนินการ...';

        approveBtn.style.opacity = isApproveAction ? '0.6' : '1';
        rejectBtn.style.opacity = isApproveAction ? '1' : '0.6';
        approveBtn.style.cursor = 'not-allowed';
        rejectBtn.style.cursor = 'not-allowed';
    }

    function openSlipReview(payId, expId, proofFilename, payDate, amount) {
        const modal = document.getElementById('slipReviewModal');
        const slipImg = document.getElementById('slipReviewImg');
        const slipEmpty = document.getElementById('slipReviewEmpty');
        const slipDate = document.getElementById('slipReviewDate');
        const slipAmount = document.getElementById('slipReviewAmount');
        const approveBtn = document.getElementById('slipReviewApproveBtn');
        const rejectBtn = document.getElementById('slipReviewRejectBtn');

        // Set info
        slipDate.textContent = payDate;
        slipAmount.textContent = '฿' + Number(amount).toLocaleString();

        if (proofFilename) {
            const url = '/dormitory_management/Public/Assets/Images/Payments/' + encodeURIComponent(proofFilename);
            slipImg.src = url;
            slipImg.style.display = 'block';
            slipEmpty.style.display = 'none';
        } else {
            slipImg.style.display = 'none';
            slipEmpty.style.display = 'flex';
        }

        // Reset buttons
        resetSlipReviewActionButtons();

        approveBtn.onclick = () => {
            setSlipReviewActionPending('approve');
            _doReviewBillPayment(payId, expId, '1', () => {
                document.getElementById('slipReviewModal').style.display = 'none';
            });
        };
        rejectBtn.onclick = () => {
            setSlipReviewActionPending('reject');
            _doReviewBillPayment(payId, expId, '2', () => {
                document.getElementById('slipReviewModal').style.display = 'none';
            });
        };

        modal.style.display = 'flex';
    }

    function _doReviewBillPayment(payId, expId, nextStatus, onDone) {
        const ctrId = document.getElementById('modal_billing_ctr_id').value;
        const formData = new URLSearchParams();
        formData.append('csrf_token', '');
        formData.append('pay_id', String(payId));
        formData.append('exp_id', String(expId));
        formData.append('pay_status', String(nextStatus));

        const fetchOptions = {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString(),
            credentials: 'include',
        };

        let requestTimeout = null;
        if (typeof AbortController !== 'undefined') {
            const controller = new AbortController();
            fetchOptions.signal = controller.signal;
            requestTimeout = window.setTimeout(() => controller.abort(), 15000);
        }

        fetch('../Manage/update_payment_status.php', fetchOptions)
            .then(r => r.json())
            .then(result => {
                if (!result?.success) throw new Error(result?.error || 'ไม่สามารถอัปเดตสถานะได้');
                refreshBillingPayments(ctrId);
                refreshWizardTable();
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(result.message || 'อัปเดตสถานะการชำระเรียบร้อย');
                }
                if (onDone) onDone();
            })
            .catch(err => {
                resetSlipReviewActionButtons();
                const errorMessage = err && err.name === 'AbortError'
                    ? 'การเชื่อมต่อล่าช้า กรุณาลองใหม่อีกครั้ง'
                    : (err.message || 'เกิดข้อผิดพลาด');
                alert(errorMessage);
            })
            .finally(() => {
                if (requestTimeout) {
                    window.clearTimeout(requestTimeout);
                }
            });
    }

    function reviewBillPayment(payId, expId, nextStatus, btnEl) {
        // Legacy direct-call path (used only if called outside openSlipReview)

        // กดยืนยันแล้ว — ดำเนินการ
        if (btnEl) {
            clearTimeout(btnEl._reviewTimer);
            btnEl.disabled         = true;
            btnEl.style.opacity    = '0.6';
            btnEl.textContent      = 'กำลังดำเนินการ...';
            btnEl.style.outline    = '';
        }

        const ctrId = document.getElementById('modal_billing_ctr_id').value;
        const formData = new URLSearchParams();
        formData.append('csrf_token', '');
        formData.append('pay_id', String(payId));
        formData.append('exp_id', String(expId));
        formData.append('pay_status', String(nextStatus));

        fetch('../Manage/update_payment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString(),
            credentials: 'include',
        })
            .then(response => response.json())
            .then(result => {
                if (!result?.success) {
                    throw new Error(result?.error || 'ไม่สามารถอัปเดตสถานะรายการชำระได้');
                }
                // Refresh billing payments in-place + soft refresh table
                const ctrId = document.getElementById('modal_billing_ctr_id').value;
                refreshBillingPayments(ctrId);
                refreshWizardTable();
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast('อัปเดตสถานะการชำระเรียบร้อย');
                }
            })
            .catch((error) => {
                if (btnEl) { btnEl.disabled = false; btnEl.style.opacity = '1'; btnEl.dataset.confirming = 'false'; }
                const errDiv = document.createElement('div');
                errDiv.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;padding:0.75rem 1rem;background:rgba(239,68,68,0.9);color:#fff;border-radius:8px;font-size:0.9rem;';
                errDiv.textContent = error.message || 'ไม่สามารถอัปเดตสถานะรายการชำระได้';
                document.body.appendChild(errDiv);
                setTimeout(() => errDiv.remove(), 4000);
            });
    }

    // Functions สำหรับ Billing Modal
    // ---- Meter-Only Modal ----
    var _moCtrId = 0, _moPrevWater = 0, _moPrevElec = 0;
    var _moMonth = 0, _moYear = 0, _moRateElec = 8;
    var _moWaterBaseUnits  = 10;    // ค่าน้ำเหมาจ่าย - หน่วยฐาน
    var _moWaterBasePrice  = 200;   // ค่าน้ำเหมาจ่าย - ราคาเหมาจ่าย
    var _moWaterExcessRate = 25;    // ค่าน้ำเหมาจ่าย - ค่าส่วนเกิน

    function openMeterOnlyModal(ctrId, tntName, roomNumber, targetYm) {
        _moCtrId = ctrId;
        document.getElementById('moHeaderSub').textContent =
            'ห้อง ' + roomNumber + ' • ' + tntName
            + (targetYm ? ' (' + formatMonthDisplay(targetYm + '-01') + ')' : '');
        document.getElementById('moPrevWater').textContent = '...';
        document.getElementById('moPrevElec').textContent  = '...';
        document.getElementById('moWaterInput').value = '';
        document.getElementById('moElecInput').value  = '';
        document.getElementById('moWaterInput').disabled = false;
        document.getElementById('moElecInput').disabled  = false;
        document.getElementById('moPreview').style.display = 'none';
        document.getElementById('moFirstReadingMsg').style.display = 'none';
        document.getElementById('moMsg').textContent = '';
        const btn = document.getElementById('moSaveBtn');
        btn.style.display = 'inline-block';
        btn.disabled = false;
        btn.textContent = '✓ บันทึกมิเตอร์';

        if (targetYm && /^\d{4}-\d{2}$/.test(targetYm)) {
            const p = targetYm.split('-');
            _moYear  = parseInt(p[0], 10);
            _moMonth = parseInt(p[1], 10);
        } else {
            const n = new Date();
            _moYear  = n.getFullYear();
            _moMonth = n.getMonth() + 1;
        }

        // ปล่อยให้จดมิเตอร์ได้ทันที ไม่ต้องรอถึงเดือนบิล
        _moIsFuture = false;  // always allow meter recording regardless of date

        document.getElementById('meterOnlyModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';

        fetch('../Manage/get_utility_reading.php?ctr_id=' + encodeURIComponent(ctrId) + '&target_month=' + _moMonth + '&target_year=' + _moYear, { credentials: 'include' })
            .then(r => r.text().then(txt => { try { return JSON.parse(txt); } catch(e) { return {error:'Invalid response'}; } }))
            .then(d => {
                if (d.error) return;
                _moPrevWater = d.prev_water || 0;
                _moPrevElec  = d.prev_elec  || 0;
                _moRateElec  = d.rate_elec  || 8;
                _moWaterBaseUnits  = d.water_base_units  || 10;
                _moWaterBasePrice  = d.water_base_price  || 200;
                _moWaterExcessRate = d.water_excess_rate || 25;
                _meterIsFirstReading = d.is_first_reading || false;  // ตั้งค่า first reading flag
                document.getElementById('moPrevWater').textContent = String(_moPrevWater).padStart(7, '0');
                document.getElementById('moPrevElec').textContent  = String(_moPrevElec).padStart(5, '0');
                if (d.saved && d.meter_month == _moMonth && d.meter_year == _moYear && !_moIsFuture && d.curr_water !== null && d.curr_elec !== null) {
                    document.getElementById('moWaterInput').value    = d.curr_water != null ? String(d.curr_water).padStart(7, '0') : '';
                    document.getElementById('moElecInput').value     = d.curr_elec  != null ? String(d.curr_elec).padStart(5, '0')  : '';
                    // Allow editing even after saved - just show the current values
                    btn.style.display = 'inline-block';
                    btn.textContent = 'อัปเดตมิเตอร์';
                    const m = document.getElementById('moMsg');
                    m.style.color = '#4ade80'; m.textContent = '✓ บันทึกแล้ว (สามารถแก้ไขได้)';
                    updateMoPreview();
                    // มิเตอร์บันทึกแล้ว (อาจจะจากเซสชันก่อน) → อัปเดตตารางในพื้นหลัง
                    refreshWizardTable();
                } else if (!d.saved && d.meter_month == _moMonth && d.meter_year == _moYear && (d.water_saved || d.elec_saved)) {
                    // Partial save: one meter recorded, the other not
                    if (d.water_saved && d.curr_water !== null) {
                        document.getElementById('moWaterInput').value = String(d.curr_water).padStart(7, '0');
                        document.getElementById('moWaterInput').disabled = true;
                        document.getElementById('moWaterInput').style.opacity = '0.6';
                    }
                    if (d.elec_saved && d.curr_elec !== null) {
                        document.getElementById('moElecInput').value = String(d.curr_elec).padStart(5, '0');
                        document.getElementById('moElecInput').disabled = true;
                        document.getElementById('moElecInput').style.opacity = '0.6';
                    }
                    btn.style.display = 'inline-block';
                    btn.textContent = '✓ บันทึกมิเตอร์';
                    const m = document.getElementById('moMsg');
                    m.style.color = '#fbbf24'; m.textContent = '⚠ บันทึกบางส่วนแล้ว';
                    updateMoPreview();
                }
            })
            .catch(() => {
                document.getElementById('moPrevWater').textContent = '-';
                document.getElementById('moPrevElec').textContent  = '-';
            });
    }

    function closeMeterOnlyModal() {
        document.getElementById('meterOnlyModal').classList.remove('active');
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    }

    function updateMoPreview() {
        const wv  = document.getElementById('moWaterInput').value.trim();
        const ev  = document.getElementById('moElecInput').value.trim();
        const pre = document.getElementById('moPreview');
        const msg = document.getElementById('moFirstReadingMsg');
        if (wv === '' && ev === '') { pre.style.display = 'none'; msg.style.display = 'none'; return; }
        pre.style.display = 'flex';
        
        // Show first reading message if applicable
        if (_meterIsFirstReading) {
            msg.style.display = 'block';
        } else {
            msg.style.display = 'none';
        }
        
        const parts = [];
        if (wv !== '') {
            const used = parseInt(wv, 10) - _moPrevWater;
            // ครั้งแรกไม่เสียตัง (cost = 0)
            const cost = _meterIsFirstReading ? 0 : (used <= 0 ? 0 : (used <= _moWaterBaseUnits ? _moWaterBasePrice : _moWaterBasePrice + (used - _moWaterBaseUnits) * _moWaterExcessRate));
            parts.push('💧 ใช้ <b style="color:#60a5fa">' + Math.max(0, used) + '</b> หน่วย → <b style="color:#4ade80">฿' + cost.toLocaleString() + '</b>');
        }
        if (ev !== '') {
            const used = parseInt(ev, 10) - _moPrevElec;
            // ครั้งแรกไม่เสียตัง (cost = 0)
            const cost = _meterIsFirstReading ? 0 : (Math.max(0, used) * _moRateElec);
            parts.push('⚡ ใช้ <b style="color:#fbbf24">' + Math.max(0, used) + '</b> หน่วย → <b style="color:#4ade80">฿' + cost.toLocaleString() + '</b>');
        }
        pre.innerHTML = parts.join('<span style="color:rgba(255,255,255,0.2);margin:0 0.35rem">|</span>');
    }

    function saveMeterOnly() {
        const wv  = document.getElementById('moWaterInput').value.trim();
        const ev  = document.getElementById('moElecInput').value.trim();
        const btn = document.getElementById('moSaveBtn');
        const msg = document.getElementById('moMsg');
        
        // Validate minimum values
        if (wv !== '' && parseInt(wv, 10) < _moPrevWater) {
            msg.style.color = '#f87171';
            msg.textContent = '❌ เลขมิเตอร์น้ำต้องไม่น้อยกว่าค่าก่อนหน้า (' + _moPrevWater + ')';
            return;
        }
        if (ev !== '' && parseInt(ev, 10) < _moPrevElec) {
            msg.style.color = '#f87171';
            msg.textContent = '❌ เลขมิเตอร์ไฟต้องไม่น้อยกว่าค่าก่อนหน้า (' + _moPrevElec + ')';
            return;
        }
        
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        msg.textContent = '';
        const fd = new FormData();
        fd.append('csrf_token',  '');
        fd.append('ctr_id',      _moCtrId);
        fd.append('water_new',   wv);
        fd.append('elec_new',    ev);
        fd.append('meter_month', _moMonth);
        fd.append('meter_year',  _moYear);
        fetch('../Manage/save_utility_ajax.php', { method: 'POST', body: fd, credentials: 'include' })
            .then(r => {
                if (!r.ok) {
                    return r.text().then(txt => {
                        try { return JSON.parse(txt); } catch(e) { return {success:false, error:'เซิร์ฟเวอร์ตอบกลับ HTTP ' + r.status}; }
                    });
                }
                return r.text().then(txt => {
                    try { return JSON.parse(txt); } catch(e) { return {success:false, error:'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง'}; }
                });
            })
            .then(d => {
                if (d.success) {
                    msg.style.color = '#4ade80';
                    msg.textContent = '✓ บันทึกสำเร็จ';
                    btn.style.display = 'none';
                    document.getElementById('moWaterInput').disabled = true;
                    document.getElementById('moElecInput').disabled  = true;
                    setTimeout(() => {
                        closeMeterOnlyModal();
                        safeShowSuccessToast('บันทึกมิเตอร์เรียบร้อยแล้ว');
                        refreshWizardTable();
                    }, 700);
                } else {
                    msg.style.color = '#fca5a5';
                    msg.textContent = d.error || 'เกิดข้อผิดพลาด';
                    btn.disabled = false;
                    btn.textContent = 'บันทึกมิเตอร์';
                }
            })
            .catch(err => {
                msg.style.color = '#fca5a5';
                msg.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message;
                btn.disabled = false;
                btn.textContent = 'บันทึกมิเตอร์';
            });
    }

    document.getElementById('meterOnlyModal').addEventListener('click', function(e) {
        if (e.target === this) closeMeterOnlyModal();
    });
    // ---- end Meter-Only Modal ----

    // ---- Meter reading helpers ----
    var _meterCtrId = 0;
    var _meterPrevWater = 0;
    var _meterPrevElec  = 0;
    var _meterMonth = 0;
    var _meterYear  = 0;
    var _meterRateWater = 18;
    var _meterRateElec  = 8;
    var _meterWaterBaseUnits  = 10;    // ค่าน้ำเหมาจ่าย - หน่วยฐาน
    var _meterWaterBasePrice  = 200;   // ค่าน้ำเหมาจ่าย - ราคาเหมาจ่าย
    var _meterWaterExcessRate = 25;    // ค่าน้ำเหมาจ่าย - ค่าส่วนเกิน
    var _meterIsFirstReading  = false;  // แฉลก: เป็นการจดมิเตอร์ครั้งแรก

    function loadMeterReading(ctrId) {
        _meterCtrId = ctrId;
        const badge = document.getElementById('meterSavedBadge');
        const btn   = document.getElementById('saveMeterBtn');
        const msgDiv = document.getElementById('meterMsg');
        badge.style.display = 'none';
        btn.style.display = 'inline-block';
        btn.disabled = false;
        msgDiv.textContent = '';
        document.getElementById('meterWaterInput').value = '';
        document.getElementById('meterElecInput').value  = '';
        document.getElementById('meterWaterInput').disabled = false;
        document.getElementById('meterElecInput').disabled  = false;
        document.getElementById('meterPreview').style.display = 'none';
        document.getElementById('prevWaterDisplay').textContent = '...';
        document.getElementById('prevElecDisplay').textContent  = '...';

        fetch(`../Manage/get_utility_reading.php?ctr_id=${encodeURIComponent(ctrId)}`, { credentials: 'include' })
            .then(r => r.text().then(txt => { try { return JSON.parse(txt); } catch(e) { return {error:'Invalid response'}; } }))
            .then(d => {
                if (d.error) return;
                _meterPrevWater  = d.prev_water  || 0;
                _meterPrevElec   = d.prev_elec   || 0;
                _meterMonth      = d.meter_month || (new Date().getMonth() + 1);
                _meterYear       = d.meter_year  || (new Date().getFullYear());
                _meterRateWater  = d.rate_water  || 18;
                _meterRateElec   = d.rate_elec   || 8;
                _meterWaterBaseUnits  = d.water_base_units  || 10;
                _meterWaterBasePrice  = d.water_base_price  || 200;
                _meterWaterExcessRate = d.water_excess_rate || 25;
                _meterIsFirstReading  = d.is_first_reading || false;

                document.getElementById('prevWaterDisplay').textContent = String(_meterPrevWater).padStart(7, '0');
                document.getElementById('prevElecDisplay').textContent  = String(_meterPrevElec).padStart(5, '0');

                if (d.saved) {
                    // already saved this month — show saved badge + allow edit and re-save
                    badge.style.display = 'inline-block';
                    btn.style.display   = 'inline-block';
                    btn.textContent     = 'อัปเดตมิเตอร์';
                    document.getElementById('meterWaterInput').value    = (d.curr_water != null && d.curr_water > 0) ? String(d.curr_water).padStart(7, '0') : '';
                    document.getElementById('meterElecInput').value     = (d.curr_elec  != null && d.curr_elec  > 0) ? String(d.curr_elec).padStart(5, '0')  : '';
                    document.getElementById('meterWaterInput').disabled = false;
                    document.getElementById('meterElecInput').disabled  = false;
                    updateMeterPreview();
                    // โหลดและแสดงรายการบิล เฉพาะเมื่อจดมิเตอร์แล้วเท่านั้น
                    document.getElementById('billSectionsWrapper').style.display = '';
                    document.getElementById('meterNoticeBlock').style.display = 'none';
                    refreshBillingPayments(_meterCtrId);
                } else {
                    // ยังไม่จดมิเตอร์ — ซ่อนบิล แสดงแจ้งเตือน ซ่อนแบดจ์
                    badge.style.display = 'none';  // เพราะยังไม่ได้จดมิเตอร์
                    document.getElementById('billSectionsWrapper').style.display = 'none';
                    
                    // แสดงข้อความแตกต่างกันสำหรับการจดมิเตอร์ครั้งแรก
                    if (_meterIsFirstReading) {
                        const noticeDiv = document.getElementById('meterNoticeBlock');
                        noticeDiv.innerHTML = '<span class="billing-inline-icon" style="color:#4ade80;"><svg class="billing-svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2A10 10 0 1 0 12 22A10 10 0 0 0 12 2zm-1 15h2v2h-2v-2zm0-8h2v6h-2v-6z" fill="currentColor"></path></svg><span>จดมิเตอร์ครั้งแรก — ไม่มีค่าใช้จ่าย</span></span>';
                        noticeDiv.style.background = 'rgba(52, 211, 153, 0.08)';
                        noticeDiv.style.borderColor = 'rgba(52, 211, 153, 0.25)';
                        noticeDiv.style.color = '#4ade80';
                    } else {
                        const noticeDiv = document.getElementById('meterNoticeBlock');
                        noticeDiv.innerHTML = '<span class="billing-inline-icon" style="color:#ef4444;"><svg class="billing-svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="12" y1="16" x2="12" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg><span>ยังไม่ได้จดมิเตอร์ — ไม่ได้มีข้อมูล</span></span>';
                        noticeDiv.style.background = 'rgba(239, 68, 68, 0.08)';
                        noticeDiv.style.borderColor = 'rgba(239, 68, 68, 0.25)';
                        noticeDiv.style.color = '#ef4444';
                    }
                    document.getElementById('meterNoticeBlock').style.display = '';
                }
            })
            .catch(() => {
                document.getElementById('prevWaterDisplay').textContent = '-';
                document.getElementById('prevElecDisplay').textContent  = '-';
                // กรณีโหลดไม่ได้ — แสดงแจ้งเตือนมิเตอร์
                document.getElementById('billSectionsWrapper').style.display = 'none';
                document.getElementById('meterNoticeBlock').style.display = '';
            });
    }

    function updateMeterPreview() {
        const waterVal = document.getElementById('meterWaterInput').value.trim();
        const elecVal  = document.getElementById('meterElecInput').value.trim();
        const preview  = document.getElementById('meterPreview');
        if (waterVal === '' && elecVal === '') { preview.style.display = 'none'; return; }
        preview.style.display = 'flex';
        let parts = [];
        if (waterVal !== '') {
            const used = parseInt(waterVal, 10) - _meterPrevWater;
            // ถ้าเป็นการจดมิเตอร์ครั้งแรก ไม่คิดค่าใช้จ่าย
            let cost = 0;
            if (!_meterIsFirstReading) {
                // ใช้ค่าน้ำเหมาจ่าย (tiered pricing) เฉพาะครั้งที่ 2 เป็นต้นไป
                cost = used <= 0 ? 0 : (used <= _meterWaterBaseUnits ? _meterWaterBasePrice : _meterWaterBasePrice + (used - _meterWaterBaseUnits) * _meterWaterExcessRate);
            }
            parts.push(`<span class="billing-inline-icon" style="color:#60a5fa;"><svg class="billing-svg-icon billing-svg-water" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C9 7 6 9.8 6 14a6 6 0 0 0 12 0c0-4.2-3-7-6-11z"></path></svg><span>ใช้ <b style="color:#60a5fa">${Math.max(0,used)}</b> หน่วย → <b style="color:#4ade80">฿${cost.toLocaleString()}${_meterIsFirstReading ? ' (ครั้งแรก)' : ''}</b></span></span>`);
        }
        if (elecVal !== '') {
            const used = parseInt(elecVal, 10) - _meterPrevElec;
            // ถ้าเป็นการจดมิเตอร์ครั้งแรก ไม่คิดค่าใช้จ่าย
            const cost = _meterIsFirstReading ? 0 : (Math.max(0, used) * _meterRateElec);
            parts.push(`<span class="billing-inline-icon" style="color:#fbbf24;"><svg class="billing-svg-icon billing-svg-elec" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"></path></svg><span>ใช้ <b style="color:#fbbf24">${Math.max(0,used)}</b> หน่วย → <b style="color:#4ade80">฿${cost.toLocaleString()}${_meterIsFirstReading ? ' (ครั้งแรก)' : ''}</b></span></span>`);
        }
        preview.innerHTML = parts.join('<span style="color:rgba(255,255,255,0.2);margin:0 0.25rem">|</span>');
    }

    function saveMeterReading() {
        const waterVal = document.getElementById('meterWaterInput').value.trim();
        const elecVal  = document.getElementById('meterElecInput').value.trim();
        const btn      = document.getElementById('saveMeterBtn');
        const msg      = document.getElementById('meterMsg');
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
        msg.textContent = '';

        const fd = new FormData();
        fd.append('csrf_token', '');
        fd.append('ctr_id',      _meterCtrId);
        fd.append('water_new',   waterVal);
        fd.append('elec_new',    elecVal);
        fd.append('meter_month', _meterMonth);
        fd.append('meter_year',  _meterYear);

        fetch('../Manage/save_utility_ajax.php', { method: 'POST', body: fd, credentials: 'include' })
            .then(r => {
                return r.text().then(txt => {
                    try { return JSON.parse(txt); } catch(e) { return {success:false, error:'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง (HTTP ' + r.status + ')'}; }
                });
            })
            .then(d => {
                if (d.success) {
                    msg.style.color = '#4ade80';
                    msg.textContent = '✓ บันทึกสำเร็จ';
                    btn.style.display = 'inline-block';
                    btn.textContent = 'อัปเดตมิเตอร์';
                    document.getElementById('meterSavedBadge').style.display = 'inline-block';
                    document.getElementById('meterWaterInput').disabled = false;
                    document.getElementById('meterElecInput').disabled  = false;
                    document.getElementById('billSectionsWrapper').style.display = '';
                    document.getElementById('meterNoticeBlock').style.display = 'none';
                    refreshBillingPayments(_meterCtrId);
                    refreshWizardTable();
                    safeShowSuccessToast('อัปเดตมิเตอร์เรียบร้อยแล้ว');
                } else {
                    msg.style.color = '#fca5a5';
                    msg.textContent = d.error || 'เกิดข้อผิดพลาด';
                    btn.disabled = false;
                    btn.textContent = 'บันทึกมิเตอร์';
                }
            })
            .catch(err => {
                msg.style.color = '#fca5a5';
                msg.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message;
                btn.disabled = false;
                btn.textContent = 'บันทึกมิเตอร์';
            });
    }
    // ---- end meter helpers ----

    function openBillingModal(ctrId, tntId, tntName, roomNumber, roomType, roomPrice) {
        // เก็บ ctrId สำหรับ meter update later
        _meterCtrId = ctrId;
        
        // ตั้งค่า hidden fields
        document.getElementById('modal_billing_ctr_id').value = ctrId;
        document.getElementById('modal_billing_tnt_id').value = tntId;
        document.getElementById('modal_billing_room_price').value = roomPrice;
        
        // แสดงข้อมูลผู้เช่า
        document.getElementById('billingBarTenant').textContent = tntName;
        document.getElementById('billingBarRoom').textContent = `ห้อง ${roomNumber} (${roomType}) • ฿${Number(roomPrice).toLocaleString()}/เดือน`;
        document.getElementById('billingModalSub').textContent = `ห้อง ${roomNumber} — ฿${Number(roomPrice).toLocaleString()}/เดือน`;

        // แสดงเดือนปัจจุบัน (เดือนที่จดมิเตอร์)
        const now = new Date();
        const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        document.getElementById('nextMonthDisplay').textContent = 
            `${monthNames[now.getMonth()]} ${now.getFullYear() + 543}`;

        // รีเซ็ต bill sections — ซ่อนไว้ก่อนจนกว่าจะรู้ว่าจดมิเตอร์แล้วหรือไม่
        document.getElementById('billSectionsWrapper').style.display = 'none';
        document.getElementById('meterNoticeBlock').style.display = 'none';
        
        // Reset meter section visibility when modal opens
        const meterBody = document.getElementById('meterBody');
        if (meterBody) meterBody.style.display = '';
        
        document.getElementById('firstBillPaymentsSection').innerHTML = '';
        document.getElementById('latestBillPaymentsSection').innerHTML = '';

        // โหลดอัตราค่าน้ำ-ไฟจาก DB
        fetch('../Manage/get_latest_rate.php', { credentials: 'include' })
            .then(response => {
                // even if response.ok, server may signal failure via JSON
                return response.json();
            })
            .then(data => {
                if (data.success === false || data.error) {
                    throw new Error(data.message || 'ไม่สามารถดึงอัตราล่าสุดได้');
                }
                const waterRate = data.rate_water || 0;
                const elecRate = data.rate_elec || 0;
                
                document.getElementById('modal_billing_rate_water').value = waterRate;
                document.getElementById('modal_billing_rate_elec').value = elecRate;
                document.getElementById('waterRateDisplay').textContent = `฿${Number(waterRate).toFixed(2)}/หน่วย`;
                document.getElementById('elecRateDisplay').textContent = `฿${Number(elecRate).toFixed(2)}/หน่วย`;
            })
            .catch((err) => {
                console.error('rate fetch error', err);
                // ใช้ค่า default ถ้าโหลดไม่ได้
                document.getElementById('modal_billing_rate_water').value = 18;
                document.getElementById('modal_billing_rate_elec').value = 8;
                document.getElementById('waterRateDisplay').textContent = '฿18.00/หน่วย';
                document.getElementById('elecRateDisplay').textContent = '฿8.00/หน่วย';
            });

        loadMeterReading(ctrId);

        document.getElementById('billingModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closeBillingModal() {
        document.getElementById('billingModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        document.getElementById('billingForm').reset();
    }

    function closeWizardIntro() {
        const introBox = document.getElementById('wizardIntroBox');
        if (introBox) {
            introBox.style.display = 'none';
            localStorage.setItem('wizardIntroHidden', '1');
        }
    }

    // Functions สำหรับ Payment Modal (Step 2)
    function openPaymentModal(bpId, bkgId, tntId, tntName, roomNumber, bpAmount, bpProof, readOnly = false) {
        document.getElementById('modal_payment_bp_id').value = bpId;
        document.getElementById('modal_payment_bkg_id').value = bkgId;
        document.getElementById('modal_payment_tnt_id').value = tntId;

        const paymentSubmitBtn = document.getElementById('paymentSubmitBtn');
        const paymentCloseBtn = document.getElementById('paymentCloseBtn');
        paymentSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
        paymentCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
        
        document.getElementById('paymentInfo').innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">ผู้เช่า</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #0f172a;">${tntName}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">ห้องพัก</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #0f172a;">${roomNumber}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">จำนวนเงินจอง</div>
                    <div style="font-size: 1.1rem; font-weight: 700; color: #059669;">฿${Number(bpAmount).toLocaleString()}</div>
                </div>
            </div>
        `;
        
        const proofContainer = document.getElementById('paymentProofContainer');
        if (bpProof) {
            // Check if bpProof already contains the path or just filename
            // Typically in DB it's stored relative to project root or full path?
            // In wizard_step2.php: href="..."
            // The path in DB seems to be relative to web root or include 'dormitory_management'?
            // Usually DB stores 'Public/Assets/Images/Payments/filename.jpg'.
            // So '/dormitory_management/' + bpProof might be safer if running in subdir.
            
            // If proof is just filename, we might need to prepend path.
            // Let's assume it's the stored path.
            // But we need to make sure the image URL is correct.
            // If stored path starts with 'Public/...', we need '/dormitory_management/Public/...' or just '/Public/...' depending on setup.
            // From wizard_step2.php: href="..." implies absolute path from root.
            
            // Let's try adding /dormitory_management/ if it doesn't start with /
            let proofUrl = bpProof;
            if (!proofUrl.startsWith('/')) {
                proofUrl = '/' + proofUrl;
            }
             // Actually, let's just use what's passed and let the caller handle format or assume relative to domain root if starting with /
             // Or relative to current page if not.
             // bpProof is just filename (e.g., 'payment_1770004240_d69375905c6f0f51.png')
             // Build full path: /dormitory_management/Public/Assets/Images/Payments/filename
             proofUrl = '/dormitory_management/Public/Assets/Images/Payments/' + bpProof;
            
            document.getElementById('paymentProofImg').src = proofUrl;
            document.getElementById('paymentProofLink').href = proofUrl;
            proofContainer.style.display = 'block';
        } else {
            proofContainer.style.display = 'none';
        }
        
        document.getElementById('paymentModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
    }

    async function remindBankAccount(ctrId, roomNumber, tntName, btnElement) {
        let confirmed = false;
        const msg = `ยืนยันการส่ง SMS แจ้งเตือนผู้เช่าห้อง ${roomNumber} (${tntName}) \nให้ระบุบัญชีธนาคารเพื่อรับเงินประกันคืนหรือไม่?`;
        
        if (typeof showConfirmDialog === 'function') {
            confirmed = await showConfirmDialog('ยืนยันส่ง SMS แจ้งเตือน', msg);
        } else {
            confirmed = confirm(msg);
        }
        
        if (!confirmed) return;
        
        btnElement.disabled = true;
        let originalText = btnElement.innerHTML;
        btnElement.innerHTML = '⏳ กำลังส่ง...';
        
        try {
            const formData = new FormData();
            formData.append('ctr_id', ctrId);
            formData.append('room_number', roomNumber);
            formData.append('tnt_name', tntName);
            
            const response = await fetch('../Manage/remind_bank_account.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                if (typeof showToast === 'function') {
                    showToast('สำเร็จ', 'ส่งแจ้งเตือนผ่าน SMS สำเร็จ', 'success');
                } else {
                    alert('ส่งแจ้งเตือนผ่าน SMS สำเร็จ');
                }
                btnElement.innerHTML = '✅ ส่งแล้ว';
                setTimeout(() => {
                    btnElement.innerHTML = '💬 ส่งแจ้งเตือนซ้ำ';
                    btnElement.disabled = false;
                }, 5000);
            } else {
                if (typeof showToast === 'function') {
                    showToast('ไม่สำเร็จ', result.error || 'เกิดข้อผิดพลาดในการส่งแจ้งเตือน', 'error');
                } else {
                    alert(result.error || 'เกิดข้อผิดพลาดในการส่งแจ้งเตือน');
                }
                btnElement.innerHTML = originalText;
                btnElement.disabled = false;
            }
        } catch (error) {
            console.error('Error reminding bank account:', error);
            if (typeof showToast === 'function') {
                showToast('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
            } else {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
            btnElement.innerHTML = originalText;
            btnElement.disabled = false;
        }
    }

    // Function สำหรับยกเลิกการจอง
    async function cancelBooking(bkgId, tntId, tntName) {
        let confirmed = false;
        if (typeof showConfirmDialog === 'function') {
            confirmed = await showConfirmDialog(
                'ยืนยันการยกเลิกการจอง',
                `คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\n\n⚠️ ข้อมูลที่จะถูกลบ:\n• ข้อมูลการจอง\n• ข้อมูลการชำระเงินมัดจำ\n• ข้อมูลสัญญา (ถ้ามี)\n• ข้อมูลค่าใช้จ่าย (ถ้ามี)\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้!`,
                'delete'
            );
        } else {
            confirmed = confirm(`คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\n\nข้อมูลทั้งหมดที่เกี่ยวข้องจะถูกลบ!`);
        }
        
        if (confirmed) {
            await doCancelBooking(bkgId, tntId);
        }
    }

    async function doCancelBooking(bkgId, tntId) {
        try {
            const formData = new FormData();
            formData.append('csrf_token', '');
            formData.append('bkg_id', bkgId);
            formData.append('tnt_id', tntId);

            const response = await fetch('../Manage/cancel_booking.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(data.message || 'ยกเลิกการจองเรียบร้อยแล้ว');
                }
                refreshWizardTable();
            } else {
                if (typeof showErrorToast === 'function') {
                    showErrorToast(data.error || 'เกิดข้อผิดพลาด');
                } else {
                    alert(data.error || 'เกิดข้อผิดพลาด');
                }
            }
        } catch (err) {
            console.error(err);
            if (typeof showErrorToast === 'function') {
                showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            } else {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        }
    }

    // === ยืนยันยกเลิกสัญญา (ctr_status → 1) ===
    async function confirmCancelContract(ctrId, tntName, btn) {
        let confirmed = false;
        const msg = `ยืนยันการยกเลิกสัญญาของ "${tntName}" หรือไม่?\n\nการดำเนินการนี้จะเปลี่ยนสถานะสัญญาเป็น "ยกเลิกแล้ว" และไม่สามารถย้อนกลับได้`;
        if (typeof showConfirmDialog === 'function') {
            confirmed = await showConfirmDialog('ยืนยันยกเลิกสัญญา', msg, 'delete');
        } else {
            confirmed = confirm(msg);
        }
        if (!confirmed) return;

        if (btn) { btn.disabled = true; btn.textContent = 'กำลังดำเนินการ...'; }

        try {
            const fd = new FormData();
            fd.append('ctr_id', ctrId);
            fd.append('ctr_status', '1');

            const res = await fetch('../Manage/update_contract_status.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                if (typeof showSuccessToast === 'function') showSuccessToast('✅ ยกเลิกสัญญาเรียบร้อยแล้ว');
                else alert('ยกเลิกสัญญาเรียบร้อยแล้ว');
                refreshWizardTable();
            } else if (data.need_refund) {
                if (btn) { btn.disabled = false; btn.textContent = '✅ ยืนยันยกเลิกสัญญา'; }
                if (typeof showErrorToast === 'function') showErrorToast('⚠️ ' + data.error);
                else alert(data.error);
            } else {
                if (btn) { btn.disabled = false; btn.textContent = '✅ ยืนยันยกเลิกสัญญา'; }
                if (typeof showErrorToast === 'function') showErrorToast('❌ ' + (data.error || 'ไม่สามารถยกเลิกสัญญาได้'));
                else alert(data.error || 'ไม่สามารถยกเลิกสัญญาได้');
            }
        } catch (err) {
            console.error(err);
            if (btn) { btn.disabled = false; btn.textContent = '✅ ยืนยันยกเลิกสัญญา'; }
            if (typeof showErrorToast === 'function') showErrorToast('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
    }

    // === Modal คืนเงินมัดจำ ===
    (function() {
        // สร้าง modal ครั้งเดียว
        const modalEl = document.createElement('div');
        modalEl.id = '_refundModal';
        modalEl.style.cssText = 'display:none;position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;';
        modalEl.innerHTML = `
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:1.75rem;width:min(460px,92vw);position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
                <button onclick="closeRefundModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1;">&times;</button>
                <h3 id="_rfTitle" style="margin:0 0 1rem;font-size:1.1rem;color:#0f172a;">💰 คืนเงินมัดจำ</h3>
                <div id="_rfBankInfo" style="display:none;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem;">
                    <div style="font-size:0.8rem;font-weight:700;color:#0369a1;margin-bottom:0.5rem;">🏦 บัญชีรับคืนเงินมัดจำที่ระบุไว้</div>
                    <div id="_rfBankName" style="font-size:0.88rem;color:#0c4a6e;margin-bottom:0.2rem;"></div>
                    <div id="_rfBankAccName" style="font-size:0.88rem;color:#0c4a6e;margin-bottom:0.2rem;"></div>
                    <div id="_rfBankAccNum" style="font-size:1rem;font-weight:700;color:#0369a1;letter-spacing:0.04em;"></div>
                </div>
                
                <div id="_rfNoBankMsg" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:0.9rem;text-align:center;">
                    ⚠️ ผู้เช่ายังไม่ได้ระบุข้อมูลบัญชีธนาคารสำหรับรับเงินคืน<br>
                    <span style="font-size:0.8rem;color:#b91c1c;">ไม่สามารถดำเนินการคืนเงินได้ กรุณาติดต่อผู้เช่าเพื่อขอข้อมูล</span>
                </div>

                <div id="_rfActionContainer">
                    <div id="_rfDepositRow" style="display:none;background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:0.65rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:0.85rem;color:#854d0e;font-weight:600;">💎 ยอดเงินมัดจำ</span>
                        <span id="_rfDepositAmt" style="font-size:1.05rem;font-weight:700;color:#b45309;"></span>
                    </div>
                    <div style="margin-bottom:0.9rem;">
                        <label style="font-size:0.85rem;color:#475569;display:block;margin-bottom:0.3rem;">ยอดหักค่าเสียหาย (บาท)</label>
                        <input type="number" id="_rfDeduct" min="0" value="0" style="width:100%;padding:0.55rem 0.75rem;border-radius:10px;border:1px solid #cbd5e1;background:#f8fafc;color:#0f172a;font-size:0.95rem;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:0.9rem;">
                        <label style="font-size:0.85rem;color:#475569;display:block;margin-bottom:0.3rem;">เหตุผลหัก (ถ้ามี)</label>
                        <input type="text" id="_rfReason" placeholder="-" style="width:100%;padding:0.55rem 0.75rem;border-radius:10px;border:1px solid #cbd5e1;background:#f8fafc;color:#0f172a;font-size:0.95rem;box-sizing:border-box;">
                    </div>
                    <div id="_rfSaveArea" style="display:flex;gap:0.6rem;margin-top:1.1rem;">
                        <button id="_rfSaveBtn" onclick="doSaveRefund()" style="flex:1;padding:0.65rem;border-radius:12px;border:none;background:linear-gradient(135deg,#fbbf24,#d97706);color:#0f172a;font-weight:700;font-size:0.95rem;cursor:pointer;">บันทึกข้อมูลคืนเงิน</button>
                        <button onclick="closeRefundModal()" style="padding:0.65rem 1rem;border-radius:12px;border:1px solid #e2e8f0;background:none;color:#64748b;cursor:pointer;">ยกเลิก</button>
                    </div>
                    <div id="_rfConfirmArea" style="display:none;margin-top:1rem;">
                        <!-- เพิ่มส่วนอัพโหลดสลิปตรงนี้ -->
                        <div style="margin-bottom:0.9rem;">
                            <label style="font-size:0.85rem;color:#475569;display:block;margin-bottom:0.3rem;">อัพโหลดหลักฐานการโอนเงิน (สลิป) <span style="color:#ef4444;">*</span></label>
                            <input type="file" id="_rfProofFile" accept="image/*,.pdf" style="width:100%;padding:0.45rem;border-radius:10px;border:1px solid #cbd5e1;background:#f8fafc;font-size:0.9rem;box-sizing:border-box;">
                        </div>
                        
                        <p style="font-size:0.85rem;color:#475569;margin:0 0 0.5rem;">บันทึกข้อมูลแล้ว อัพโหลดสลิปและกด <strong>ยืนยันโอนเงินแล้ว</strong> เมื่อเรียบร้อย</p>
                        <button onclick="doConfirmRefund()" style="width:100%;padding:0.65rem;border-radius:12px;border:none;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-weight:700;font-size:0.95rem;cursor:pointer;text-shadow:0 1px 2px rgba(0,0,0,0.2);">✓ ยืนยันโอนเงินแล้ว</button>
                        <div id="_rfProofProgress" style="display:none; text-align:center; font-size:0.85rem; color:#0369a1; margin-top:0.5rem; font-weight:600;">กำลังอัพโหลดสลิป...</div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modalEl);

        modalEl.addEventListener('click', function(e) { if (e.target === modalEl) closeRefundModal(); });
    })();

    var _rfCtrId = 0;

    function openRefundModal(ctrId, tntName, roomNumber, bankName, bankAccName, bankAccNum, depositAmt) {
        _rfCtrId = ctrId;
        document.getElementById('_rfTitle').textContent = '💰 คืนเงินมัดจำ — ห้อง ' + (roomNumber || '') + ' (' + (tntName || '') + ')';
        document.getElementById('_rfDeduct').value = '0';
        document.getElementById('_rfReason').value = '';
        document.getElementById('_rfSaveArea').style.display = 'flex';
        document.getElementById('_rfConfirmArea').style.display = 'none';
        // Bank info — แสดงเสมอ ถ้าไม่มีข้อมูลแสดง "ไม่ระบุบัญชี"
        const bankInfoEl = document.getElementById('_rfBankInfo');
        const noBankMsgEl = document.getElementById('_rfNoBankMsg');
        const actionContainerEl = document.getElementById('_rfActionContainer');
        
        document.getElementById('_rfBankName').textContent = bankName || '—';
        document.getElementById('_rfBankAccName').textContent = bankAccName || '—';
        document.getElementById('_rfBankAccNum').textContent = bankAccNum || '—';
        bankInfoEl.style.display = 'block';

        if (!bankAccNum || bankAccNum.trim() === '' || bankAccNum.trim() === '-') {
            noBankMsgEl.style.display = 'block';
            actionContainerEl.style.display = 'none';
        } else {
            noBankMsgEl.style.display = 'none';
            actionContainerEl.style.display = 'block';
        }

        // Deposit amount
        const depositRow = document.getElementById('_rfDepositRow');
        if (depositAmt && depositAmt > 0) {
            document.getElementById('_rfDepositAmt').textContent = Number(depositAmt).toLocaleString('th-TH') + ' บาท';
            depositRow.style.display = 'flex';
        } else {
            depositRow.style.display = 'none';
        }
        const modal = document.getElementById('_refundModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeRefundModal() {
        document.getElementById('_refundModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    async function doSaveRefund() {
        const btn = document.getElementById('_rfSaveBtn');
        const orig = btn.textContent;
        btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('ctr_id', _rfCtrId);
        fd.append('deduction_amount', document.getElementById('_rfDeduct').value || '0');
        fd.append('deduction_reason', document.getElementById('_rfReason').value || '');
        try {
            const res = await fetch('../Manage/process_deposit_refund.php', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd });
            const data = await res.json();
            if (data.success) {
                if (typeof showSuccessToast === 'function') showSuccessToast('✅ บันทึกข้อมูลคืนเงินแล้ว');
                document.getElementById('_rfSaveArea').style.display = 'none';
                document.getElementById('_rfConfirmArea').style.display = 'block';
            } else {
                btn.disabled = false; btn.textContent = orig;
                if (typeof showErrorToast === 'function') showErrorToast('❌ ' + (data.error || 'เกิดข้อผิดพลาด'));
                else alert(data.error || 'เกิดข้อผิดพลาด');
            }
        } catch(e) { btn.disabled = false; btn.textContent = orig; if (typeof showErrorToast === 'function') showErrorToast('❌ ข้อผิดพลาดเครือข่าย'); }
    }

    async function doConfirmRefund() {
        const fileInput = document.getElementById('_rfProofFile');
        
        // เช็คก่อนว่าได้เลือกไฟล์หรือยัง
        if (fileInput && fileInput.files.length === 0) {
            if (typeof showErrorToast === 'function') showErrorToast('❌ กรุณาแนบไฟล์สลิปหลักฐานการโอนเงินครับ');
            else alert('กรุณาแนบไฟล์สลิปหลักฐานการโอนเงิน');
            return;
        }

        const ok = typeof showConfirmDialog === 'function'
            ? await showConfirmDialog('ยืนยันการคืนเงิน', 'ยืนยันว่าแนบสลิปและโอนคืนเงินเรียบร้อยแล้ว?', 'success')
            : confirm('ยืนยันว่าแนบสลิปและโอนคืนเงินมัดจำเรียบร้อยแล้ว?');
            
        if (!ok) return;

        document.getElementById('_rfProofProgress').style.display = 'block';

        // 1. อัพโหลดสลิปก่อน
        try {
            const uploadFd = new FormData();
            uploadFd.append('action', 'upload');
            uploadFd.append('ctr_id', _rfCtrId);
            uploadFd.append('refund_proof', fileInput.files[0]);
            
            const upRes = await fetch('../Manage/process_deposit_refund.php', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: uploadFd });
            const upData = await upRes.json();
            if (!upData.success) {
                document.getElementById('_rfProofProgress').style.display = 'none';
                if (typeof showErrorToast === 'function') showErrorToast('❌ ' + (upData.error || 'ไฟล์อัพโหลดล้มเหลว'));
                else alert(upData.error || 'ไฟล์อัพโหลดล้มเหลว');
                return;
            }
        } catch(e) {
            document.getElementById('_rfProofProgress').style.display = 'none';
            if (typeof showErrorToast === 'function') showErrorToast('❌ ข้อผิดพลาดเครือข่ายขณะอัพโหลดสลิป');
            return;
        }

        // 2. ถ้าอัพโหลดผ่าน จึงส่งคำสั่งยืนยัน (Confirm) การคืนเงิน
        const fd = new FormData();
        fd.append('action', 'confirm');
        fd.append('ctr_id', _rfCtrId);
        try {
            const res = await fetch('../Manage/process_deposit_refund.php', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd });
            const data = await res.json();
            
            document.getElementById('_rfProofProgress').style.display = 'none';
            if (data.success) {
                if (typeof showSuccessToast === 'function') showSuccessToast('✅ ยืนยันคืนเงินมัดจำเรียบร้อย');
                closeRefundModal();
                if (typeof refreshWizardTable === 'function') refreshWizardTable();
            } else {
                if (typeof showErrorToast === 'function') showErrorToast('❌ ' + (data.error || 'เกิดข้อผิดพลาด'));
                else alert(data.error || 'เกิดข้อผิดพลาด');
            }
        } catch(e) { 
            document.getElementById('_rfProofProgress').style.display = 'none';
            if (typeof showErrorToast === 'function') showErrorToast('❌ ข้อผิดพลาดเครือข่าย'); 
        }
    }

    // Add tooltip to all buttons in wizard scope (clickable + disabled-like).
    let _wizTooltipObserver = null;

    function normalizeTooltipText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function isDisabledLikeButton(el) {
        if (!el || !(el instanceof HTMLElement)) return false;
        const cursorStyle = (el.style && el.style.cursor ? el.style.cursor : '').toLowerCase();
        return el.disabled === true
            || el.getAttribute('aria-disabled') === 'true'
            || el.classList.contains('btn-disabled')
            || cursorStyle.includes('not-allowed');
    }

    function deriveWizardButtonTooltip(el) {
        const bsTitle = normalizeTooltipText(el.getAttribute('data-bs-title'));
        if (bsTitle) return bsTitle;

        const title = normalizeTooltipText(el.getAttribute('title'));
        if (title) return title;

        const ariaLabel = normalizeTooltipText(el.getAttribute('aria-label'));
        if (ariaLabel) return ariaLabel;

        const dataTooltip = normalizeTooltipText(el.getAttribute('data-tooltip'));
        if (dataTooltip) return dataTooltip;

        const text = normalizeTooltipText(el.textContent);
        if (text && text !== '×' && text !== '✕') return text;

        if (text === '×' || text === '✕' || el.classList.contains('modal-close')) {
            return 'ปิดหน้าต่าง';
        }

        if (isDisabledLikeButton(el)) {
            return 'ปุ่มนี้ยังไม่พร้อมใช้งาน';
        }

        return 'กดเพื่อดำเนินการ';
    }

    function isInWizardTooltipScope(el) {
        if (!el || !(el instanceof HTMLElement)) return false;
        return !!el.closest('.wizard-panel, .modal-overlay, #billingModal, #meterOnlyModal, #slipReviewModal, #_refundModal');
    }

    function applyWizardButtonTooltips(root) {
        const scope = root || document;
        const targets = scope.querySelectorAll('button, a.action-btn, a.wiz-meter-alert, [role="button"]');

        targets.forEach(function(el) {
            if (!isInWizardTooltipScope(el)) return;

            const tooltipText = deriveWizardButtonTooltip(el);
            if (!tooltipText) return;

            // Keep native tooltip and bootstrap tooltip in sync.
            el.setAttribute('title', tooltipText);
            el.setAttribute('data-bs-toggle', 'tooltip');
            if (!el.hasAttribute('data-bs-placement')) {
                el.setAttribute('data-bs-placement', 'top');
            }
            el.setAttribute('data-bs-title', tooltipText);

            if (window.bootstrap && window.bootstrap.Tooltip) {
                const existing = window.bootstrap.Tooltip.getInstance(el);
                if (existing) {
                    existing.dispose();
                }
                new window.bootstrap.Tooltip(el, { container: 'body' });
            }
        });
    }

    function startWizardTooltipObserver() {
        if (_wizTooltipObserver || !window.MutationObserver) return;

        _wizTooltipObserver = new MutationObserver(function(mutations) {
            for (const mutation of mutations) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    applyWizardButtonTooltips(document);
                    break;
                }
            }
        });

        _wizTooltipObserver.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    // === AJAX Wizard Step Submission ===
    function submitWizardStep(formId, closeModalFn) {
        const form = document.getElementById(formId);
        if (!form) return;
        const formData = new FormData(form);
        const actionUrl = form.getAttribute('action');
        
        // Find and disable the submit button
        const modal = form.closest('.modal-overlay') || form.closest('.modal-container');
        let submitBtn = null;
        if (modal) {
            submitBtn = modal.querySelector('.btn-modal-primary') || modal.querySelector('[id$="SubmitBtn"]');
        }
        let origBtnText = '';
        if (submitBtn) {
            origBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'กำลังบันทึก...';
        }

        fetch(actionUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.text().then(txt => {
            try { return JSON.parse(txt); }
            catch(e) { return { success: false, error: 'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง' }; }
        }))
        .then(data => {
            if (data.success) {
                if (typeof closeModalFn === 'function') closeModalFn();
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(data.message || 'บันทึกเรียบร้อย');
                }
                refreshWizardTable();
            } else {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origBtnText; }
                if (typeof showErrorToast === 'function') {
                    showErrorToast(data.error || 'เกิดข้อผิดพลาด');
                } else {
                    alert(data.error || 'เกิดข้อผิดพลาด');
                }
            }
        })
        .catch(err => {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origBtnText; }
            if (typeof showErrorToast === 'function') {
                showErrorToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            } else {
                alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message);
            }
        });
    }

    // Soft-refresh: fetch page and replace table content without full reload
    function refreshWizardTable() {
        const wrapper = document.getElementById('wizardTableWrapper');
        if (!wrapper) { location.reload(); return; }
        // Add cache-busting timestamp to prevent stale browser cache
        const sep = location.href.includes('?') ? '&' : '?';
        const freshUrl = location.href + sep + '_t=' + Date.now();
        fetch(freshUrl, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newWrapper = doc.getElementById('wizardTableWrapper');
            if (newWrapper) {
                wrapper.innerHTML = newWrapper.innerHTML;
                if (typeof wizFilterApply === 'function') wizFilterApply(window._wizCurrentGroup || 0);
                applyWizardButtonTooltips(wrapper);
            } else {
                // Table might have been replaced by empty state
                const newPanelBody = doc.querySelector('.wizard-panel-body');
                if (newPanelBody) {
                    const panelBody = document.querySelector('.wizard-panel-body');
                    if (panelBody) {
                        panelBody.innerHTML = newPanelBody.innerHTML;
                        applyWizardButtonTooltips(panelBody);
                    }
                }
            }
        })
        .catch(() => {
            // Fallback: full reload if soft refresh fails
            location.reload();
        });
    }

    async function softNavigateWizard(targetUrl) {
        const panelBody = document.querySelector('.wizard-panel-body');
        if (!panelBody || !targetUrl) {
            return false;
        }

        const cleanUrl = new URL(targetUrl, window.location.href);
        const fetchUrl = new URL(cleanUrl.toString());
        fetchUrl.searchParams.set('_t', Date.now().toString());

        if (window._wizSoftNavigating === true) {
            return false;
        }
        window._wizSoftNavigating = true;

        try {
            const response = await fetch(fetchUrl.toString(), { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('โหลดข้อมูลตัวกรองไม่สำเร็จ');
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newPanelBody = doc.querySelector('.wizard-panel-body');
            if (!newPanelBody) {
                throw new Error('ไม่พบข้อมูลตารางตัวช่วยผู้เช่า');
            }

            panelBody.innerHTML = newPanelBody.innerHTML;

            const groupParam = parseInt(cleanUrl.searchParams.get('completed') || '0', 10);
            const nextGroup = groupParam === 1 ? 1 : 0;
            window._wizCurrentGroup = nextGroup;

            if (typeof wizFilter === 'function') {
                wizFilter(nextGroup);
            } else if (typeof wizFilterApply === 'function') {
                wizFilterApply(nextGroup);
            }

            if (typeof applyWizardButtonTooltips === 'function') {
                applyWizardButtonTooltips(panelBody);
            }

            history.replaceState(null, '', cleanUrl.toString());
            return true;
        } catch (error) {
            if (typeof showToast === 'function') {
                showToast(error.message || 'ไม่สามารถล้างตัวกรองได้', 'error');
            } else {
                console.error(error);
            }
            return false;
        } finally {
            window._wizSoftNavigating = false;
        }
    }

    // --- Wizard group filter (no page reload) ---
    var _wizCurrentGroup = "";
    window._wizCurrentGroup = _wizCurrentGroup;

    function wizFilterApply(group) {
        var rows = document.querySelectorAll('#wizardTableWrapper tr[data-wiz-group]');
        var visible = 0;
        rows.forEach(function(row) {
            var match = parseInt(row.getAttribute('data-wiz-group'), 10) === group;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        var emptyEl = document.getElementById('wizFilterEmptyState');
        if (emptyEl) emptyEl.style.display = visible === 0 ? '' : 'none';
        
        // update URL without reloading
        var url = new URL(window.location);
        url.searchParams.set('completed', group);
        window.history.pushState({}, '', url);
    }

    function wizFilter(group) {
        _wizCurrentGroup = group;
        var btn0 = document.getElementById('wizBtn0');
        var btn1 = document.getElementById('wizBtn1');
        if (btn0) { btn0.classList.toggle('active', group === 0); }
        if (btn1) { btn1.classList.toggle('active', group === 1); }

        var emptyTitle = document.getElementById('wizFilterEmptyTitle');
        var emptyMsg   = document.getElementById('wizFilterEmptyMsg');
        if (emptyTitle) emptyTitle.textContent = group === 1 ? 'ยังไม่มีผู้เช่าที่ครบ 5 ขั้นตอน' : 'ไม่มีรายการที่รอดำเนินการ';
        if (emptyMsg)   emptyMsg.textContent   = group === 1 ? 'ผู้เช่าที่ผ่านครบ 5 ขั้นตอนแล้วจะแสดงที่นี่' : 'ผู้เช่าทุกคนผ่านครบ 5 ขั้นตอนแล้ว';
        wizFilterApply(group);
    }

    document.addEventListener('DOMContentLoaded', function() {
        wizFilter(_wizCurrentGroup);
        
        // Auto-refresh the wizard table every 30 seconds to reflect status changes in real-time
        var wizRefreshInterval = setInterval(function() {
            if (document.hidden) return; // Don't refresh if tab is not active
            if (typeof refreshWizardTable === 'function') {
                refreshWizardTable();
            }
        }, 30000); // 30 seconds
        
        // Clear interval if page is unloaded
        window.addEventListener('beforeunload', function() {
            clearInterval(wizRefreshInterval);
        });
        applyWizardButtonTooltips(document);
        startWizardTooltipObserver();

        document.addEventListener('click', function(event) {
            const clearLink = event.target.closest('.wiz-filter-clear');
            if (!clearLink) {
                return;
            }

            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            softNavigateWizard(clearLink.href);
        });
    });
    // --- end wizard filter ---

    // Submit checkin form via AJAX (with validation)
    function validateAndSubmitCheckinAjax() {
        const form = document.getElementById('checkinForm');
        const errors = [];
        const checkinDate = document.getElementById('checkin_date_hidden').value;
        if (!checkinDate) errors.push('กรุณาเลือกวันที่เช็คอิน');
        
        const errorContainer = document.getElementById('validationError');
        const errorList = document.getElementById('errorList');
        
        if (errors.length > 0) {
            errorList.innerHTML = errors.map(err => '<li>' + err + '</li>').join('');
            errorContainer.style.display = 'block';
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            errorContainer.style.display = 'none';
            submitWizardStep('checkinForm', closeCheckinModal);
        }
    }



<script src="/dormitory_management/Public/Assets/Javascript/confirm-modal.js">
<script src="/dormitory_management/Public/Assets/Javascript/toast-notification.js">
<script src="/dormitory_management/Public/Assets/Javascript/animate-ui.js">



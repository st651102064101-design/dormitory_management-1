// Provide a queued global helper early so inline onclicks can call it
// before the modal system initializes. Calls will be flushed when ready.
if (!window.animateUIOpen) {
    window.__animateUICallQueue = window.__animateUICallQueue || [];
    window.animateUIOpen = function(config) {
        window.__animateUICallQueue.push(config || {});
    };
}

document.addEventListener('DOMContentLoaded', () => {
    // Accessibility helper: add aria-label/title/placeholder when missing
    (function ensureAccessibleFormControls(){
        const controls = document.querySelectorAll('input, select, textarea');
        controls.forEach(ctrl => {
            const id = ctrl.id;
            let labelText = '';
            if (id) {
                const lbl = document.querySelector(`label[for="${id}"]`);
                labelText = lbl ? lbl.textContent.trim() : '';
            }
            const fallback = labelText || ctrl.getAttribute('name') || 'input';
            if (!ctrl.getAttribute('aria-label')) {
                ctrl.setAttribute('aria-label', fallback);
            }
            if (!ctrl.getAttribute('title')) {
                ctrl.setAttribute('title', fallback);
            }
            if (!ctrl.getAttribute('placeholder') && (ctrl.tagName === 'INPUT' || ctrl.tagName === 'TEXTAREA')) {
                ctrl.setAttribute('placeholder', fallback);
            }
        });
    })();

    const card = document.querySelector('.animate-ui-card');
    const status = document.querySelector('#animate-ui-status');

    if (card) {
        card.addEventListener('mousemove', (event) => {
            const rect = card.getBoundingClientRect();
            const x = (event.clientX - rect.left - rect.width / 2) / rect.width;
            const y = (event.clientY - rect.top - rect.height / 2) / rect.height;
            card.style.transform = `translate(${x * 10}px, ${y * 10}px) scale(1.02)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
    }

    // Let the form submit normally so server-side login can run.
    /* Toast helper: create, show, auto-hide, and optionally redirect */
    function showToast(message = 'Done', duration = 1600, redirect = '') {
        // prevent multiple toasts stacking
        if (document.querySelector('.animate-ui-toast')) return;

        const el = document.createElement('div');
        el.className = 'animate-ui-toast';
        el.textContent = message;
        document.body.appendChild(el);

        // Auto-hide after duration
        const hide = () => {
            el.style.animation = 'toastOut 260ms ease forwards';
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
                if (redirect) window.location.href = redirect;
            }, 260);
        };

        // click to dismiss immediately
        el.addEventListener('click', hide);

        setTimeout(hide, duration);
    }

    // If server set login success flags, show the styled toast and then redirect
    if (window.__loginSuccess) {
        const msg = window.__loginMessage || 'ล็อกอินสำเร็จ';
        const redirect = window.__loginRedirect || 'Index.php';
        // Slight delay so users notice the toast animation
        showToast(msg, 1200, redirect);
    }

    // Sidebar: make summary toggle arrow and remember active link
    document.querySelectorAll('.app-nav summary').forEach((s) => {
        s.addEventListener('click', () => {
            // toggle open handled by details element
            // rotate an optional chevron if present
            const chevron = s.querySelector('.chev');
            if (chevron) chevron.classList.toggle('open');
        });
    });

    // Active link handling
    document.querySelectorAll('.app-nav a').forEach((a) => {
        a.addEventListener('click', (e) => {
            try { console.debug('nav link clicked', a.href); } catch (err) {}
            document.querySelectorAll('.app-nav a').forEach(x => x.classList.remove('active'));
            a.classList.add('active');
            // allow navigation to proceed; no preventDefault
        });
    });

    // Sidebar toggle (open / collapse) with persistence
    const sidebar = document.querySelector('.app-sidebar');
    const toggleButtons = Array.from(document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]'));
    const SIDEBAR_KEY = 'animateui.sidebar.collapsed';

    console.log('Sidebar toggle setup:', { sidebar: !!sidebar, toggleButtons: toggleButtons.length, toggleButtons });

    const isMobile = () => window.innerWidth <= 1024;

    function applySidebarState(collapsed) {
        if (!sidebar) return;
        if (isMobile()) {
            // On mobile never keep collapsed; use mobile-open instead
            sidebar.classList.remove('collapsed');
            toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open').toString()));
            return;
        }
        sidebar.classList.toggle('collapsed', !!collapsed);
        toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', (!collapsed).toString()));
    }

    // Initialize from localStorage (desktop only)
    try {
        const stored = localStorage.getItem(SIDEBAR_KEY);
        applySidebarState(!isMobile() && stored === 'true');
    } catch (e) { /* ignore storage errors */ }

    // Force-clean collapsed state on mobile so it never blocks the slide-in
    if (isMobile()) {
        if (sidebar) {
            sidebar.classList.remove('collapsed');
            console.debug('Mobile detected: removed collapsed class from sidebar');
        }
        try { localStorage.removeItem(SIDEBAR_KEY); } catch (e) {}
    }

    if (toggleButtons.length) {
        toggleButtons.forEach((btn) => {
            console.log('Adding click listener to button:', btn);
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!sidebar) {
                    console.error('Sidebar element not found!');
                    return;
                }

                // Mobile: toggle slide-in
                if (isMobile()) {
                    console.debug('Mobile click detected, classes before:', sidebar.className);
                    sidebar.classList.remove('collapsed');
                    const opened = sidebar.classList.toggle('mobile-open');
                    // also toggle on body for CSS fallback
                    document.body.classList.toggle('sidebar-open', opened);
                    toggleButtons.forEach(tb => tb.setAttribute('aria-expanded', opened.toString()));
                    console.debug('Mobile toggle complete, classes after:', sidebar.className, 'opened:', opened);
                    return;
                }
                
                console.debug('Desktop: sidebar toggle clicked, current classes:', sidebar.className);

                // Desktop: collapsed state
                const isCollapsed = sidebar.classList.toggle('collapsed');
                console.debug('sidebar after toggle:', sidebar.className, 'collapsed:', isCollapsed);
                try { localStorage.setItem(SIDEBAR_KEY, isCollapsed ? 'true' : ''); } catch (e) {}
                toggleButtons.forEach(tb => tb.setAttribute('aria-expanded', (!isCollapsed).toString()));
            });
        });
    } else {
        console.error('Toggle button (#sidebar-toggle) not found!');
    }

    // Keep states in sync on resize
    window.addEventListener('resize', () => {
        if (!sidebar) return;
        if (isMobile()) {
            sidebar.classList.remove('collapsed');
            try { localStorage.removeItem(SIDEBAR_KEY); } catch (e) {}
            applySidebarState(false);
        } else {
            sidebar.classList.remove('mobile-open');
            document.body.classList.remove('sidebar-open');
            const stored = localStorage.getItem(SIDEBAR_KEY) === 'true';
            applySidebarState(stored);
        }
    });

    // Close sidebar when clicking on overlay (mobile)
    document.addEventListener('click', (e) => {
        if (!isMobile() || !sidebar) return;
        const isOverlay = document.body.classList.contains('sidebar-open') && 
                         !sidebar.contains(e.target) && 
                         !toggleButtons.some(btn => btn.contains(e.target));
        if (isOverlay) {
            sidebar.classList.remove('mobile-open');
            document.body.classList.remove('sidebar-open');
            toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
            console.debug('Overlay clicked: sidebar closed');
        }
    });

    // Close sidebar with Escape key (mobile)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isMobile() && sidebar && document.body.classList.contains('sidebar-open')) {
            sidebar.classList.remove('mobile-open');
            document.body.classList.remove('sidebar-open');
            toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
            console.debug('Escape key: sidebar closed');
        }
    });

    // Shared modal/dialog helper
    const modalContainer = document.createElement('div');
    modalContainer.className = 'animate-ui-modal-overlay';
    modalContainer.style.display = 'none';
    modalContainer.innerHTML = `
        <div class="animate-ui-modal">
            <button type="button" class="animate-ui-modal-close" aria-label="Close modal">×</button>
            <h3></h3>
            <form>
                <div class="animate-ui-modal-fields"></div>
                <button type="submit">บันทึก</button>
            </form>
        </div>
    `;
    document.body.appendChild(modalContainer);

    function closeModal() {
        modalContainer.style.display = 'none';
    }

    function openModal(config = {}) {
        // debug: log when modal is requested
        try { console.debug('openModal called', config.title, config.button && config.button.dataset); } catch (e) {}
        const title = config.title || 'จัดการข้อมูล';
        const fields = config.fields || [];
        const isEdit = title.includes('แก้ไข');
        const button = config.button; // pass the button for data
        modalContainer.querySelector('h3').textContent = title;
        const fieldsContainer = modalContainer.querySelector('.animate-ui-modal-fields');
        fieldsContainer.innerHTML = '';
        // Add hidden id if edit
        if (isEdit && button?.dataset.newsId) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'news_id';
            hidden.value = button.dataset.newsId;
            fieldsContainer.appendChild(hidden);
        }
        if (isEdit && button?.dataset.ctrId) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'ctr_id';
            hidden.value = button.dataset.ctrId;
            fieldsContainer.appendChild(hidden);
        }
        if (isEdit && button?.dataset.repairId) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'repair_id';
            hidden.value = button.dataset.repairId;
            fieldsContainer.appendChild(hidden);
        }
        // Map field keys to Thai labels for the modal
        const fieldLabelMap = {
            'bkg_id': 'รหัสการจอง',
            'bkg_date': 'วันที่จอง',
            'bkg_checkin_date': 'วันที่เข้าพัก',
            'bkg_status': 'สถานะการจอง',
            'room_id': 'หมายเลขห้อง',
            'วันที่': 'วันที่',
            'หัวข้อ': 'หัวข้อ',
            'รายละเอียด': 'รายละเอียด',
            'รูปภาพ': 'รูปภาพ',
            'สถานะ': 'สถานะ',
            'หมายเลขห้อง': 'หมายเลขห้อง'
        };
        function getLabelText(f) {
            if (!f) return '';
            return fieldLabelMap[f] || f.replace(/_/g, ' ');
        }

        // helper: convert snake_case or kebab-case to camelCase to match dataset keys
        function toDatasetKey(name) {
            return name.replace(/[-_]+(.)?/g, (m, c) => c ? c.toUpperCase() : '');
        }

        fields.forEach((field) => {
            const label = document.createElement('label');
            // normalize some common Thai/DB field names so we can render appropriate inputs
            const fieldKey = (function(k){
                const t = (k || '').trim();
                if (!t) return t;
                if (t === 'room_id' || t.toLowerCase() === 'room_id') return 'room_id';
                if (t === 'ห้อง' || t === 'หมายเลขห้อง') return 'room_id';
                return t;
            })(field);
            if (field === 'ประเภท' && window.roomTypes) {
                const select = document.createElement('select');
                select.name = field;
                select.required = true;
                select.innerHTML = window.roomTypes.map(rt => `<option value="${rt.type_id}">${rt.type_name}</option>`).join('');
                if (window.roomTypes.length > 0) {
                    select.value = window.roomTypes[0].type_id;
                }
                if (isEdit && button?.dataset.typeId) {
                    select.value = button.dataset.typeId;
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(select);
            } else if (field === 'รูปภาพ') {
                const input = document.createElement('input');
                input.type = 'file';
                input.name = field;
                input.accept = 'image/*';
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(input);
                if (isEdit && button?.dataset.roomImage) {
                    const preview = document.createElement('img');
                    preview.src = '../Assets/Images/Rooms/' + button.dataset.roomImage;
                    preview.style.maxWidth = '100px';
                    preview.style.maxHeight = '100px';
                    preview.style.marginTop = '0.5rem';
                    label.appendChild(preview);
                }
            } else if (field === 'สถานะ' && title.includes('ห้องพัก')) {
                const select = document.createElement('select');
                select.name = field;
                select.innerHTML = '<option value="0">ว่าง</option><option value="1">ไม่ว่าง</option>';
                if (isEdit && button?.dataset.roomStatus) {
                    select.value = button.dataset.roomStatus;
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(select);
            } else if (typeof field === 'string' && /^bkg[_-]?status$/i.test(field)) {
                // Booking status: 1=จองแล้ว, 0=ยกเลิก, 2=เข้าพักแล้ว
                const select = document.createElement('select');
                select.name = field;
                select.innerHTML = '<option value="1">จองแล้ว</option><option value="0">ยกเลิก</option><option value="2">เข้าพักแล้ว</option>';
                if (isEdit && button) {
                    const key = toDatasetKey(field);
                    if (button.dataset && button.dataset[key] !== undefined) select.value = button.dataset[key];
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(select);
            } else if (typeof field === 'string' && /status/i.test(field)) {
                // Fallback for other status-like fields: keep room availability (0=ว่าง,1=ไม่ว่าง)
                const select = document.createElement('select');
                select.name = field;
                select.innerHTML = '<option value="0">ว่าง</option><option value="1">ไม่ว่าง</option>';
                if (isEdit && button) {
                    const key = toDatasetKey(field);
                    if (button.dataset && button.dataset[key] !== undefined) select.value = button.dataset[key];
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(select);
            } else if (typeof field === 'string' && field.toLowerCase().includes('date')) {
                // treat any field name containing "date" as a date input (e.g. bkg_date, bkg_checkin_date)
                const input = document.createElement('input');
                input.type = 'date';
                input.name = field;
                if (isEdit && button?.dataset[field.replace(/-/g,'')]) {
                    input.value = button.dataset[field.replace(/-/g,'')];
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(input);
            } else if ((fieldKey === 'room_id' || (typeof field === 'string' && (field.toLowerCase().includes('room') || field.includes('ห้อง') || field.includes('หมายเลขห้อง'))))) {
                const select = document.createElement('select');
                select.name = field;
                // Use available room list; if none available, create an empty select
                const roomsArray = Array.isArray(window.rooms) ? window.rooms : [];
                if (roomsArray.length === 0) {
                    select.innerHTML = '<option value="">(ไม่มีหมายเลขห้อง)</option>';
                } else {
                    const options = roomsArray.map(r => {
                        // robustly pick id and number fields from different schemas
                        const roomId = r.room_id ?? r.id ?? r.roomId ?? r.roomid ?? r.room_id;
                        const roomNumber = r.room_number ?? r.room_no ?? r.number ?? r.room ?? r.room_label ?? r.room_name ?? r.name ?? '';
                        const text = String(roomNumber || roomId || '').trim();
                        return `<option value="${roomId}">${text}</option>`;
                    }).join('');
                    select.innerHTML = options;
                }
                // If editing, try to select the current room by dataset key (roomId or room_id)
                if (isEdit && button) {
                    const key = toDatasetKey(field);
                    const dsVal = button.dataset ? (button.dataset[key] || button.dataset.roomId || button.dataset.room_id || button.dataset.roomid) : undefined;
                    if (dsVal) {
                        // If the current booked room was filtered out from window.rooms, add it so edit modal can show it
                        if (!select.querySelector(`option[value="${dsVal}"]`)) {
                            const text = button.dataset.roomNumber || button.dataset.room_number || dsVal;
                            const opt = document.createElement('option');
                            opt.value = dsVal;
                            opt.textContent = text;
                            select.appendChild(opt);
                        }
                        select.value = dsVal;
                    }
                }
                select.required = true;
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(select);
            } else if (field === 'สถานะ' && (title.includes('สัญญา') || title.includes('ซ่อม'))) {
                const select = document.createElement('select');
                select.name = field;
                select.innerHTML = '<option value="0">รอ</option><option value="1">กำลังดำเนินการ</option><option value="2">เสร็จสิ้น</option>';
                if (isEdit && (button?.dataset.ctrStatus || button?.dataset.repairStatus)) {
                    select.value = button.dataset.ctrStatus || button.dataset.repairStatus;
                }
                label.innerHTML = `<span>${field}</span>`;
                label.appendChild(select);
            } else if (field === 'รายละเอียด') {
                const textarea = document.createElement('textarea');
                textarea.name = field;
                textarea.placeholder = field;
                textarea.rows = 4;
                if (isEdit && (button?.dataset.newsDetails || button?.dataset.repairDesc)) {
                    textarea.value = button.dataset.newsDetails || button.dataset.repairDesc;
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(textarea);
            } else {
                const inputType = field.includes('วันที่') ? 'date' : 'text';
                const input = document.createElement('input');
                input.type = inputType;
                input.name = field;
                input.placeholder = field;
                if (field === 'หมายเลขห้อง' && window.nextRoom && !isEdit) {
                    input.value = window.nextRoom;
                    input.required = true;
                }
                if (isEdit && field === 'หมายเลขห้อง' && button?.dataset.roomNumber) {
                    input.value = button.dataset.roomNumber;
                    input.readOnly = true;
                }
                if (isEdit && field === 'วันที่' && (button?.dataset.newsDate || button?.dataset.repairDate)) {
                    input.value = button.dataset.newsDate || button.dataset.repairDate;
                }
                if (isEdit && field === 'หัวข้อ' && button?.dataset.newsTitle) {
                    input.value = button.dataset.newsTitle;
                }
                label.innerHTML = `<span>${getLabelText(field)}</span>`;
                label.appendChild(input);
            }
            fieldsContainer.appendChild(label);
        });
        if (!fields.length) {
            const note = document.createElement('p');
            note.style.color = 'rgba(255,255,255,0.6)';
            note.style.margin = '0';
            note.textContent = 'ฟิลด์เพิ่มเติมจะถูกกำหนดในภายหลัง';
            fieldsContainer.appendChild(note);
        }
        modalContainer.style.display = 'flex';
        modalContainer.querySelector('form').onsubmit = async (event) => {
            event.preventDefault();
            if (title.includes('เพิ่ม ห้องพัก')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/add_room.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message, 2000);
                        closeModal();
                        setTimeout(() => { window.location.href = 'manage_rooms.php'; }, 500);
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else if (title.includes('เพิ่ม') && title.includes('การจอง')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/add_booking.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message || 'เพิ่มการจองเรียบร้อย', 2000);
                        closeModal();
                        setTimeout(() => { window.location.href = 'manage_rooms.php'; }, 500);
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาดในการเพิ่มการจอง', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else if (title.includes('เพิ่ม') && /ข่าว/i.test(title)) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/add_news.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message || 'เพิ่มข่าวเรียบร้อย', 1400);
                        closeModal();
                        // If we're on the dashboard with a small news report, refresh that panel instead of redirecting
                        const newsPanel = document.getElementById('report-news-content');
                        if (newsPanel) {
                            try {
                                const res = await fetch('manage_news.php');
                                const html = await res.text();
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const table = doc.querySelector('.report-table');
                                newsPanel.innerHTML = table ? table.outerHTML : '<p>ไม่พบข้อมูล</p>';
                            } catch (e) {
                                console.error('Failed to refresh news panel', e);
                                setTimeout(() => { window.location.href = 'manage_news.php'; }, 400);
                            }
                            return;
                        }

                        // If currently on manage_news.php and table exists, prepend the new row to avoid full reload
                        const newsTable = document.getElementById('table-news');
                        if (newsTable) {
                            try {
                                const tbody = newsTable.tBodies[0] || newsTable.querySelector('tbody');
                                if (tbody) {
                                    const tr = document.createElement('tr');
                                    const dateCell = document.createElement('td'); dateCell.textContent = (formData.get('วันที่') || '');
                                    const titleCell = document.createElement('td'); titleCell.textContent = (formData.get('หัวข้อ') || '');
                                    const detailsCell = document.createElement('td'); detailsCell.textContent = (formData.get('รายละเอียด') || '');
                                    const byCell = document.createElement('td'); byCell.textContent = (result.by || '');
                                    const actionCell = document.createElement('td'); actionCell.className = 'crud-column';
                                    const editBtn = document.createElement('button');
                                    editBtn.type = 'button'; editBtn.className = 'animate-ui-action-btn edit crud-action';
                                    editBtn.dataset.entity = `ข่าว ${result.id}`;
                                    editBtn.dataset.fields = 'วันที่,หัวข้อ,รายละเอียด';
                                    editBtn.dataset.newsId = result.id;
                                    editBtn.dataset.newsDate = formData.get('วันที่') || '';
                                    editBtn.dataset.newsTitle = formData.get('หัวข้อ') || '';
                                    editBtn.dataset.newsDetails = formData.get('รายละเอียด') || '';
                                    editBtn.textContent = 'แก้ไข';
                                    const delBtn = document.createElement('button');
                                    delBtn.type = 'button'; delBtn.className = 'animate-ui-action-btn delete crud-action';
                                    delBtn.dataset.entity = `ข่าว ${result.id}`;
                                    delBtn.dataset.itemId = result.id;
                                    delBtn.dataset.deleteEndpoint = '../Manage/delete_news.php';
                                    delBtn.textContent = 'ลบ';
                                    actionCell.appendChild(editBtn); actionCell.appendChild(delBtn);
                                    tr.appendChild(dateCell); tr.appendChild(titleCell); tr.appendChild(detailsCell); tr.appendChild(byCell); tr.appendChild(actionCell);
                                    tbody.insertBefore(tr, tbody.firstChild);
                                }
                            } catch (e) {
                                console.error('Failed to insert new news row', e);
                                setTimeout(() => { window.location.href = 'manage_news.php'; }, 400);
                            }
                            return;
                        }

                        // Default: navigate to full list page
                        setTimeout(() => { window.location.href = 'manage_news.php'; }, 400);
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาดในการเพิ่มข่าว', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else if (title.includes('แก้ไข ห้องพัก')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/edit_room.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message, 2000);
                        closeModal();
                        // Update the row without reload
                        const row = button.closest('tr');
                        const statusSelect = event.target.querySelector('select[name="สถานะ"]');
                        const statusText = statusSelect.options[statusSelect.selectedIndex].text;
                        row.cells[1].textContent = statusText;
                        const typeSelect = event.target.querySelector('select[name="ประเภท"]');
                        const typeId = typeSelect.value;
                        const type = window.roomTypes.find(t => t.type_id == typeId);
                        row.cells[2].textContent = type.type_name;
                        row.cells[3].textContent = new Intl.NumberFormat().format(type.type_price);
                        // If image changed, reload to update
                        const imageInput = event.target.querySelector('input[name="รูปภาพ"]');
                        if (imageInput.files.length > 0) {
                            setTimeout(() => { location.reload(); }, 500);
                        }
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else if (title.includes('แก้ไข ข่าว')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/edit_news.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message, 2000);
                        closeModal();
                        // Update the row without reload
                        const row = button.closest('tr');
                        const dateInput = event.target.querySelector('input[name="วันที่"]');
                        row.cells[0].textContent = dateInput.value;
                        const titleInput = event.target.querySelector('input[name="หัวข้อ"]');
                        row.cells[1].textContent = titleInput.value;
                        const detailsTextarea = event.target.querySelector('textarea[name="รายละเอียด"]');
                        row.cells[2].textContent = detailsTextarea.value;
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else if (title.includes('แก้ไข สัญญา')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/edit_contract.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message, 2000);
                        closeModal();
                        // Update the row without reload
                        const row = button.closest('tr');
                        const tntSelect = event.target.querySelector('select[name="ผู้เช่า"]');
                        const tntText = tntSelect.options[tntSelect.selectedIndex].text;
                        row.cells[1].textContent = tntText;
                        const roomSelect = event.target.querySelector('select[name="ห้อง"]');
                        const roomText = roomSelect.options[roomSelect.selectedIndex].text;
                        row.cells[2].textContent = roomText;
                        const startInput = event.target.querySelector('input[name="เริ่ม"]');
                        row.cells[3].textContent = startInput.value;
                        const endInput = event.target.querySelector('input[name="สิ้นสุด"]');
                        row.cells[4].textContent = endInput.value;
                        const depositInput = event.target.querySelector('input[name="มัดจำ"]');
                        row.cells[5].textContent = new Intl.NumberFormat().format(depositInput.value);
                        const statusSelect = event.target.querySelector('select[name="สถานะ"]');
                        const statusText = statusSelect.options[statusSelect.selectedIndex].text;
                        row.cells[6].textContent = statusText;
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else if (title.includes('แก้ไข') && title.includes('ซ่อม')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/edit_repair.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message, 2000);
                        closeModal();
                        // Update the row without reload
                        const row = button.closest('tr');
                        const dateInput = event.target.querySelector('input[name="วันที่"]');
                        row.cells[0].textContent = dateInput.value;
                        const descTextarea = event.target.querySelector('textarea[name="รายละเอียด"]');
                        row.cells[2].textContent = descTextarea.value;
                        const statusSelect = event.target.querySelector('select[name="สถานะ"]');
                        const statusText = statusSelect.options[statusSelect.selectedIndex].text;
                        row.cells[3].textContent = statusText;
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else {
                closeModal();
                showToast(`${title} เรียบร้อยแล้ว`, 1200);
            }
        };
    }

    function openDeleteModal(config = {}) {
        const title = config.title || 'ยืนยันการลบ';
        const message = config.message || 'คุณต้องการดำเนินการนี้ใช่หรือไม่?';
        const onConfirm = config.onConfirm || (() => {});
        modalContainer.querySelector('h3').textContent = title;
        const form = modalContainer.querySelector('form');
        form.innerHTML = `
            <div class="animate-ui-modal-fields">
                <p style="color: rgba(255,255,255,0.8); margin: 0 0 1rem;">${message}</p>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="animate-ui-cancel-btn">ยกเลิก</button>
                <button type="submit">ยืนยัน</button>
            </div>
        `;
        form.onsubmit = (event) => {
            event.preventDefault();
            onConfirm();
            closeModal();
        };
        form.querySelector('.animate-ui-cancel-btn').addEventListener('click', closeModal);
        modalContainer.style.display = 'flex';
    }
    modalContainer.querySelector('.animate-ui-modal-close').addEventListener('click', closeModal);
    modalContainer.addEventListener('click', (event) => { if (event.target === modalContainer) closeModal(); });

    document.body.addEventListener('click', (event) => {
        const target = event.target;
        if (target.matches('.animate-ui-add-btn, .animate-ui-add-btn *')) {
            const button = target.closest('.animate-ui-add-btn');
            const entity = button?.dataset.entity || 'ข้อมูลใหม่';
            const fields = (button?.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
            openModal({ title: `เพิ่ม ${entity}`, fields });
        }
        if (target.matches('.animate-ui-edit-btn, .animate-ui-edit-btn *, .animate-ui-action-btn.edit, .animate-ui-action-btn.edit *')) {
            const button = target.closest('.animate-ui-edit-btn') || target.closest('.animate-ui-action-btn.edit');
            const entity = button?.dataset.entity || 'ข้อมูลที่เลือก';
            const fields = (button?.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
            try { console.debug('edit button clicked', { entity, fields, dataset: button?.dataset }); } catch (e) {}
            openModal({ title: `แก้ไข ${entity}`, fields, button });
        }
        if (target.matches('.animate-ui-action-btn.delete, .animate-ui-action-btn.delete *')) {
            const button = target.closest('.animate-ui-action-btn.delete');
            const entity = button?.dataset.entity || 'รายการนี้';
            // Support several attribute patterns. Prefer explicit data-item-id + data-delete-endpoint.
            const itemId = button?.dataset.itemId || button?.dataset.roomNumber || button?.dataset.newsId || button?.dataset.ctrId || button?.dataset.repairId;
            const endpoint = button?.dataset.deleteEndpoint || (button?.dataset.roomNumber ? '../Manage/delete_room.php' : null);
            if (!itemId) {
                showToast('ไม่พบหมายเลขรายการ', 3000);
                return;
            }
            openDeleteModal({
                title: 'ยืนยันการลบ',
                message: `คุณต้องการลบ ${entity} ใช่หรือไม่?`,
                onConfirm: () => {
                    if (endpoint) {
                        // send generic payload { id: <itemId> }
                        fetch(endpoint, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id: itemId})
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showToast(result.message, 2000);
                                setTimeout(() => { const row = button.closest('tr'); if (row) row.remove(); }, 500);
                            } else {
                                showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                            }
                        })
                        .catch(error => {
                            showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                            console.error(error);
                        });
                    } else if (button.dataset.roomNumber) {
                        // legacy room delete flow (room_number key)
                        fetch('../Manage/delete_room.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({room_number: itemId})
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showToast(result.message, 2000);
                                setTimeout(() => { window.location.href = 'manage_rooms.php'; }, 500);
                            } else {
                                showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                            }
                        })
                        .catch(error => {
                            showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                            console.error(error);
                        });
                    } else {
                        showToast('ไม่พบ endpoint สำหรับลบรายการ', 3000);
                    }
                }
            });
        }

        // Fallback: any element marked with .crud-action should be actionable
        try {
            const fallbackBtn = target.closest && target.closest('.crud-action');
            if (fallbackBtn) {
                // if it's an add button
                if (fallbackBtn.classList.contains('animate-ui-add-btn')) {
                    const entity = fallbackBtn.dataset.entity || 'ข้อมูลใหม่';
                    const fields = (fallbackBtn.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
                    console.debug('fallback: add clicked', { entity, fields });
                    openModal({ title: `เพิ่ม ${entity}`, fields });
                    return;
                }
                // if it's an edit-like button
                if (fallbackBtn.classList.contains('edit') || fallbackBtn.classList.contains('animate-ui-action-btn')) {
                    const entity = fallbackBtn.dataset.entity || 'ข้อมูลที่เลือก';
                    const fields = (fallbackBtn.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
                    console.debug('fallback: edit clicked', { entity, fields, dataset: fallbackBtn.dataset });
                    openModal({ title: `แก้ไข ${entity}`, fields, button: fallbackBtn });
                    return;
                }
            }
        } catch (e) { console.error(e); }
    });

    // Also attach direct listeners to add/edit buttons so modals open
    // even if event propagation is stopped by other elements.
    try {
        const addBtns = document.querySelectorAll('.animate-ui-add-btn');
        console.log('Direct listeners: found', addBtns.length, 'add buttons');
        addBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const entity = btn.dataset.entity || 'ข้อมูลใหม่';
                const fields = (btn.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
                console.log('Direct add click:', { entity, fields });
                openModal({ title: `เพิ่ม ${entity}`, fields });
            });
        });
        const editBtns = document.querySelectorAll('.animate-ui-action-btn.edit, .animate-ui-edit-btn');
        console.log('Direct listeners: found', editBtns.length, 'edit buttons');
        editBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const entity = btn.dataset.entity || 'ข้อมูลที่เลือก';
                const fields = (btn.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
                console.log('Direct edit click:', { entity, fields, dataset: btn.dataset });
                openModal({ title: `แก้ไข ${entity}`, fields, button: btn });
            });
        });
        const deleteBtns = document.querySelectorAll('.animate-ui-action-btn.delete');
        console.log('Direct listeners: found', deleteBtns.length, 'delete buttons');
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const entity = btn.dataset.entity || 'รายการนี้';
                const itemId = btn.dataset.itemId;
                const endpoint = btn.dataset.deleteEndpoint;
                console.log('Direct delete click:', { entity, itemId, endpoint });
                if (!itemId || !endpoint) {
                    showToast('ข้อมูลไม่สมบูรณ์', 3000);
                    return;
                }
                openDeleteModal({
                    title: 'ยืนยันการลบ',
                    message: `คุณต้องการลบ ${entity} ใช่หรือไม่?`,
                    onConfirm: () => {
                        fetch(endpoint, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id: itemId})
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showToast(result.message || 'ลบสำเร็จ', 2000);
                                setTimeout(() => { const row = btn.closest('tr'); if (row) row.remove(); }, 500);
                            } else {
                                showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                            }
                        })
                        .catch(error => {
                            showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                            console.error(error);
                        });
                    }
                });
            });
        });
    } catch (err) {
        console.error('Error attaching direct modal listeners', err);
    }

    // Expose a small global helper so inline handlers or other scripts
    // can open our modal reliably even if event delegation fails.
    try {
        // Replace the queued helper with the real implementation that
        // calls the internal openModal function.
        window.animateUIOpen = function(config) {
            try {
                openModal(config || {});
            } catch (e) {
                console.error('animateUIOpen error', e);
            }
        };
        // Flush any queued calls that happened before initialization
        if (Array.isArray(window.__animateUICallQueue) && window.__animateUICallQueue.length) {
            window.__animateUICallQueue.forEach(cfg => {
                try { openModal(cfg || {}); } catch (e) { console.error('flush animateUIOpen', e); }
            });
            window.__animateUICallQueue.length = 0;
        }
    } catch (e) {
        console.error('Failed to expose animateUIOpen', e);
    }

    document.querySelectorAll('[data-manage-toggle]').forEach((button) => {
        button.addEventListener('click', () => toggleCrudActions(button));
    });

    // Also attach to single toggle button by id to ensure pages with that id respond
    const singleCrudToggle = document.getElementById('toggle-crud-btn');
    if (singleCrudToggle) {
        singleCrudToggle.addEventListener('click', (e) => toggleCrudActions(singleCrudToggle));
    }

    // Apply manage-visible state from localStorage on initial load
    let manageVisible = localStorage.getItem('manageVisible');
    if (manageVisible === null) {
        manageVisible = 'true';
        localStorage.setItem('manageVisible', 'true');
    }
    if (manageVisible === 'true') {
        const panel = document.querySelector('.manage-panel');
        if (panel) {
            panel.classList.add('manage-visible');
            try {
                panel.querySelectorAll('.crud-column').forEach(td => { td.style.display = ''; });
                panel.querySelectorAll('.crud-action').forEach(btn => { btn.style.display = ''; });
            } catch (e) {}
        }
    }
});

// Toggle manage controls by adding/removing the manage-visible class on the closest panel
function toggleCrudActions(button) {
    const panel = button && typeof button.closest === 'function'
        ? button.closest('.manage-panel')
        : document.querySelector('.manage-panel');
    if (!panel) return;
    // Toggle visible class only; avoid setting inline styles so stylesheet controls presentation
    panel.classList.toggle('manage-visible');
    // Save state to localStorage for future loads
    try {
        const visible = panel.classList.contains('manage-visible');
        localStorage.setItem('manageVisible', visible ? 'true' : 'false');
    } catch (e) { /* ignore storage errors */ }
}

// Toggle table compact/expanded columns
function toggleTableColumns(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    table.classList.toggle('table--expanded');
}

// Note: AJAX page-loading removed — navigation uses full page reloads now.
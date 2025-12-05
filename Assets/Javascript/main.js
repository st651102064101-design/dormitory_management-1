/**
 * Main JavaScript for Report Pages
 * Handles: CRUD toggle (gear), Modal management, Toast notifications, Delete confirmations
 * Note: Sidebar toggle is handled by animate-ui.js
 */

document.addEventListener('DOMContentLoaded', () => {
    // ===== Toast Helper =====
    function showToast(message = 'Done', duration = 1600, redirect = '') {
        if (document.querySelector('.animate-ui-toast')) return;
        const el = document.createElement('div');
        el.className = 'animate-ui-toast';
        el.textContent = message;
        document.body.appendChild(el);
        
        const hide = () => {
            el.style.animation = 'toastOut 260ms ease forwards';
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
                if (redirect) window.location.href = redirect;
            }, 260);
        };
        el.addEventListener('click', hide);
        setTimeout(hide, duration);
    }

    // ===== CRUD Toggle (Gear Button) =====
    const toggleCrudBtn = document.getElementById('toggle-crud-btn');
    const managePanel = document.querySelector('.manage-panel');
    const MANAGE_KEY = 'app.manage.visible';

    function setupCrudToggle() {
        if (!managePanel) return;

        // Always show manage controls by default and override any saved hidden state
        try {
            localStorage.setItem(MANAGE_KEY, 'true');
        } catch (e) {}
        managePanel.classList.add('manage-visible');
        if (toggleCrudBtn) toggleCrudBtn.classList.add('active');

        // Ensure elements are visible
        try {
            managePanel.querySelectorAll('.crud-column, .crud-action').forEach(el => {
                el.style.display = '';
            });
        } catch (e) {}
    }
    setupCrudToggle();

    // ===== Modal Management =====
    const modalContainer = document.createElement('div');
    modalContainer.className = 'animate-ui-modal-overlay';
    modalContainer.style.display = 'none';
    modalContainer.innerHTML = `
        <div class="animate-ui-modal">
            <button type="button" class="animate-ui-modal-close" aria-label="Close">×</button>
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
        const title = config.title || 'จัดการข้อมูล';
        const fields = config.fields || [];
        const isEdit = title.includes('แก้ไข');
        const button = config.button;

        modalContainer.querySelector('h3').textContent = title;
        const fieldsContainer = modalContainer.querySelector('.animate-ui-modal-fields');
        fieldsContainer.innerHTML = '';

        // Add hidden id fields for edit
        if (isEdit && button?.dataset.newsId) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'news_id';
            hidden.value = button.dataset.newsId;
            fieldsContainer.appendChild(hidden);
        }

        // Build form fields
        fields.forEach((field) => {
            const label = document.createElement('label');
            const inputType = field.includes('วันที่') ? 'date' : 'text';
            const input = document.createElement(field === 'รายละเอียด' ? 'textarea' : 'input');
            
            if (field === 'รายละเอียด') {
                input.rows = 4;
                input.placeholder = field;
                if (isEdit && button?.dataset.newsDetails) {
                    input.value = button.dataset.newsDetails;
                }
            } else {
                input.type = inputType;
                input.placeholder = field;
                if (isEdit && button?.dataset.newsDate && field === 'วันที่') {
                    input.value = button.dataset.newsDate;
                }
                if (isEdit && button?.dataset.newsTitle && field === 'หัวข้อ') {
                    input.value = button.dataset.newsTitle;
                }
            }

            input.name = field;
            label.innerHTML = `<span>${field}</span>`;
            label.appendChild(input);
            fieldsContainer.appendChild(label);
        });

        modalContainer.style.display = 'flex';
        modalContainer.querySelector('form').onsubmit = async (event) => {
            event.preventDefault();

            if (title.includes('แก้ไข')) {
                const formData = new FormData(event.target);
                try {
                    const response = await fetch('../Manage/edit_news.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message || 'บันทึกสำเร็จ', 2000);
                        closeModal();
                        setTimeout(() => {
                            const row = button.closest('tr');
                            if (row) {
                                row.cells[0].textContent = formData.get('วันที่') || '';
                                row.cells[1].textContent = formData.get('หัวข้อ') || '';
                                row.cells[2].textContent = formData.get('รายละเอียด') || '';
                            }
                        }, 300);
                    } else {
                        showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                    }
                } catch (error) {
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
                    console.error(error);
                }
            } else {
                closeModal();
                showToast(title + ' เรียบร้อยแล้ว', 1200);
            }
        };
    }



    // ===== Modal Close Handlers =====
    modalContainer.querySelector('.animate-ui-modal-close').addEventListener('click', closeModal);
    modalContainer.addEventListener('click', (event) => {
        if (event.target === modalContainer) closeModal();
    });

    // ===== CRUD Action Handlers =====
    document.body.addEventListener('click', (event) => {
        const target = event.target;

        // Edit button
        if (target.closest('.animate-ui-action-btn.edit')) {
            const button = target.closest('.animate-ui-action-btn.edit');
            const entity = button?.dataset.entity || 'ข้อมูล';
            const fields = (button?.dataset.fields || '').split(',').map(f => f.trim()).filter(Boolean);
            openModal({ title: `แก้ไข ${entity}`, fields, button });
        }

        // Delete button - Direct delete without confirmation modal
        if (target.closest('.animate-ui-action-btn.delete')) {
            const button = target.closest('.animate-ui-action-btn.delete');
            const entity = button?.dataset.entity || 'รายการ';
            const itemId = button?.dataset.itemId;
            const endpoint = button?.dataset.deleteEndpoint;

            // if (!itemId || !endpoint) {
            //     showToast('ข้อมูลไม่สมบูรณ์', 3000);
            //     return;
            // }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: itemId })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message || 'ลบ' + entity + 'เรียบร้อยแล้ว', 2000);
                    setTimeout(() => {
                        const row = button.closest('tr');
                        if (row) row.remove();
                    }, 300);
                } else {
                    showToast(result.error || 'เกิดข้อผิดพลาด', 3000);
                }
            })
            // .catch(error => {
            //     showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 3000);
            //     console.error(error);
            // });
        }
    });
});

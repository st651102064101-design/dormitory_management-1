/**
 * Animate UI - Core JavaScript
 * Handles: Sidebar toggle, Form interception, Active link highlighting
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Animate UI initialized');

    // ===== Helper: Check if mobile =====
    function isMobile() {
        return window.innerWidth < 768;
    }

    // ===== Sidebar Toggle (Open/Collapse) with Persistence =====
    const sidebar = document.querySelector('.app-sidebar');
    const toggleButtons = Array.from(document.querySelectorAll('#sidebar-toggle, [data-sidebar-toggle]'));
    const SIDEBAR_KEY = 'sidebarCollapsed';

    console.log('Sidebar toggle setup:', { 
        sidebar: !!sidebar, 
        toggleButtons: toggleButtons.length 
    });

    function applySidebarState(collapsed) {
        if (!sidebar) return;

        if (isMobile()) {
            sidebar.classList.remove('collapsed');
            toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open').toString()));
            return;
        }

        sidebar.classList.toggle('collapsed', !!collapsed);
        toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', (!collapsed).toString()));
    }

    // Load saved state on page load
    try {
        const stored = localStorage.getItem(SIDEBAR_KEY);
        applySidebarState(stored === 'true');
        console.log('Sidebar state loaded:', stored);
    } catch (e) {
        console.warn('Failed to load sidebar state:', e);
    }

    // Mobile: always expanded
    if (isMobile()) {
        if (sidebar) {
            sidebar.classList.remove('collapsed');
            console.debug('Mobile detected: removed collapsed class from sidebar');
        }
    }

    // Toggle button click handlers
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            if (!sidebar) {
                console.warn('Sidebar not found');
                return;
            }

            if (isMobile()) {
                sidebar.classList.toggle('mobile-open');
                console.log('Mobile sidebar toggled:', sidebar.classList.contains('mobile-open'));
            } else {
                const isCollapsed = sidebar.classList.toggle('collapsed');
                try {
                    localStorage.setItem(SIDEBAR_KEY, isCollapsed.toString());
                    console.log('Sidebar collapsed:', isCollapsed);
                } catch (e) {
                    console.warn('Failed to save sidebar state:', e);
                }
            }

            toggleButtons.forEach(b => {
                if (isMobile()) {
                    b.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open').toString());
                } else {
                    b.setAttribute('aria-expanded', (!sidebar.classList.contains('collapsed')).toString());
                }
            });
        });
    });

    // Signal that sidebar toggle has already been wired to avoid duplicate handlers elsewhere
    window.__sidebarToggleHandled = true;

    // ===== Sidebar: Arrow toggle and active link =====
    const activeLink = document.querySelector('.app-nav a.active, .app-nav-sublist a.active');
    if (activeLink) {
        const parentDetails = activeLink.closest('details');
        if (parentDetails) {
            parentDetails.setAttribute('open', '');
        }
    }

    // ===== Form Interception (Skip forms marked with data attributes) =====
    document.addEventListener('submit', (e) => {
        const form = e.target;
        
        // Skip if form has opt-out flags
        if (form.hasAttribute('data-animate-ui-skip') || 
            form.hasAttribute('data-no-modal') ||
            form.hasAttribute('data-allow-submit')) {
            console.log('Form submission allowed (opt-out flag detected)');
            return; // Allow normal form submission
        }

        // Skip if submit button has opt-out flags
        const submitButton = e.submitter || form.querySelector('button[type="submit"]');
        if (submitButton && (
            submitButton.hasAttribute('data-animate-ui-skip') ||
            submitButton.hasAttribute('data-no-modal') ||
            submitButton.hasAttribute('data-allow-submit')
        )) {
            console.log('Form submission allowed (button opt-out flag detected)');
            return; // Allow normal form submission
        }

        // Otherwise, prevent default for modal handling
        console.log('Form intercepted for modal handling');
        e.preventDefault();
    });

    // ===== Login Success Handler =====
    if (window.__loginSuccess && window.__loginRedirect) {
        console.log('Login successful, redirecting to:', window.__loginRedirect);
        setTimeout(() => {
            window.location.href = window.__loginRedirect;
        }, 100);
    }

    console.log('Animate UI setup complete');
});

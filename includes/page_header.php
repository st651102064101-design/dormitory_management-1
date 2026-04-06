<?php
/**
 * Page Header Component
 *
 * Includes the hamburger menu button and page title.
 *
 * Usage:
 *   <?php $pageTitle = 'หน้าจัดการ'; include __DIR__ . '/includes/page_header.php'; ?>
 *
 * Optional:
 *   $pageHeaderActions = [
 *     [
 *       'label' => 'เพิ่ม',
 *       'type' => 'button',
 *       'className' => 'active',
 *       'attributes' => ['data-foo' => 'bar']
 *     ]
 *   ];
 *
 * Required: sidebar_toggle.php must be included in <head> first
 */

$translateHeader = static function (string $key, string $fallback): string {
  if (function_exists('__')) {
    $translated = (string) __($key);
    if ($translated !== '' && $translated !== $key) {
      return $translated;
    }
  }

  return $fallback;
};

$defaultHeaderActions = [
  ['label' => $translateHeader('menu_payments', 'การชำระเงิน'), 'href' => 'manage_payments.php', 'shortcut' => 'Ctrl+1'],
  ['label' => $translateHeader('menu_bookings', 'จองห้อง'), 'href' => 'manage_booking.php', 'shortcut' => 'Ctrl+2'],
  ['label' => $translateHeader('menu_expenses', 'ค่าใช้จ่าย'), 'href' => 'manage_expenses.php', 'shortcut' => 'Ctrl+3'],
  ['label' => $translateHeader('menu_contracts', 'สัญญา'), 'href' => 'manage_contracts.php', 'shortcut' => 'Ctrl+4'],
  ['label' => $translateHeader('menu_wizard', 'ตัวช่วยผู้เช่า'), 'href' => 'tenant_wizard.php', 'shortcut' => 'Ctrl+5'],
];

$normalizeHeaderActions = static function (array $actions, array $defaults) use ($translateHeader): array {
  $knownDefaultLabelsByHref = [
    'manage_payments.php' => ['การชำระเงิน', 'Payments'],
    'manage_booking.php' => ['จองห้อง', 'การจอง', 'Bookings'],
    'manage_expenses.php' => ['ค่าใช้จ่าย', 'Expenses'],
    'manage_contracts.php' => ['สัญญา', 'Contracts'],
    'tenant_wizard.php' => ['ตัวช่วยผู้เช่า', 'Tenant Wizard'],
  ];

  $localizedLabelsByHref = [
    'manage_payments.php' => $translateHeader('menu_payments', 'การชำระเงิน'),
    'manage_booking.php' => $translateHeader('menu_bookings', 'จองห้อง'),
    'manage_expenses.php' => $translateHeader('menu_expenses', 'ค่าใช้จ่าย'),
    'manage_contracts.php' => $translateHeader('menu_contracts', 'สัญญา'),
    'tenant_wizard.php' => $translateHeader('menu_wizard', 'ตัวช่วยผู้เช่า'),
  ];

  $normalized = [];
  foreach ($actions as $index => $action) {
    if (!is_array($action)) {
      continue;
    }

    $default = $defaults[$index] ?? ['label' => '', 'href' => '#', 'shortcut' => ''];
    $enabled = array_key_exists('enabled', $action) ? (bool) $action['enabled'] : true;
    if (!$enabled) {
      continue;
    }

    $label = trim((string) ($action['label'] ?? $default['label']));
    $href = trim((string) ($action['href'] ?? $default['href']));
    $shortcut = trim((string) ($action['shortcut'] ?? $default['shortcut']));

    $hrefPath = parse_url($href, PHP_URL_PATH);
    if (!is_string($hrefPath) || $hrefPath === '') {
      $hrefPath = preg_replace('/[?#].*$/', '', $href);
    }

    $hrefPath = trim((string)$hrefPath);
    $hrefPath = ltrim($hrefPath, './');
    $hrefKey = basename($hrefPath);
    if ($hrefKey === '') {
      $hrefKey = $hrefPath;
    }

    $labelNormalized = preg_replace('/\s+/u', ' ', trim(html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $knownLabels = $knownDefaultLabelsByHref[$hrefKey] ?? null;
    if (is_array($knownLabels)) {
      $knownLabels = array_map(
        static fn(string $known): string => preg_replace('/\s+/u', ' ', trim(html_entity_decode($known, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
        $knownLabels
      );
    }

    if (is_array($knownLabels) && in_array($labelNormalized, $knownLabels, true)) {
      $label = $localizedLabelsByHref[$hrefKey] ?? $label;
    }

    if ($label === '' || $href === '') {
      continue;
    }

    if (strpos($href, '..') !== false || preg_match('/^(?:https?:|javascript:|\/\/)/i', $href) || !preg_match('/^[A-Za-z0-9_\/.\-?#=&%]+$/', $href)) {
      continue;
    }

    $normalized[] = [
      'label' => $label,
      'href' => $href,
      'shortcut' => $shortcut,
      'type' => $action['type'] ?? 'link',
      'className' => $action['className'] ?? '',
      'attributes' => isset($action['attributes']) && is_array($action['attributes']) ? $action['attributes'] : [],
    ];
  }

  return $normalized ?: $defaults;
};

$headerActions = $defaultHeaderActions;
if (isset($pageHeaderActions) && is_array($pageHeaderActions)) {
  $headerActions = $normalizeHeaderActions($pageHeaderActions, $defaultHeaderActions);
} elseif (isset($adminQuickActions) && is_array($adminQuickActions)) {
  $headerActions = $normalizeHeaderActions($adminQuickActions, $defaultHeaderActions);
} else {
  try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pageHeaderPdo = connectDB();
    $pageHeaderStmt = $pageHeaderPdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'admin_quick_actions' LIMIT 1");
    $pageHeaderStmt->execute();
    $quickActionsJson = $pageHeaderStmt->fetchColumn();
    if ($quickActionsJson) {
      $decodedQuickActions = json_decode((string) $quickActionsJson, true);
      if (is_array($decodedQuickActions)) {
        $headerActions = $normalizeHeaderActions($decodedQuickActions, $defaultHeaderActions);
      }
    }
  } catch (Throwable $e) {
    $headerActions = $defaultHeaderActions;
  }
}

$headerActionsLabel = $pageHeaderActionsLabel ?? $translateHeader('quick_actions', 'Quick actions');
$pageHeaderTitle = '';
if (isset($pageTitle)) {
  $pageHeaderTitle = trim((string) $pageTitle);
}

$buildHeaderAttributes = static function (array $attributes): string {
  $parts = [];
  foreach ($attributes as $name => $value) {
    if ($value === null || $value === false) {
      continue;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $name);
    if ($safeName === '') {
      continue;
    }

    if ($value === true) {
      $parts[] = $safeName;
      continue;
    }

    $parts[] = $safeName . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
  }

  return $parts ? ' ' . implode(' ', $parts) : '';
};
?>
<header class="page-header-bar">
  <div class="page-header-left">
    <button id="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="false" class="sidebar-toggle-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <h2 id="page-header-title"><?php echo htmlspecialchars($pageHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
  </div>
  <nav class="quick-actions" aria-label="<?php echo htmlspecialchars($headerActionsLabel, ENT_QUOTES, 'UTF-8'); ?>">
    <?php foreach ($headerActions as $action): ?>
      <?php
        $label = $action['label'] ?? '';
        if ($label === '') {
          continue;
        }
        $type = $action['type'] ?? 'link';
        $shortcut = $action['shortcut'] ?? '';
        $className = trim('quick-action-link' . (!empty($action['className']) ? ' ' . $action['className'] : '') . ($type === 'button' ? ' quick-action-button' : ''));
        $attributes = $action['attributes'] ?? [];
        if ($shortcut !== '') {
          $attributes['data-shortcut'] = $shortcut;
        }
      ?>
      <?php if ($type === 'button'): ?>
        <button type="button" class="<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $buildHeaderAttributes($attributes); ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
      <?php else: ?>
        <a href="<?php echo htmlspecialchars($action['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($className, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $buildHeaderAttributes($attributes); ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</header>
<div class="page-header-spacer"></div>
<script>
(function() {
  function fallbackSidebarToggle(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
      if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
      }
    }

    var sidebar = document.querySelector('.app-sidebar');
    var btn = document.getElementById('sidebar-toggle');
    if (!sidebar) return false;

    var isMobile = window.innerWidth <= 1024;
    if (isMobile) {
      var isOpen = sidebar.classList.toggle('mobile-open');
      document.body.classList.toggle('sidebar-open', isOpen);
      if (btn) btn.setAttribute('aria-expanded', isOpen.toString());
    } else {
      var isCollapsed = sidebar.classList.toggle('collapsed');
      try { localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false'); } catch (e) {}
      if (btn) btn.setAttribute('aria-expanded', (!isCollapsed).toString());
    }

    return false;
  }

  window.__fallbackSidebarToggle = fallbackSidebarToggle;

  var btn = document.getElementById('sidebar-toggle');
  if (btn && !btn.__toggleBound) {
    btn.__toggleBound = true;
    btn.addEventListener('click', function(e) {
      if (typeof window.__directSidebarToggle === 'function') {
        return window.__directSidebarToggle(e);
      }
      return fallbackSidebarToggle(e);
    }, true);
  }
  
  // Initialize sidebar state if not already done
  if (typeof window.__initSidebarState === 'function' && !window.__sidebarStateInitialized) {
    window.__sidebarStateInitialized = true;
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', window.__initSidebarState);
    } else {
      window.__initSidebarState();
    }
  }

  function initHeaderAutoHide() {
    var header = document.querySelector('.page-header-bar');
    if (!header || header.__autoHideBound) {
      return;
    }

    header.__autoHideBound = true;

    var scrollContainer = document.querySelector('.app-main') || document.querySelector('.main-content') || window;
    var lastScrollTop = 0;
    var ticking = false;

    function getScrollTop() {
      if (scrollContainer && scrollContainer !== window) {
        return scrollContainer.scrollTop || 0;
      }

      return window.pageYOffset || document.documentElement.scrollTop || 0;
    }

    function updateHeaderState() {
      var currentScrollTop = getScrollTop();
      var scrollDelta = currentScrollTop - lastScrollTop;

      if (currentScrollTop > 8) {
        header.classList.add('header-scrolled');
      } else {
        header.classList.remove('header-scrolled');
      }

      if (currentScrollTop <= 16) {
        header.classList.remove('header-hidden');
      } else if (scrollDelta > 3) {
        header.classList.add('header-hidden');
      } else if (scrollDelta < -1) {
        header.classList.remove('header-hidden');
      }

      lastScrollTop = currentScrollTop < 0 ? 0 : currentScrollTop;
      ticking = false;
    }

    function requestUpdate() {
      if (ticking) {
        return;
      }

      ticking = true;
      window.requestAnimationFrame(updateHeaderState);
    }

    if (scrollContainer && scrollContainer !== window) {
      scrollContainer.addEventListener('scroll', requestUpdate, { passive: true });
    }
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });

    updateHeaderState();
  }

  function fillMissingHeaderTitle() {
    var titleEl = document.getElementById('page-header-title');
    if (!titleEl || titleEl.textContent.trim() !== '') {
      return;
    }

    var documentTitle = (document.title || '').trim();
    if (!documentTitle) {
      return;
    }

    var parts = documentTitle.split(' - ').map(function(part) {
      return part.trim();
    }).filter(Boolean);

    titleEl.textContent = parts.length > 1 ? parts[parts.length - 1] : documentTitle;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderAutoHide);
    document.addEventListener('DOMContentLoaded', fillMissingHeaderTitle);
  } else {
    initHeaderAutoHide();
    fillMissingHeaderTitle();
  }
})();
</script>
<style>
/* Page Header Styles - Apple-style Auto-hide */
.page-header-bar {
  position: sticky;
  width: 100%;
  top: 1rem;
  z-index: 120;
  padding: 1rem 1.5rem;
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 1rem 0 1rem 0;
  transform: translateY(0);
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1),
              background 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Add padding to main content container */
.app-main > div {
  padding-left: 1rem;
  padding-right: 1rem;
}

@media (min-width: 769px) {
  .app-main > div {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }
}

.page-header-bar.header-hidden {
  transform: translateY(calc(-100% - 1rem));
}

.page-header-bar.header-scrolled {
  background: rgba(15, 23, 42, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.page-header-spacer {
  display: none;
}

.page-header-bar > div {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.page-header-left {
  min-width: 0;
}
.page-header-bar h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  color: #f5f8ff;
  transition: opacity 0.3s ease;
}
.quick-actions {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: nowrap;
  justify-content: flex-start;
  overflow-x: auto;
  overflow-y: hidden;
  min-width: 0;
  max-width: 100%;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
  padding-bottom: 2px;
  cursor: grab;
}
.quick-actions::-webkit-scrollbar {
  height: 4px;
}
.quick-actions::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.55);
  border-radius: 999px;
}
.quick-actions::-webkit-scrollbar-track {
  background: transparent;
}
.quick-action-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.45rem 0.75rem;
  border-radius: 8px;
  border: 1px solid rgba(59, 130, 246, 0.5);
  background: rgba(59, 130, 246, 0.18);
  color: #dbeafe;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 700;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.quick-action-button {
  cursor: pointer;
  appearance: none;
}
.quick-action-link:hover {
  background: rgba(59, 130, 246, 0.18);
  border-color: rgba(96, 165, 250, 0.8);
  color: #ffffff;
}
.quick-action-link.active {
  background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
  border-color: #1d4ed8 !important;
  color: #ffffff !important;
}
.quick-action-link::after {
  content: attr(data-shortcut);
  margin-left: 0.45rem;
  padding: 0.08rem 0.35rem;
  border-radius: 6px;
  border: 1px solid rgba(96, 165, 250, 0.6);
  background: rgba(30, 64, 175, 0.35);
  font-size: 0.76rem;
  color: #dbeafe;
  font-weight: 700;
  line-height: 1.2;
}
.quick-action-link:not([data-shortcut])::after,
.quick-action-link[data-shortcut=""]::after {
  display: none;
}
.quick-action-link.active::after,
.quick-action-link:hover::after {
  color: #ffffff !important;
  border-color: rgba(255, 255, 255, 0.7) !important;
  background: rgba(15, 23, 42, 0.5) !important;
}
.page-header-bar.header-hidden h2 {
  opacity: 0;
}
.sidebar-toggle-btn {
  position: relative;
  z-index: 10001;
  background: transparent;
  border: 0;
  color: #fff;
  padding: 0.6rem 0.85rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.3s ease, transform 0.2s ease;
  outline: none;
  pointer-events: auto !important;
  visibility: visible !important;
  opacity: 1 !important;
}
.sidebar-toggle-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  transform: scale(1.05);
}
.sidebar-toggle-btn:active {
  transform: scale(0.95);
}
.sidebar-toggle-btn svg {
  width: 24px;
  height: 24px;
  stroke-width: 2;
  transition: transform 0.3s ease;
}
@media (max-width: 768px) {
  .page-header-bar {
    top: 0.75rem;
    padding: 0.875rem 1rem;
    flex-direction: column;
    align-items: stretch;
    gap: 0.6rem;
  }
  .page-header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .page-header-bar h2 {
    font-size: 1rem;
  }
  .quick-actions {
    justify-content: flex-start;
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 0.2rem;
  }
  .quick-actions::-webkit-scrollbar {
    height: 4px;
  }
  .quick-actions::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.45);
    border-radius: 999px;
  }
  .page-header-spacer {
    height: 64px;
  }
}

/* Light Theme Support */
body.light-theme .page-header-bar,
body[data-theme="light"] .page-header-bar {
  background: rgba(255, 255, 255, 0.8);
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-theme .page-header-bar.header-scrolled,
body[data-theme="light"] .page-header-bar.header-scrolled {
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

body.light-theme .page-header-bar h2,
body[data-theme="light"] .page-header-bar h2 {
  color: #1e293b;
}

body.light-theme .sidebar-toggle-btn,
body[data-theme="light"] .sidebar-toggle-btn {
  color: #1e293b;
}

html.light-theme .page-header-bar .quick-action-link,
html.live-light .page-header-bar .quick-action-link,
body.light-theme .page-header-bar .quick-action-link,
body[data-theme="light"] .page-header-bar .quick-action-link,
body.live-light .page-header-bar .quick-action-link {
  background: rgba(59,130,246,0.16) !important;
  border-color: rgba(59,130,246,0.55) !important;
  color: #1d4ed8 !important;
}

html.light-theme .page-header-bar .quick-action-link::after,
html.live-light .page-header-bar .quick-action-link::after,
body.light-theme .page-header-bar .quick-action-link::after,
body[data-theme="light"] .page-header-bar .quick-action-link::after,
body.live-light .page-header-bar .quick-action-link::after {
  color: #1d4ed8 !important;
  border-color: rgba(59, 130, 246, 0.5) !important;
  background: rgba(59, 130, 246, 0.15) !important;
}

html.light-theme .page-header-bar .quick-action-link:hover,
html.live-light .page-header-bar .quick-action-link:hover,
body.light-theme .page-header-bar .quick-action-link:hover,
body[data-theme="light"] .page-header-bar .quick-action-link:hover,
body.live-light .page-header-bar .quick-action-link:hover {
  background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
  border-color: #1d4ed8 !important;
  color: #ffffff !important;
}

html.light-theme .page-header-bar .quick-action-link.active,
html.live-light .page-header-bar .quick-action-link.active,
body.light-theme .page-header-bar .quick-action-link.active,
body[data-theme="light"] .page-header-bar .quick-action-link.active,
body.live-light .page-header-bar .quick-action-link.active {
  background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
  border-color: #1d4ed8 !important;
  color: #ffffff !important;
}

body.light-theme .sidebar-toggle-btn:hover,
body[data-theme="light"] .sidebar-toggle-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}
</style>
<script>
// Apple-style Auto-hide Header on Scroll - Works on all pages
(function() {
  let lastScrollTop = 0;
  let ticking = false;
  let scrollContainer = null;
  
  function getScrollTop() {
    if (scrollContainer) {
      return scrollContainer.scrollTop;
    }
    return window.pageYOffset || document.documentElement.scrollTop;
  }
  
  function handleHeaderScroll() {
    const header = document.querySelector('.page-header-bar');
    if (!header) return;
    
    const currentScrollTop = getScrollTop();
    const scrollDelta = currentScrollTop - lastScrollTop;
    
    // Add scrolled class when scrolled
    if (currentScrollTop > 5) {
      header.classList.add('header-scrolled');
    } else {
      header.classList.remove('header-scrolled');
    }
    
    // Scrolling DOWN (positive delta) - hide header
    if (scrollDelta > 2 && currentScrollTop > 50) {
      header.classList.add('header-hidden');
    }
    // Scrolling UP (negative delta) - show header IMMEDIATELY
    else if (scrollDelta < -2) {
      header.classList.remove('header-hidden');
    }
    
    // Always show when at very top
    if (currentScrollTop <= 10) {
      header.classList.remove('header-hidden');
    }
    
    lastScrollTop = currentScrollTop <= 0 ? 0 : currentScrollTop;
    ticking = false;
  }
  
  function requestHeaderUpdate() {
    if (!ticking) {
      window.requestAnimationFrame(handleHeaderScroll);
      ticking = true;
    }
  }
  
  function initHeaderScroll() {
    const header = document.querySelector('.page-header-bar');
    if (!header || header.__autoHideBound) {
      return;
    }

    header.__autoHideBound = true;

    // Find scroll container - check common containers used in the app
    scrollContainer = document.querySelector('.app-main') || 
                      document.querySelector('.main-content') || 
                      document.querySelector('main') ||
                      null;
    
    // Initial check
    handleHeaderScroll();
    
    // Add scroll listener to container if found, otherwise use window
    if (scrollContainer) {
      scrollContainer.addEventListener('scroll', requestHeaderUpdate, { passive: true });
    }
    // Always add window scroll listener as fallback
    window.addEventListener('scroll', requestHeaderUpdate, { passive: true });
    
    // Handle resize
    window.addEventListener('resize', requestHeaderUpdate, { passive: true });
  }
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderScroll);
  } else {
    // Small delay to ensure containers are rendered
    setTimeout(initHeaderScroll, 100);
  }
})();
</script>

<script>
(function() {
  var quickLinks = Array.from(document.querySelectorAll('.quick-action-link'));
  if (!quickLinks.length) return;

  function normalizePath(value) {
    if (!value) return '';

    try {
      value = new URL(value, window.location.href).pathname || '';
    } catch (e) {
      value = String(value);
    }

    value = String(value).split('?')[0].split('#')[0].trim();
    if (!value) return '';

    value = value.replace(/\/+$/, '');
    if (!value) return '';

    return value;
  }

  function basename(value) {
    var normalized = normalizePath(value);
    if (!normalized) return '';

    var parts = normalized.split('/').filter(Boolean);
    return parts.length ? parts[parts.length - 1] : normalized;
  }

  var currentPath = normalizePath(window.location.pathname);
  var currentBase = basename(window.location.pathname);

  quickLinks.forEach(function(link) {
    var href = (link.getAttribute('href') || '').trim();
    var linkPath = normalizePath(href);
    var linkBase = basename(href);

    if (href && (
      (linkPath && linkPath === currentPath) ||
      (linkBase && linkBase === currentBase)
    )) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });

  document.addEventListener('keydown', function(e) {
    var isCtrlPrimary = e.ctrlKey && !e.shiftKey && !e.altKey && !e.metaKey;
    var isCtrlShiftFallback = e.ctrlKey && e.shiftKey && !e.altKey && !e.metaKey;
    var isAltFallback = e.altKey && !e.metaKey;
    if (!isCtrlPrimary && !isCtrlShiftFallback && !isAltFallback) return;

    var active = document.activeElement;
    if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) {
      return;
    }

    var codeMap = {
      'Digit1': 'manage_payments.php',
      'Digit2': 'manage_booking.php',
      'Digit3': 'manage_expenses.php',
      'Digit4': 'manage_contracts.php',
      'Digit5': 'tenant_wizard.php',
      'Numpad1': 'manage_payments.php',
      'Numpad2': 'manage_booking.php',
      'Numpad3': 'manage_expenses.php',
      'Numpad4': 'manage_contracts.php',
      'Numpad5': 'tenant_wizard.php'
    };

    var target = codeMap[e.code];
    if (!target) return;

    e.preventDefault();
    window.location.href = target;
  });
})();

// Drag-to-scroll for quick-actions nav
(function() {
  var nav = document.querySelector('.quick-actions');
  if (!nav) return;
  var isDown = false, startX, scrollLeft;
  nav.addEventListener('mousedown', function(e) {
    if (e.target.closest('a, button')) return;
    isDown = true;
    nav.style.cursor = 'grabbing';
    startX = e.pageX - nav.offsetLeft;
    scrollLeft = nav.scrollLeft;
  });
  document.addEventListener('mouseup', function() {
    isDown = false;
    if (nav) nav.style.cursor = 'grab';
  });
  nav.addEventListener('mousemove', function(e) {
    if (!isDown) return;
    e.preventDefault();
    var x = e.pageX - nav.offsetLeft;
    nav.scrollLeft = scrollLeft - (x - startX);
  });
  nav.addEventListener('mouseleave', function() {
    isDown = false;
    nav.style.cursor = 'grab';
  });
})();

// Drag-to-scroll for filter bars (all pages)
(function() {
  function initFilterDrag(el) {
    if (!el || el._dragInit) return;
    el._dragInit = true;
    var isDown = false, startX, scrollLeft;
    el.addEventListener('mousedown', function(e) {
      if (e.target.closest('a, button, input, select')) return;
      isDown = true;
      el.style.cursor = 'grabbing';
      startX = e.pageX - el.offsetLeft;
      scrollLeft = el.scrollLeft;
    });
    document.addEventListener('mouseup', function() {
      isDown = false;
      if (el) el.style.cursor = '';
    });
    el.addEventListener('mousemove', function(e) {
      if (!isDown) return;
      e.preventDefault();
      var x = e.pageX - el.offsetLeft;
      el.scrollLeft = scrollLeft - (x - startX);
    });
    el.addEventListener('mouseleave', function() {
      isDown = false;
      el.style.cursor = '';
    });
  }
  // Expose globally so pages can re-attach after AJAX refresh
  window.initFilterDrag = initFilterDrag;
  function attachFilterBars() {
    document.querySelectorAll('.ctr-filter-bar, .wiz-filter-bar, .expense-filter-tabs, .payment-filter-tabs, .info-bar').forEach(initFilterDrag);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachFilterBars);
  } else {
    attachFilterBars();
  }
})();
</script>

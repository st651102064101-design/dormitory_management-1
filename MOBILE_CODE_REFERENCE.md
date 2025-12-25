# Mobile Responsive Code Reference

## CSS Snippets

### Mobile Sidebar Overlay Styles
```css
@media (max-width: 1024px) {
  /* Base sidebar - hidden off-screen by default */
  .app-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    width: 240px !important;
    z-index: 1000 !important;
    background: #0b162a !important;
    transform: translateX(-100%) !important;  /* Closed state */
    transition: transform 0.35s ease !important;
    will-change: transform;  /* GPU acceleration */
    box-shadow: 4px 0 24px rgba(0,0,0,0.6) !important;
    padding: 1.25rem 0.75rem !important;
    overflow: auto !important;
    flex-shrink: 0 !important;
    margin: 0 !important;
  }

  /* Open state - slide in from left */
  .app-sidebar.mobile-open {
    transform: translateX(0) !important;  /* Visible state */
  }

  /* Alternative selector for body-based toggle */
  body.sidebar-open .app-sidebar {
    transform: translateX(0) !important;
  }

  /* Dark overlay background */
  body.sidebar-open::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
}
```

### Content Area Responsiveness
```css
@media (max-width: 1024px) {
  /* Prevent horizontal scroll */
  html, body {
    width: 100%;
    overflow-x: hidden;
  }

  /* Content fills viewport */
  .app-main {
    margin: 0 !important;
    width: 100vw !important;  /* Full viewport width */
    height: auto !important;
    position: relative;
    z-index: 1 !important;
    flex: 1 1 auto !important;
    padding: 1rem !important;
    transition: all 0.3s ease !important;
    box-sizing: border-box !important;
    overflow-x: hidden;
  }

  /* Remove spacing gaps */
  .app-main section {
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Responsive header */
  .app-main header {
    width: 100% !important;
    margin: 0 !important;
    padding: 0.5rem !important;
    box-sizing: border-box;
  }

  .app-main header h2 {
    font-size: 1rem !important;
    margin: 0 !important;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;  /* ... on long titles */
    white-space: nowrap;
  }

  /* Text wrapping */
  .app-main h1, .app-main h2, .app-main h3,
  .app-main p, .app-main .card,
  .app-main .manage-panel {
    word-break: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
    margin: 0 !important;
    padding-right: 0 !important;
  }
}
```

---

## JavaScript Snippets

### Mobile Detection
```javascript
const isMobile = () => window.innerWidth <= 1024;
```

### Sidebar Toggle (Mobile vs Desktop)
```javascript
const toggleButtons = document.querySelectorAll('#sidebar-toggle');
const sidebar = document.querySelector('.app-sidebar');

toggleButtons.forEach((btn) => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    
    if (isMobile()) {
      // Mobile: slide-in overlay
      sidebar.classList.remove('collapsed');
      const opened = sidebar.classList.toggle('mobile-open');
      document.body.classList.toggle('sidebar-open', opened);
      btn.setAttribute('aria-expanded', opened.toString());
    } else {
      // Desktop: collapse narrow
      const isCollapsed = sidebar.classList.toggle('collapsed');
      localStorage.setItem('animateui.sidebar.collapsed', isCollapsed ? 'true' : '');
      btn.setAttribute('aria-expanded', (!isCollapsed).toString());
    }
  });
});
```

### Overlay Click-to-Close
```javascript
document.addEventListener('click', (e) => {
  if (!isMobile() || !sidebar) return;
  
  // Check if click is on overlay (not sidebar or button)
  const isOverlay = document.body.classList.contains('sidebar-open') && 
                   !sidebar.contains(e.target) && 
                   !toggleButtons.some(btn => btn.contains(e.target));
  
  if (isOverlay) {
    sidebar.classList.remove('mobile-open');
    document.body.classList.remove('sidebar-open');
    toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
  }
});
```

### Escape Key Handler
```javascript
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && isMobile() && sidebar && 
      document.body.classList.contains('sidebar-open')) {
    sidebar.classList.remove('mobile-open');
    document.body.classList.remove('sidebar-open');
    toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
  }
});
```

### Resize Synchronization
```javascript
window.addEventListener('resize', () => {
  if (!sidebar) return;
  
  if (isMobile()) {
    // Mobile: clean up desktop state
    sidebar.classList.remove('collapsed');
    localStorage.removeItem('animateui.sidebar.collapsed');
  } else {
    // Desktop: clean up mobile state
    sidebar.classList.remove('mobile-open');
    document.body.classList.remove('sidebar-open');
  }
});
```

---

## HTML Structure

### Required Markup
```html
<!-- Sidebar toggle button (typically in header) -->
<button id="sidebar-toggle" 
        aria-label="Toggle sidebar" 
        aria-expanded="false">
  <svg><!-- hamburger icon --></svg>
</button>

<!-- Main app container -->
<div class="app-shell">
  <!-- Sidebar -->
  <aside class="app-sidebar">
    <!-- Navigation content -->
  </aside>
  
  <!-- Main content -->
  <main class="app-main">
    <header>
      <h2>Page Title</h2>
    </header>
    <section>
      <!-- Page content -->
    </section>
  </main>
</div>
```

---

## State Management

### Classes Used
| Class | Element | Purpose | Platforms |
|-------|---------|---------|-----------|
| `.mobile-open` | `.app-sidebar` | Sidebar visible on mobile | Mobile only |
| `.sidebar-open` | `body` | Overlay visible | Mobile only |
| `.collapsed` | `.app-sidebar` | Sidebar collapsed on desktop | Desktop only |

### CSS Properties Toggled
| Property | Mobile (Closed) | Mobile (Open) | Desktop | Purpose |
|----------|-----------------|---------------|---------|---------|
| `transform` | `translateX(-100%)` | `translateX(0)` | N/A | Slide animation |
| `width` | N/A | N/A | `72px` / `220px` | Collapse animation |

---

## Accessibility Attributes

### Button Attributes
```javascript
// When sidebar opens
btn.setAttribute('aria-expanded', 'true');

// When sidebar closes
btn.setAttribute('aria-expanded', 'false');
```

### Form Control Fallbacks
```javascript
// Ensure all inputs have labels for screen readers
const controls = document.querySelectorAll('input, select, textarea');
controls.forEach(ctrl => {
  if (!ctrl.getAttribute('aria-label')) {
    const label = document.querySelector(`label[for="${ctrl.id}"]`);
    const fallback = label?.textContent || ctrl.name || 'input';
    ctrl.setAttribute('aria-label', fallback);
  }
});
```

---

## Performance Optimization

### GPU Acceleration
```css
/* Enable hardware acceleration for smooth animations */
.app-sidebar {
  will-change: transform;
  transform: translateX(-100%);
  transition: transform 0.35s ease;
}
```

### Why Transform Over Width?
- **Transform**: GPU-accelerated, 60fps, no layout recalculation
- **Width**: CPU-based, may cause repaints, janky on slow devices

### Timing Values
- **0.35s**: Optimal for perception (too fast: jumpy, too slow: laggy)
- **ease**: Natural acceleration/deceleration curve
- **cubic-bezier(0.4, 0, 0.2, 1)**: Material Design easing (alternative)

---

## Browser API Compatibility

### localStorage
```javascript
try {
  localStorage.setItem('key', 'value');
  localStorage.getItem('key');
  localStorage.removeItem('key');
} catch (e) {
  // Private browsing or storage full
  console.warn('localStorage unavailable');
}
```

### Transform Detection
```javascript
// Check if transform is supported
function supportsTransform() {
  const el = document.createElement('div');
  return el.style.transform !== undefined;
}
```

### Mobile Viewport Detection
```javascript
// More sophisticated detection
function isMobileDevice() {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Or by viewport width (current implementation)
function isMobile() {
  return window.innerWidth <= 1024;
}
```

---

## Common Issues & Solutions

### Issue: Sidebar Doesn't Slide
**Cause**: `position: sticky` or `position: relative` with transform
**Solution**: Change to `position: fixed` for mobile overlay

### Issue: Content Overflows Horizontally
**Cause**: Child elements with fixed width or missing box-sizing
**Solution**: Add `box-sizing: border-box` and `max-width: 100%` to children

### Issue: Animation Jank on Mobile
**Cause**: Using `width` instead of `transform`
**Solution**: Use `transform: translateX()` for smooth 60fps animation

### Issue: LocalStorage Blocks Sidebar
**Cause**: Stale `collapsed` state on mobile
**Solution**: Remove localStorage entry on mobile load/resize

### Issue: Overlay Not Clickable
**Cause**: Sidebar z-index too high (1001+) compared to overlay (999)
**Solution**: Ensure sidebar z-index ≤ 1000 and overlay z-index = 999

### Issue: Viewport Width Detection Unreliable
**Cause**: Device pixel ratio or browser chrome
**Solution**: Use `window.innerWidth` instead of `window.outerWidth`

---

## Testing Code Snippets

### Check Mobile State
```javascript
// In DevTools console
console.log('Is Mobile:', window.innerWidth <= 1024);
console.log('Sidebar Classes:', document.querySelector('.app-sidebar').className);
console.log('Body Classes:', document.body.className);
console.log('Transform Value:', getComputedStyle(document.querySelector('.app-sidebar')).transform);
```

### Toggle Sidebar Programmatically
```javascript
// Open sidebar
document.querySelector('.app-sidebar').classList.add('mobile-open');
document.body.classList.add('sidebar-open');

// Close sidebar
document.querySelector('.app-sidebar').classList.remove('mobile-open');
document.body.classList.remove('sidebar-open');
```

### Check Animation Performance
```javascript
// In DevTools Performance tab
// 1. Click "Record"
// 2. Toggle sidebar
// 3. Click "Stop"
// 4. Look for 60fps in timeline
// 5. Check for green (rendering) vs purple (scripting)
```

---

## Migration Checklist

If updating existing code:
- [ ] Update sidebar positioning from `sticky`/`relative` to `fixed`
- [ ] Change animation from `width` to `transform`
- [ ] Add `will-change: transform`
- [ ] Add overlay styling with dark background
- [ ] Add click-to-close overlay handler
- [ ] Add Escape key handler
- [ ] Update aria attributes
- [ ] Test on actual mobile device
- [ ] Verify no horizontal scroll
- [ ] Check localStorage clearing on mobile

---

## Files Summary

| File | Changes | Lines |
|------|---------|-------|
| `includes/sidebar.php` | Media query CSS, overlay styling, reset script | ~180 lines |
| `/Assets/Javascript/animate-ui.js` | Mobile toggle logic, overlay close, Escape key | ~50 lines |

---

**Last Updated**: December 2024
**Version**: 1.0
**Status**: Production Ready

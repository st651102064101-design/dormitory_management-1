# Mobile Testing Guide

## Quick Start

### 1. Enable Mobile View in Browser DevTools
**Chrome/Edge:**
- Press `F12` or `Cmd+Option+I` (Mac)
- Click device toggle icon (top-left of DevTools)
- Select a mobile device or use custom size

**Safari (Mac):**
- Enable Develop menu: Safari → Preferences → Advanced → Check "Show Develop menu"
- Develop → Enter Responsive Design Mode
- Select device or custom size

**Firefox:**
- Press `Ctrl+Shift+M` (Windows) or `Cmd+Shift+M` (Mac)
- Select device from dropdown

### 2. Test Viewport Sizes
Test at these critical breakpoints:
- **320px** (iPhone SE)
- **375px** (iPhone 12/13)
- **390px** (Pixel 7)
- **768px** (iPad)
- **1024px** (iPad Pro) ← Mobile/Desktop transition point
- **1440px+** (Desktop)

---

## Sidebar Toggle Testing

### Test 1: Sidebar Opens Smoothly
**Steps:**
1. Set viewport to 375px width
2. Click hamburger menu button
3. Observe sidebar slides in from left over 0.35 seconds
4. Check dark overlay appears behind sidebar

**Expected Results:**
- ✅ Sidebar animation is smooth (not jumpy)
- ✅ Dark semi-transparent overlay visible
- ✅ Content is still readable through overlay
- ✅ No horizontal scrollbar appears

### Test 2: Overlay Click Closes Sidebar
**Steps:**
1. Open sidebar (as above)
2. Click on the dark overlay area (not on sidebar content)
3. Observe sidebar slides back out

**Expected Results:**
- ✅ Sidebar closes smoothly
- ✅ Overlay disappears
- ✅ Can click on content area that was behind overlay
- ✅ No console errors

### Test 3: Escape Key Closes Sidebar
**Steps:**
1. Open sidebar
2. Press Escape key on keyboard
3. Observe sidebar closes

**Expected Results:**
- ✅ Sidebar closes when Escape pressed
- ✅ Works on mobile viewport
- ✅ Does not close when sidebar is already closed
- ✅ Button aria-expanded updates to "false"

### Test 4: Toggle Button Accessibility
**Steps:**
1. Open DevTools Inspector
2. Select hamburger button element
3. Check attributes in Inspector

**Expected Results:**
- ✅ `aria-label="Toggle sidebar"` present
- ✅ `aria-expanded` toggles between "true" and "false"
- ✅ Button is keyboard focusable (Tab key)

### Test 5: Desktop Behavior Unchanged (≥1024px)
**Steps:**
1. Set viewport to 1440px width
2. Click hamburger button
3. Sidebar should collapse instead of sliding

**Expected Results:**
- ✅ Sidebar collapses to narrow state (not overlay)
- ✅ Content shifts to accommodate collapsed sidebar
- ✅ No dark overlay appears
- ✅ Desktop experience unchanged

---

## Content Responsiveness Testing

### Test 6: No Horizontal Scroll
**Steps:**
1. Set viewport to 375px
2. View each page (Dashboard, Manage News, etc.)
3. Scroll vertically to see all content
4. Try to scroll horizontally

**Expected Results:**
- ✅ No horizontal scrollbar appears
- ✅ Content fits within 375px width
- ✅ Text wraps properly (no overflow)
- ✅ Tables/charts are responsive

### Test 7: Text Wrapping
**Steps:**
1. Set viewport to 320px (narrow phone)
2. Check page titles with long text
3. Check form labels and descriptions
4. Check sidebar navigation items

**Expected Results:**
- ✅ Long titles show ellipsis (...) 
- ✅ Labels wrap to multiple lines if needed
- ✅ No text overflow or hidden content
- ✅ All text is readable

### Test 8: No Blue Left Gap
**Steps:**
1. Set viewport to any mobile size
2. Look at left edge of page content
3. Check for any blue/dark background bleeding through

**Expected Results:**
- ✅ Content area flush against left edge (when sidebar closed)
- ✅ No colored gaps or margins
- ✅ Consistent padding on all sides

### Test 9: Images/Charts Responsive
**Steps:**
1. Navigate to Dashboard with charts
2. Resize viewport from 375px to 768px
3. Observe charts scale properly

**Expected Results:**
- ✅ Charts maintain aspect ratio
- ✅ Charts don't overflow horizontally
- ✅ Labels/legend visible on mobile
- ✅ No fixed-width containers breaking layout

### Test 10: Header Responsive
**Steps:**
1. View any page with page title
2. Make viewport very narrow (320px)
3. Check page title/header

**Expected Results:**
- ✅ Title fits in header without wrapping
- ✅ Long titles cut off with ellipsis (...)
- ✅ Hamburger button still accessible
- ✅ Logo/branding visible

---

## Page-Specific Testing

### Dashboard
- [ ] Charts render and resize responsively
- [ ] Statistics cards stack vertically
- [ ] No fixed-width containers
- [ ] Sidebar toggle accessible

### Manage News
- [ ] Table/list responsive (scrolls vertically, not horizontally)
- [ ] Add/Edit buttons accessible
- [ ] Modal dialogs display properly on mobile
- [ ] Form inputs full width

### Manage Rooms
- [ ] Room grid/list responsive
- [ ] Room images visible on mobile
- [ ] Sidebar toggle doesn't break layout
- [ ] Form submits without page breaking

### Manage Bookings
- [ ] Booking table responsive
- [ ] Date picker accessible on mobile
- [ ] Form fields full width
- [ ] Status indicators visible

### Manage Contracts
- [ ] Contract list responsive
- [ ] Download/print buttons accessible
- [ ] Sidebar toggle works
- [ ] Tables don't require horizontal scroll

### Reports
- [ ] All report pages responsive
- [ ] Filter/search functional on mobile
- [ ] Export buttons accessible
- [ ] Charts/graphs resize properly

---

## Advanced Testing

### Performance Testing
1. Open DevTools → Performance tab
2. Record page interaction:
   - Click hamburger to open sidebar
   - Click overlay to close sidebar
3. Check metrics:
   - FPS: Should stay 60fps during animation
   - Frame rendering: <16ms per frame
   - No layout thrashing

### Accessibility Testing
1. Install WAVE browser extension
2. Run on each page
3. Check for warnings/errors:
   - No missing alt text
   - Proper heading hierarchy
   - Sufficient color contrast
   - Form labels associated with inputs

4. Test keyboard navigation:
   - Tab through all interactive elements
   - Shift+Tab to go backwards
   - Enter/Space to activate buttons
   - Escape to close sidebar/modal

### Touch Testing
1. Use actual mobile device (iOS or Android)
2. Test:
   - Tap hamburger button
   - Tap overlay to close
   - Tap links in sidebar
   - Swipe/scroll content
   - Form input on soft keyboard
   - Responsiveness at actual device size

### Browser Compatibility
Test on:
- iOS Safari (iPad, iPhone)
- Chrome Android
- Firefox Mobile
- Samsung Internet (if available)

---

## Debugging Tips

### Check Console for Errors
1. Open DevTools → Console tab
2. Click menu button
3. Look for any red errors
4. Read debug messages (starts with "Sidebar toggle setup")

### Inspect Element
1. Right-click sidebar element
2. Select "Inspect" 
3. Check:
   - `transform: translateX(...)` property
   - `mobile-open` class presence
   - `sidebar-open` on body element
   - z-index values (sidebar: 1000, overlay: 999)

### Check LocalStorage
1. DevTools → Application tab
2. LocalStorage → current site
3. Check `animateui.sidebar.collapsed`:
   - Should NOT exist on mobile
   - Should exist on desktop with value "true"/"" (false)

### Test Resize
1. Open sidebar on mobile (375px)
2. Slowly drag viewport width to 1025px
3. Watch transition from mobile to desktop behavior
4. Sidebar should switch from overlay to collapse mode

---

## Bug Report Template

If issues found, document:

```
**Device:** iPhone 12 / Desktop / Tablet
**Viewport:** 375px / 1440px / 768px
**Browser:** Safari / Chrome / Firefox
**Page:** Dashboard / Manage News / etc.

**Issue:**
[Description of problem]

**Steps to Reproduce:**
1. 
2. 
3. 

**Expected Behavior:**
[What should happen]

**Actual Behavior:**
[What actually happened]

**Console Error (if any):**
[Error message from DevTools console]

**Screenshots/Video:**
[Attach if helpful]
```

---

## Performance Targets

- **Sidebar Animation**: 0.35s (should feel smooth, not slow)
- **Page Load**: <3 seconds on 4G
- **Interactions**: <100ms response time
- **FPS**: 60fps during animations
- **Mobile Performance Score**: 85+ (Lighthouse)

---

## Notes

- All tests should pass before deploying to production
- Test on both emulator/DevTools AND actual devices
- Different devices may have different screen densities (DPI)
- Touch interactions may differ from mouse/trackpad
- Test with real network conditions (DevTools throttling)
- Test with browser zoom (100%, 125%, 150%)

---

**Last Updated**: December 2024
**Status**: Ready for Testing

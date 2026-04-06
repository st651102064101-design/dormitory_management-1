# No-Reload Logo Update - Debug & Testing Guide

## Implementation Complete ✅

The no-reload logo update flow has been implemented in `Reports/settings/section_images.php` with comprehensive debugging logs.

---

## Testing Steps

### 1. Open Browser Developer Console
- **Windows/Linux**: Press `F12` or `Ctrl+Shift+J`
- **Mac**: Press `Cmd+Option+J`
- Go to the **Console** tab

### 2. Test Upload Flow (Upload New Logo)
1. Navigate to **Settings** > **Images** > **Logo**
2. Click the upload area or select a JPG/PNG file
3. Select a test image file (under 5MB)
4. **Watch the Console** for logs like:
   ```
   [uploadLogoFile] API response: {success: true, filename: "logo_20240604_120000.jpg", message: "..."}
   [uploadLogoFile] Calling syncLogoUiFromFilename with: logo_20240604_120000.jpg
   [syncLogoUiFromFilename] Updating with filename: logo_20240604_120000.jpg
   [forceReloadImage] Image reloaded: /dormitory_management/Public/Assets/Images/logo_20240604_120000.jpg?t=1234567890
   [syncLogoUiFromFilename] Updated #logoPreviewImg
   [syncLogoUiFromFilename] Updated #logoRowImg
   ```

### 3. Test Use-Existing Flow (Apply Existing Logo)
1. In the same **Logo** sheet, select an image from "Select from existing" dropdown
2. Click the **ใช้รูปนี้** button
3. **Watch the Console** for logs like:
   ```
   [applyOldLogo] API response: {success: true, filename: "old_logo.jpg", message: "..."}
   [applyOldLogo] Calling syncLogoUiFromFilename with: old_logo.jpg
   [syncLogoUiFromFilename] Updating with filename: old_logo.jpg
   [forceReloadImage] Image reloaded: /dormitory_management/Public/Assets/Images/old_logo.jpg?t=1234567890
   ```

---

## Expected Behavior

### ✅ Success Indicators
- **Preview image changes** in the sheet immediately (no page reload)
- **Thumbnail** in settings row changes immediately
- **Sidebar logo** updates immediately
- **Filename text** under "Current Logo" updates
- **Success toast** appears briefly
- **Sheet closes automatically** after 0.5 seconds
- **Old logo dropdown** is cleared (empty state)
- **Console shows all sync logs** starting with `[syncLogoUiFromFilename]`

### ❌ Problem Indicators  
1. **Image doesn't change visually but console shows success logs**
   - Browser cache issue → Try `Ctrl+Shift+Delete` (Clear Browse Data) + hard refresh `Ctrl+F5`
   - Or add `?cache-buster=` to image URLs in HTML

2. **Console shows `#logoPreviewImg not found`**
   - HTML structure mismatch → Check that `id="logoPreviewImg"` exists in line 56 of section_images.php
   
3. **Console shows `No filename in result!`**
   - API not returning filename → Check `save_system_settings.php` line 271 and 90 include `filename` field

4. **Page reloads automatically**
   - Old code still in use → Browser cache → Clear cache and refresh
   - Hard refresh: `Ctrl+F5` (Windows) or `Cmd+Shift+R` (Mac)

5. **Sheet doesn't close**
   - Check if `sheet-logo` element exists
   - Check if `#sheet-logo` has `active` class being removed

6. **API call fails (JSON parse error)**
   - Check `Manage/save_system_settings.php` for BOM or extra whitespace at start
   - Verify `Content-Type: application/json` is being set

---

## Key Code Locations

| Function | File | Line | Purpose |
|----------|------|------|---------|
| `buildImageUrl()` | section_images.php | 112 | Create path-safe image URLs |
| `forceReloadImage()` | section_images.php | 126 | Bypass browser cache by preloading image |
| `syncLogoUiFromFilename()` | section_images.php | 141 | Update all UI elements without reload |
| `uploadLogoFile()` | section_images.php | 450 | Handle logo upload, call sync instead of reload |
| `applyOldLogo()` | section_images.php | 510 | Handle existing logo apply, call sync instead of reload |

---

## Console Log Prefixes

- `[buildImageUrl]` - URL construction logs (rare)
- `[forceReloadImage]` - Image preload and reload logs
- `[syncLogoUiFromFilename]` - Main UI sync flow logs
- `[uploadLogoFile]` - Upload handler logs
- `[applyOldLogo]` - Existing logo apply logs

---

## If Still Not Working

1. **Hard refresh your browser**
   - Windows: `Ctrl+Shift+Delete` then `Ctrl+F5`
   - Mac: `Cmd+Shift+Delete` then `Cmd+Shift+R`

2. **Check Network Tab** in DevTools
   - When you upload/apply, does the request to `save_system_settings.php` complete with 200 status?
   - Does the response have `"filename"` field?

3. **Check Elements Tab** in DevTools
   - Right-click on the logo image in the sheet → Inspect
   - Verify the `src` attribute changes after upload/apply
   - Look for `<img id="logoPreviewImg" src="...?t=1234567890">`

4. **Clear All Cache** (Nuclear Option)
   - Delete `Public/Assets/Images/*` old test files
   - Clear browser cache completely
   - Close and reopen browser
   - Try uploading a brand new test image

---

## Debugging Commands

Run these in the Console to manually test:

```javascript
// Test URL builder
buildImageUrl('logo_test.jpg')
// Output: /dormitory_management/Public/Assets/Images/logo_test.jpg

// Test with timestamp
buildImageUrl('logo_test.jpg') + '?t=' + Date.now()

// Manually trigger sync (replace with actual filename)
syncLogoUiFromFilename('logo_20240604_120000.jpg')

// Check if elements exist
typeof document.getElementById('logoPreviewImg')  // should be 'object'
typeof document.getElementById('logoRowImg')      // should be 'object'
```

---

## Summary

The no-reload implementation:
- ✅ Extracts helper functions (`buildImageUrl`, `forceReloadImage`, `syncLogoUiFromFilename`)
- ✅ Replaces `window.location.reload()` calls with DOM updates
- ✅ Includes comprehensive console debugging
- ✅ Uses cache-busting timestamps to force fresh images
- ✅ Auto-closes sheet after success
- ✅ Handles errors gracefully

**Report any issues with console log output for faster debugging!**

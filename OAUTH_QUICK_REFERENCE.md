# 📱 OAuth Configuration - Quick Reference Card

## 🎯 Your Setup
```
Domain      : project.3bbddns.com
Protocol    : HTTPS (port 443 - standard)
Project Path: /dormitory_management
```

## 📍 Callback URLs
```
LINE   → https://project.3bbddns.com/dormitory_management/line_callback.php
Google → https://project.3bbddns.com/dormitory_management/google_callback.php
```

## ✅ Verification Checklist

### 1. Browser Access
- [ ] Open debug page: `https://project.3bbddns.com/dormitory_management/debug_oauth.php`
- [ ] Page loads without errors
- [ ] Shows "Configuration Settings" section

### 2. Configuration Values
- [ ] SITE_HOST shows: `project.3bbddns.com`
- [ ] SITE_PROTOCOL shows: `https`
- [ ] LINE Login Callback URI matches above

### 3. LINE Developers Console
- [ ] Logged into [LINE Developers](https://developers.line.biz/console/)
- [ ] Selected correct Channel
- [ ] Went to OAuth Settings
- [ ] Added redirect URI: `https://project.3bbddns.com/dormitory_management/line_callback.php`
- [ ] Clicked Save/Refresh

### 4. Test LINE Login
- [ ] Open: `https://project.3bbddns.com/dormitory_management/Public/booking.php`
- [ ] Click "ผูกบัญชี LINE"
- [ ] Redirects to LINE OAuth (no errors)
- [ ] Can complete LINE linking

### 5. Google Console (if used)
- [ ] Logged into [Google Cloud Console](https://console.cloud.google.com/)
- [ ] Selected your OAuth 2.0 Client
- [ ] Added to redirect URIs: `https://project.3bbddns.com/dormitory_management/google_callback.php`
- [ ] Clicked Save

---

## 🔧 Troubleshooting

| Error | Cause | Solution |
|-------|-------|----------|
| 400 Bad Request | URI mismatch | Check debug_oauth.php, ensure exact match |
| ERR_SSL_PROTOCOL_ERROR | HTTPS failed | Already using HTTP - clear cache |
| Blank page | Cache issue | Clear browser cache, try incognito |
| URI still wrong | Config not reloaded | Restart XAMPP or reload PHP |

---

## 📚 Documentation
- **[OAUTH_SETUP_SUMMARY.md](OAUTH_SETUP_SUMMARY.md)** - Full setup guide
- **[debug_oauth.php](debug_oauth.php)** - Configuration verification tool
- **[config.php](config.php)** - Main configuration file (lines 8-25)
- **[SSL_TROUBLESHOOTING.md](SSL_TROUBLESHOOTING.md)** - HTTPS/SSL fixes

---

## 🚀 Next: Production HTTPS

Already using HTTPS on port 443 with valid SSL certificate! 

If you need to use a custom port with HTTPS:
1. Get SSL certificate for that port
2. Configure XAMPP/Apache for custom HTTPS port
3. Update config.php with the custom domain:port
4. Re-test everything

---

**Last Updated:** 12 April 2025  
**Status:** ✅ HTTPS Setup Complete (port 443)

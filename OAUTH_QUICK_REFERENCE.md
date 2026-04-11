# 📱 OAuth Configuration - Quick Reference Card

## 🎯 Your Setup
```
Domain      : project.3bbddns.com:36140
Protocol    : HTTP (not HTTPS - due to SSL issues)
Project Path: /dormitory_management
```

## 📍 Callback URLs
```
LINE   → http://project.3bbddns.com:36140/dormitory_management/line_callback.php
Google → http://project.3bbddns.com:36140/dormitory_management/google_callback.php
```

## ✅ Verification Checklist

### 1. Browser Access
- [ ] Open debug page: `http://project.3bbddns.com:36140/dormitory_management/debug_oauth.php`
- [ ] Page loads without errors
- [ ] Shows "Configuration Settings" section

### 2. Configuration Values
- [ ] SITE_HOST shows: `project.3bbddns.com:36140`
- [ ] SITE_PROTOCOL shows: `http`
- [ ] LINE Login Callback URI matches above

### 3. LINE Developers Console
- [ ] Logged into [LINE Developers](https://developers.line.biz/console/)
- [ ] Selected correct Channel
- [ ] Went to OAuth Settings
- [ ] Added redirect URI: `http://project.3bbddns.com:36140/dormitory_management/line_callback.php`
- [ ] Clicked Save/Refresh

### 4. Test LINE Login
- [ ] Open: `http://project.3bbddns.com:36140/dormitory_management/Public/booking.php`
- [ ] Click "ผูกบัญชี LINE"
- [ ] Redirects to LINE OAuth (no 400 error)
- [ ] Can complete LINE linking

### 5. Google Console (if used)
- [ ] Logged into [Google Cloud Console](https://console.cloud.google.com/)
- [ ] Selected your OAuth 2.0 Client
- [ ] Added to redirect URIs: `http://project.3bbddns.com:36140/dormitory_management/google_callback.php`
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

When ready for production:
1. Get SSL certificate (Let's Encrypt, AWS, etc.)
2. Configure HTTPS on port 36140 or reverse proxy
3. Change `config.php`:
   ```php
   define('SITE_PROTOCOL', 'https');
   ```
4. Update URIs in LINE/Google Developers Console
5. Re-test everything

See [SSL_TROUBLESHOOTING.md](SSL_TROUBLESHOOTING.md) for details.

---

**Last Updated:** 12 April 2025  
**Status:** ✅ HTTP Setup Complete

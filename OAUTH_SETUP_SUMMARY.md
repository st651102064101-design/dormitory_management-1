# ✅ LINE OAuth Configuration - Summary

## 🎯 Current Status
✅ System is now configured to work with `project.3bbddns.com:36140`

### Configuration Applied:
```php
define('SITE_HOST', 'project.3bbddns.com:36140');
define('SITE_PROTOCOL', 'http');
```

---

## 🚀 Next Steps

### Step 1️⃣: Access Debug Page (Verify Configuration)
Open this URL in your browser:
```
http://project.3bbddns.com:36140/dormitory_management/debug_oauth.php
```

✅ You should see:
- Configuration Settings
- Server Information
- **LINE Login Callback URI** (copy this!)
- **Google OAuth Callback URI** (copy this!)

---

### Step 2️⃣: Register Redirect URIs in LINE Developers Console

1. Go to [LINE Developers Console](https://developers.line.biz/console/)
2. Select your Channel
3. Go to **Channel Settings** → **OAuth Settings**
4. Find **Redirect URI** field
5. Add the URI from debug_oauth.php:
   ```
   http://project.3bbddns.com:36140/dormitory_management/line_callback.php
   ```
6. **Save**

---

### Step 3️⃣: Register Redirect URI in Google Cloud Console (if using)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Go to **APIs & Services** → **Credentials**
3. Find your OAuth 2.0 Client ID
4. Click **Edit**
5. Add to **Authorized redirect URIs**:
   ```
   http://project.3bbddns.com:36140/dormitory_management/google_callback.php
   ```
6. **Save**

---

### Step 4️⃣: Test LINE Login
1. Go to: `http://project.3bbddns.com:36140/dormitory_management/Public/booking.php`
2. Click "ผูกบัญชี LINE" button
3. Should redirect to LINE OAuth without errors ✅

---

## 📋 Important Notes

### HTTP vs HTTPS
- **Currently using:** HTTP (due to SSL/TLS issues on port 36140)
- **For Production:** You should use HTTPS
  - See [SSL_TROUBLESHOOTING.md](SSL_TROUBLESHOOTING.md) for options
  - Options: Install SSL cert, Use reverse proxy, or fix self-signed cert

### URL Must Match Exactly
- ⚠️ Protocol must match: `http://` vs `https://`
- ⚠️ Domain must match: `project.3bbddns.com`
- ⚠️ Port must match: `:36140`
- ⚠️ Path must match: `/dormitory_management/line_callback.php`

If any part is different → **400 Bad Request Error**

---

## 📁 Related Documents
- [LINE_OAUTH_SETUP_GUIDE.md](LINE_OAUTH_SETUP_GUIDE.md) - Detailed setup guide
- [SSL_TROUBLESHOOTING.md](SSL_TROUBLESHOOTING.md) - SSL/TLS issues & fixes
- [debug_oauth.php](debug_oauth.php) - Debug tool to verify configuration

---

## ✅ Checklist
- [ ] Accessed debug_oauth.php successfully
- [ ] Copied LINE Login Callback URI
- [ ] Registered URI in LINE Developers Console
- [ ] Copied Google OAuth Callback URI (if using)
- [ ] Registered URI in Google Cloud Console (if using)
- [ ] Tested LINE Login button
- [ ] LINE OAuth redirects without errors
- [ ] Ready for production (consider HTTPS)

---

**Need Help?** Share screenshot from debug_oauth.php if you encounter issues

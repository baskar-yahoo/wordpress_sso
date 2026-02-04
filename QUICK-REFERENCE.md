# WordPress SSO Integration - Quick Reference

## 🚀 Quick Start

### Prerequisites
- WordPress with WP OAuth Server plugin installed
- Webtrees 2.x installed
- PHP 8.0+ with session support
- HTTPS enabled (required for OAuth)

### Installation (5 Minutes)

1. **Copy module to Webtrees:**
   ```powershell
   Copy-Item -Recurse wordpress_sso c:\xampp\htdocs\familytree\modules_v4\
   ```

2. **Configure WordPress OAuth:**
   - Go to: WP Admin → WP OAuth Server → Clients
   - Click "Add New Client"
   - Name: `Webtrees SSO`
   - Redirect URI: `https://yourdomain.com/familytree/wordpress-sso/callback`
   - Grant Type: `Authorization Code`
   - Enable PKCE: ✅ Yes
   - Save and copy Client ID and Secret

3. **Configure Webtrees:**
   - Go to: Control Panel → Modules → WordPress SSO
   - Enable module
   - Enter Client ID and Secret
   - Set URLs:
     - Authorize: `https://yourdomain.com/oauth/authorize`
     - Access Token: `https://yourdomain.com/oauth/token`
     - User Info: `https://yourdomain.com/oauth/me`
     - Logout: `https://yourdomain.com/wp-login.php?action=logout`
   - Save

4. **Test:**
   - Log out of Webtrees
   - Visit Webtrees URL
   - Should redirect to WordPress login
   - After login, should return to Webtrees (logged in)
   - Click Logout → Should log out of both systems

---

## 🔐 Security Features

| Feature | Status | Description |
|---------|--------|-------------|
| **OAuth 2.0 + PKCE** | ✅ Enabled | Prevents authorization code interception |
| **One-Time Tokens** | ✅ Enabled | Logout tokens expire after single use (60s) |
| **Nonce Protection** | ✅ Enabled | WordPress nonce prevents CSRF |
| **Timing-Safe Comparison** | ✅ Enabled | Uses `hash_equals()` to prevent timing attacks |
| **Secure Random** | ✅ Enabled | Uses `random_bytes(32)` for cryptographic security |
| **Error Sanitization** | ✅ Enabled | No path disclosure in error messages |
| **Session Security** | ✅ Enabled | HttpOnly, Secure, SameSite cookies |

---

## 📁 File Structure

```
wordpress_sso/
├── module.php                              # Module entry point
├── composer.json                           # Dependencies
├── sso_logout.php                          # ⭐ NEW: Logout bridge script
├── README.md                               # Main documentation
├── DEPLOYMENT-CHECKLIST.md                 # ⭐ NEW: Production deployment guide
├── AUTHENTICATION-FLOW.md                  # ⭐ NEW: Complete auth flow documentation
├── SECURITY-ANALYSIS.md                    # Security review
├── src/
│   ├── WordPressSsoModule.php              # ✏️ UPDATED: Added logout route
│   ├── Http/
│   │   ├── WordPressSsoLoginAction.php     # OAuth login handler
│   │   ├── WordPressSsoLogout.php          # ⭐ UPDATED: Security-hardened logout
│   │   └── WordPressSsoHomePage.php        # Auto-login on homepage
│   ├── Helpers/
│   │   └── MenuHelper.php                  # ⭐ NEW: Menu filtering logic
│   ├── Services/
│   │   └── DebugLogger.php                 # Debug logging
│   └── Exceptions/                         # Custom exceptions
├── resources/
│   └── views/
│       ├── settings.phtml                  # Admin UI
│       └── examples/
│           └── menu-integration-example.phtml  # ⭐ NEW: Menu template example
└── tests/
    └── Unit/
        └── WordPressSsoLogoutTest.php      # ⭐ NEW: Unit tests
```

**Legend:**
- ⭐ NEW: Newly created file
- ✏️ UPDATED: Modified existing file

---

## 🔄 Authentication Flow

### Login Flow
```
User → Webtrees (not logged in)
  ↓
Redirect to WordPress OAuth
  ↓
WordPress login/consent
  ↓
Redirect back with code
  ↓
Exchange code for token
  ↓
Fetch user info
  ↓
Create/update Webtrees user
  ↓
Login to Webtrees ✅
```

### Logout Flow (NEW - Security Hardened)
```
User clicks Logout in Webtrees
  ↓
Generate secure token (256-bit)
  ↓
Logout from Webtrees
  ↓
Redirect to sso_logout.php with token
  ↓
Validate token (timing-safe, one-time use)
  ↓
Load WordPress environment
  ↓
Generate nonce-protected logout URL
  ↓
Redirect to WordPress logout
  ↓
WordPress logs out and redirects home
  ↓
User at WordPress home (logged out) ✅
```

---

## 🎨 Menu Integration

### Step 1: Add CSS Classes to WordPress Menu Items

In **WordPress Admin → Appearance → Menus:**

1. Click "Screen Options" (top right)
2. Enable "CSS Classes"
3. Find your Login menu item
4. Add CSS class: `menu-item-login`
5. Find your Logout menu item
6. Add CSS class: `menu-item-logout`
7. Save menu

### Step 2: Use MenuHelper in Your Theme

```php
use Webtrees\WordPressSso\Helpers\MenuHelper;

// Get menu from WordPress
$wp_menu_items = $wp_header_data['menu_items'];

// Filter based on login state
$filtered_menu = MenuHelper::filterMenuTree($wp_menu_items);

// Display menu
foreach ($filtered_menu as $item) {
    echo '<a href="' . $item['url'] . '">' . $item['title'] . '</a>';
}
```

**Result:**
- ✅ Login shown ONLY when logged out
- ✅ Logout shown ONLY when logged in
- ✅ Seamless user experience

---

## 🐛 Common Issues

### Issue: "Token validation failed"
**Solution:** Token expired (60s limit). Click logout again.

### Issue: "WordPress not found"
**Solution:** Check wp-load.php path in `sso_logout.php` candidates array.

### Issue: Both Login/Logout show in menu
**Solution:** Add CSS classes `menu-item-login` and `menu-item-logout` to WordPress menu items.

### Issue: Redirect loop on login
**Solution:** Check OAuth client redirect URI matches exactly (including `/callback`).

### Issue: "Nonce verification failed"
**Solution:** Ensure WordPress user is logged in before logout attempt.

---

## 📊 Configuration Options

### config.ini.php (Recommended for Production)

```ini
; Enable/disable SSO
WordPress_SSO_enabled="1"

; OAuth Credentials
WordPress_SSO_clientId="your_client_id_here"
WordPress_SSO_clientSecret="your_client_secret_here"

; OAuth URLs
WordPress_SSO_urlAuthorize="https://yourdomain.com/oauth/authorize"
WordPress_SSO_urlAccessToken="https://yourdomain.com/oauth/token"
WordPress_SSO_urlResourceOwner="https://yourdomain.com/oauth/me"
WordPress_SSO_urlLogout="https://yourdomain.com/wp-login.php?action=logout"

; Security
WordPress_SSO_pkceMethod="S256"

; User Management
WordPress_SSO_allowCreation="0"  # 0=disabled, 1=enabled (requires admin approval)
WordPress_SSO_syncEmail="1"      # Sync email from WordPress to Webtrees

; Debug (DISABLE in production)
WordPress_SSO_debugEnabled="0"   # 0=disabled, 1=enabled
```

---

## 🧪 Testing

### Manual Tests

```powershell
# Test 1: Login Flow
# - Log out of both systems
# - Visit Webtrees URL
# - Should redirect to WordPress
# - Login to WordPress
# - Should return to Webtrees (logged in)

# Test 2: Logout Flow
# - Click Logout in Webtrees
# - Should log out of both systems
# - Should end at WordPress home

# Test 3: Menu Display
# - When logged out: Only Login shows
# - When logged in: Only Logout shows

# Test 4: Token Security
# - Copy logout URL with token
# - Use it twice - second use should fail
# - Wait 61 seconds - token should expire
```

### Automated Tests

```powershell
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run tests
.\vendor\bin\phpunit tests\Unit\WordPressSsoLogoutTest.php
```

---

## 📞 Support

| Resource | Location |
|----------|----------|
| **Full Documentation** | [README.md](README.md) |
| **Deployment Guide** | [DEPLOYMENT-CHECKLIST.md](DEPLOYMENT-CHECKLIST.md) |
| **Auth Flow Details** | [AUTHENTICATION-FLOW.md](AUTHENTICATION-FLOW.md) |
| **Security Analysis** | [SECURITY-ANALYSIS.md](SECURITY-ANALYSIS.md) |
| **Debug Logs** | `data/sso_debug.txt` |
| **Security Logs** | `data/sso_security.log` |

---

## ⚡ Performance Tips

1. **Enable PHP OPcache** for faster execution
2. **Use file-based config** (`config.ini.php`) instead of database
3. **Cache WordPress menus** (1 hour TTL)
4. **Enable Redis/Memcached** for session storage
5. **Use HTTP/2** for faster redirects

---

## 🔒 Production Checklist

- [ ] HTTPS enabled and enforced
- [ ] Debug logging disabled (`debugEnabled="0"`)
- [ ] Strong OAuth client secret (32+ characters)
- [ ] Secure cookie flags enabled in php.ini
- [ ] File permissions set correctly (644 for PHP files)
- [ ] Error logs monitored
- [ ] Backup created before deployment
- [ ] Tested in staging environment
- [ ] Security scan completed
- [ ] Documentation updated

---

## 📝 Change Log

### Version 2.0.0 (February 4, 2026)

**New Features:**
- ⭐ Security-hardened logout with token authentication
- ⭐ Menu filtering helper for Login/Logout display
- ⭐ Comprehensive documentation suite

**Improvements:**
- ✏️ Dynamic URL construction in logout handler
- ✏️ Enhanced error handling with security logging
- ✏️ One-time use tokens with expiration
- ✏️ WordPress nonce integration for seamless logout

**Security:**
- 🔒 Timing-safe token comparison
- 🔒 Cryptographically secure random tokens
- 🔒 Token expiration (60 seconds)
- 🔒 No path disclosure in errors

**Files Changed:**
- `src/Http/WordPressSsoLogout.php` - Complete rewrite
- `src/WordPressSsoModule.php` - Added logout route
- `sso_logout.php` - New security-hardened bridge script
- `src/Helpers/MenuHelper.php` - New menu filtering utility

---

**Version:** 2.0.0  
**License:** GPL v3  
**Requires:** Webtrees 2.x, PHP 8.0+, WordPress 5.x+

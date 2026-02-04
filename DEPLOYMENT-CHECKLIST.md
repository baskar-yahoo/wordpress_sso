# WordPress-Webtrees SSO Deployment Checklist

## 🚀 Production Deployment Guide

### Pre-Deployment Security Review

#### ✅ **1. Security Hardening**

- [ ] **Token Security**
  - [ ] Verify `random_bytes(32)` is being used for token generation
  - [ ] Confirm `hash_equals()` is used for token comparison (prevents timing attacks)
  - [ ] Test token expiration (60 seconds timeout)
  - [ ] Verify tokens are consumed after single use

- [ ] **File Permissions**
  - [ ] Set `sso_logout.php` to `644` (read-only for web)
  - [ ] Set `data/` directory to `755` with proper ownership
  - [ ] Verify `data/sso_security.log` is writable but not web-accessible
  - [ ] Ensure `data/config.ini.php` is protected by `.htaccess`

- [ ] **Error Handling**
  - [ ] Remove or disable debug logging in production
  - [ ] Verify error messages don't expose paths or system info
  - [ ] Test all error scenarios (missing WP, invalid tokens, etc.)
  - [ ] Confirm logs go to secure location

- [ ] **Session Security**
  - [ ] Enable secure session cookies (`session.cookie_secure = 1` in php.ini)
  - [ ] Set `session.cookie_httponly = 1`
  - [ ] Configure `session.cookie_samesite = 'Lax'` or `'Strict'`
  - [ ] Verify session regeneration on logout

#### ✅ **2. Code Review**

- [ ] **WordPress SSO Module**
  - [ ] Review [WordPressSsoModule.php](src/WordPressSsoModule.php)
    - [ ] Verify `SSO_LOGOUT_ROUTE` constant is defined
    - [ ] Confirm logout route is registered in `boot()`
    - [ ] Check container injection of `WordPressSsoLogout`
  
- [ ] **Logout Handler**
  - [ ] Review [WordPressSsoLogout.php](src/Http/WordPressSsoLogout.php)
    - [ ] Confirm token generation before session destruction
    - [ ] Verify dynamic URL construction
    - [ ] Check debug logging is conditional
    - [ ] Ensure `parent::handle()` is called first

- [ ] **Bridge Script**
  - [ ] Review [sso_logout.php](sso_logout.php)
    - [ ] Verify all security validations are in place
    - [ ] Confirm wp-load.php path detection works
    - [ ] Test error handling and fallbacks
    - [ ] Check WordPress function existence validation

- [ ] **Menu Helper**
  - [ ] Review [MenuHelper.php](src/Helpers/MenuHelper.php)
    - [ ] Test login/logout item filtering
    - [ ] Verify recursive menu filtering
    - [ ] Check active state detection

#### ✅ **3. Configuration**

- [ ] **WordPress Settings**
  - [ ] Install and activate WP OAuth Server plugin
  - [ ] Create OAuth client for Webtrees
  - [ ] Configure redirect URI: `https://yourdomain.com/familytree/wordpress-sso/callback`
  - [ ] Enable PKCE (S256 method recommended)
  - [ ] Set appropriate scopes (basic, email)

- [ ] **Webtrees Settings**
  - [ ] Navigate to: Control Panel → Modules → WordPress SSO
  - [ ] Enable "Seamless SSO"
  - [ ] Configure OAuth credentials
  - [ ] Set WordPress URLs (authorize, token, user info, logout)
  - [ ] Test configuration

- [ ] **config.ini.php (Optional - Recommended)**
  ```ini
  ; WordPress SSO Configuration
  WordPress_SSO_enabled="1"
  WordPress_SSO_clientId="YOUR_CLIENT_ID"
  WordPress_SSO_clientSecret="YOUR_CLIENT_SECRET"
  WordPress_SSO_urlAuthorize="https://yourdomain.com/oauth/authorize"
  WordPress_SSO_urlAccessToken="https://yourdomain.com/oauth/token"
  WordPress_SSO_urlResourceOwner="https://yourdomain.com/oauth/me"
  WordPress_SSO_urlLogout="https://yourdomain.com/wp-login.php?action=logout"
  WordPress_SSO_pkceMethod="S256"
  WordPress_SSO_syncEmail="1"
  WordPress_SSO_allowCreation="0"
  WordPress_SSO_debugEnabled="0"
  ```

#### ✅ **4. Testing**

- [ ] **Functional Tests**
  - [ ] Test login flow (WordPress → Webtrees)
  - [ ] Test logout flow (Webtrees → WordPress → Home)
  - [ ] Verify menu items show/hide correctly
  - [ ] Test user creation (if enabled)
  - [ ] Test email synchronization
  - [ ] Verify session cleanup

- [ ] **Security Tests**
  - [ ] Try direct access to `sso_logout.php` without token
  - [ ] Test with expired token
  - [ ] Test token reuse (should fail)
  - [ ] Test with tampered token
  - [ ] Verify CSRF protection
  - [ ] Test logout without being logged in

- [ ] **Edge Cases**
  - [ ] Test with WordPress in maintenance mode
  - [ ] Test with invalid OAuth credentials
  - [ ] Test with blocked network requests
  - [ ] Test logout during active session in both systems
  - [ ] Test concurrent logins from different devices

- [ ] **Browser Compatibility**
  - [ ] Chrome/Edge
  - [ ] Firefox
  - [ ] Safari
  - [ ] Mobile browsers

#### ✅ **5. Performance**

- [ ] **Caching**
  - [ ] Verify relationship caching works (WebtreesSvajana theme)
  - [ ] Test WordPress menu caching
  - [ ] Check OAuth token caching
  - [ ] Monitor session storage size

- [ ] **Optimization**
  - [ ] Enable PHP OPcache
  - [ ] Configure WordPress object cache
  - [ ] Minimize external API calls
  - [ ] Test with realistic user load

#### ✅ **6. Monitoring**

- [ ] **Logging Setup**
  - [ ] Configure `sso_security.log` monitoring
  - [ ] Set up log rotation
  - [ ] Create alerts for suspicious activity
  - [ ] Monitor `sso_debug.txt` (if enabled)

- [ ] **Metrics to Track**
  - [ ] Login success rate
  - [ ] Logout completion rate
  - [ ] Average login time
  - [ ] Failed authentication attempts
  - [ ] Token validation failures

#### ✅ **7. Documentation**

- [ ] **User Documentation**
  - [ ] Create login/logout guide
  - [ ] Document menu behavior
  - [ ] Explain user profile synchronization
  - [ ] FAQ for common issues

- [ ] **Admin Documentation**
  - [ ] Module configuration guide
  - [ ] Troubleshooting steps
  - [ ] Security incident response
  - [ ] Rollback procedures

---

## 📋 Deployment Steps

### Step 1: Backup

```powershell
# Backup Webtrees
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
Copy-Item -Path "c:\xampp\htdocs\familytree" -Destination "c:\xampp\backups\familytree_$timestamp" -Recurse

# Backup WordPress
Copy-Item -Path "c:\xampp\htdocs\svajana" -Destination "c:\xampp\backups\svajana_$timestamp" -Recurse

# Backup database
mysqldump -u root -p webtrees_db > "c:\xampp\backups\webtrees_db_$timestamp.sql"
mysqldump -u root -p wordpress_db > "c:\xampp\backups\wordpress_db_$timestamp.sql"
```

### Step 2: Deploy Files

```powershell
# Deploy WordPress SSO Module updates
Copy-Item -Path ".\src\Http\WordPressSsoLogout.php" -Destination "c:\xampp\htdocs\familytree\modules_v4\wordpress_sso\src\Http\" -Force
Copy-Item -Path ".\src\WordPressSsoModule.php" -Destination "c:\xampp\htdocs\familytree\modules_v4\wordpress_sso\src\" -Force
Copy-Item -Path ".\sso_logout.php" -Destination "c:\xampp\htdocs\familytree\modules_v4\wordpress_sso\" -Force
Copy-Item -Path ".\src\Helpers\MenuHelper.php" -Destination "c:\xampp\htdocs\familytree\modules_v4\wordpress_sso\src\Helpers\" -Force

# Set proper permissions
icacls "c:\xampp\htdocs\familytree\modules_v4\wordpress_sso\sso_logout.php" /grant "IIS_IUSRS:(R)"
```

### Step 3: Configure

1. **Update config.ini.php** (if using file-based config)
   - Add WordPress SSO settings
   - Set `debugEnabled="0"` for production

2. **Configure Module UI**
   - Go to: Control Panel → Modules → WordPress SSO
   - Verify all settings
   - Save configuration

3. **Test in Staging** (if available)
   - Deploy to staging environment first
   - Run full test suite
   - Get user acceptance

### Step 4: Production Deployment

1. **Enable Maintenance Mode**
   ```powershell
   # WordPress maintenance
   Copy-Item -Path "c:\xampp\htdocs\svajana\maintenance.html" -Destination "c:\xampp\htdocs\svajana\.maintenance"
   
   # Webtrees maintenance (add to config.ini.php)
   # offline="1"
   ```

2. **Deploy Changes**
   - Upload all modified files
   - Clear all caches
   - Restart PHP-FPM/Apache

3. **Verification**
   - Test logout flow
   - Verify menu display
   - Check logs for errors

4. **Disable Maintenance Mode**
   ```powershell
   Remove-Item "c:\xampp\htdocs\svajana\.maintenance"
   # Remove offline="1" from config.ini.php
   ```

### Step 5: Post-Deployment

- [ ] Monitor logs for 24 hours
- [ ] Check error rates
- [ ] Verify user feedback
- [ ] Document any issues
- [ ] Update change log

---

## 🔧 Troubleshooting

### Issue: Logout doesn't work

**Symptoms:**
- User clicks logout but stays logged in
- Redirect loop occurs
- Token validation fails

**Solutions:**
1. Check `sso_security.log` for token errors
2. Verify session is being created properly
3. Confirm `sso_logout.php` is accessible
4. Test WordPress `wp_logout_url()` function
5. Clear browser cookies and retry

### Issue: Menu items don't filter

**Symptoms:**
- Both Login and Logout show simultaneously
- Menu items missing

**Solutions:**
1. Verify `MenuHelper::filterMenuTree()` is called
2. Check menu item CSS classes in WordPress
3. Confirm `Auth::check()` returns correct state
4. Add debug output to view template
5. Clear menu cache

### Issue: Token expired error

**Symptoms:**
- "Token expired" in security log
- Logout fails after delay

**Solutions:**
1. Increase token timeout if needed (currently 60s)
2. Check server time synchronization
3. Verify `$_SESSION['webtrees_logout_time']` is set correctly

---

## 📞 Support Resources

- **Documentation:** [README.md](README.md)
- **Security Issues:** Check [SECURITY-ANALYSIS.md](SECURITY-ANALYSIS.md)
- **Authentication Flow:** See [AUTHENTICATION-FLOW.md](AUTHENTICATION-FLOW.md)
- **Logs:** `data/sso_security.log` and `data/sso_debug.txt`

---

## ✅ Sign-Off

- [ ] **Developer Review:** _____________________ Date: _______
- [ ] **Security Review:** _____________________ Date: _______
- [ ] **QA Testing:** _____________________ Date: _______
- [ ] **Production Deploy:** _____________________ Date: _______
- [ ] **Post-Deploy Check:** _____________________ Date: _______

---

**Version:** 2.0.0  
**Last Updated:** February 4, 2026  
**Deployment Method:** Manual with PowerShell scripts

# WordPress SSO Module - Windows Development Deployment Guide

**Target Environment:** Windows 11 with XAMPP  
**WordPress URL:** http://localhost/svajana  
**Webtrees URL:** http://localhost/svajana/familytree  
**Date:** February 2026

---

## Pre-Deployment Checklist

### Backup Everything First! ⚠️

```powershell
# Open PowerShell as Administrator
cd C:\xampp\htdocs\svajana

# Backup WordPress .htaccess
Copy-Item .htaccess ".htaccess.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Backup Webtrees .htaccess
Copy-Item familytree\.htaccess "familytree\.htaccess.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Backup entire wordpress_sso module
Compress-Archive -Path familytree\modules_v4\wordpress_sso -DestinationPath "wordpress_sso-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').zip"

# Verify backups exist
Get-ChildItem *.backup*, *.zip | Select-Object Name, LastWriteTime, Length
```

### Environment Verification

- [ ] XAMPP Control Panel shows Apache running
- [ ] XAMPP Control Panel shows MySQL running
- [ ] WordPress accessible at http://localhost/svajana
- [ ] Webtrees accessible at http://localhost/svajana/familytree
- [ ] WordPress version: 5.x or higher
- [ ] Webtrees version: 2.4.4
- [ ] PHP version: 7.4+ or 8.0+ (check in XAMPP)
- [ ] LiteSpeed Cache plugin active in WordPress (or other cache plugin)

---

## Deployment Steps

### Step 1: Upload Modified PHP Files

**Source Files:** From your Downloads folder or development location  
**Destination:** `C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso\`

#### Using File Explorer:

1. **WordPressSsoModule.php**
   - Source: `C:\Users\DTE5232\Downloads\WordPressSsoModule.php`
   - Destination: `C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso\src\WordPressSsoModule.php`
   - Action: Replace existing file

2. **WordPressSsoLoginAction.php**
   - Source: `C:\Users\DTE5232\Downloads\WordPressSsoLoginAction.php`
   - Destination: `C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso\src\Http\WordPressSsoLoginAction.php`
   - Action: Replace existing file

3. **WordPressSsoHomePage.php** (NEW)
   - Source: `C:\Users\DTE5232\Downloads\WordPressSsoHomePage.php`
   - Destination: `C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso\src\Http\WordPressSsoHomePage.php`
   - Action: Create new file

#### Using PowerShell:

```powershell
# Copy files from Downloads to wordpress_sso module
$downloads = "C:\Users\DTE5232\Downloads"
$module = "C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso"

Copy-Item "$downloads\WordPressSsoModule.php" "$module\src\WordPressSsoModule.php" -Force
Copy-Item "$downloads\WordPressSsoLoginAction.php" "$module\src\Http\WordPressSsoLoginAction.php" -Force
Copy-Item "$downloads\WordPressSsoHomePage.php" "$module\src\Http\WordPressSsoHomePage.php" -Force

# Verify files copied
Get-ChildItem "$module\src" -Recurse -Include *.php | Select-Object Name, LastWriteTime
```

---

### Step 2: Deploy WordPress .htaccess

**File:** `.htaccess-svajana` (from Downloads)  
**Destination:** `C:\xampp\htdocs\svajana\.htaccess`

```powershell
# PowerShell method
cd C:\xampp\htdocs\svajana

# Backup current .htaccess (if not already done)
Copy-Item .htaccess ".htaccess.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Copy new .htaccess
Copy-Item "C:\Users\DTE5232\Downloads\.htaccess-svajana" .htaccess -Force

# Verify content
Get-Content .htaccess | Select-Object -First 20
```

**Or using File Explorer:**
- Navigate to `C:\Users\DTE5232\Downloads`
- Copy `.htaccess-svajana`
- Navigate to `C:\xampp\htdocs\svajana`
- Paste and rename to `.htaccess` (replace existing)

---

### Step 3: Deploy Webtrees .htaccess

**File:** `.htaccess-familytree` (from Downloads)  
**Destination:** `C:\xampp\htdocs\svajana\familytree\.htaccess`

```powershell
# PowerShell method
cd C:\xampp\htdocs\svajana\familytree

# Backup current .htaccess (if not already done)
Copy-Item .htaccess ".htaccess.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Copy new .htaccess
Copy-Item "C:\Users\DTE5232\Downloads\.htaccess-familytree" .htaccess -Force

# Verify content
Get-Content .htaccess
```

**Note:** Windows may hide files starting with `.` - enable "Show hidden files" in File Explorer:
- View → Options → Change folder and search options
- View tab → Show hidden files, folders, and drives

---

### Step 4: Restart Apache

**XAMPP Control Panel:**
1. Click "Stop" next to Apache
2. Wait 3 seconds
3. Click "Start" next to Apache
4. Verify Apache shows "Running" in green

**Or via PowerShell:**
```powershell
# Stop Apache
& "C:\xampp\apache\bin\httpd.exe" -k stop

# Wait a moment
Start-Sleep -Seconds 3

# Start Apache
& "C:\xampp\apache\bin\httpd.exe" -k start
```

---

### Step 5: Clear All Caches

#### WordPress Cache
```
1. Open browser → http://localhost/svajana/wp-admin
2. If using LiteSpeed Cache:
   - LiteSpeed Cache → Toolbox → Purge → Purge All
3. If using WP Super Cache:
   - Settings → WP Super Cache → Delete Cache
4. If using W3 Total Cache:
   - Performance → Dashboard → Empty All Caches
```

#### Browser Cache
```
1. Press Ctrl+Shift+Delete
2. Select "All time"
3. Check "Cached images and files"
4. Click "Clear data"
```

#### PHP OpCache (XAMPP)
```powershell
# Restart Apache (already done in Step 4)
# This automatically clears OpCache
```

---

### Step 6: Test SSO Flow

#### Test 1: Fresh Login
```
1. Open browser in Incognito/Private mode (Ctrl+Shift+N)
2. Navigate to: http://localhost/svajana
3. Login to WordPress
   - Username: [your WP admin]
   - Password: [your WP password]
4. Verify: Dashboard loads successfully
5. Navigate to: http://localhost/svajana/familytree
6. Expected: Automatic redirect to SSO login
7. Expected: After OAuth, logged into Webtrees
8. Result: Webtrees shows logged-in user name
```

#### Test 2: Session Persistence
```
1. While logged in (from Test 1)
2. Click around Webtrees (view trees, settings, etc.)
3. Refresh page (F5)
4. Expected: User remains logged in
5. Open new tab → http://localhost/svajana/familytree
6. Expected: User already logged in (no redirect)
```

#### Test 3: Cache Bypass Verification
```
1. While logged in
2. Press F12 (Developer Tools)
3. Go to Network tab
4. Refresh page: http://localhost/svajana/familytree
5. Click on "index.php" request
6. Look at Response Headers
7. Should see:
   Cache-Control: no-cache, no-store, must-revalidate
   Pragma: no-cache
   Expires: 0
```

#### Test 4: Logout Flow
```
1. While logged in to Webtrees
2. Click "Sign out"
3. Expected: Redirected to WordPress logout
4. Expected: Logged out from both systems
5. Navigate to: http://localhost/svajana/familytree
6. Expected: Shows "Sign in" option
```

---

### Step 7: Check Debug Logs

```powershell
# View Webtrees SSO debug log (if debug enabled)
Get-Content C:\xampp\htdocs\svajana\familytree\data\sso_debug.txt -Tail 50

# View Apache error log
Get-Content C:\xampp\apache\logs\error.log -Tail 50

# View PHP error log
Get-Content C:\xampp\php\logs\php_error_log.txt -Tail 50
```

**What to look for:**
- ✅ "Login successful" entries
- ✅ "User data retrieved from WordPress"
- ❌ "redirect_uri_mismatch" errors
- ❌ "Class not found" errors
- ❌ PHP fatal errors

---

## Troubleshooting

### Issue: "Class WordPressSsoHomePage not found"

**Cause:** File not copied or in wrong location

**Fix:**
```powershell
# Verify file exists
Test-Path C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso\src\Http\WordPressSsoHomePage.php

# If FALSE, copy file again
Copy-Item "C:\Users\DTE5232\Downloads\WordPressSsoHomePage.php" `
  "C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso\src\Http\WordPressSsoHomePage.php" -Force

# Restart Apache
```

### Issue: User Not Auto-Logging In

**Cause:** Cache serving old page, or WordPress cookie not detected

**Fix:**
```powershell
# 1. Clear all browser cookies for localhost
#    F12 → Application → Cookies → localhost → Delete all

# 2. Clear browser cache (Ctrl+Shift+Delete)

# 3. Verify .htaccess-familytree was deployed
Get-Content C:\xampp\htdocs\svajana\familytree\.htaccess | Select-String "wordpress_logged_in"
# Should return matching line

# 4. Restart Apache
```

### Issue: "Redirect URI Mismatch"

**Cause:** OAuth configuration doesn't match callback URL

**Fix:**
```
1. Go to WordPress Admin → Users → Applications → OAuth Clients
2. Find your webtrees OAuth client
3. Check Redirect URI should be EXACTLY:
   http://localhost/svajana/familytree/index.php?route=/wordpress-sso/callback
4. If different, update and save
5. Clear cache and test again
```

### Issue: Infinite Redirect Loop

**Cause:** Session not persisting, or OAuth state mismatch

**Fix:**
```powershell
# 1. Check session.ini settings
Get-Content C:\xampp\php\php.ini | Select-String "session.cookie"

# Should have:
# session.cookie_path = /
# session.cookie_domain = localhost

# 2. If not correct, edit php.ini:
notepad C:\xampp\php\php.ini

# Find [Session] section, set:
# session.cookie_path = /
# session.cookie_domain = localhost
# session.cookie_httponly = 1

# 3. Restart Apache
```

---

## Rollback Procedure

If deployment causes issues:

```powershell
cd C:\xampp\htdocs\svajana

# 1. Restore WordPress .htaccess
$latest = Get-ChildItem ".htaccess.backup-*" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
Copy-Item $latest.FullName .htaccess -Force

# 2. Restore Webtrees .htaccess
cd familytree
$latest = Get-ChildItem ".htaccess.backup-*" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
Copy-Item $latest.FullName .htaccess -Force

# 3. Restore PHP files from backup zip
cd C:\xampp\htdocs\svajana
$latest = Get-ChildItem "wordpress_sso-backup-*.zip" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
Expand-Archive $latest.FullName -DestinationPath familytree\modules_v4\ -Force

# 4. Restart Apache
& "C:\xampp\apache\bin\httpd.exe" -k restart
```

---

## Post-Deployment Verification

### Verification Checklist

- [ ] WordPress accessible at http://localhost/svajana
- [ ] Webtrees accessible at http://localhost/svajana/familytree
- [ ] Login to WordPress successful
- [ ] Navigate to Webtrees triggers SSO
- [ ] SSO login completes successfully
- [ ] User remains logged in after page refresh
- [ ] No errors in Apache error log
- [ ] No errors in PHP error log
- [ ] No JavaScript console errors (F12)
- [ ] Logout works from both systems

---

## Next Steps

1. **Test thoroughly in development**
2. **Document any custom changes**
3. **Prepare for Linux deployment** (use Linux deployment guide)
4. **Create deployment checklist for production**

---

## Development Environment Configuration

### Recommended php.ini Settings for SSO

```ini
; C:\xampp\php\php.ini

[Session]
session.save_handler = files
session.use_cookies = 1
session.use_only_cookies = 1
session.name = PHPSESSID
session.auto_start = 0
session.cookie_lifetime = 0
session.cookie_path = /
session.cookie_domain = localhost
session.cookie_httponly = 1
session.cookie_secure = 0  ; Set to 1 when using HTTPS
session.cookie_samesite = Lax
session.gc_maxlifetime = 1440
session.gc_probability = 1
session.gc_divisor = 1000
```

### Recommended Apache Settings

File: `C:\xampp\apache\conf\httpd.conf`

```apache
# Enable mod_rewrite (should already be enabled)
LoadModule rewrite_module modules/mod_rewrite.so

# Enable mod_headers
LoadModule headers_module modules/mod_headers.so

# Allow .htaccess overrides
<Directory "C:/xampp/htdocs">
    AllowOverride All
    Require all granted
</Directory>
```

---

## Support Resources

- **Module Documentation:** [README.md](README.md)
- **Linux Deployment:** [DEPLOYMENT-GUIDE-LINUX.md](DEPLOYMENT-GUIDE-LINUX.md)
- **Rollback Plan:** [ROLLBACK-PLAN-LINUX.md](ROLLBACK-PLAN-LINUX.md)
- **OAuth Fix:** [OAUTH_REDIRECT_URI_FIX.md](OAUTH_REDIRECT_URI_FIX.md)

---

**Deployment completed on:** _________________  
**Tested by:** _________________  
**Issues encountered:** _________________  
**Status:** ☐ Success ☐ Partial ☐ Failed

# WordPress SSO Module - Linux Production Deployment Guide

**Target Environment:** Linux Shared Hosting  
**WordPress URL:** https://svajana.org  
**Webtrees URL:** https://svajana.org/familytree  
**Date:** February 2026

---

## Pre-Deployment Checklist

### Backup Everything First! ⚠️

```bash
# SSH into your server and create backups
cd ~

# Backup WordPress .htaccess
cp /path/to/public_html/.htaccess .htaccess-wordpress-backup-$(date +%Y%m%d-%H%M%S)

# Backup Webtrees .htaccess
cp /path/to/public_html/familytree/.htaccess .htaccess-webtrees-backup-$(date +%Y%m%d-%H%M%S)

# Backup entire wordpress_sso module
cd /path/to/public_html/familytree/modules_v4
tar -czf wordpress_sso-backup-$(date +%Y%m%d-%H%M%S).tar.gz wordpress_sso/

# Verify backups exist
ls -lh ~/*.backup* ~/*.tar.gz
```

### Environment Verification

- [ ] SSH access to server confirmed
- [ ] File permissions verified (755 for directories, 644 for files)
- [ ] LiteSpeed Cache plugin is active in WordPress
- [ ] WordPress version: 5.x or higher
- [ ] Webtrees version: 2.4.4
- [ ] PHP version: 7.4+ or 8.0+
- [ ] HTTPS certificate valid for svajana.org

---

## Deployment Steps

### Step 1: Upload Modified PHP Files

**Files to upload to:** `/path/to/public_html/familytree/modules_v4/wordpress_sso/src/`

1. **WordPressSsoModule.php**
   ```bash
   # Upload from your local: WordPressSsoModule.php
   # Destination: /path/to/public_html/familytree/modules_v4/wordpress_sso/src/WordPressSsoModule.php
   # Permissions: 644
   ```

2. **WordPressSsoLoginAction.php**
   ```bash
   # Upload from your local: WordPressSsoLoginAction.php
   # Destination: /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/WordPressSsoLoginAction.php
   # Permissions: 644
   ```

3. **WordPressSsoHomePage.php** (NEW FILE)
   ```bash
   # Upload from your local: WordPressSsoHomePage.php
   # Destination: /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/WordPressSsoHomePage.php
   # Permissions: 644
   ```

**Via SSH/SCP:**
```bash
# From your local machine
scp WordPressSsoModule.php user@svajana.org:/path/to/public_html/familytree/modules_v4/wordpress_sso/src/
scp WordPressSsoLoginAction.php user@svajana.org:/path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/
scp WordPressSsoHomePage.php user@svajana.org:/path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/

# Set correct permissions
chmod 644 /path/to/public_html/familytree/modules_v4/wordpress_sso/src/WordPressSsoModule.php
chmod 644 /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/WordPressSsoLoginAction.php
chmod 644 /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/WordPressSsoHomePage.php
```

**Via FTP/SFTP (FileZilla, WinSCP):**
- Connect to server
- Navigate to `familytree/modules_v4/wordpress_sso/src/`
- Upload `WordPressSsoModule.php` (replace existing)
- Navigate to `familytree/modules_v4/wordpress_sso/src/Http/`
- Upload `WordPressSsoLoginAction.php` (replace existing)
- Upload `WordPressSsoHomePage.php` (new file)

---

### Step 2: Deploy WordPress .htaccess

**File:** `.htaccess-svajana-linux-PRODUCTION`  
**Destination:** `/path/to/public_html/.htaccess` (WordPress root)

```bash
# SSH method
cd /path/to/public_html

# Backup current .htaccess (if not already done)
cp .htaccess .htaccess.backup-$(date +%Y%m%d-%H%M%S)

# Upload the new .htaccess
# (Upload .htaccess-svajana-linux-PRODUCTION from wordpress_sso module folder)
# Then rename it:
mv .htaccess-svajana-linux-PRODUCTION .htaccess

# Set correct permissions
chmod 644 .htaccess

# Verify content
head -n 20 .htaccess
```

**What changed:**
- Added cache bypass headers for authenticated WordPress users
- Ensures fresh content served when logged in

---

### Step 3: Deploy Webtrees .htaccess

**File:** `.htaccess-familytree-linux-PRODUCTION`  
**Destination:** `/path/to/public_html/familytree/.htaccess` (Webtrees root)

```bash
# SSH method
cd /path/to/public_html/familytree

# Backup current .htaccess (if not already done)
cp .htaccess .htaccess.backup-$(date +%Y%m%d-%H%M%S)

# Upload the new .htaccess
# (Upload .htaccess-familytree-linux-PRODUCTION from wordpress_sso module folder)
# Then rename it:
mv .htaccess-familytree-linux-PRODUCTION .htaccess

# Set correct permissions
chmod 644 .htaccess

# Verify content
cat .htaccess
```

**What changed:**
- Replaced "deny from all" with proper cache bypass rules
- Added LiteSpeed cache bypass for WordPress authenticated users
- Added URL rewriting for Webtrees
- Added security headers

---

### Step 4: Clear All Caches

#### WordPress LiteSpeed Cache
```
1. Login to WordPress admin (https://svajana.org/wp-admin)
2. Navigate to: LiteSpeed Cache → Toolbox → Purge
3. Click "Purge All"
4. Verify: "Cache purged successfully" message
```

#### Browser Cache
```
1. Open browser Developer Tools (F12)
2. Right-click refresh button
3. Select "Empty Cache and Hard Reload"
4. Or use Ctrl+Shift+Delete and clear all cache
```

#### Server Cache (if applicable)
```bash
# If using OPcache
sudo service php7.4-fpm reload  # Or php8.0-fpm

# If using Memcached
sudo service memcached restart

# If using Redis
sudo service redis-server restart
```

---

### Step 5: Verify File Permissions

```bash
# SSH into server
cd /path/to/public_html/familytree/modules_v4/wordpress_sso

# Set correct permissions recursively
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Verify
ls -la src/
ls -la src/Http/
```

**Expected output:**
```
-rw-r--r-- 1 user user  xxxxx date WordPressSsoModule.php
-rw-r--r-- 1 user user  xxxxx date WordPressSsoLoginAction.php
-rw-r--r-- 1 user user  xxxxx date WordPressSsoHomePage.php
```

---

### Step 6: Test SSO Flow

#### Test 1: Fresh Login
```
1. Open Incognito/Private browser window
2. Navigate to: https://svajana.org
3. Login to WordPress
4. Verify: Login successful, dashboard visible
5. Navigate to: https://svajana.org/familytree
6. Expected: Automatic redirect to SSO, then login
7. Result: Webtrees shows logged-in user
```

#### Test 2: Session Persistence
```
1. While logged in (from Test 1)
2. Navigate to: https://svajana.org/familytree
3. Expected: User remains logged in (no redirect)
4. Refresh page (F5)
5. Expected: User still logged in
6. Close tab, reopen: https://svajana.org/familytree
7. Expected: User still logged in
```

#### Test 3: Cache Bypass Verification
```
1. While logged in
2. Open Developer Tools (F12) → Network tab
3. Navigate to: https://svajana.org/familytree
4. Check Response Headers for index.php
5. Look for:
   Cache-Control: no-cache, no-store, must-revalidate
   Pragma: no-cache
   Expires: 0
6. Expected: All three headers present
```

#### Test 4: Logout Flow
```
1. While logged in to Webtrees
2. Click "Sign out" in Webtrees
3. Expected: Redirected to WordPress logout
4. Expected: Logged out from both systems
5. Navigate to: https://svajana.org/familytree
6. Expected: Shows "Sign in" button
```

---

### Step 7: Monitor Error Logs

```bash
# Check Apache/LiteSpeed error log
tail -f /path/to/logs/error.log

# Check PHP error log
tail -f /path/to/logs/php_errors.log

# Check Webtrees debug log (if enabled)
tail -f /path/to/public_html/familytree/data/sso_debug.txt
```

**Common issues to watch for:**
- "Class not found: WordPressSsoHomePage" → File permission or path issue
- "redirect_uri_mismatch" → OAuth configuration issue
- "State validation failed" → Session/cookie issue
- "Cache-Control headers not set" → mod_headers not enabled

---

## Post-Deployment Verification

### Verification Checklist

- [ ] WordPress accessible at https://svajana.org
- [ ] Webtrees accessible at https://svajana.org/familytree
- [ ] Login to WordPress successful
- [ ] Navigate to Webtrees triggers SSO
- [ ] SSO login completes successfully
- [ ] User remains logged in after page refresh
- [ ] Cache-Control headers present in response
- [ ] No errors in server error logs
- [ ] No JavaScript console errors
- [ ] Logout works from both systems
- [ ] New user creation works (if enabled)

### Performance Check

```bash
# Check response times
curl -w "@curl-format.txt" -o /dev/null -s https://svajana.org/familytree

# Create curl-format.txt:
cat > curl-format.txt << EOF
    time_namelookup:  %{time_namelookup}\n
       time_connect:  %{time_connect}\n
    time_appconnect:  %{time_appconnect}\n
   time_pretransfer:  %{time_pretransfer}\n
      time_redirect:  %{time_redirect}\n
 time_starttransfer:  %{time_starttransfer}\n
                    ----------\n
         time_total:  %{time_total}\n
EOF
```

**Expected response times:**
- Name lookup: < 0.1s
- Total time: < 2s (first request), < 1s (subsequent)

---

## Troubleshooting

### Issue: "Access Denied" to Webtrees

**Cause:** .htaccess still has "deny from all"

**Fix:**
```bash
cd /path/to/public_html/familytree
cat .htaccess | grep -i "deny from all"
# If found, re-deploy .htaccess-familytree-linux-PRODUCTION
```

### Issue: User Not Auto-Logging In

**Cause:** Cache not bypassed, WordPress cookie not detected

**Fix:**
```bash
# Verify WordPress cookie exists
# In browser DevTools → Application → Cookies
# Look for: wordpress_logged_in_*

# Check .htaccess has cache bypass rules
grep -i "wordpress_logged_in" /path/to/public_html/familytree/.htaccess
```

### Issue: "Class WordPressSsoHomePage not found"

**Cause:** File not uploaded or wrong location

**Fix:**
```bash
# Verify file exists
ls -la /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/WordPressSsoHomePage.php

# Check file permissions
chmod 644 /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http/WordPressSsoHomePage.php
```

### Issue: Redirect Loop

**Cause:** OAuth configuration mismatch

**Fix:**
1. Check WordPress OAuth client redirect URI
2. Should be: `https://svajana.org/familytree/index.php?route=/wordpress-sso/callback`
3. Update if different
4. Clear LiteSpeed cache

---

## Rollback Procedure

See: [ROLLBACK-PLAN-LINUX.md](ROLLBACK-PLAN-LINUX.md)

---

## Support & Resources

- **Module Documentation:** [README.md](README.md)
- **OAuth URI Fix:** [OAUTH_REDIRECT_URI_FIX.md](OAUTH_REDIRECT_URI_FIX.md)
- **Production Readiness:** [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md)

---

## Deployment Completion

**Deployment completed on:** _________________  
**Deployed by:** _________________  
**Tested by:** _________________  
**Issues encountered:** _________________  
**Resolution notes:** _________________

---

**Next Steps:**
1. Monitor for 24-48 hours
2. Check error logs daily
3. Gather user feedback
4. Document any issues in module folder
5. Schedule follow-up review

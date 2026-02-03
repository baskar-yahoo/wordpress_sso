# WordPress SSO Module - Rollback Plan (Linux Production)

**Purpose:** Quick recovery procedure if SSO deployment causes issues  
**Environment:** Linux Shared Hosting (https://svajana.org)  
**Estimated Rollback Time:** 5-10 minutes

---

## When to Rollback

Execute rollback if you encounter:

- ❌ Webtrees becomes inaccessible (404, 500 errors)
- ❌ WordPress admin becomes inaccessible
- ❌ Infinite redirect loops
- ❌ Users cannot login to either system
- ❌ Critical errors in server logs
- ❌ Site performance degradation > 50%

**Do NOT rollback for:**
- ✅ Minor CSS/styling issues (these can be fixed separately)
- ✅ SSO configuration issues (these can be adjusted)
- ✅ Single user reporting login issues (may be browser-specific)

---

## Pre-Rollback Actions

### 1. Document the Issue

```bash
# Capture error logs
tail -n 100 /path/to/logs/error.log > ~/rollback-error-$(date +%Y%m%d-%H%M%S).log

# Capture PHP errors
tail -n 100 /path/to/logs/php_errors.log >> ~/rollback-error-$(date +%Y%m%d-%H%M%S).log

# Capture Webtrees debug (if available)
tail -n 100 /path/to/public_html/familytree/data/sso_debug.txt >> ~/rollback-error-$(date +%Y%m%d-%H%M%S).log

# Note: Save this for post-mortem analysis
```

### 2. Take Current State Snapshot

```bash
# Screenshot of error (if visible)
# Browser Console errors (F12 → Console)
# Network tab status codes

# Current .htaccess files
cp /path/to/public_html/.htaccess ~/htaccess-wordpress-before-rollback-$(date +%Y%m%d-%H%M%S)
cp /path/to/public_html/familytree/.htaccess ~/htaccess-webtrees-before-rollback-$(date +%Y%m%d-%H%M%S)
```

---

## Rollback Procedure

### Step 1: Restore Webtrees .htaccess (PRIORITY 1)

**Time:** 1 minute  
**Risk:** Low  
**Impact:** Restores Webtrees accessibility

```bash
# SSH into server
cd /path/to/public_html/familytree

# List available backups
ls -lht ~/.htaccess-webtrees-backup* ~/htaccess-webtrees-backup*

# Identify the most recent backup BEFORE deployment
# Example: .htaccess-webtrees-backup-20260203-143022

# Restore backup
cp ~/.htaccess-webtrees-backup-20260203-143022 .htaccess

# Verify restoration
cat .htaccess

# Expected content if original was "deny from all":
# order allow,deny
# deny from all

# Or restore a known-good version:
cat > .htaccess << 'EOF'
# Webtrees Basic .htaccess (Safe Mode)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /familytree/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /familytree/index.php [L]
</IfModule>
Options -Indexes
EOF

# Set permissions
chmod 644 .htaccess

# Test accessibility
curl -I https://svajana.org/familytree
# Expected: HTTP/1.1 200 OK or 302 redirect (not 403 or 500)
```

---

### Step 2: Restore WordPress .htaccess (PRIORITY 2)

**Time:** 1 minute  
**Risk:** Low  
**Impact:** Removes SSO cache bypass (WordPress still functional)

```bash
# SSH into server
cd /path/to/public_html

# List available backups
ls -lht ~/.htaccess-wordpress-backup* ~/htaccess-wordpress-backup*

# Identify the most recent backup BEFORE deployment
# Example: .htaccess-wordpress-backup-20260203-143015

# Restore backup
cp ~/.htaccess-wordpress-backup-20260203-143015 .htaccess

# Verify restoration
tail -n 20 .htaccess

# Should NOT see: "BEGIN WordPress SSO Integration" section

# Set permissions
chmod 644 .htaccess

# Test WordPress accessibility
curl -I https://svajana.org
# Expected: HTTP/1.1 200 OK
```

---

### Step 3: Rollback PHP Files (PRIORITY 3)

**Time:** 3-5 minutes  
**Risk:** Low  
**Impact:** Removes SSO HomePage interceptor and route changes

```bash
# SSH into server
cd /path/to/public_html/familytree/modules_v4

# Restore from backup tarball
tar -tzf ~/wordpress_sso-backup-*.tar.gz | head
# Verify backup contents look correct

# Extract backup (this will restore original files)
tar -xzf ~/wordpress_sso-backup-20260203-143000.tar.gz

# This restores:
# - wordpress_sso/src/WordPressSsoModule.php (original)
# - wordpress_sso/src/Http/WordPressSsoLoginAction.php (original)
# - Removes wordpress_sso/src/Http/WordPressSsoHomePage.php (if it didn't exist)

# Set correct permissions
cd wordpress_sso
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Verify restoration
ls -la src/Http/
# Should see WordPressSsoLoginAction.php
# Should NOT see WordPressSsoHomePage.php (if it was new)
```

**Alternative: Manual File Rollback**

If you don't have a tarball backup:

```bash
cd /path/to/public_html/familytree/modules_v4/wordpress_sso/src/Http

# Remove the new HomePage interceptor
rm -f WordPressSsoHomePage.php

# For WordPressSsoModule.php and WordPressSsoLoginAction.php:
# Download original versions from webtrees-svajana theme backup
# Or re-download from version control if available
```

---

### Step 4: Clear All Caches (CRITICAL)

**Time:** 2 minutes  
**Risk:** None  
**Impact:** Ensures old configuration takes effect

```bash
# WordPress LiteSpeed Cache
# Via WP Admin:
# 1. Login to https://svajana.org/wp-admin
# 2. LiteSpeed Cache → Toolbox → Purge → Purge All

# Via command line (if available):
php /path/to/public_html/wp-cli.phar cache flush --path=/path/to/public_html

# Server caches
sudo service php7.4-fpm reload  # Or php8.0-fpm
# Note: May not have sudo access on shared hosting

# Browser cache
# Instruct users to clear browser cache (Ctrl+Shift+Delete)
```

---

### Step 5: Verify Rollback Success

**Time:** 2-3 minutes

#### Test 1: Webtrees Accessibility
```bash
# From command line
curl -I https://svajana.org/familytree
# Expected: HTTP/1.1 200 OK or 302

# From browser
# Navigate to: https://svajana.org/familytree
# Expected: Webtrees homepage loads (may need manual login)
```

#### Test 2: WordPress Accessibility
```bash
# From command line
curl -I https://svajana.org
# Expected: HTTP/1.1 200 OK

# From browser
# Navigate to: https://svajana.org
# Expected: WordPress homepage loads
# Login to wp-admin
# Expected: Dashboard accessible
```

#### Test 3: No Critical Errors
```bash
# Check error logs
tail -n 50 /path/to/logs/error.log
# Look for: No new errors after rollback

# Check PHP errors
tail -n 50 /path/to/logs/php_errors.log
# Look for: No fatal errors
```

---

## Post-Rollback Actions

### 1. Notify Users

**Email Template:**
```
Subject: [Action Required] WordPress/Webtrees Login Update

Dear Users,

We have temporarily reverted recent login system changes due to 
technical issues. You may need to:

1. Clear your browser cache (Ctrl+Shift+Delete)
2. Login to WordPress at: https://svajana.org
3. Login to Webtrees separately at: https://svajana.org/familytree

We are working to resolve the issue and will notify you when 
the improved login system is restored.

If you experience any issues, please contact support.

Thank you for your patience.
```

### 2. Document Root Cause

Create incident report:

```bash
# Create incident report file
cat > ~/sso-rollback-incident-$(date +%Y%m%d).txt << EOF
SSO Rollback Incident Report
============================

Date/Time: $(date)
Rolled back by: [YOUR NAME]

Issue Description:
[Describe what went wrong]

Symptoms:
[List symptoms that triggered rollback]

Actions Taken:
1. [Step 1]
2. [Step 2]
...

Current Status:
[System status after rollback]

Root Cause Analysis:
[What caused the issue]

Prevention Plan:
[How to prevent this in future]

Next Steps:
[What needs to be done before re-deployment]

Attached Logs:
- rollback-error-*.log
- htaccess-*-before-rollback-*

EOF
```

### 3. Review and Plan

**Before re-attempting deployment:**

- [ ] Review error logs from failed deployment
- [ ] Identify specific failure point
- [ ] Test fix in development environment first
- [ ] Create more granular rollback points
- [ ] Consider staged deployment (one component at a time)
- [ ] Plan maintenance window with user notification
- [ ] Prepare better monitoring/alerting

---

## Partial Rollback Options

If only specific components are problematic:

### Option A: Rollback Only PHP Files, Keep .htaccess

**Use when:** .htaccess changes work, but PHP code has issues

```bash
# Keep both .htaccess files (they work for caching)
# Rollback only PHP files (Step 3 above)
# SSO won't work, but cache bypass will help performance
```

### Option B: Rollback Only Webtrees .htaccess

**Use when:** Webtrees inaccessible, but WordPress fine

```bash
# Rollback only Step 1
# Keep WordPress .htaccess (helps with caching)
# Keep PHP files (they won't break anything)
```

### Option C: Keep Everything, Disable SSO

**Use when:** Code works but OAuth configuration wrong

```bash
# Don't rollback files
# Just disable SSO in Webtrees admin:
# 1. Login to Webtrees directly
# 2. Control Panel → Modules → WordPress SSO
# 3. Uncheck "Enable Seamless SSO"
# 4. Save

# Users can still login manually to both systems
```

---

## Emergency Contact

**In case of complete site failure:**

1. **Hosting Provider Support:**
   - Contact: [Your hosting provider]
   - Phone: [Support phone]
   - Priority: Critical - Site Down

2. **Restore from Hosting Backup:**
   ```
   Most hosting providers have automatic daily backups
   Request restoration to date before deployment
   Usually available within 1-2 hours
   ```

3. **Database Rollback:**
   ```
   Only needed if database changes were made
   (Current deployment does NOT modify database)
   ```

---

## Rollback Checklist

Complete this checklist during rollback:

- [ ] Error logs captured
- [ ] Current state snapshot taken
- [ ] Webtrees .htaccess restored
- [ ] WordPress .htaccess restored
- [ ] PHP files restored
- [ ] All caches cleared
- [ ] Webtrees accessibility verified
- [ ] WordPress accessibility verified
- [ ] No critical errors in logs
- [ ] Users notified (if needed)
- [ ] Incident report created
- [ ] Root cause identified
- [ ] Prevention plan documented

**Rollback completed by:** _________________  
**Date/Time:** _________________  
**Status:** ☐ Successful ☐ Partial ☐ Failed  
**Notes:** _________________

---

## Prevention for Next Deployment

1. **Deploy to staging environment first**
2. **Test thoroughly before production**
3. **Deploy during low-traffic hours**
4. **Monitor for 15 minutes after deployment**
5. **Have rollback team ready**
6. **Create more frequent backup points**
7. **Use feature flags to enable/disable SSO**
8. **Implement health check monitoring**

---

## Testing Rollback Procedure

**Recommended:** Test this rollback procedure in development environment before production deployment.

```bash
# In development:
1. Deploy SSO changes
2. Verify working
3. Intentionally break something (e.g., corrupt .htaccess)
4. Execute rollback procedure
5. Time how long it takes
6. Refine procedure based on results
```

---

**Last Updated:** February 2026  
**Document Version:** 1.0  
**Next Review:** After first production deployment

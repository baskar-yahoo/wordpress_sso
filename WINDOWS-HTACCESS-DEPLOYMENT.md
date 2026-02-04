# Windows .htaccess Deployment Quick Guide

**For:** Windows 11 with XAMPP Development Environment  
**WordPress:** http://localhost/svajana  
**Webtrees:** http://localhost/svajana/familytree

---

## Required Files (All in wordpress_sso module folder)

1. `.htaccess-svajana-WINDOWS` → Deploy to WordPress root
2. `.htaccess-familytree-WINDOWS` → Deploy to Webtrees root

---

## Deployment: WordPress .htaccess

### Using File Explorer (Easiest)

**Step 1:** Enable viewing hidden files
```
File Explorer → View → Options → View tab
→ Select "Show hidden files, folders, and drives"
→ Click OK
```

**Step 2:** Backup existing .htaccess
```
Navigate to: C:\xampp\htdocs\svajana
Find: .htaccess
Right-click → Copy
Right-click → Paste
Rename copied file to: .htaccess.backup
```

**Step 3:** Deploy new .htaccess
```
Navigate to: C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso
Find: .htaccess-svajana-WINDOWS
Copy this file
Navigate to: C:\xampp\htdocs\svajana
Paste file
Rename from: .htaccess-svajana-WINDOWS
Rename to: .htaccess (overwrite when prompted)
```

### Using PowerShell (Advanced)

```powershell
# Open PowerShell and run:
cd C:\xampp\htdocs\svajana

# Backup current .htaccess
Copy-Item .htaccess ".htaccess.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Deploy new .htaccess
Copy-Item "familytree\modules_v4\wordpress_sso\.htaccess-svajana-WINDOWS" .htaccess -Force

# Verify deployment
Get-Content .htaccess | Select-String "WordPress SSO Integration"
# Should output: # BEGIN WordPress SSO Integration - Windows Development
```

---

## Deployment: Webtrees .htaccess

### Using File Explorer (Easiest)

**Step 1:** Backup existing .htaccess
```
Navigate to: C:\xampp\htdocs\svajana\familytree
Find: .htaccess
Right-click → Copy
Right-click → Paste
Rename copied file to: .htaccess.backup
```

**Step 2:** Deploy new .htaccess
```
Navigate to: C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso
Find: .htaccess-familytree-WINDOWS
Copy this file
Navigate to: C:\xampp\htdocs\svajana\familytree
Paste file
Rename from: .htaccess-familytree-WINDOWS
Rename to: .htaccess (overwrite when prompted)
```

### Using PowerShell (Advanced)

```powershell
# Open PowerShell and run:
cd C:\xampp\htdocs\svajana\familytree

# Backup current .htaccess
Copy-Item .htaccess ".htaccess.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Deploy new .htaccess
Copy-Item "modules_v4\wordpress_sso\.htaccess-familytree-WINDOWS" .htaccess -Force

# Verify deployment
Get-Content .htaccess | Select-String "Cache Bypass for Authenticated Users"
# Should output: # BEGIN LiteSpeed/Apache Cache Bypass for Authenticated Users
```

---

## What These Files Do

### WordPress .htaccess (svajana-WINDOWS)

**Adds to existing WordPress configuration:**
- ✅ Cache bypass for WordPress-authenticated users
- ✅ Sets Cache-Control headers
- ✅ Required for SSO to work correctly
- ✅ Maintains all existing WordPress functionality

**Key Addition:**
```apache
# BEGIN WordPress SSO Integration - Windows Development
# Cache Bypass for Authenticated Users
<IfModule mod_headers.c>
    SetEnvIf Cookie "wordpress_logged_in_" wp_authenticated
    Header set Cache-Control "no-cache, no-store, must-revalidate" env=wp_authenticated
    Header set Pragma "no-cache" env=wp_authenticated
    Header set Expires "0" env=wp_authenticated
</IfModule>
# END WordPress SSO Integration
```

### Webtrees .htaccess (familytree-WINDOWS)

**Provides complete Webtrees root configuration:**
- ✅ Cache bypass for WordPress-authenticated users
- ✅ Cache bypass for Webtrees-authenticated users (PHPSESSID)
- ✅ Works with LiteSpeed AND Apache (XAMPP)
- ✅ URL rewriting for Webtrees
- ✅ Security headers
- ✅ File protection

**Key Features:**
```apache
# Detects WordPress authentication
RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]

# Detects Webtrees authentication
RewriteCond %{HTTP_COOKIE} PHPSESSID [NC]

# Sets cache-control headers for both
SetEnvIf Cookie "wordpress_logged_in_" wp_user_authenticated
SetEnvIf Cookie "PHPSESSID" wt_user_authenticated
```

---

## Verification Steps

### Step 1: Verify Files Exist

```powershell
# Check WordPress .htaccess
Test-Path C:\xampp\htdocs\svajana\.htaccess
# Should return: True

# Check Webtrees .htaccess
Test-Path C:\xampp\htdocs\svajana\familytree\.htaccess
# Should return: True
```

### Step 2: Verify Content

```powershell
# Check WordPress .htaccess has SSO integration
Get-Content C:\xampp\htdocs\svajana\.htaccess | Select-String "WordPress SSO Integration"
# Should output: # BEGIN WordPress SSO Integration - Windows Development

# Check Webtrees .htaccess has cache bypass
Get-Content C:\xampp\htdocs\svajana\familytree\.htaccess | Select-String "wordpress_logged_in|PHPSESSID"
# Should output TWO lines (one for each cookie)
```

### Step 3: Restart Apache

```
XAMPP Control Panel → Apache → Stop
Wait 3 seconds
XAMPP Control Panel → Apache → Start
```

### Step 4: Test in Browser

```
1. Open browser → http://localhost/svajana
2. Login to WordPress
3. Open Developer Tools (F12) → Network tab
4. Navigate to: http://localhost/svajana/familytree
5. Click on request → Headers → Response Headers
6. Look for: Cache-Control: no-cache, no-store, must-revalidate
```

**Expected Result:** ✅ Cache-Control header present

---

## Troubleshooting

### Can't See .htaccess Files

**Solution:**
```
File Explorer → View → Options
→ View tab
→ Select "Show hidden files, folders, and drives"
→ Uncheck "Hide protected operating system files"
→ Click OK
```

### Apache Won't Start After .htaccess Deployment

**Cause:** Syntax error in .htaccess

**Solution:**
```powershell
# Check Apache error log
Get-Content C:\xampp\apache\logs\error.log -Tail 20

# If you see syntax errors, restore backup:
cd C:\xampp\htdocs\svajana
Copy-Item .htaccess.backup .htaccess -Force

cd C:\xampp\htdocs\svajana\familytree
Copy-Item .htaccess.backup .htaccess -Force

# Restart Apache
```

### Cache Headers Not Appearing

**Cause:** mod_headers not enabled

**Solution:**
```powershell
# Edit Apache config
notepad C:\xampp\apache\conf\httpd.conf

# Find this line (Ctrl+F):
#LoadModule headers_module modules/mod_headers.so

# Remove the # at the start to uncomment:
LoadModule headers_module modules/mod_headers.so

# Save file
# Restart Apache in XAMPP Control Panel
```

### .htaccess Being Ignored

**Cause:** AllowOverride not enabled

**Solution:**
```powershell
# Edit Apache config
notepad C:\xampp\apache\conf\httpd.conf

# Find: <Directory "C:/xampp/htdocs">
# Change AllowOverride None to AllowOverride All

<Directory "C:/xampp/htdocs">
    AllowOverride All
    Require all granted
</Directory>

# Save file
# Restart Apache in XAMPP Control Panel
```

### SSO Still Not Working

**Checklist:**
```
1. Both .htaccess files deployed? → Check Step 1 & 2
2. Apache restarted? → XAMPP Control Panel
3. Browser cache cleared? → Ctrl+Shift+Delete
4. Cookies present? → F12 → Application → Cookies
5. mod_headers enabled? → Check httpd.conf
6. mod_rewrite enabled? → Check httpd.conf (should be by default)
```

---

## Common Mistakes

❌ **Mistake 1:** Forgetting the leading dot (`.`) in filename
- Wrong: `htaccess`
- Correct: `.htaccess`

❌ **Mistake 2:** Not enabling "Show hidden files"
- Can't see .htaccess files
- Think they don't exist

❌ **Mistake 3:** Not restarting Apache after changes
- Changes won't take effect
- Always restart Apache

❌ **Mistake 4:** Deploying Linux version on Windows
- Wrong RewriteBase paths
- Won't work correctly

❌ **Mistake 5:** Editing the LiteSpeed Cache section
- Breaking cache plugin configuration
- Only add to "WordPress SSO Integration" section

---

## Security Note

**Q:** Does this weaken security?  
**A:** No! See `SECURITY-QA-SUMMARY.md` for details.

**Key Points:**
- ✅ Cache bypass does NOT grant unauthorized access
- ✅ data/ folder still protected (separate .htaccess)
- ✅ All Webtrees authentication still works
- ✅ Actually IMPROVES security by preventing stale permissions

---

## Next Steps

After deploying .htaccess files:

1. ✅ Deploy PHP files (see `WINDOWS-DEPLOYMENT-GUIDE.md` Step 1)
2. ✅ Restart Apache
3. ✅ Clear browser cache
4. ✅ Test SSO flow (see `WINDOWS-DEPLOYMENT-GUIDE.md` Step 6)
5. ✅ Verify cache headers (F12 → Network → Headers)

---

## Quick Reference

**File Locations After Deployment:**

```
C:\xampp\htdocs\svajana\
├── .htaccess ← WordPress (with SSO cache bypass)
└── familytree\
    ├── .htaccess ← Webtrees (with BOTH cookie checks)
    └── modules_v4\
        └── wordpress_sso\
            ├── .htaccess-svajana-WINDOWS (source)
            └── .htaccess-familytree-WINDOWS (source)
```

**Backup Locations:**
```
C:\xampp\htdocs\svajana\.htaccess.backup-YYYYMMDD-HHMMSS
C:\xampp\htdocs\svajana\familytree\.htaccess.backup-YYYYMMDD-HHMMSS
```

---

**Questions?** See:
- `SECURITY-QA-SUMMARY.md` - Security questions answered
- `WINDOWS-DEPLOYMENT-GUIDE.md` - Complete deployment guide
- `SECURITY-ANALYSIS.md` - Full security details

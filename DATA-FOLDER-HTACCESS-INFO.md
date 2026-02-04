# Data Folder .htaccess - Why You Should NOT Deploy Custom Version

## Quick Answer

**DO NOT deploy a custom .htaccess to the data/ folder.**

Webtrees CORE manages this file automatically. Deploying your own version:
- ❌ May be overwritten by Webtrees during updates
- ❌ Could accidentally weaken security
- ❌ Is unnecessary - Webtrees' version is sufficient
- ❌ Creates maintenance burden

---

## What Webtrees Creates

**File:** `familytree/data/.htaccess`  
**Created by:** Webtrees core during installation  
**Managed by:** Webtrees (not SSO module)

**Content:**
```apache
Order allow,deny
Deny from all
```

**This is PERFECT and SUFFICIENT. Do not modify it.**

---

## Why Webtrees' Version is Best

### 1. Simplicity = Security
- "Deny from all" is absolute
- No exceptions, no loopholes
- Can't be misconfigured
- Works on all server types

### 2. Automatic Management
- Webtrees creates it during installation
- Webtrees may update it during upgrades
- Webtrees verifies it exists
- Your custom version could conflict

### 3. Apache Hierarchy
```
/familytree/.htaccess           ← Your SSO module changes
    ├── Applies to: /familytree/*
    └── Does NOT apply to: /familytree/data/*

/familytree/data/.htaccess      ← Webtrees CORE (DO NOT TOUCH)
    ├── Applies to: /familytree/data/*
    └── OVERRIDES parent settings
    └── "Deny from all" blocks EVERYTHING
```

The subdirectory .htaccess takes precedence. Your root .htaccess cannot affect it.

---

## What If You Need to Recreate It?

**Only if the file is missing or corrupted, manually recreate using Webtrees' exact content:**

### Linux:
```bash
cat > /path/to/public_html/familytree/data/.htaccess << 'EOF'
Order allow,deny
Deny from all
EOF

chmod 644 /path/to/public_html/familytree/data/.htaccess
chown www-data:www-data /path/to/public_html/familytree/data/.htaccess
```

### Windows:
```powershell
Set-Content -Path "C:\xampp\htdocs\svajana\familytree\data\.htaccess" -Value @"
Order allow,deny
Deny from all
"@
```

**Important:** Use EXACTLY this content. Do not add anything else.

---

## How to Verify Protection is Working

### Windows (PowerShell):
```powershell
# Run verification script
cd C:\xampp\htdocs\svajana\familytree\modules_v4\wordpress_sso
.\data-folder-protection-verification.ps1
```

### Linux (Bash):
```bash
# Run verification script
cd /path/to/familytree/modules_v4/wordpress_sso
chmod +x data-folder-protection-verification.sh
./data-folder-protection-verification.sh
```

### Manual Browser Test:
```
Windows: http://localhost/svajana/familytree/data/
Linux: https://svajana.org/familytree/data/

Expected Result: 403 Forbidden
```

---

## What About index.php in data/?

Webtrees also creates: `familytree/data/index.php`

**Content:**
```php
<?php
header('Location: ../index.php');
exit;
```

**Purpose:** Backup protection if .htaccess fails

**Action:** Also managed by Webtrees core. Do not modify.

---

## Security Layers

The data/ folder is protected by THREE independent layers:

```
Layer 1: data/.htaccess
├── "Deny from all" blocks HTTP access
├── Managed by Webtrees core
└── YOUR ROOT .htaccess CANNOT OVERRIDE THIS

Layer 2: data/index.php
├── Redirects anyone who bypasses Layer 1
├── Managed by Webtrees core
└── Backup protection

Layer 3: PHP Application Code
├── All file access goes through Webtrees methods
├── Permission checks before serving files
└── Prevents directory traversal attacks
```

**Your SSO module changes NONE of these layers.**

---

## Common Mistakes to Avoid

❌ **Mistake 1:** Creating custom data/.htaccess
- Unnecessary
- May conflict with Webtrees updates
- Could introduce security bugs

❌ **Mistake 2:** Adding rules to data/.htaccess
- "Deny from all" is sufficient
- Adding exceptions could expose files
- Keep it simple

❌ **Mistake 3:** Deleting data/.htaccess
- Exposes sensitive files
- Webtrees will recreate it
- But briefly vulnerable

❌ **Mistake 4:** Copying root .htaccess to data/
- Wrong configuration
- Could accidentally allow access
- Use Webtrees' version only

---

## Verification Checklist

After deploying SSO module, verify data/ protection:

- [ ] data/.htaccess exists
- [ ] Content is exactly: "Order allow,deny\nDeny from all"
- [ ] Browser test returns 403 Forbidden
- [ ] data/config.ini.php returns 403 Forbidden
- [ ] data/index.php exists (backup protection)
- [ ] Root .htaccess does NOT affect data/ folder
- [ ] Verification script passes all tests

---

## Questions & Answers

### Q: Should I add SSO cache bypass to data/.htaccess?

**A:** NO! The data/ folder should NEVER serve content to users. Cache bypass is irrelevant because "Deny from all" blocks everything.

### Q: What if I want to allow specific file access from data/?

**A:** Don't! Use Webtrees' PHP methods instead. They handle authentication and permissions correctly. Direct HTTP access to data/ should ALWAYS be blocked.

### Q: Can I add security headers to data/.htaccess?

**A:** Unnecessary. "Deny from all" is stronger than any security headers. No content is served, so headers don't apply.

### Q: What if Webtrees updates change data/.htaccess?

**A:** Let it! Webtrees knows best. If they add features or improve security, your custom version would block those improvements.

### Q: How do I know if data/.htaccess is protecting files?

**A:** Run the verification script:
- Windows: `data-folder-protection-verification.ps1`
- Linux: `data-folder-protection-verification.sh`

### Q: What if the file is missing?

**A:** Recreate it with EXACTLY Webtrees' content (see above). Or reinstall Webtrees to regenerate all core files.

---

## Summary

### ✅ DO:
- Let Webtrees manage data/.htaccess
- Verify it exists and works
- Run verification scripts
- Trust Webtrees' default security

### ❌ DON'T:
- Create custom data/.htaccess
- Modify Webtrees' version
- Add rules or exceptions
- Deploy SSO changes to data/
- Delete or move the file

---

## Related Documentation

- `SECURITY-QA-SUMMARY.md` - Overall security questions
- `SECURITY-ANALYSIS.md` - Complete security analysis
- `data-folder-protection-verification.ps1` - Windows testing
- `data-folder-protection-verification.sh` - Linux testing

---

**Bottom Line:** The data/ folder .htaccess is managed by Webtrees core. Your SSO module should never touch it. The existing protection is perfect - leave it alone! ✅

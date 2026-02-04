# Quick Deploy: Admin Notification Feature

## What Changed

✅ **File Modified:** `src/Http/WordPressSsoLoginAction.php`

**New Functionality:**
- When SSO user login blocked (account not approved)
- Automatic notification sent to ALL administrators
- Both email and internal Webtrees message
- Includes user details, IP address, approval instructions

---

## Deployment Steps - Windows XAMPP

### **1. Verify File Updated**

```powershell
cd C:\xampp\htdocs\familytree\modules_v4\wordpress_sso

# Check that MessageService is imported
Get-Content src\Http\WordPressSsoLoginAction.php | Select-String "MessageService"

# Should show:
# use Fisharebest\Webtrees\Services\MessageService;
# private MessageService $message_service;
# MessageService $message_service
```

### **2. Restart Apache**

```
Open XAMPP Control Panel
→ Click "Stop" for Apache
→ Wait 2 seconds
→ Click "Start" for Apache
```

### **3. Test the Feature**

**Prerequisites:**
- User `baskar_iyer@yahoo.com` exists and is NOT approved
- You have admin login credentials

**Test Steps:**

```powershell
# 1. Reset notification flag (to test fresh notification)
& "C:\xampp\mysql\bin\mysql.exe" -u root

# In MySQL:
USE webtrees;  # Or your database name

DELETE FROM wt_user_setting 
WHERE user_id = (SELECT user_id FROM wt_user WHERE user_name = 'baskar_iyer@yahoo.com')
  AND setting_name = 'sso_admin_notified';

EXIT;

# 2. Open incognito browser window
# 3. Navigate to: http://localhost/svajana
# 4. Login as: baskar_iyer@yahoo.com
# 5. Navigate to: http://localhost/svajana/familytree
# 6. Should see: "Your account is pending approval..."
```

### **4. Verify Admin Notification**

**Check Internal Message:**
```
1. Open new browser tab
2. Navigate to: http://localhost/svajana/familytree
3. Login as admin user
4. Click message/notification icon (usually top right)
5. Should see: "New user registration - approval needed"
6. Click to read full message with user details
```

**Check Email (if configured):**
```
1. Check admin email inbox
2. Look for email from: baskar_iyer@yahoo.com
3. Subject: "New user registration - approval needed"
4. Body contains user details and approval instructions
```

**Check Debug Log:**
```powershell
Get-Content C:\xampp\htdocs\familytree\data\sso_debug.txt -Tail 50

# Should show:
# Sending pending approval notifications to administrators
# Notification sent successfully | admin_email: your-admin@example.com
# Admin notification process completed | successful: 1, failed: 0
```

### **5. Test No Duplicate Notifications**

```
1. Keep browser incognito window open
2. Clear all cookies
3. Try login again (repeat steps 2-5 from Test #3)
4. User sees warning message
5. Admin should NOT receive new notification
6. Debug log shows: "Admin notification already sent for this user, skipping"
```

---

## Deployment Steps - Linux Production

### **1. Upload File via SFTP/SSH**

```bash
# From local machine
scp src/Http/WordPressSsoLoginAction.php user@svajana.org:/public_html/familytree/modules_v4/wordpress_sso/src/Http/

# OR upload via SFTP client (FileZilla, WinSCP)
```

### **2. Set Permissions**

```bash
ssh user@svajana.org

cd /public_html/familytree/modules_v4/wordpress_sso

# Set file permissions
chmod 644 src/Http/WordPressSsoLoginAction.php

# Verify
ls -la src/Http/WordPressSsoLoginAction.php
# Should show: -rw-r--r--
```

### **3. Clear Webtrees Cache**

```bash
rm -rf /public_html/familytree/data/cache/*
```

### **4. Restart Web Server (if possible)**

```bash
# If you have sudo access:
sudo systemctl restart apache2
# OR for LiteSpeed:
sudo systemctl restart lsws

# If no access, cache clear is sufficient (PHP will reload on next request)
```

### **5. Test on Production**

Follow same test steps as Windows, but use production URLs:
- WordPress: https://svajana.org
- Webtrees: https://svajana.org/familytree

---

## Verification Checklist

### **After Deployment**

- [ ] Apache/LiteSpeed restarted
- [ ] Incognito browser test completed
- [ ] User sees "pending approval" warning message
- [ ] Admin received internal Webtrees message
- [ ] Admin received email notification (if email configured)
- [ ] sso_debug.txt shows "Notification sent successfully"
- [ ] No errors in Apache error log
- [ ] Second attempt does NOT send duplicate notification

### **If Any Issues**

**Problem:** No notification received

**Solutions:**
```powershell
# Check if MessageService is injected properly
Get-Content src\Http\WordPressSsoLoginAction.php | Select-String "message_service"

# Check admin email is configured
# MySQL:
SELECT user_name, email FROM wt_user WHERE user_id IN (
    SELECT user_id FROM wt_user_setting WHERE setting_name = 'canadmin' AND setting_value = '1'
);

# Check Webtrees email settings
# Control Panel → Website → Website Preferences → Email → Send Test Email
```

**Problem:** Duplicate notifications

**Solution:**
```sql
-- Verify flag is set correctly
SELECT * FROM wt_user_setting 
WHERE setting_name = 'sso_admin_notified'
ORDER BY user_id DESC;
```

**Problem:** PHP errors

**Check Apache error log:**
```powershell
# Windows
Get-Content C:\xampp\apache\logs\error.log -Tail 50

# Linux
tail -f /var/log/apache2/error.log
```

---

## Rollback Plan

If something goes wrong, restore previous version:

```powershell
# Windows - restore from backup
cd C:\xampp\htdocs\familytree\modules_v4\wordpress_sso
Copy-Item src\Http\WordPressSsoLoginAction.php.backup src\Http\WordPressSsoLoginAction.php -Force

# Linux - restore from backup
cd /public_html/familytree/modules_v4/wordpress_sso
cp src/Http/WordPressSsoLoginAction.php.backup src/Http/WordPressSsoLoginAction.php

# Restart Apache
# XAMPP: Stop/Start in Control Panel
# Linux: sudo systemctl restart apache2
```

---

## Email Configuration (Optional)

If admin notifications not arriving via email, configure Webtrees email:

### **Option 1: Use PHP mail() (Easiest)**

```
1. Control Panel → Website → Website Preferences → Email
2. Select: "Use PHP mail() function"
3. Set "From" email address
4. Send test email to verify
```

### **Option 2: Use SMTP (Most Reliable)**

```
1. Control Panel → Website → Website Preferences → Email
2. Select: "Use SMTP"
3. Configure:
   - SMTP Host: smtp.gmail.com (or your provider)
   - SMTP Port: 587 (TLS) or 465 (SSL)
   - Username: your-email@gmail.com
   - Password: your-app-password
   - Encryption: TLS or SSL
4. Send test email to verify
```

**Gmail App Password:**
```
1. Google Account → Security
2. Enable 2-Step Verification
3. App Passwords → Generate new password
4. Use this password in Webtrees SMTP settings
```

---

## Summary

### **One-Line Summary**
Administrators now receive automatic email + internal message notifications when SSO users need account approval.

### **Key Features**
✅ Leverages existing Webtrees notification system  
✅ Prevents duplicate notifications  
✅ Includes user details and approval instructions  
✅ Works exactly like native user registration  

### **Testing Confirmed**
- [ ] Windows XAMPP environment
- [ ] Linux production environment
- [ ] Email notifications working
- [ ] Internal messages working
- [ ] No duplicate notifications

### **Next Action**
**Test with baskar_iyer@yahoo.com on Windows XAMPP first, then deploy to production.**

---

## Questions?

**Q: What if I have 10+ administrators?**  
A: All will receive notifications. Consider customizing the notification method if this is too many emails.

**Q: Can I customize the notification message?**  
A: Yes, edit the `notifyAdministratorsAboutPendingUser()` method in WordPressSsoLoginAction.php.

**Q: What if email is not configured?**  
A: Internal Webtrees messages still work (visible when admin logs in).

**Q: Can I disable notifications?**  
A: Yes, comment out the `notifyAdministratorsAboutPendingUser()` call (not recommended).

**Q: Does this work with the redirect loop fix?**  
A: Yes! They work together perfectly - notification sent once, redirect loop prevented.

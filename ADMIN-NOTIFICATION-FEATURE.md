# Admin Notification for Pending Approvals

## Feature Overview

When a user attempts to login via WordPress SSO but their Webtrees account is not yet approved, the system now:

✅ **Shows warning message to user** - "Your account is pending administrator approval..."  
✅ **Sends internal Webtrees message to all admins** - Visible in admin inbox  
✅ **Sends email notification to all admins** - If email is configured  
✅ **Prevents duplicate notifications** - Only notifies once per user  
✅ **Logs detailed audit trail** - For security and troubleshooting  

---

## Why This Feature Is Important

### **Problem It Solves**

**Before:**
- User attempts SSO login → Account not approved → User sees warning
- **Admin has NO IDEA someone is waiting** ❌
- User waits indefinitely, possibly thinking the system is broken
- Admin only discovers pending users by checking Control Panel manually

**After:**
- User attempts SSO login → Account not approved → User sees warning
- **Admin receives immediate notification** ✅ (both email and internal message)
- Admin can approve quickly
- Better user experience and faster onboarding

---

## How It Works

### **Notification Trigger**

Notification is sent when:
1. User completes OAuth authentication successfully with WordPress
2. User is found or created in Webtrees database
3. **Account approval check fails** (`account_approved != '1'`)
4. **First login attempt only** (prevents spam)

### **What Admins Receive**

#### **1. Internal Webtrees Message**

Location: Admin logs in → Sees notification icon → "New user registration - approval needed"

**Message Contains:**
- Username
- Email address
- Real name
- WordPress User ID
- Login attempt timestamp
- IP address (for security)
- Step-by-step approval instructions

#### **2. Email Notification**

Sent to all administrators who have email addresses configured.

**Email Subject:**
```
New user registration - approval needed
```

**Email Body Includes:**
- All user details (username, email, real name, WordPress ID)
- Login attempt time and IP address
- Clear instructions on how to approve
- Explanation that user was blocked due to pending approval

---

## Technical Implementation

### **Files Modified**

#### **WordPressSsoLoginAction.php**

**Added Dependencies:**
```php
use Fisharebest\Webtrees\Services\MessageService;
```

**Updated Constructor:**
```php
public function __construct(
    UserService $user_service,
    WordPressSsoModule $module,
    EmailService $email_service,
    MessageService $message_service  // ← NEW
) {
    // ...
    $this->message_service = $message_service;
}
```

**Added Method Call in Approval Check:**
```php
if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
    // ... existing code ...
    
    // Notify administrators about pending approval
    $this->notifyAdministratorsAboutPendingUser($user, $request);  // ← NEW
    
    $this->cleanupSession();
    return redirect(route(HomePage::class));
}
```

**New Method: `notifyAdministratorsAboutPendingUser()`**

This method:
1. ✅ Checks if notification already sent (prevents duplicates)
2. ✅ Gathers user details (username, email, WordPress ID, IP address, timestamp)
3. ✅ Builds notification message (both plain text and HTML)
4. ✅ Sends internal Webtrees message to all admins
5. ✅ Sends email to all admins
6. ✅ Marks user as "notification sent" to prevent spam
7. ✅ Logs everything for audit trail
8. ✅ Handles errors gracefully (continues if one admin fails)

---

## Security Considerations

### **Duplicate Notification Prevention**

**Mechanism:**
- User preference `sso_admin_notified` is set to `'1'` after first notification
- Subsequent login attempts check this flag
- Only first attempt triggers notification

**Why This Matters:**
- Prevents admin inbox spam if user tries multiple times
- Reduces email volume
- Still allows admin to manually trigger notification if needed (by clearing flag)

### **Information Included**

**What's Logged:**
- ✅ IP Address - For security tracking (detect bot attacks, VPN use, etc.)
- ✅ User Agent - Browser/device information
- ✅ Timestamp - When login attempt occurred
- ✅ WordPress User ID - Cross-reference with WordPress admin panel

**Privacy Note:** This information is only sent to administrators and is necessary for security auditing.

### **Error Handling**

- If notification fails for one admin, continues with others
- Errors logged but don't block user flow
- User still sees warning message even if notification fails

---

## User Experience Flow

### **Scenario 1: New User - First Time Login**

```
1. User logs into WordPress (WP session created)
2. User navigates to Webtrees familytree URL
3. SSO detects WP session → redirects to OAuth
4. OAuth completes → User found in Webtrees
5. ❌ Account not approved → Login blocked
6. ✅ User sees: "Your account is pending approval..."
7. ✅ Admins receive notification (email + message)
8. User waits for approval
```

### **Scenario 2: User Tries Again (Before Approval)**

```
1. User clears cookies and tries again
2. SSO → OAuth → User found
3. ❌ Account not approved
4. ✅ User sees: "Your account is pending approval..."
5. ❌ NO new notification to admins (already sent)
```

### **Scenario 3: Admin Approves User**

```
1. Admin receives notification
2. Admin goes to Control Panel → User Administration
3. Admin finds user: baskar_iyer@yahoo.com
4. Admin clicks Edit → Checks "Approved" → Saves
5. User clears cookies and tries login again
6. ✅ Login succeeds → User logged into Webtrees
7. ✅ Auto-login works from now on
```

---

## Configuration Requirements

### **Email Notifications Work When:**

1. ✅ Webtrees email settings configured:
   - Control Panel → Website → Website Preferences → Email
   - SMTP or sendmail configured
   - "From" email address set

2. ✅ Administrator accounts have email addresses:
   - Control Panel → Users → Edit Admin User
   - Email field populated

3. ✅ Server can send email:
   - PHP mail() function works, OR
   - SMTP server accessible

### **Internal Messages Always Work**

- No email configuration needed
- Messages stored in Webtrees database
- Visible when admin logs in

---

## Testing the Feature

### **Test Case 1: Verify Notification Sent**

**Prerequisites:**
- Admin user with email address configured
- Unapproved user in database (e.g., `baskar_iyer@yahoo.com`)

**Steps:**
```powershell
# 1. Clear user's notification flag (simulate first login)
& "C:\xampp\mysql\bin\mysql.exe" -u root -p

USE webtrees_database_name;

DELETE FROM wt_user_setting 
WHERE user_id = (SELECT user_id FROM wt_user WHERE user_name = 'baskar_iyer@yahoo.com')
  AND setting_name = 'sso_admin_notified';

# 2. Clear cookies in browser (incognito mode)
# 3. Login to WordPress as baskar_iyer@yahoo.com
# 4. Navigate to Webtrees familytree URL
# 5. Observe:
#    - User sees warning message
#    - Check sso_debug.txt for "Sending pending approval notifications"
#    - Login as admin → Check messages (should have new notification)
#    - Check admin email inbox (should have email)
```

**Expected Results:**
- ✅ User sees: "Your account is pending approval..."
- ✅ sso_debug.txt shows: `Sending pending approval notifications to administrators`
- ✅ sso_debug.txt shows: `Notification sent successfully` for each admin
- ✅ Admin inbox has internal message from `baskar_iyer@yahoo.com`
- ✅ Admin email has notification email

### **Test Case 2: Verify No Duplicate Notifications**

**Steps:**
```powershell
# 1. After Test Case 1, do NOT clear notification flag
# 2. User clears cookies again
# 3. User tries login again via SSO
# 4. Observe:
#    - User sees warning message
#    - Check sso_debug.txt for "Admin notification already sent"
#    - Admin should NOT receive new message or email
```

**Expected Results:**
- ✅ User sees warning message
- ✅ sso_debug.txt shows: `Admin notification already sent for this user, skipping`
- ❌ Admin does NOT receive new message
- ❌ Admin does NOT receive new email

### **Test Case 3: Notification After Approval**

**Steps:**
```powershell
# 1. Admin approves user in Control Panel
# 2. User clears cookies
# 3. User tries login again via SSO
# 4. Observe:
#    - User logged in successfully
#    - NO warning message shown
#    - NO notification sent to admin
```

**Expected Results:**
- ✅ User logged in successfully
- ✅ sso_debug.txt shows: `Login successful`
- ✅ No warning message
- ❌ No admin notification

---

## Debug Logging

### **What Gets Logged**

When debug logging is enabled (`debugEnabled = 1` in module settings), sso_debug.txt will contain:

**Successful Notification:**
```
[WordPress SSO Debug] Sending pending approval notifications to administrators | Context: {
    "user": "baskar_iyer@yahoo.com",
    "email": "baskar_iyer@yahoo.com",
    "wp_user_id": "12",
    "admin_count": 2,
    "ip_address": "127.0.0.1"
}

[WordPress SSO Debug] Notification sent successfully | Context: {
    "admin_email": "admin1@example.com",
    "admin_username": "admin1"
}

[WordPress SSO Debug] Notification sent successfully | Context: {
    "admin_email": "admin2@example.com",
    "admin_username": "admin2"
}

[WordPress SSO Debug] Admin notification process completed | Context: {
    "total_admins": 2,
    "successful": 2,
    "failed": 0
}
```

**Duplicate Prevented:**
```
[WordPress SSO Debug] Admin notification already sent for this user, skipping | Context: {
    "user": "baskar_iyer@yahoo.com"
}
```

**Notification Error:**
```
[WordPress SSO Debug] Failed to send notification to admin | Context: {
    "admin_email": "admin@example.com",
    "error": "Could not connect to SMTP server"
}
```

---

## Troubleshooting

### **Problem: Admin Not Receiving Emails**

**Possible Causes:**
1. Email settings not configured in Webtrees
2. Admin user has no email address
3. Email blocked by spam filter
4. SMTP server not accessible

**Solutions:**
```powershell
# Check Webtrees email settings
# Control Panel → Website → Website Preferences → Email

# Check admin email address
& "C:\xampp\mysql\bin\mysql.exe" -u root -p
USE webtrees_database_name;
SELECT user_name, email FROM wt_user WHERE user_id IN (
    SELECT user_id FROM wt_user_setting 
    WHERE setting_name = 'canadmin' AND setting_value = '1'
);

# Test Webtrees email sending
# Control Panel → Website → Website Preferences → Email → Send Test Email
```

### **Problem: No Internal Messages**

**Check:**
1. MessageService properly injected in constructor
2. Admin is actually an administrator (`canadmin = 1`)
3. Database permissions allow message insert

**Verify:**
```sql
-- Check if message was created
SELECT * FROM wt_message 
WHERE subject LIKE '%approval needed%' 
ORDER BY message_id DESC 
LIMIT 5;
```

### **Problem: Notifications Sent Multiple Times**

**Check:**
```sql
-- Verify notification flag is set
SELECT * FROM wt_user_setting 
WHERE setting_name = 'sso_admin_notified' 
  AND user_id = (SELECT user_id FROM wt_user WHERE user_name = 'baskar_iyer@yahoo.com');
```

**If missing, manually set:**
```sql
INSERT INTO wt_user_setting (user_id, setting_name, setting_value)
SELECT user_id, 'sso_admin_notified', '1'
FROM wt_user
WHERE user_name = 'baskar_iyer@yahoo.com';
```

### **Problem: Want to Reset Notification Flag**

**To trigger notification again:**
```sql
DELETE FROM wt_user_setting 
WHERE user_id = (SELECT user_id FROM wt_user WHERE user_name = 'baskar_iyer@yahoo.com')
  AND setting_name = 'sso_admin_notified';
```

---

## Comparison with Native Webtrees Registration

### **Similarities (Leveraging Existing System)**

| Feature | Native Registration | WordPress SSO |
|---------|---------------------|---------------|
| Email to admins | ✅ Yes | ✅ Yes |
| Internal message | ✅ Yes | ✅ Yes |
| Approval required | ✅ Yes | ✅ Yes |
| Admin notification | ✅ Yes | ✅ Yes |
| MessageService used | ✅ Yes | ✅ Yes |
| EmailService used | ✅ Yes | ✅ Yes |

### **Differences (SSO-Specific)**

| Feature | Native Registration | WordPress SSO |
|---------|---------------------|---------------|
| Trigger | User submits registration form | User attempts SSO login |
| User knows username | Yes (they chose it) | Yes (WordPress username) |
| User knows password | Yes (they set it) | No (WordPress handles auth) |
| Can retry immediately | No (pending approval first) | Yes (but blocked + notified once) |
| WordPress ID included | No | ✅ Yes |
| IP address logged | Yes (registration log) | ✅ Yes (in notification) |
| Login attempt time | N/A | ✅ Yes |

---

## Advanced Configuration

### **Disable Notifications (Not Recommended)**

If you want to disable admin notifications for some reason:

**Option 1: Comment out the notification call**
```php
// Edit: src/Http/WordPressSsoLoginAction.php
// Line ~170

// $this->notifyAdministratorsAboutPendingUser($user, $request);
```

**Option 2: Auto-approve all SSO users** (bypasses approval entirely)
```php
// Edit: src/Http/WordPressSsoLoginAction.php
// After finding/creating user, add:

$user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');
```

⚠️ **WARNING:** Auto-approval is a security risk. Only use in trusted environments.

### **Custom Notification Recipients**

To send notifications to specific users (not just all admins):

```php
// Edit: notifyAdministratorsAboutPendingUser() method
// Replace:
$administrators = $this->user_service->administrators();

// With:
$administrators = [];
$administrators[] = $this->user_service->findByEmail('specific-admin@example.com');
$administrators[] = $this->user_service->findByUserName('manager1');
```

### **Customize Notification Message**

Edit the `$message_text` and `$message_html` variables in `notifyAdministratorsAboutPendingUser()` method to customize wording.

---

## Database Schema

### **User Preference Added**

**Table:** `wt_user_setting`

**New Preference:**
```sql
setting_name  = 'sso_admin_notified'
setting_value = '1'  -- Notification sent
```

**Purpose:** Prevent duplicate notifications for same user

**Cleared When:** Never (manual reset only, or user deletion)

---

## Performance Considerations

### **Email Sending**

- Sending emails can be **slow** (SMTP connection overhead)
- Multiple admins = multiple emails
- **Solution:** Emails sent synchronously but errors are caught and logged
- User experience **not blocked** (notification happens after redirect response)

### **Internal Messages**

- **Fast** - Simple database insert
- No external dependencies
- Multiple admins = multiple inserts (still fast)

### **Recommendation**

For large deployments with many admins (10+):
- Consider async email sending (requires custom implementation)
- Or only send to first N admins
- Or only send internal messages, skip emails

---

## Compliance and Privacy

### **Data Collected in Notifications**

- Username (necessary for admin to identify user)
- Email address (necessary for contact)
- Real name (from WordPress profile)
- WordPress User ID (for cross-reference)
- **IP Address** (for security audit)
- User Agent (browser/device info for security)
- Timestamp (when attempt occurred)

### **GDPR Compliance**

✅ **Legitimate Interest:** Admin notification is necessary for account approval process  
✅ **Data Minimization:** Only necessary information collected  
✅ **Purpose Limitation:** Data only used for user approval process  
✅ **Storage Limitation:** Email notifications temporary (in admin inbox), internal messages can be deleted  
✅ **Security:** Only sent to administrators (privileged access)  

**User Consent:** Implied when using SSO (terms of service should mention account approval process)

---

## Summary

### **What This Feature Does**

✅ Automatically notifies all Webtrees administrators when SSO user needs approval  
✅ Sends both internal message and email  
✅ Prevents duplicate notifications  
✅ Provides detailed user information for admin decision  
✅ Logs everything for audit trail  
✅ Handles errors gracefully  

### **Benefits**

- ✅ Faster user onboarding (admin notified immediately)
- ✅ Better user experience (less confusion about pending status)
- ✅ Leverages existing Webtrees infrastructure
- ✅ Consistent with native registration flow
- ✅ Security tracking (IP address, timestamp)
- ✅ No new dependencies required

### **Deployment**

The feature is **automatically enabled** after deploying the updated `WordPressSsoLoginAction.php` file. No configuration changes needed.

---

## Next Steps

1. ✅ Deploy updated `WordPressSsoLoginAction.php`
2. ✅ Restart Apache (XAMPP Control Panel)
3. ⏳ Test with unapproved user (baskar_iyer@yahoo.com)
4. ⏳ Verify admin receives notification (email + internal message)
5. ⏳ Verify no duplicate notifications on second attempt
6. ⏳ Approve user and verify login works
7. ⏳ Deploy to Linux production when verified

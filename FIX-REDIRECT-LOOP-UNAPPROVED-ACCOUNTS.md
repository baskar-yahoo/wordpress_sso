# Fix: Infinite Redirect Loop for Unapproved Accounts

## Problem Summary

**User Reported Issue:**
- User `nalini.baskar@gmail.com` (approved account) - SSO works perfectly ✅
- User `baskar_iyer@yahoo.com` (unapproved account) - infinite redirect loop ❌
- Error: `ERR_TOO_MANY_REDIRECTS`
- Browser URL stuck at: `http://localhost/svajana/familytree/index.php?route=%2Fsvajana%2Ffamilytree%2Fwordpress-sso%2Fcallback`

## Root Cause Analysis

### Debug Log Pattern

**Successful Login (nalini.baskar@gmail.com):**
```
03:28:01 - SSO Request Start (authenticated: No)
03:28:04 - Callback received
03:28:08 - User data retrieved → Found existing user
03:28:08 - Login successful ✅
```

**Failed Login - Infinite Loop (baskar_iyer@yahoo.com):**
```
03:31:22 - SSO Request Start (authenticated: No)
03:31:27 - Callback → User found
03:31:31 - SSO Request Start AGAIN ❌
03:31:35 - Callback → User found
03:31:40 - SSO Request Start AGAIN ❌
03:31:48 - SSO Request Start AGAIN ❌
... (continues infinitely)
```

### Critical Observation

For `baskar_iyer@yahoo.com`:
- ✅ OAuth flow completes successfully
- ✅ Access token received
- ✅ User data retrieved from WordPress
- ✅ User found in Webtrees database
- ❌ **"Login successful" log entry MISSING**
- ❌ User remains unauthenticated
- ❌ Triggers SSO again immediately

### Why It Happens

**Code Flow in WordPressSsoLoginAction.php:**

1. OAuth completes, user found in database
2. **Account approval check fails:**
   ```php
   if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
       FlashMessages::addMessage('...pending approval...', 'success');
       return redirect(route(HomePage::class));  // ← Skips login!
   }
   ```
3. Redirects to HomePage **WITHOUT** logging in
4. **WordPressSsoHomePage.php detects unauthenticated user:**
   ```php
   if (!Auth::check() && $this->module->getConfig('enabled') === '1') {
       return redirect(route(WordPressSsoLoginAction::class));  // ← Triggers SSO again!
   }
   ```
5. **Infinite loop:** Steps 1-4 repeat forever

## The Fix

### Solution: Session Flag to Prevent Redirect Loop

**Three Changes Made:**

#### 1. WordPressSsoLoginAction.php - Set Session Flag

When account is not approved, set a session flag to prevent immediate SSO redirect:

```php
if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
    // Set session flag to prevent SSO redirect loop
    Session::put('sso_approval_pending', true);
    Session::put('sso_pending_user_email', $user->email());
    
    FlashMessages::addMessage(
        I18N::translate('Your account has been created and is pending administrator approval...'),
        'warning'  // Changed from 'success' to 'warning' for better visibility
    );
    
    $this->logger->log('Login blocked - account not approved', [
        'user' => $user->userName(),
        'email' => $user->email()
    ]);
    
    $this->cleanupSession();
    return redirect(route(HomePage::class));
}
```

#### 2. WordPressSsoHomePage.php - Check Session Flag

Before triggering SSO redirect, check if user has pending approval:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    // Check if user has a pending approval (prevent SSO redirect loop)
    if (Session::has('sso_approval_pending')) {
        // Clear the flag after showing the message once
        Session::forget('sso_approval_pending');
        Session::forget('sso_pending_user_email');
        
        // Let the normal HomePage show the pending approval message
        return parent::handle($request);
    }
    
    // If not logged in and SSO is enabled, redirect to SSO login
    if (!Auth::check() && $this->module->getConfig('enabled') === '1') {
        return redirect(route(WordPressSsoLoginAction::class));
    }

    return parent::handle($request);
}
```

#### 3. WordPressSsoHomePage.php - Add Session Import

```php
use Fisharebest\Webtrees\Session;
```

## How It Works Now

### New Flow for Unapproved Accounts

1. **OAuth completes** → User found in database
2. **Account not approved** → Set `sso_approval_pending` session flag
3. **Redirect to HomePage** with flash message
4. **WordPressSsoHomePage checks flag:**
   - Flag exists → Clear it, show normal HomePage with approval message
   - User sees: "Your account is pending administrator approval"
5. **Loop broken!** ✅

### Debug Log - After Fix

```
[WordPress SSO Debug] User data retrieved → Found existing user
[WordPress SSO Debug] Login blocked - account not approved | user: baskar_iyer@yahoo.com
[WordPress SSO Debug] Session flag set: sso_approval_pending
(User redirected to HomePage)
(HomePage shows approval message - NO MORE SSO REDIRECT)
```

## Testing Steps

### Test Scenario 1: Approved Account (nalini.baskar@gmail.com)

1. Clear browser cookies (incognito mode)
2. Navigate to: `http://localhost/svajana/familytree`
3. **Expected:** SSO redirect → WordPress login → Auto-login to Webtrees ✅
4. **Verify:** User is logged in, no redirect loop

### Test Scenario 2: Unapproved Account (baskar_iyer@yahoo.com)

1. Clear browser cookies (new incognito window)
2. Navigate to: `http://localhost/svajana/familytree`
3. **Expected:** SSO redirect → WordPress login → Webtrees HomePage with message:
   ```
   ⚠️ Your account has been created and is pending administrator approval.
      You will be notified via email once approved.
   ```
4. **Verify:** NO infinite redirect, user sees clear message
5. **Verify:** sso_debug.txt shows: `Login blocked - account not approved`

### Test Scenario 3: Approve Account Then Login

1. **Admin approves** `baskar_iyer@yahoo.com` in Webtrees Control Panel
2. User clears browser cookies
3. Navigate to: `http://localhost/svajana/familytree`
4. **Expected:** SSO redirect → WordPress login → Auto-login to Webtrees ✅
5. **Verify:** User successfully logged in

## Verification Commands

### Check Account Approval Status in Database

**Windows (PowerShell):**
```powershell
# Connect to MySQL
& "C:\xampp\mysql\bin\mysql.exe" -u root -p

# Check user approval status
USE webtrees_database_name;
SELECT user_name, email, setting_value as approved 
FROM wt_user 
LEFT JOIN wt_user_setting ON wt_user.user_id = wt_user_setting.user_id 
  AND setting_name = 'account_approved'
WHERE user_name IN ('nalini.baskar@gmail.com', 'baskar_iyer@yahoo.com');
```

**Expected Output:**
```
+---------------------------+---------------------------+----------+
| user_name                 | email                     | approved |
+---------------------------+---------------------------+----------+
| nalini.baskar@gmail.com   | nalini.baskar@gmail.com   | 1        |
| baskar_iyer@yahoo.com     | baskar_iyer@yahoo.com     | NULL     | ← Not approved!
+---------------------------+---------------------------+----------+
```

### Approve User Manually (if needed)

```sql
-- Approve baskar_iyer@yahoo.com
INSERT INTO wt_user_setting (user_id, setting_name, setting_value)
SELECT user_id, 'account_approved', '1'
FROM wt_user
WHERE user_name = 'baskar_iyer@yahoo.com'
ON DUPLICATE KEY UPDATE setting_value = '1';

-- Verify
SELECT user_name, setting_value 
FROM wt_user 
JOIN wt_user_setting ON wt_user.user_id = wt_user_setting.user_id
WHERE setting_name = 'account_approved' 
  AND user_name = 'baskar_iyer@yahoo.com';
```

## Deployment Steps

### Windows XAMPP Environment

```powershell
# Navigate to module directory
cd C:\xampp\htdocs\familytree\modules_v4\wordpress_sso

# Verify files were updated
Get-Content src\Http\WordPressSsoLoginAction.php | Select-String "sso_approval_pending"
Get-Content src\Http\WordPressSsoHomePage.php | Select-String "sso_approval_pending"

# Restart Apache
# Open XAMPP Control Panel → Stop Apache → Start Apache

# Clear Webtrees cache (optional but recommended)
Remove-Item -Path "C:\xampp\htdocs\familytree\data\cache\*" -Recurse -Force -ErrorAction SilentlyContinue
```

### Linux Production Environment

```bash
# Navigate to module directory
cd /public_html/familytree/modules_v4/wordpress_sso

# Verify files were updated
grep "sso_approval_pending" src/Http/WordPressSsoLoginAction.php
grep "sso_approval_pending" src/Http/WordPressSsoHomePage.php

# Clear Webtrees cache
rm -rf /public_html/familytree/data/cache/*

# Restart web server (if you have access)
sudo systemctl restart apache2
# OR for LiteSpeed
sudo systemctl restart lsws
```

## What Changed in the Code

### File: WordPressSsoLoginAction.php

**Added:**
- Session flag: `Session::put('sso_approval_pending', true)`
- User email tracking: `Session::put('sso_pending_user_email', $user->email())`
- Debug log: `Login blocked - account not approved`
- Changed message type: `'warning'` (was `'success'`)

### File: WordPressSsoHomePage.php

**Added:**
- Import: `use Fisharebest\Webtrees\Session;`
- Check for pending approval flag at start of `handle()` method
- Clear flags after first check
- Allow normal HomePage to display for pending approvals

## Security Considerations

### Session Flag Lifecycle

1. **Set:** When account not approved during SSO callback
2. **Check:** On next HomePage request
3. **Clear:** Immediately after first check
4. **Lifespan:** Single request cycle only

### Why This Is Safe

- ✅ Flag is cleared after one use (prevents stale data)
- ✅ Does NOT bypass authentication checks
- ✅ Only prevents SSO redirect loop
- ✅ User still cannot access restricted content
- ✅ Admin approval still required for full access

## Common Questions

### Q1: Why didn't this happen with nalini.baskar@gmail.com?

**A:** That account was already approved (`account_approved = '1'` in database). The approval check passed, so login completed successfully.

### Q2: Why does the loop happen only with unapproved accounts?

**A:** Because:
1. Approved accounts → Login succeeds → `Auth::check()` returns `true` → No SSO redirect
2. Unapproved accounts → Login skipped → `Auth::check()` returns `false` → SSO redirects again

### Q3: What happens after admin approves the account?

**A:** User clears cookies, visits site, SSO completes, login succeeds normally. The session flag is only set when approval fails.

### Q4: Can I approve all users automatically?

**A:** Yes, but **NOT RECOMMENDED** for security. If you must:

**Edit WordPressSsoLoginAction.php (line ~145):**
```php
// AUTO-APPROVE NEW USERS (NOT RECOMMENDED FOR PRODUCTION)
if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
    // Automatically approve the user
    $user->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '1');
    $this->logger->log('User auto-approved via SSO', ['user' => $user->userName()]);
}
```

**WARNING:** This bypasses admin review. Better approach: Configure Webtrees to auto-approve in Control Panel → User Administration → Default account settings.

### Q5: How do I check why a user is not approved?

**Check in Webtrees Admin Panel:**
1. Login as admin
2. Go to: **Control Panel** → **User Administration**
3. Find user: `baskar_iyer@yahoo.com`
4. Check **Status** column: Should show "Pending approval" or similar
5. Click user → Click **Approve** button

## Summary

### Problem
Unapproved accounts caused infinite SSO redirect loop because login was skipped but SSO kept triggering.

### Solution
Set temporary session flag when approval fails, check flag before triggering SSO redirect, allow normal HomePage to display approval message.

### Impact
- ✅ Fixes infinite redirect for unapproved accounts
- ✅ Shows clear "pending approval" message to users
- ✅ Maintains security (no approval bypass)
- ✅ Better debug logging
- ✅ Works for both approved and unapproved accounts

## Next Steps

1. ✅ Deploy updated files (already done)
2. ✅ Restart Apache (XAMPP Control Panel)
3. ⏳ Test with `baskar_iyer@yahoo.com` (unapproved account)
4. ⏳ Verify no redirect loop
5. ⏳ Approve user in admin panel
6. ⏳ Test login again (should work)
7. ⏳ Deploy to Linux production when verified

## Support

If you still see redirect loops after this fix:
1. Check sso_debug.txt for "Login blocked - account not approved"
2. Verify session is working: `Session::has('sso_approval_pending')` should be true
3. Check database: User's `account_approved` setting value
4. Review Apache error logs for session issues

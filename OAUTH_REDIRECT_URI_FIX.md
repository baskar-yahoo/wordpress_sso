# OAuth Redirect URI Fix

## Problem
WordPress's OAuth2 Provider plugin uses `sanitize_text_field()` to clean the redirect URI field. This function **strips forward slashes**, which breaks OAuth2 redirect URIs that contain slashes in route parameters.

### Example
- **Expected redirect URI**: `http://localhost/svajana/familytree/index.php?route=/svajana/familytree/wordpress-sso/callback`
- **What gets saved**: `http://localhost/svajana/familytree/index.php?route=svajanafamilytreewordpress-ssocallback`
- **Result**: `redirect_uri_mismatch` error during OAuth flow

## Solution for New Installations (No Code Changes Required)

### ✅ Recommended: Enter URL-Encoded Redirect URI
When creating the OAuth client in WordPress admin, **enter the redirect URI with URL-encoded slashes** (`%2F` instead of `/`):

```
http://localhost/svajana/familytree/index.php?route=%2Fsvajana%2Ffamilytree%2Fwordpress-sso%2Fcallback
```

**Why this works:**
- WordPress's `sanitize_text_field()` strips forward slash characters (`/`) but preserves percent-encoded slashes (`%2F`)
- The wordpress_sso module sends redirect URIs with URL-encoded slashes in the OAuth flow
- They match perfectly, no code modifications needed!

**Steps:**
1. Install OAuth2 Provider plugin
2. Go to Users → Applications → Add New
3. Enter the redirect URI as shown above (with `%2F`)
4. Save the OAuth client
5. Test the login flow - it should work immediately

## Alternative Solutions

### Option 2: Modify Plugin Code (If Automatic Updates Aren't Needed)
If you prefer working with readable URLs in the admin interface, modify the OAuth2 Provider plugin to use `esc_url_raw()` instead of `sanitize_text_field()`:

**File**: `wp-content/plugins/oauth2-provider/includes/functions.php`

**Lines to Change**:
- Line 90 in `wo_insert_client()` function
- Line 145 in `wo_update_client()` function

**Change**:
```php
// BEFORE (strips slashes):
$redirect_url = sanitize_text_field( $client_data['redirect_uri'] );

// AFTER (preserves slashes):
$redirect_url = esc_url_raw( $client_data['redirect_uri'] );
```

⚠️ **Warning**: Plugin updates will overwrite this change.

### Option 3: Database Workaround (Emergency Fix)
If the OAuth client is already created with stripped slashes:
```sql
UPDATE wpw4_postmeta 
SET meta_value = 'http://localhost/svajana/familytree/index.php?route=%2Fsvajana%2Ffamilytree%2Fwordpress-sso%2Fcallback'
WHERE post_id = (SELECT ID FROM wpw4_posts WHERE post_type = 'wo_client' LIMIT 1)
AND meta_key = 'redirect_uri';
```

## Testing
After applying the fix, verify the OAuth flow:
1. Go to Webtrees login page
2. Click "Login with WordPress"
3. Should redirect to WordPress, authenticate, and return successfully
4. Check Apache error log for any redirect_uri_mismatch errors

## References
- WordPress function: [`sanitize_text_field()`](https://developer.wordpress.org/reference/functions/sanitize_text_field/) - Strips HTML tags and **slashes**
- WordPress function: [`esc_url_raw()`](https://developer.wordpress.org/reference/functions/esc_url_raw/) - Sanitizes URLs while preserving encoded characters

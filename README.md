# WordPress SSO for webtrees

**Version:** 2.0.0  
**Status:** Production Ready  
**Compatible with:** webtrees 2.2.4+

Production-ready Single Sign-On module for webtrees using WordPress as the identity provider.

---

## Features

✅ **Seamless SSO** - Automatic login when users visit webtrees  
✅ **PKCE Security** - RFC 7636 Proof Key for Code Exchange  
✅ **Email Synchronization** - Keep emails in sync with WordPress  
✅ **Comprehensive Error Handling** - Detailed error messages and logging  
✅ **Debug Logging** - Extensive debugging capabilities  
✅ **User Switch Detection** - Prevents session hijacking  
✅ **Cookie Validation** - Ensures browser compatibility  
✅ **JIT Provisioning** - Automatic user creation with admin approval  
✅ **Single Logout** - Log out from both webtrees and WordPress  
✅ **Config File Support** - Version-controlled configuration  

---

## Requirements

- **webtrees:** 2.2.4 or later
- **WordPress:** with [oauth2-provider plugin](https://wordpress.org/plugins/miniorange-oauth-20-server/)
- **PHP:** 7.4+ or 8.0+
- **HTTPS:** Strongly recommended for security

---

## Installation

### Step 1: Install the Module

1. Copy the `wordpress_sso` directory to your webtrees `modules_v4` folder
2. Open a command prompt and navigate to the module directory:
   ```cmd
   cd c:\laragon\www\familytree\modules_v4\wordpress_sso
   ```
3. Install dependencies using the provided script:
   ```cmd
   c:\Users\baska\.gemini\antigravity\playground\harmonic-shepard\install_wordpress_sso_dependencies.bat
   ```
   
   Or manually:
   ```cmd
   c:\laragon\bin\composer\composer.bat install
   ```

### Step 2: Configure WordPress OAuth2 Provider

1. Install and activate the **WP OAuth Server** plugin in WordPress
2. Go to WordPress Admin → OAuth Server → Configure Application
3. Click "Custom OAuth Client"
4. Enter:
   - **Client Name:** webtrees
   - **Callback/Redirect URI:** (You'll get this from webtrees in Step 3)
5. Save and note down:
   - Client ID
   - Client Secret
   - Authorization Endpoint
   - Token Endpoint
   - Userinfo Endpoint

### Step 3: Configure webtrees Module

You have two options for configuration:

#### Option A: Using config.ini.php (Recommended)

1. Open your webtrees `config.ini.php` file
2. Add the configuration from `wordpress_sso_config_example.ini`:
   ```ini
   ; WordPress SSO Configuration
   WordPress_SSO_enabled='1'
   WordPress_SSO_allowCreation='1'
   WordPress_SSO_clientId='your_client_id_from_wordpress'
   WordPress_SSO_clientSecret='your_client_secret_from_wordpress'
   WordPress_SSO_urlAuthorize='https://your-wordpress-site.com/oauth/authorize'
   WordPress_SSO_urlAccessToken='https://your-wordpress-site.com/oauth/token'
   WordPress_SSO_urlResourceOwner='https://your-wordpress-site.com/oauth/me'
   WordPress_SSO_urlLogout='https://your-wordpress-site.com/wp-login.php?action=logout'
   WordPress_SSO_pkceMethod='S256'
   WordPress_SSO_syncEmail='1'
   WordPress_SSO_debugEnabled='0'
   ```

#### Option B: Using Control Panel UI

1. Log in to webtrees as administrator
2. Go to **Control Panel → Modules**
3. Find **WordPress SSO** and click **Configure**
4. Fill in the settings:
   - Enable Seamless SSO: ✓
   - Allow New User Creation: ✓
   - Client ID: (from WordPress)
   - Client Secret: (from WordPress)
   - Authorization URL: `https://your-site.com/oauth/authorize`
   - Access Token URL: `https://your-site.com/oauth/token`
   - Resource Owner Details URL: `https://your-site.com/oauth/me`
   - Logout URL: `https://your-site.com/wp-login.php?action=logout`
   - PKCE Method: S256
   - Sync Email: ✓
   - Debug Logging: (only for troubleshooting)
5. Copy the **Callback URL** shown at the bottom
6. Go back to WordPress and paste it into the OAuth client's Redirect URI field
7. Click **Save**

---

## Configuration Options

| Option | Description | Values | Default |
|--------|-------------|--------|---------|
| **enabled** | Enable/disable WordPress SSO | `'1'` or `'0'` | `'0'` |
| **allowCreation** | Allow automatic user creation | `'1'` or `'0'` | `'0'` |
| **clientId** | OAuth2 Client ID from WordPress | string | - |
| **clientSecret** | OAuth2 Client Secret from WordPress | string | - |
| **urlAuthorize** | WordPress authorization endpoint | URL | - |
| **urlAccessToken** | WordPress token endpoint | URL | - |
| **urlResourceOwner** | WordPress user info endpoint | URL | - |
| **urlLogout** | WordPress logout URL | URL | - |
| **pkceMethod** | PKCE security method | `'S256'`, `'plain'`, `''` | `'S256'` |
| **syncEmail** | Sync email from WordPress | `'1'` or `'0'` | `'0'` |
| **debugEnabled** | Enable debug logging | `'1'` or `'0'` | `'0'` |

---

## Security Features

### PKCE (Proof Key for Code Exchange)

PKCE adds an extra layer of security to the OAuth2 flow, protecting against:
- Authorization code interception attacks
- CSRF attacks (additional layer beyond state parameter)
- Man-in-the-middle attacks

**Recommended setting:** `WordPress_SSO_pkceMethod='S256'`

### User Switch Detection

Prevents session hijacking by detecting if a different user tries to complete an OAuth flow started by another user.

### Cookie Validation

Ensures the browser accepts cookies before attempting the OAuth flow, preventing authentication failures.

### CSRF Protection

Uses OAuth2 state parameter to prevent Cross-Site Request Forgery attacks.

---

## User Workflow

### New User Registration

1. User visits webtrees (not logged in)
2. Automatically redirected to WordPress login
3. User logs into WordPress
4. WordPress redirects back to webtrees
5. Module creates new webtrees account (if enabled)
6. Account requires administrator approval
7. Administrator receives email notification
8. Administrator approves account in Control Panel → Users
9. User can now log in automatically

### Existing User Login

1. User visits webtrees (not logged in)
2. Automatically redirected to WordPress login
3. User logs into WordPress
4. WordPress redirects back to webtrees
5. Module matches user by WordPress ID or email
6. User is logged into webtrees automatically

### Email Synchronization

If enabled (`WordPress_SSO_syncEmail='1'`):
- User's email is updated from WordPress on each login
- Keeps webtrees and WordPress emails in sync
- User receives notification when email is updated

---

## Troubleshooting

### Module Not Visible in Control Panel

1. Check that `vendor` folder exists in the module directory
2. Run: `c:\laragon\bin\composer\composer.bat install`
3. Check webtrees logs for errors
4. Verify file permissions

### OAuth2 Flow Fails

1. Enable debug logging: `WordPress_SSO_debugEnabled='1'`
2. Check webtrees logs: Control Panel → Website logs
3. Verify all URLs are correct (https://)
4. Check WordPress OAuth2 plugin configuration
5. Verify Callback URL matches in both systems

### User Creation Fails

1. Check that `WordPress_SSO_allowCreation='1'`
2. Verify email doesn't already exist in webtrees
3. Check webtrees logs for specific error
4. Ensure WordPress provides all required user data (ID, email, username)

### PKCE Errors

1. Verify WordPress OAuth2 plugin supports PKCE
2. Try setting `WordPress_SSO_pkceMethod=''` to disable PKCE
3. Check debug logs for PKCE-related errors

### Email Not Syncing

1. Verify `WordPress_SSO_syncEmail='1'`
2. Check that user's email in WordPress is different
3. Enable debug logging to see sync attempts

---

## Debug Logging

Enable detailed logging for troubleshooting:

```ini
WordPress_SSO_debugEnabled='1'
```

Logs include:
- Request details (method, URI, authentication status)
- OAuth2 flow steps (authorization, token exchange, user data)
- User matching and creation
- Email synchronization
- Security checks (PKCE, state validation, user switch detection)
- Error details with stack traces

**View logs:** Control Panel → Website logs

**Important:** Disable debug logging in production for performance and security.

---

## Migration from Database to config.ini.php

If you initially configured via Control Panel and want to move to config.ini.php:

1. Note your current settings from Control Panel → Modules → WordPress SSO → Configure
2. Add them to `config.ini.php` using the format shown in `wordpress_sso_config_example.ini`
3. The module will automatically use config.ini.php values if present
4. Database preferences serve as fallback for backward compatibility

---

## Changelog

### Version 2.0.0 (2026-01-18)

**Major Enhancements:**
- ✅ Upgraded OAuth2 client library to ^2.8
- ✅ Added PKCE support (RFC 7636)
- ✅ Implemented user switch detection
- ✅ Added cookie validation
- ✅ Implemented email synchronization from WordPress
- ✅ Added comprehensive error handling with custom exceptions
- ✅ Implemented detailed debug logging service
- ✅ Added config.ini.php support for version-controlled configuration
- ✅ Enhanced security with multiple validation layers

**Breaking Changes:**
- None - fully backward compatible with 1.0.0

**Security Improvements:**
- PKCE protection against code interception
- User switch detection prevents session hijacking
- Cookie validation ensures browser compatibility
- Enhanced CSRF protection with state validation

### Version 1.0.0 (Previous)

- Initial release
- Basic WordPress SSO functionality
- JIT provisioning with admin approval
- Single logout support

---

## License

GNU General Public License v3.0

---

## Support

For issues, questions, or feature requests:
1. Check the troubleshooting section above
2. Enable debug logging and check logs
3. Review WordPress OAuth2 plugin documentation
4. Contact your webtrees administrator

---

## Credits

- **Enhanced by:** Gemini AI
- **Based on:** webtrees OAuth2 concepts
- **OAuth2 Library:** [The League OAuth2 Client](https://oauth2-client.thephpleague.com/)
- **Compatible with:** [WP OAuth Server](https://wordpress.org/plugins/miniorange-oauth-20-server/)
# WordPress SSO for webtrees

**Version:** 2.0.0  
**Status:** Production Ready ✅  
**Platform:** Cross-Platform (Windows & Linux)  
**Compatible with:** webtrees 2.2.4+

Production-ready Single Sign-On module for webtrees using WordPress as the identity provider. Fully tested for both Windows and Linux environments.

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
✅ **Cross-Platform** - Works on Windows and Linux servers  

---

## Requirements

- **webtrees:** 2.2.4 or later
- **WordPress:** with [WP OAuth Server plugin](https://wordpress.org/plugins/miniorange-oauth-20-server/) (v4.4.0+)
- **PHP:** 7.4+ or 8.0+
- **HTTPS:** **Required for production** (strongly recommended)
- **Composer:** For dependency installation
- **Server:** Apache or Nginx on Windows or Linux

---

## Installation

### Step 1: Install the Module

#### On Windows:
1. Copy the `wordpress_sso` directory to your webtrees `modules_v4` folder
2. Open Command Prompt or PowerShell and navigate to the module directory:
   ```cmd
   cd C:\xampp\htdocs\familytree\modules_v4\wordpress_sso
   ```
3. Install dependencies using Composer:
   ```cmd
   composer install --no-dev
   ```

#### On Linux:
1. Copy the `wordpress_sso` directory to your webtrees `modules_v4` folder:
   ```bash
   cp -r wordpress_sso /var/www/html/webtrees/modules_v4/
   ```
2. Navigate to the module directory:
   ```bash
   cd /var/www/html/webtrees/modules_v4/wordpress_sso
   ```
3. Install dependencies using Composer:
   ```bash
   composer install --no-dev
   ```
4. Set proper permissions:
   ```bash
   chown -R www-data:www-data /var/www/html/webtrees/modules_v4/wordpress_sso
   chmod -R 755 /var/www/html/webtrees/modules_v4/wordpress_sso
   ```

### Step 2: Configure WordPress OAuth2 Provider

1. Install and activate the **WP OAuth Server** plugin in WordPress
2. Go to WordPress Admin → Users → Applications → Add New
3. Enter:
   - **Client Name:** webtrees-sso
   - **Redirect URI:** `http://yourdomain.com/webtrees/index.php?route=/wordpress-sso/callback`
     
     **Note**: Use standard forward slashes (`/`). The module handles decoding automatically.
     
     ✅ **Correct**: `route=/wordpress-sso/callback`
     ❌ **Avoid**: `route=%2Fwordpress-sso%2Fcallback` (Double encoding may occur)
     
4. Select Grant Types: **Authorization Code**
5. Save and note down:
   - Client ID
   - Client Secret

### Step 3: Configure webtrees Module

You have two configuration options:

#### Option A: Using config.ini.php (Recommended for Production)

**Benefits:** Version-controlled, no database changes, portable between environments

1. Open your webtrees `data/config.ini.php` file
2. Add the WordPress SSO configuration section:
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
3. Save the file
4. **Linux only:** Set proper permissions:
   ```bash
   chmod 644 /var/www/html/webtrees/data/config.ini.php
   chown www-data:www-data /var/www/html/webtrees/data/config.ini.php
   ```

#### Option B: Using Control Panel UI

1. Log in to webtrees as administrator
2. Go to **Control Panel → Modules → All modules**
3. Find **WordPress SSO** and click **Preferences**
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
5. Copy the **Callback URL** shown at the bottom (e.g., `https://your-site.com/webtrees/index.php?route=/wordpress-sso/callback`)
6. Go back to WordPress OAuth client configuration and paste it into the Redirect URI field
7. Click **Save**

---

## Production Deployment

### Security Checklist

- [ ] **HTTPS Enabled** - SSL/TLS certificate installed and working
- [ ] **Redirect URI** uses standard slashes (`/`)
- [ ] **PKCE Enabled** - Set `WordPress_SSO_pkceMethod='S256'`
- [ ] **Debug Logging Disabled** - Set `WordPress_SSO_debugEnabled='0'`
- [ ] **Strong Client Secret** - Use auto-generated secret from WordPress
- [ ] **Firewall Rules** - Configure firewall to allow OAuth traffic
- [ ] **File Permissions** (Linux only):
  - config.ini.php: 644
  - wordpress_sso folder: 755
  - data directory: 755 (www-data owner)

### Platform-Specific Considerations

#### Windows (IIS/Apache/XAMPP)
- Use backslashes in Windows paths internally (handled by `DIRECTORY_SEPARATOR`)
- Ensure PHP has write permissions to `data` directory
- Test with both localhost and domain name
- Check Windows Firewall allows port 80/443

#### Linux (Apache/Nginx)
- Set correct file ownership: `www-data:www-data` (or `apache:apache` on CentOS/RHEL)
- Configure SELinux if enabled:
  ```bash
  chcon -R -t httpd_sys_rw_content_t /var/www/html/webtrees/data/
  ```
- Ensure mod_rewrite (Apache) or URL rewriting (Nginx) is enabled
- Configure logrotate for debug logs if used

### Environment-Specific URLs

**Development:**
```ini
WordPress_SSO_urlAuthorize='http://localhost/wordpress/oauth/authorize'
WordPress_SSO_urlAccessToken='http://localhost/wordpress/oauth/token'
WordPress_SSO_urlResourceOwner='http://localhost/wordpress/oauth/me'
```

**Production:**
```ini
WordPress_SSO_urlAuthorize='https://www.example.com/oauth/authorize'
WordPress_SSO_urlAccessToken='https://www.example.com/oauth/token'
WordPress_SSO_urlResourceOwner='https://www.example.com/oauth/me'
```

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

**Windows:**
```cmd
cd C:\xampp\htdocs\familytree\modules_v4\wordpress_sso
composer install --no-dev
```

**Linux:**
```bash
cd /var/www/html/webtrees/modules_v4/wordpress_sso
composer install --no-dev
chown -R www-data:www-data vendor/
```

**Verify:**
- Check that `vendor` folder exists
- Check webtrees logs: Control Panel → Website logs
- Check PHP error log

### OAuth2 Flow Fails (redirect_uri_mismatch)

**This is the most common issue.** The redirect URI must use URL-encoded slashes.

1. Check your WordPress OAuth client configuration
2. Verify redirect URI is **exactly**:
   ```
   https://your-site.com/webtrees/index.php?route=/wordpress-sso/callback
   ```
3. Enable debug logging: `WordPress_SSO_debugEnabled='1'`
4. Check error logs for exact URI being sent
5. *Note: As of v2.0.0, manual encoding is no longer required.*

### User Creation Fails

1. Check that `WordPress_SSO_allowCreation='1'`
2. Verify email doesn't already exist in webtrees
3. Check webtrees logs for specific error
4. Ensure WordPress provides all required user data (ID, email, username)
5. Check file permissions (Linux):
   ```bash
   ls -la /var/www/html/webtrees/data/
   ```

### PKCE Errors

1. Verify WordPress OAuth2 plugin supports PKCE (v4.4.0+)
2. Try setting `WordPress_SSO_pkceMethod='plain'` (less secure)
3. If that fails, disable PKCE: `WordPress_SSO_pkceMethod=''`
4. Check debug logs for PKCE-related errors

### Email Not Syncing

1. Verify `WordPress_SSO_syncEmail='1'`
2. Check that user's email in WordPress is different
3. Enable debug logging to see sync attempts
4. Check that user is approved (`IS_ACCOUNT_APPROVED = 1`)

### Permission Issues (Linux Only)

```bash
# Fix ownership
chown -R www-data:www-data /var/www/html/webtrees/modules_v4/wordpress_sso
chown www-data:www-data /var/www/html/webtrees/data/config.ini.php

# Fix permissions
chmod -R 755 /var/www/html/webtrees/modules_v4/wordpress_sso
chmod 644 /var/www/html/webtrees/data/config.ini.php
chmod 755 /var/www/html/webtrees/data/

# Check SELinux (if enabled)
getenforce
# If Enforcing, run:
chcon -R -t httpd_sys_rw_content_t /var/www/html/webtrees/data/
```

### Windows Path Issues

- Module uses `DIRECTORY_SEPARATOR` for cross-platform compatibility
- If encountering path issues, check PHP version and path configuration
- Ensure no hardcoded slashes in custom code

---

## Debug Logging

Enable detailed logging for troubleshooting:

```ini
WordPress_SSO_debugEnabled='1'
```

**Log Location:** `webtrees/data/sso_debug.txt` (cross-platform)

Logs include:
- Request details (method, URI, authentication status)
- OAuth2 flow steps (authorization, token exchange, user data)
- User matching and creation
- Email synchronization
- Security checks (PKCE, state validation, user switch detection)
- Error details with stack traces

**View logs:**
- **Windows:** `C:\xampp\htdocs\familytree\data\sso_debug.txt`
- **Linux:** `/var/www/html/webtrees/data/sso_debug.txt`
- **Control Panel:** Website logs section

**⚠️ Important:** Disable debug logging in production:
```ini
WordPress_SSO_debugEnabled='0'
```

---

## Performance & Optimization

### Production Performance Tips

1. **Disable Debug Logging** - Significantly improves performance
2. **Use OpCache** - Enable PHP OpCache for better performance
3. **Database Optimization** - Ensure WordPress and webtrees databases are optimized
4. **HTTPS/2** - Use HTTP/2 for faster OAuth redirects
5. **CDN** - Consider CDN for WordPress assets
6. **Caching** - Enable WordPress object caching (Redis/Memcached)

### Recommended PHP Settings

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
session.cookie_httponly=1
session.cookie_secure=1
```

---

## Migration & Backup

### Before Deployment

### Before Deployment

1. **Backup Configuration:**
   ```bash
   # Linux
   cp /var/www/html/webtrees/data/config.ini.php /backup/config.ini.php.bak
   
   # Windows
   copy C:\xampp\htdocs\familytree\data\config.ini.php C:\backup\config.ini.php.bak
   ```

2. **Backup Databases:**
   ```bash
   # Webtrees database
   mysqldump -u root -p webtrees_db > webtrees_backup.sql
   
   # WordPress database
   mysqldump -u root -p wordpress_db > wordpress_backup.sql
   ```

3. **Test in Staging:**
   - Set up identical staging environment
   - Test complete OAuth flow
   - Test user creation and email sync
   - Test logout flow
   - Verify HTTPS works correctly

### Moving Between Environments

When moving from development to production:

1. Update URLs in `config.ini.php`:
   ```ini
   ; Change from localhost to production domain
   WordPress_SSO_urlAuthorize='https://production-site.com/oauth/authorize'
   WordPress_SSO_urlAccessToken='https://production-site.com/oauth/token'
   WordPress_SSO_urlResourceOwner='https://production-site.com/oauth/me'
   WordPress_SSO_urlLogout='https://production-site.com/wp-login.php?action=logout'
   ```

2. Update WordPress OAuth client redirect URI to match new domain

3. Verify HTTPS certificate is valid

4. Test OAuth flow completely

---

## Security Best Practices

### HTTPS Configuration

**Required for production.** OAuth2 requires HTTPS for security.

**Apache (httpd.conf or .htaccess):**
```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Nginx (nginx.conf):**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
}
```

### Security Headers

Add these headers for enhanced security:

**Apache (.htaccess):**
```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

**Nginx:**
```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

---

## Monitoring & Maintenance

### Health Checks

Create a monitoring script to verify SSO is working:

```bash
#!/bin/bash
# sso-health-check.sh

# Check if WordPress OAuth endpoints respond
curl -s -o /dev/null -w "%{http_code}" https://your-site.com/oauth/authorize
curl -s -o /dev/null -w "%{http_code}" https://your-site.com/oauth/token

# Check webtrees module is loaded
curl -s https://your-site.com/webtrees/ | grep -q "wordpress-sso"

# Check debug log size (should be small if debug disabled)
ls -lh /var/www/html/webtrees/data/sso_debug.txt 2>/dev/null

echo "SSO Health Check Complete"
```

### Log Rotation (Linux)

Create `/etc/logrotate.d/webtrees-sso`:
```
/var/www/html/webtrees/data/sso_debug.txt {
    weekly
    rotate 4
    compress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

### Regular Maintenance Tasks

- [ ] Review access logs monthly
- [ ] Check for WordPress OAuth plugin updates
- [ ] Monitor failed login attempts
- [ ] Verify SSL certificate expiration
- [ ] Review user approval queue
- [ ] Clean up old debug logs
- [ ] Test backup restoration quarterly

---

## Changelog

### Version 2.0.0 (2026-01-19)

**Production Readiness:**
- ✅ **Cross-Platform Support** - Fully tested on Windows and Linux
- ✅ **Path Resolution** - Uses `DIRECTORY_SEPARATOR` for cross-platform compatibility
- ✅ **Comprehensive Documentation** - Production deployment guide included

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

**Security Improvements:**
- PKCE protection against code interception
- User switch detection prevents session hijacking
- Cookie validation ensures browser compatibility
- Enhanced CSRF protection with state validation
- Proper error handling without information disclosure

**Bug Fixes:**
- Fixed hardcoded path separators for Windows/Linux compatibility
- Fixed debug log file path resolution
- Fixed resource folder path resolution

**Breaking Changes:**
- None - fully backward compatible with 1.0.0

### Version 1.0.0 (Previous)

- Initial release
- Basic WordPress SSO functionality
- JIT provisioning with admin approval
- Single logout support

---

## Production Readiness Checklist

Use this checklist before deploying to production:

### Installation
- [ ] Composer dependencies installed (`vendor/` folder exists)
- [ ] Module appears in webtrees Control Panel → Modules
- [ ] File permissions set correctly (Linux: 755 for directories, 644 for files)
- [ ] SELinux configured if enabled (Linux only)

### WordPress Configuration
- [ ] WP OAuth Server plugin installed (v4.4.0+)
- [ ] OAuth client created with correct redirect URI
- [ ] Redirect URI uses URL-encoded slashes (`%2F`)
- [ ] Client ID and Client Secret generated
- [ ] Grant type set to "Authorization Code"

### webtrees Configuration
- [ ] config.ini.php updated with WordPress SSO settings (or UI configured)
- [ ] All URLs use HTTPS in production
- [ ] PKCE method set to S256
- [ ] Debug logging disabled (`WordPress_SSO_debugEnabled='0'`)
- [ ] Email sync configured if desired

### Security
- [ ] HTTPS/SSL certificate installed and valid
- [ ] Security headers configured (X-Frame-Options, etc.)
- [ ] Firewall rules configured
- [ ] Strong client secret used (auto-generated recommended)
- [ ] WordPress admin accounts secured with strong passwords

### Testing
- [ ] OAuth flow tested from start to finish
- [ ] New user creation tested (if enabled)
- [ ] Email synchronization tested (if enabled)
- [ ] Logout tested (single sign-out)
- [ ] User approval workflow tested
- [ ] Error handling tested (invalid credentials, network issues)
- [ ] Cross-browser testing completed

### Monitoring
- [ ] Backup procedures in place
- [ ] Log rotation configured (Linux)
- [ ] Health check script created
- [ ] Administrator notifications working
- [ ] Error logs monitoring configured

### Documentation
- [ ] Internal documentation updated with configuration details
- [ ] Support team trained on SSO workflow
- [ ] Troubleshooting procedures documented
- [ ] Rollback plan prepared

---

## Support & Resources

### Documentation
- [OAuth2 Redirect URI Fix](OAUTH_REDIRECT_URI_FIX.md) - Detailed explanation of redirect URI issues
- [WP OAuth Server Plugin](https://wordpress.org/plugins/miniorange-oauth-20-server/) - WordPress OAuth provider
- [The League OAuth2 Client](https://oauth2-client.thephpleague.com/) - OAuth2 library documentation
- [webtrees Documentation](https://www.webtrees.net/documentation/) - webtrees official docs

### Getting Help

1. **Check Troubleshooting Section** - Most issues are covered above
2. **Enable Debug Logging** - Provides detailed error information
3. **Check Error Logs:**
   - webtrees: Control Panel → Website logs
   - Apache/Nginx: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
   - PHP: Check `error_log` location in `php.ini`
4. **Review WordPress OAuth Logs** - Check WordPress OAuth plugin logs

### Common Resources Needed

- **Client ID & Secret:** WordPress → Users → Applications → Your OAuth Client
- **Endpoints:** Usually:
  - Authorize: `https://your-site.com/oauth/authorize`
  - Token: `https://your-site.com/oauth/token`
  - User Info: `https://your-site.com/oauth/me`
- **Callback URL:** webtrees Control Panel → Modules → WordPress SSO → Preferences

---

## License

GNU General Public License v3.0

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

---

## Credits

- **Enhanced by:** Gemini AI (2026)
- **Based on:** webtrees OAuth2 concepts
- **OAuth2 Library:** [The League OAuth2 Client](https://oauth2-client.thephpleague.com/)
- **Compatible with:** [WP OAuth Server](https://wordpress.org/plugins/miniorange-oauth-20-server/)
- **Tested on:** Windows (XAMPP, WAMP, Laragon) and Linux (Ubuntu, CentOS, Debian)

---

## Contributing

Contributions are welcome! Please ensure:
- Code works on both Windows and Linux
- Uses `DIRECTORY_SEPARATOR` for all file paths
- Includes appropriate error handling
- Maintains backward compatibility
- Updates documentation as needed

---

**For production deployment assistance, refer to the [Production Deployment](#production-deployment) section above.**
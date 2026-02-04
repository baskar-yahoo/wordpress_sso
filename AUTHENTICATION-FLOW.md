# WordPress-Webtrees SSO Authentication Flow

## 🔐 Complete Authentication & Logout Flow Documentation

This document provides a comprehensive overview of the authentication flow between WordPress and Webtrees, including the security-hardened logout implementation.

---

## 📊 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         User Browser                                     │
└────────────────────────────┬────────────────────────────────────────────┘
                             │
                ┌────────────┴────────────┐
                │                         │
        ┌───────▼────────┐       ┌───────▼────────┐
        │   WordPress    │       │   Webtrees     │
        │   (OAuth 2.0   │◄──────┤   (OAuth 2.0   │
        │    Server)     │       │     Client)    │
        └────────────────┘       └────────────────┘
                │                         │
                │    Shared Database      │
                └─────────┬───────────────┘
                          ▼
                  ┌───────────────┐
                  │   MySQL DB    │
                  │   - wp_*      │
                  │   - wt_*      │
                  └───────────────┘
```

---

## 🚀 Flow 1: Initial Login (WordPress → Webtrees)

### Step-by-Step Process

#### 1. **User Accesses Webtrees** (Not Logged In)
```
URL: https://yourdomain.com/familytree/
```

**What Happens:**
- `WordPressSsoModule::handle()` is invoked (implements `ModuleGlobalInterface`)
- Checks: `Auth::check()` returns `false`
- Checks: SSO is enabled (`sso_enabled = '1'`)
- Action: Redirects to OAuth authorization

```php
if (!Auth::check() && !$is_sso_callback && $sso_enabled === '1') {
    return redirect(route(WordPressSsoLoginAction::class));
}
```

#### 2. **OAuth Authorization Request**
```
URL: /wordpress-sso/callback (triggers WordPressSsoLoginAction)
```

**WordPressSsoLoginAction generates:**
```
https://yourdomain.com/oauth/authorize?
  response_type=code
  &client_id=abc123
  &redirect_uri=https://yourdomain.com/familytree/wordpress-sso/callback
  &scope=basic email
  &state=random_csrf_token
  &code_challenge=base64url_encoded_challenge
  &code_challenge_method=S256
```

**Security Features:**
- `state` parameter prevents CSRF attacks
- `code_challenge` enables PKCE (prevents authorization code interception)
- HTTPS enforced

#### 3. **User Authenticates in WordPress**

**If not logged in to WordPress:**
```
WordPress Login Screen
  ↓
Username: __________
Password: __________
[Login Button]
```

**If already logged in:**
- WordPress shows OAuth consent screen (optional, can be auto-approved)

#### 4. **WordPress Redirects Back with Authorization Code**
```
URL: https://yourdomain.com/familytree/wordpress-sso/callback?
  code=auth_code_xyz
  &state=random_csrf_token
```

**Webtrees Validates:**
- `state` matches stored value (CSRF check)
- Code is present and valid

#### 5. **Token Exchange** (Backend)

**WordPressSsoLoginAction requests access token:**
```http
POST https://yourdomain.com/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&code=auth_code_xyz
&redirect_uri=https://yourdomain.com/familytree/wordpress-sso/callback
&client_id=abc123
&client_secret=secret_key
&code_verifier=plain_text_verifier
```

**WordPress Responds:**
```json
{
  "access_token": "eyJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "def456..."
}
```

**Security:**
- `code_verifier` proves PKCE challenge (prevents token theft)
- Token exchange happens server-to-server (never exposed to browser)

#### 6. **Fetch User Information**

**Request to WordPress:**
```http
GET https://yourdomain.com/oauth/me
Authorization: Bearer eyJhbGc...
```

**Response:**
```json
{
  "ID": "123",
  "user_login": "johndoe",
  "user_email": "john@example.com",
  "display_name": "John Doe",
  "user_nicename": "johndoe"
}
```

#### 7. **Create or Update Webtrees User**

**WordPressSsoLoginAction logic:**

```php
// Find existing user by OAuth ID or email
$user = $this->findUserByOAuthId($oauth_id) ?? $this->findUserByEmail($email);

if (!$user) {
    if ($allow_creation) {
        // Create new user (requires admin approval)
        $user = $this->createUser($username, $email, $real_name);
        $user->setPreference('account_approved', '0');
    } else {
        throw new UserCreationException('User not found and creation disabled');
    }
}

// Update user data
if ($sync_email) {
    $user->setEmail($email);
}

// Store OAuth mapping
$user->setPreference('oauth_id', $oauth_id);

// Log user in
Auth::login($user);
```

#### 8. **Redirect to Webtrees Home**

```
URL: https://yourdomain.com/familytree/
Status: Logged in ✅
```

---

## 🔓 Flow 2: Logout (Webtrees → WordPress → Home)

### Security-Hardened Logout Flow

#### 1. **User Clicks Logout in Webtrees**

```
URL: /tree/{tree}/logout (Webtrees logout route)
Handler: WordPressSsoLogout::handle()
```

#### 2. **Generate Secure One-Time Token**

**WordPressSsoLogout.php:**
```php
// Generate cryptographically secure token
$logout_token = bin2hex(random_bytes(32)); // 64 hex chars

// Store in session BEFORE destroying it
Session::put('webtrees_logout_token', $logout_token);
Session::put('webtrees_logout_time', time());
```

**Security:**
- 32 bytes = 256 bits of entropy
- Cryptographically secure random generator
- Time-limited (60 seconds)

#### 3. **Logout from Webtrees**

```php
// Call parent to destroy Webtrees session
parent::handle($request);
```

**What Happens:**
- User session destroyed
- Authentication cookies cleared
- User object removed from memory

#### 4. **Redirect to Bridge Script**

```php
$logout_url = $base_url . '/modules_v4/wordpress_sso/sso_logout.php?token=' . urlencode($logout_token);
return redirect($logout_url);
```

**URL:**
```
https://yourdomain.com/modules_v4/wordpress_sso/sso_logout.php?token=a1b2c3d4...
```

#### 5. **Bridge Script Validates Token** (sso_logout.php)

**Security Checks:**

```php
function validate_logout_token(): bool {
    // ✅ Check 1: Token present in URL
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        log_security_event('Missing token');
        return false;
    }
    
    // ✅ Check 2: Session token exists
    if (!isset($_SESSION['webtrees_logout_token'])) {
        log_security_event('No session token');
        return false;
    }
    
    // ✅ Check 3: Timing-safe comparison (prevents timing attacks)
    if (!hash_equals($_SESSION['webtrees_logout_token'], $_GET['token'])) {
        log_security_event('Token mismatch');
        return false;
    }
    
    // ✅ Check 4: Token not expired (60 second window)
    $token_age = time() - ($_SESSION['webtrees_logout_time'] ?? 0);
    if ($token_age > 60) {
        log_security_event('Token expired');
        return false;
    }
    
    // ✅ Check 5: Consume token (one-time use)
    unset($_SESSION['webtrees_logout_token'], $_SESSION['webtrees_logout_time']);
    
    return true;
}
```

**If validation fails:**
```php
header('Location: /'); // Silent redirect to home
exit;
```

#### 6. **Load WordPress Environment**

**Path Detection:**
```php
$candidates = [
    __DIR__ . '/../../../wp-load.php',                    // Standard
    __DIR__ . '/../../../../wp-load.php',                 // Deep nested
    __DIR__ . '/../../../svajana/wp-load.php',            // Sibling
    $_SERVER['DOCUMENT_ROOT'] . '/svajana/wp-load.php',   // Absolute
];

foreach ($candidates as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
```

**Error Handling:**
```php
try {
    ob_start();
    require_once $wp_load_path;
    ob_end_clean();
} catch (Exception $e) {
    log_security_event('WordPress load failed');
    header('Location: /');
    exit;
}
```

#### 7. **Generate WordPress Nonce-Protected Logout URL**

```php
// WordPress generates logout URL with nonce
$logout_url = wp_logout_url(home_url());

// Example output:
// https://yourdomain.com/wp-login.php?action=logout&_wpnonce=abc123&redirect_to=https://yourdomain.com
```

**What `wp_logout_url()` does:**
1. Creates a nonce for the current logged-in user
2. Adds `action=logout` parameter
3. Adds `redirect_to` parameter for post-logout destination
4. Returns signed URL

**Security:**
- Nonce prevents unauthorized logout (CSRF protection)
- Nonce is user-specific and time-limited
- No "Are you sure?" prompt because nonce is valid

#### 8. **Redirect to WordPress Logout**

```php
$logout_url = html_entity_decode($logout_url); // Handle &amp; encoding
header('Location: ' . $logout_url);
exit;
```

#### 9. **WordPress Processes Logout**

**WordPress Core (`wp-login.php?action=logout`):**
```php
// Validate nonce
check_admin_referer('log-out');

// Destroy WordPress session
wp_logout();

// Hooks fire: wp_logout, clear_auth_cookie
// Cookies cleared: wordpress_*, wordpress_logged_in_*

// Redirect to home
wp_safe_redirect($redirect_to);
exit;
```

#### 10. **User Returns to WordPress Home**

```
URL: https://yourdomain.com/
Status: Logged out ✅ (both Webtrees and WordPress)
```

---

## 🔄 Flow 3: Menu Display Logic

### Dynamic Menu Filtering

#### WordPress Menu Structure (Before Filtering)

```php
$wp_menu_items = [
    [
        'title' => 'Home',
        'url' => 'https://yourdomain.com',
        'classes' => ['menu-item-home'],
    ],
    [
        'title' => 'Family Tree',
        'url' => 'https://yourdomain.com/familytree',
        'classes' => ['menu-item-familytree'],
    ],
    [
        'title' => 'Login',
        'url' => 'https://yourdomain.com/wp-login.php',
        'classes' => ['menu-item-login'], // ← Identifies as login link
    ],
    [
        'title' => 'Logout',
        'url' => 'https://yourdomain.com/wp-login.php?action=logout',
        'classes' => ['menu-item-logout'], // ← Identifies as logout link
    ],
];
```

#### Filtering Process

**MenuHelper::filterMenuTree():**

```php
public static function filterMenuTree(array $menu_items): array
{
    $user_logged_in = Auth::check(); // true or false
    
    return array_filter($menu_items, function($item) use ($user_logged_in) {
        $classes = $item['classes'] ?? [];
        
        $is_login_item = in_array('menu-item-login', $classes);
        $is_logout_item = in_array('menu-item-logout', $classes);
        
        // Hide login if logged in
        if ($is_login_item && $user_logged_in) {
            return false;
        }
        
        // Hide logout if NOT logged in
        if ($is_logout_item && !$user_logged_in) {
            return false;
        }
        
        return true; // Show all other items
    });
}
```

#### Result

**When logged out:**
```php
[
    ['title' => 'Home', ...],
    ['title' => 'Family Tree', ...],
    ['title' => 'Login', ...], // ✅ Shown
    // 'Logout' is hidden
]
```

**When logged in:**
```php
[
    ['title' => 'Home', ...],
    ['title' => 'Family Tree', ...],
    // 'Login' is hidden
    ['title' => 'Logout', ...], // ✅ Shown
]
```

---

## 🛡️ Security Features Summary

### 1. **OAuth 2.0 with PKCE**
- Authorization Code Flow
- PKCE (Proof Key for Code Exchange) prevents code interception
- State parameter prevents CSRF
- Tokens never exposed to browser

### 2. **Logout Token Security**
- Cryptographically secure random tokens (256-bit)
- One-time use (consumed after validation)
- Time-limited (60 seconds)
- Timing-safe comparison (prevents timing attacks)
- Session-bound (can't be used across sessions)

### 3. **WordPress Nonce Protection**
- Generated by WordPress core
- User-specific and time-limited
- Validates logout requests
- Prevents CSRF attacks

### 4. **Error Handling**
- No path disclosure
- Generic error messages to users
- Detailed logging for administrators
- Graceful fallbacks

### 5. **Session Management**
- Secure cookie flags (HttpOnly, Secure, SameSite)
- Proper session destruction
- Cross-system session coordination

---

## 🔍 Troubleshooting Guide

### Login Issues

**Symptom:** Redirect loop during login

**Causes:**
- State mismatch (CSRF token invalid)
- Code verifier mismatch (PKCE failed)
- OAuth client credentials incorrect

**Solutions:**
1. Check `data/sso_debug.txt` for specific error
2. Verify OAuth client ID and secret
3. Confirm redirect URI matches exactly
4. Clear browser cookies and retry

### Logout Issues

**Symptom:** User stays logged in after clicking logout

**Causes:**
- Token validation failed
- WordPress not loading properly
- Session not being destroyed

**Solutions:**
1. Check `data/sso_security.log` for token errors
2. Verify `sso_logout.php` is accessible
3. Test WordPress `wp_logout_url()` manually
4. Confirm session is being created before token check

### Menu Display Issues

**Symptom:** Both Login and Logout show simultaneously

**Causes:**
- Menu filtering not applied
- CSS classes missing on menu items
- `Auth::check()` returning incorrect state

**Solutions:**
1. Add CSS classes to WordPress menu items: `menu-item-login` and `menu-item-logout`
2. Verify `MenuHelper::filterMenuTree()` is called in theme
3. Test `Auth::check()` independently
4. Clear menu cache

---

## 📈 Performance Considerations

### Caching Strategy

**What to cache:**
- WordPress menu structure (1 hour)
- OAuth user information (session lifetime)
- Webtrees relationship calculations (1 hour)

**What NOT to cache:**
- Authentication state
- Session data
- CSRF tokens
- OAuth codes

### Database Queries

**Optimized queries:**
- User lookup by OAuth ID (indexed)
- Menu item fetching (cached)
- Token validation (in-memory session)

---

## 🎯 Next Steps

1. **Test in staging environment**
2. **Run security audit**
3. **Load test with multiple users**
4. **Document customizations**
5. **Train administrators**
6. **Deploy to production**

---

**Version:** 2.0.0  
**Last Updated:** February 4, 2026  
**Maintained by:** Development Team

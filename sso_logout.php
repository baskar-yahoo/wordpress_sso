<?php
/**
 * Security-Hardened SSO Logout Bridge
 *
 * This script bridges the logout between Webtrees and WordPress.
 * It is called after the user has been logged out of Webtrees.
 * 
 * Security Features:
 * - Token-based authentication (one-time use)
 * - Time-based token expiration (60 seconds)
 * - Session validation
 * - Safe error handling (no path disclosure)
 * - WordPress nonce validation
 * 
 * Flow:
 * 1. Validate the one-time logout token
 * 2. Load WordPress environment
 * 3. Generate nonce-protected logout URL
 * 4. Redirect to WordPress logout
 * 5. WordPress redirects to home page
 */

// Start session to access Webtrees logout token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// STEP 1: SECURITY VALIDATION
// ============================================

/**
 * Validate the logout token
 */
function validate_logout_token(): bool
{
    // Check if token exists in URL
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        log_security_event('Missing token in logout request', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return false;
    }
    
    $provided_token = $_GET['token'];
    
    // Check if session token exists
    if (!isset($_SESSION['webtrees_logout_token'])) {
        log_security_event('No session token found', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return false;
    }
    
    $session_token = $_SESSION['webtrees_logout_token'];
    
    // Validate token match (timing-safe comparison)
    if (!hash_equals($session_token, $provided_token)) {
        log_security_event('Token mismatch', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return false;
    }
    
    // Check token expiration (60 seconds)
    $token_time = $_SESSION['webtrees_logout_time'] ?? 0;
    if (time() - $token_time > 60) {
        log_security_event('Token expired', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        unset($_SESSION['webtrees_logout_token'], $_SESSION['webtrees_logout_time']);
        return false;
    }
    
    // Token is valid - consume it (one-time use)
    unset($_SESSION['webtrees_logout_token'], $_SESSION['webtrees_logout_time']);
    
    return true;
}

/**
 * Log security events
 */
function log_security_event(string $event, string $ip): void
{
    $log_entry = date('Y-m-d H:i:s') . " - SSO Logout Security: {$event} from {$ip}\n";
    $log_file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sso_security.log';
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Validate token before proceeding
if (!validate_logout_token()) {
    // Log failure reason (already logged in validate_logout_token function)
    log_security_event('Token validation failed - redirecting to home', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // Redirect to home page without disclosing error details
    header('Location: /');
    exit;
}

// Log successful token validation
log_security_event('Token validated successfully - proceeding with WordPress logout', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

// ============================================
// STEP 2: LOCATE & LOAD WORDPRESS
// ============================================

/**
 * Find wp-load.php in multiple candidate locations
 */
function find_wp_load(): ?string
{
    // Current location: modules_v4/wordpress_sso/sso_logout.php
    $candidates = [
        __DIR__ . '/../../../wp-load.php',                    // Standard: familytree/../wp-load.php
        __DIR__ . '/../../../../wp-load.php',                 // Deep nested
        __DIR__ . '/../../../svajana/wp-load.php',            // Sibling folder
        $_SERVER['DOCUMENT_ROOT'] . '/svajana/wp-load.php',   // Absolute from doc root
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',           // Root installation
    ];
    
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

$wp_load_path = find_wp_load();

if (!$wp_load_path) {
    log_security_event('WordPress wp-load.php not found - tried multiple paths', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    // Redirect to fallback location
    header('Location: /');
    exit;
}

// Log successful WordPress discovery
log_security_event('WordPress found at: ' . $wp_load_path, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

// Load WordPress environment with error handling
try {
    // Suppress WordPress output during load
    ob_start();
    require_once $wp_load_path;
    ob_end_clean();
} catch (Exception $e) {
    log_security_event('WordPress load failed: ' . $e->getMessage(), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    header('Location: /');
    exit;
}

// ============================================
// STEP 3: VALIDATE WORDPRESS FUNCTIONS
// ============================================

if (!function_exists('wp_logout_url') || !function_exists('home_url')) {
    log_security_event('WordPress functions not available', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    header('Location: /');
    exit;
}

// ============================================
// STEP 4: GENERATE LOGOUT URL & REDIRECT
// ============================================

// Determine redirect destination (WordPress home)
$redirect_to = home_url();

// Generate nonce-protected logout URL
// wp_logout_url() automatically creates a nonce for the current user
$logout_url = wp_logout_url($redirect_to);

// Log the generated logout URL for debugging
log_security_event('Generated logout URL: ' . $logout_url, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

// Decode HTML entities (some WP configs return &amp; instead of &)
$logout_url = html_entity_decode($logout_url);

// Validate the URL before redirecting
if (filter_var($logout_url, FILTER_VALIDATE_URL) === false) {
    log_security_event('Invalid logout URL generated', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    header('Location: ' . home_url());
    exit;
}

// Final redirect to WordPress logout
header('Location: ' . $logout_url);
exit;

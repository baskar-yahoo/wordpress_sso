<?php
/**
 * Security-Hardened SSO Logout Bridge
 *
 * This script bridges the logout between Webtrees and WordPress.
 * It relies on WordPress cookies/session to determine if logout is needed.
 * 
 * Flow:
 * 1. Load WordPress environment
 * 2. Check if user is logged in (WordPress handles this via cookies)
 * 3. Generate nonce-protected logout URL
 * 4. Redirect to WordPress logout
 * 5. WordPress redirects to home page
 */

// ============================================
// STEP 1: LOCATE & LOAD WORDPRESS
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

/**
 * Log security events
 */
function log_security_event(string $event, string $ip): void
{
    $log_entry = date('Y-m-d H:i:s') . " - SSO Logout Security: {$event} from {$ip}\n";
    $log_file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sso_security.log';
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

log_security_event('Bridge script called', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

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

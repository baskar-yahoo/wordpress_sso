<?php
/**
 * Clean Single Sign-On (SSO) Logout Bridge
 *
 * This script is called after the user has been logged out of Webtrees.
 * It loads the WordPress environment to securely log the user out of WordPress
 * without triggering the "Do you really want to log out?" confirmation prompt.
 *
 * It works by:
 * 1. Loading WordPress Core (wp-load.php)
 * 2. Generating a nonce-signed logout URL via wp_logout_url()
 * 3. Redirecting the browser to that URL
 */

// 1. Locate and Load WordPress
// Current location: modules_v4/wordpress_sso/sso_logout.php
// We need to go up:
// sso_logout.php -> wordpress_sso (1) -> modules_v4 (2) -> familytree (3) -> [Root (../wp-load.php or ../svajana/wp-load.php)]

$candidates = [
    __DIR__ . '/../../../wp-load.php',            // Standard nested (Production: svajana/familytree)
    __DIR__ . '/../../../../wp-load.php',         // Extra deep nested check (just in case)
    __DIR__ . '/../../../svajana/wp-load.php',    // Sibling directory (Local: www/familytree & www/svajana)
    $_SERVER['DOCUMENT_ROOT'] . '/svajana/wp-load.php', // Absolute from doc root
];

$wp_load_path = null;
foreach ($candidates as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

if (!$wp_load_path) {
    die('Error: WordPress wp-load.php not found. Searched paths: ' . implode(', ', $candidates));
}

// Load WordPress environment
require_once $wp_load_path;

// 2. Determine Redirect URL (Home Page)
$redirect_to = home_url();

// 3. Generate Nonce-Protected Logout URL
// wp_logout_url() automatically handles the nonce creation for the current user
// We use html_entity_decode because some WP configurations/filters might return encoded ampersands (&amp;)
// which breaks the Location header redirect (PHP won't parse the query string correctly).
$logout_url = html_entity_decode(wp_logout_url($redirect_to));

// 4. Redirect
header("Location: $logout_url");
exit;

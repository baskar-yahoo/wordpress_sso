<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Http\RequestHandlers\Logout;
use Fisharebest\Webtrees\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webtrees\WordPressSso\WordPressSsoModule;

/**
 * WordPress SSO Logout Handler
 * 
 * Security-hardened logout that:
 * 1. Logs out user from Webtrees
 * 2. Generates a secure one-time token
 * 3. Redirects to bridge script for WordPress logout with nonce
 * 4. Returns user to WordPress home page
 */
class WordPressSsoLogout extends Logout
{
    private WordPressSsoModule $module;

    public function __construct(WordPressSsoModule $module)
    {
        $this->module = $module;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Generate a secure one-time logout token
        $logout_token = bin2hex(random_bytes(32));
        $logout_time = time();
        
        // Store token in PHP native session (survives Webtrees session destruction)
        // We need to do this BEFORE calling parent::handle() which destroys Webtrees session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['webtrees_logout_token'] = $logout_token;
        $_SESSION['webtrees_logout_time'] = $logout_time;
        
        // CRITICAL: Write session data to disk immediately
        // Without this, session data may not be available in the bridge script
        session_write_close();
        
        // Restart session for parent::handle() to work properly
        session_start();
        
        // Log the user out of webtrees (this destroys Webtrees session data but not PHP session)
        parent::handle($request);

        // Build the bridge script URL dynamically
        $uri = $request->getUri();
        $base_url = $uri->getScheme() . '://' . $uri->getHost();
        
        // Get Webtrees installation path (handles subdirectory installations)
        // Example: /familytree/index.php?route=wordpress-sso/logout -> /familytree
        $request_path = $uri->getPath();
        
        // Extract base path by removing everything after the first path segment that contains a file or query
        // For /familytree/index.php -> /familytree
        // For /index.php -> /
        if (preg_match('#^(.*?)/(index\.php|[^/]+\.php)#', $request_path, $matches)) {
            $webtrees_base = $matches[1] ?: '/';
        } else {
            // Fallback: assume root installation
            $webtrees_base = '/';
        }
        
        // Construct the logout bridge URL with token
        $logout_url = $base_url . $webtrees_base . '/modules_v4/wordpress_sso/sso_logout.php?token=' . urlencode($logout_token);
        
        // Debug logging if enabled
        if ($this->module->getConfig('debugEnabled', '0') === '1') {
            $this->logDebug("Logout initiated: token={$logout_token}, url={$logout_url}");
        }

        return redirect($logout_url);
    }
    
    /**
     * Log debug information
     */
    private function logDebug(string $message): void
    {
        $log_entry = date('Y-m-d H:i:s') . " - SSO Logout: {$message}\n";
        $module_dir = dirname(dirname(__DIR__));
        $modules_dir = dirname($module_dir);
        $webtrees_dir = dirname($modules_dir);
        $debug_log = $webtrees_dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sso_debug.txt';
        @file_put_contents($debug_log, $log_entry, FILE_APPEND);
    }
}

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
            $this->logDebug("Session started. Session ID: " . session_id());
        } else {
            $this->logDebug("Session already active. Session ID: " . session_id());
        }
        
        // Log session configuration
        $this->logDebug("Session config - save_path: " . session_save_path() . ", name: " . session_name());
        
        $_SESSION['webtrees_logout_token'] = $logout_token;
        $_SESSION['webtrees_logout_time'] = $logout_time;
        $this->logDebug("Token stored in session. Keys: " . implode(', ', array_keys($_SESSION)));
        
        // CRITICAL: Write session data to disk immediately
        // Without this, session data may not be available in the bridge script
        session_write_close();
        $this->logDebug("Session written to disk and closed");
        
        // Get the session ID BEFORE calling parent::handle()
        $session_id = session_id();
        $this->logDebug("Session ID captured: {$session_id}");
        
        // Log logout initiation with session details
        // Path: src/Http -> src -> wordpress_sso -> modules_v4 -> familytree -> data
        $data_dir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'data';
        $log_file = $data_dir . DIRECTORY_SEPARATOR . 'sso_debug.txt';
        $log_msg = date('Y-m-d H:i:s') . " - SSO Logout: Token saved to session. Session ID: {$session_id}, Token: {$logout_token}\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
        
        // Restart session for parent::handle() to work properly
        session_start();
        $this->logDebug("Session restarted. Session ID: " . session_id());
        
        // Verify session ID hasn't changed
        $session_id_after = session_id();
        if ($session_id !== $session_id_after) {
            $log_msg = date('Y-m-d H:i:s') . " - SSO Logout WARNING: Session ID changed! Before: {$session_id}, After: {$session_id_after}\n";
            @file_put_contents($log_file, $log_msg, FILE_APPEND);
            $this->logDebug("WARNING: Session ID changed! Before: {$session_id}, After: {$session_id_after}");
            // Use the new session ID
            $session_id = $session_id_after;
        }
        
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
        
        // Construct the logout bridge URL with token and session ID
        $logout_url = $base_url . $webtrees_base . '/modules_v4/wordpress_sso/sso_logout.php?token=' . urlencode($logout_token) . '&sid=' . urlencode($session_id);
        
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

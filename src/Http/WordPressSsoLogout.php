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
        // Generate a secure one-time logout token before destroying the session
        $logout_token = bin2hex(random_bytes(32));
        Session::put('webtrees_logout_token', $logout_token);
        Session::put('webtrees_logout_time', time());
        
        // Log the user out of webtrees (this destroys most session data)
        parent::handle($request);

        // Build the bridge script URL dynamically
        $uri = $request->getUri();
        $base_url = $uri->getScheme() . '://' . $uri->getHost();
        
        // Get the base path (handles subdirectory installations)
        $request_path = $uri->getPath();
        $base_path = preg_replace('/\/[^\/]*$/', '', $request_path); // Remove last segment
        
        // Construct the logout bridge URL with token
        $logout_url = $base_url . '/modules_v4/wordpress_sso/sso_logout.php?token=' . urlencode($logout_token);
        
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

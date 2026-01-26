<?php

namespace Webtrees\WordPressSso\Services;

/**
 * Debug logging service for WordPress SSO
 */
class DebugLogger
{
    private bool $debug_enabled;
    private string $prefix = '[WordPress SSO Debug]';

    public function __construct(bool $debug_enabled)
    {
        $this->debug_enabled = $debug_enabled;
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param array  $context
     */
    public function log(string $message, array $context = []): void
    {
        if (!$this->debug_enabled) {
            return;
        }

        $log_message = date('Y-m-d H:i:s') . ' ' . $this->prefix . ' ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context, JSON_PRETTY_PRINT);
        }

        $log_message .= "\n";

        // Calculate path to sso_debug.txt
        // Current dir: .../wordpress_sso/src/Services
        // Webtrees root: .../familytree
        $service_dir = __DIR__;
        $src_dir = dirname($service_dir);
        $module_dir = dirname($src_dir);
        $modules_dir = dirname($module_dir);
        $webtrees_dir = dirname($modules_dir);
        
        $log_file = $webtrees_dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sso_debug.txt';

        // Write to file, attempt to append
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        // Also log to error_log for fallback visibility in system logs
        // error_log($log_message); 
    }

    /**
     * Log a request phase with detailed data
     *
     * @param string $phase
     * @param array  $data
     */
    public function logRequest(string $phase, array $data): void
    {
        if (!$this->debug_enabled) {
            return;
        }

        $this->log("=== $phase ===");
        $this->log('Timestamp: ' . date('Y-m-d H:i:s'));

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->log("$key: " . json_encode($value));
            } else {
                // Mask sensitive data
                if (in_array($key, ['code', 'token', 'secret', 'password', 'client_secret'])) {
                    $value = substr((string) $value, 0, 8) . '...[MASKED]';
                }
                $this->log("$key: $value");
            }
        }

        $this->log("=== End $phase ===");
    }
}

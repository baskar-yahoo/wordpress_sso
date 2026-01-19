<?php

namespace Webtrees\WordPressSso\Services;

use Fisharebest\Webtrees\Log;

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

        $log_message = $this->prefix . ' ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context, JSON_PRETTY_PRINT);
        }

        Log::addDebugLog($log_message);
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

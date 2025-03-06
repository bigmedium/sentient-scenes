<?php
/**
 * rate-limiter.php
 * 
 * Simple token bucket rate limiting for Sentient Scenes demo.
 * Focuses on per-minute and per-day limits for both user and global traffic.
 */

class RateLimiter {
    // WARNING: Setting this to true will completely disable rate limiting.
    // Only use DEBUG_MODE=true during development/testing, never in production
    const DEBUG_MODE = false;
    
    // Hardcoded time windows - no need for configuration
    const MINUTE_WINDOW = 60;   // 60 seconds in a minute
    const DAY_WINDOW = 86400;   // 86400 seconds in a day
    
    private $config;
    private $dataDir;

    /**
     * Initialize the rate limiter
     */
    public function __construct($config) {
        // Skip initialization if in debug mode
        if (self::DEBUG_MODE) {
            return;
        }
        
        $this->config = $config;
        $this->dataDir = __DIR__ . '/data';
        
        // Create data directory if needed
        if (!is_dir($this->dataDir)) {
            if (!@mkdir($this->dataDir, 0755, true)) {
                error_log("Warning: Failed to create rate limit data directory");
                // Fallback to a temporary directory if we can't create our own
                $this->dataDir = sys_get_temp_dir() . '/sentient_scenes_rate_limits';
                if (!is_dir($this->dataDir)) {
                    @mkdir($this->dataDir, 0755, true);
                }
            }
        }

        // Initialize session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize token buckets (only if they don't exist)
        if (!isset($_SESSION['token_buckets'])) {
            $_SESSION['token_buckets'] = [
                'minute' => [
                    'tokens' => $this->config['rate_limits']['user']['per_minute']['max'],
                    'last_refill' => time()
                ],
                'day' => [
                    'tokens' => $this->config['rate_limits']['user']['per_day']['max'],
                    'last_refill' => time()
                ]
            ];
        }
        
        // Make sure both buckets exist (handles case where config changed)
        if (!isset($_SESSION['token_buckets']['minute'])) {
            $_SESSION['token_buckets']['minute'] = [
                'tokens' => $this->config['rate_limits']['user']['per_minute']['max'],
                'last_refill' => time()
            ];
        }
        
        if (!isset($_SESSION['token_buckets']['day'])) {
            $_SESSION['token_buckets']['day'] = [
                'tokens' => $this->config['rate_limits']['user']['per_day']['max'],
                'last_refill' => time()
            ];
        }
        
        $this->logBucketState("Session initialized");
    }

    /**
     * Check if request should be rate limited
     * 
     * @return array|null Error response or null if not limited
     */
    public function checkLimits() {
        // Skip checking if in debug mode
        if (self::DEBUG_MODE) {
            return null;
        }
        
        // Check user limits first
        $userLimitResult = $this->checkUserLimits();
        if ($userLimitResult !== null) {
            return $userLimitResult;
        }
        
        // Then check global limits
        $globalLimitResult = $this->checkGlobalLimits();
        if ($globalLimitResult !== null) {
            return $globalLimitResult;
        }
        
        // All checks passed
        return null;
    }

    /**
     * Consume a token after processing a request
     */
    public function consumeToken() {
        // Skip if in debug mode
        if (self::DEBUG_MODE) {
            return;
        }
        
        // Decrement user buckets
        $this->decrementUserBucket('minute');
        $this->decrementUserBucket('day');
        
        // Decrement global buckets
        $this->decrementGlobalBucket('minute');
        $this->decrementGlobalBucket('day');
        
        $this->logBucketState("After consumption");
    }

    /**
     * Check user limits (session-based)
     */
    private function checkUserLimits() {
        $now = time();
        $limits = $this->config['rate_limits']['user'];
        
        // Refill tokens based on elapsed time
        $this->refillUserBucket('minute', $limits['per_minute']['max'], self::MINUTE_WINDOW, $now);
        $this->refillUserBucket('day', $limits['per_day']['max'], self::DAY_WINDOW, $now);
        
        $this->logBucketState("After refill");
        
        // Check minute bucket against current config limit
        if ($_SESSION['token_buckets']['minute']['tokens'] < 1) {
            return $this->createErrorResponse(
                'user_rate_limit_minute',
                "Whoa whoa, slow down there! Please wait a minute before trying again."
            );
        }
        
        // Check day bucket against current config limit
        if ($_SESSION['token_buckets']['day']['tokens'] < 1) {
            return $this->createErrorResponse(
                'user_rate_limit_day',
                "Daily limit reached; we're glad you like it so much! Please come back tomorrow to make more scenes."
            );
        }
        
        return null;
    }

    /**
     * Calculate approximate wait time until a token becomes available
     * @param string $type Bucket type ('minute' or 'day')
     * @return string Human-readable wait time
     */
    private function calculateWaitTime($type) {
        if ($type === 'minute') {
            return "30 seconds";
        } else {
            return "tomorrow";
        }
    }

    /**
     * Check global limits (file-based)
     */
    private function checkGlobalLimits() {
        $limits = $this->config['rate_limits']['global'];
        
        // Check global minute limit
        $minuteBucket = $this->getGlobalBucket('minute', $limits['per_minute']['max'], self::MINUTE_WINDOW);
        if ($minuteBucket['tokens'] < 1) {
            return $this->createErrorResponse(
                'global_rate_limit_minute',
                "Our system is really, really busy. Please come back in a few minutes."
            );
        }
        
        // Check global day limit
        $dayBucket = $this->getGlobalBucket('day', $limits['per_day']['max'], self::DAY_WINDOW);
        if ($dayBucket['tokens'] < 1) {
            return $this->createErrorResponse(
                'global_rate_limit_day',
                "Our system has reached its daily limit. Please come back again tomorrow."
            );
        }
        
        return null;
    }

    /**
     * Refill user bucket with tokens based on elapsed time
     * Always uses current config for maximum values
     */
    private function refillUserBucket($type, $maxTokens, $window, $now) {
        if (!isset($_SESSION['token_buckets'][$type])) {
            // Initialize bucket if it doesn't exist
            $_SESSION['token_buckets'][$type] = [
                'tokens' => $maxTokens,
                'last_refill' => $now
            ];
            return;
        }
        
        $bucket = &$_SESSION['token_buckets'][$type];
        $elapsed = $now - $bucket['last_refill'];
        
        if ($elapsed > 0) {
            // Simple token refill calculation (tokens per second)
            $tokensToAdd = intval(($elapsed * $maxTokens) / $window);
            
            if ($tokensToAdd > 0) {
                // Always use current config max as the ceiling
                $bucket['tokens'] = min($maxTokens, $bucket['tokens'] + $tokensToAdd);
                $bucket['last_refill'] = $now;
            }
        }
        
        // Ensure token count never exceeds current config max
        // This handles case where config max was reduced
        $bucket['tokens'] = min($bucket['tokens'], $maxTokens);
    }

    /**
     * Decrement user bucket by one token
     */
    private function decrementUserBucket($type) {
        if (isset($_SESSION['token_buckets'][$type]) && $_SESSION['token_buckets'][$type]['tokens'] > 0) {
            $_SESSION['token_buckets'][$type]['tokens']--;
        }
    }

    /**
     * Get global bucket with file locking
     */
    private function getGlobalBucket($type, $maxTokens, $window) {
        $fileName = $this->dataDir . "/global_bucket_$type.json";
        $now = time();
        $lockStartTime = microtime(true);
        
        // Create conservative default values (50% of maximum)
        $defaultBucket = [
            'tokens' => intval($maxTokens * 0.5), 
            'last_refill' => $now
        ];
        
        // Try to open the file with exclusive locking
        $fp = @fopen($fileName, 'c+');
        if (!$fp) {
            error_log("Warning: Could not open global bucket file: $fileName");
            return $defaultBucket;
        }
        
        // Lock file with timeout - try non-blocking lock for 2 seconds
        $lockAcquired = false;
        $lockTimeout = 2; // 2 second timeout
        
        while (!$lockAcquired && (microtime(true) - $lockStartTime) < $lockTimeout) {
            $lockAcquired = flock($fp, LOCK_EX | LOCK_NB);
            if (!$lockAcquired) {
                usleep(50000); // 50ms sleep before retry
            }
        }
        
        if (!$lockAcquired) {
            error_log("Warning: Could not acquire lock on bucket file: $fileName - high load detected");
            fclose($fp);
            return $defaultBucket;
        }
        
        // Read the file
        $content = '';
        fseek($fp, 0);
        while (!feof($fp)) {
            $content .= fread($fp, 8192);
        }
        
        // Parse bucket data with error handling
        $bucket = null;
        if (!empty($content)) {
            // Attempt to decode JSON with error handling
            $bucket = @json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Warning: Invalid JSON in bucket file: $fileName");
                $bucket = null;
            }
        }
        
        // Validate bucket structure, use default if invalid
        if (!$bucket || !isset($bucket['tokens']) || !isset($bucket['last_refill']) || 
            !is_numeric($bucket['tokens']) || !is_numeric($bucket['last_refill'])) {
            error_log("Warning: Invalid bucket structure in file: $fileName - rebuilding");
            $bucket = $defaultBucket;
        } else {
            // Refill tokens based on elapsed time
            $elapsed = $now - $bucket['last_refill'];
            if ($elapsed > 0) {
                $tokensToAdd = intval(($elapsed * $maxTokens) / $window);
                
                if ($tokensToAdd > 0) {
                    // Always use current config max as the ceiling
                    $bucket['tokens'] = min($maxTokens, $bucket['tokens'] + $tokensToAdd);
                    $bucket['last_refill'] = $now;
                }
            }
            
            // Ensure token count never exceeds current config max
            // This handles case where config max was reduced
            $bucket['tokens'] = min($bucket['tokens'], $maxTokens);
        }
        
        // Update bucket tokens if needed for this request
        if ($bucket['tokens'] > 0) {
            $bucket['tokens']--;
        }
        
        // Write the bucket back to file
        ftruncate($fp, 0);
        fseek($fp, 0);
        $json = json_encode($bucket);
        if ($json === false) {
            error_log("Warning: Failed to JSON encode bucket data");
        } else {
            fwrite($fp, $json);
        }
        
        // Release lock and close file
        flock($fp, LOCK_UN);
        fclose($fp);
        
        return $bucket;
    }

    /**
     * Decrement global bucket by one token
     */
    private function decrementGlobalBucket($type) {
        // Simply use getGlobalBucket which already decrements and saves the bucket
        $this->getGlobalBucket(
            $type, 
            $this->config['rate_limits']['global']["per_$type"]['max'], 
            $type === 'minute' ? self::MINUTE_WINDOW : self::DAY_WINDOW
        );
    }

    /**
     * Create an error response
     */
    private function createErrorResponse($code, $message) {
        return [
            'error' => true,
            'type' => 'rate_limit_error',
            'code' => $code,
            'message' => $message
        ];
    }
    
    /**
     * Debug helper for tracking bucket states
     * Only logs when DEBUG_MODE is true
     */
    private function logBucketState($message) {
        if (!self::DEBUG_MODE) {
            return;
        }
        
        $logFile = $this->dataDir . '/rate_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $sessionId = session_id();
        
        // Get current user bucket levels
        $minuteTokens = isset($_SESSION['token_buckets']['minute']['tokens']) 
            ? $_SESSION['token_buckets']['minute']['tokens'] 
            : 'undefined';
        
        $minuteLastRefill = isset($_SESSION['token_buckets']['minute']['last_refill']) 
            ? date('Y-m-d H:i:s', $_SESSION['token_buckets']['minute']['last_refill']) 
            : 'undefined';
        
        $dayTokens = isset($_SESSION['token_buckets']['day']['tokens']) 
            ? $_SESSION['token_buckets']['day']['tokens'] 
            : 'undefined';
        
        $dayLastRefill = isset($_SESSION['token_buckets']['day']['last_refill']) 
            ? date('Y-m-d H:i:s', $_SESSION['token_buckets']['day']['last_refill']) 
            : 'undefined';
        
        // Also log current config limits for comparison
        $minuteMax = $this->config['rate_limits']['user']['per_minute']['max'];
        $dayMax = $this->config['rate_limits']['user']['per_day']['max'];
        
        $logMessage = "$timestamp [$sessionId] $message - " .
                     "Minute: tokens=$minuteTokens/$minuteMax, last_refill=$minuteLastRefill | " .
                     "Day: tokens=$dayTokens/$dayMax, last_refill=$dayLastRefill\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
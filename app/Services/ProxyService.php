<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ProxyService
{
    private $proxyUrl;
    private $maxRetries;
    private $retryDelay;
    private $attemptCount = 0;
    
    /**
     * Create a new proxy service instance.
     * 
     * @param string $proxyUrl The SOCKS5 proxy URL (our SOCKS5 proxy with authentication)
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $retryDelay Delay between retries in milliseconds
     */
    public function __construct(
        string $proxyUrl = 'socks5://acaf2e69bfcad31446035-zone-custom-region-eu:4a267823f5caaeac707529fd4c32e91e@p2.mangoproxy.com:2333', 
        int $maxRetries = 3,
        int $retryDelay = 500
    ) {
        $this->proxyUrl = $proxyUrl;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }
    
    /**
     * Get configured cURL options for using the SOCKS5 proxy
     * 
     * @param bool $randomIp Whether to append a random identifier to force proxy to use a different IP
     * @return array
     */
    public function getCurlOptions(bool $randomIp = false): array
    {
        // Reset the attempt counter for new requests
        $this->attemptCount = 0;
        
        // Create proxy URL - add random parameter if requested to get different IP
        $proxyUrl = $this->proxyUrl;
        if ($randomIp) {
            // Append a random ID to cause the proxy to use a different IP
            $randomId = mt_rand(1000, 9999);
            $proxyUrl .= "?rand={$randomId}";
            Log::info("Setting up SOCKS5 proxy with random ID {$randomId}");
        } else {
            Log::info("Setting up SOCKS5 proxy without IP rotation");
        }
        
        // Return curl options to use with the WHOIS library
        return [
            CURLOPT_PROXY => $proxyUrl,
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
            CURLOPT_SSL_VERIFYPEER => false, // Don't verify SSL
            CURLOPT_TIMEOUT => 30, // Set a reasonable timeout
            CURLOPT_VERBOSE => true, // Enable verbose output
            CURLOPT_CONNECTTIMEOUT => 5, // Connection timeout in seconds
            // Force IPv4 to avoid IPv6 issues with some proxies
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
    }
    
    /**
     * Execute a function with retry logic using the proxy
     * 
     * @param callable $callback The function to execute
     * @return mixed The result of the callback function
     * @throws \Exception If all retry attempts fail
     */
    public function executeWithRetry(callable $callback)
    {
        $this->attemptCount = 0;
        $lastException = null;
        
        do {
            $this->attemptCount++;
            Log::info("Proxy request attempt #{$this->attemptCount}");
            
            try {
                // Try to execute the callback function
                $result = $callback();
                Log::info("Proxy request successful on attempt #{$this->attemptCount}");
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Proxy request failed on attempt #{$this->attemptCount}: {$e->getMessage()}");
                
                if ($this->attemptCount < $this->maxRetries) {
                    // Calculate delay with exponential backoff - simplified to avoid syntax error
                    $delayMs = $this->retryDelay;
                    
                    // Increase delay for each retry attempt (exponential backoff)
                    for ($i = 1; $i < $this->attemptCount; $i++) {
                        $delayMs = $delayMs * 2;
                    }
                    
                    // Add random jitter (0-50% of the delay)
                    $jitterMs = mt_rand(0, (int)($delayMs * 0.5));
                    $totalDelayMs = $delayMs + $jitterMs;
                    
                    Log::info("Waiting {$totalDelayMs}ms before retry attempt");
                    usleep($totalDelayMs * 1000); // Convert to microseconds
                }
            }
        } while ($this->attemptCount < $this->maxRetries);
        
        // If we get here, all retries failed
        Log::error("All {$this->maxRetries} proxy request attempts failed");
        throw $lastException ?? new \Exception("All proxy request attempts failed");
    }
} 
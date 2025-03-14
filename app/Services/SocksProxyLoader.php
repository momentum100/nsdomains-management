<?php

namespace App\Services;

use Iodev\Whois\Loaders\CurlLoader;
use Iodev\Whois\Exceptions\ConnectionException;
use Iodev\Whois\Helpers\TextHelper;
use Illuminate\Support\Facades\Log;
use Exception;

class SocksProxyLoader extends CurlLoader
{
    private $proxyUrl;
    private $maxRetries;
    private $retryDelay;
    private $requestCounter = 0;
    
    /**
     * Create a new SOCKS proxy loader instance.
     * 
     * @param string $proxyUrl The SOCKS5 proxy URL with authentication
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $retryDelay Base delay between retries in milliseconds
     */
    public function __construct(
        string $proxyUrl = 'socks5://acaf2e69bfcad31446035-zone-custom-region-eu:4a267823f5caaeac707529fd4c32e91e@p2.mangoproxy.com:2333', 
        int $maxRetries = 3,
        int $retryDelay = 500
    ) {
        parent::__construct(); // Initialize parent CurlLoader
        $this->proxyUrl = $proxyUrl;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        
        // Set default options including proxy
        $this->applyProxyOptions();
        
        Log::info("SocksProxyLoader initialized with SOCKS5 proxy");
    }
    
    /**
     * Apply proxy options to the loader
     * 
     * @param bool $randomIp Whether to use a random IP for this request
     * @return $this
     */
    public function applyProxyOptions(bool $randomIp = true)
    {
        // Create proxy URL - add random parameter if requested to get different IP
        $proxyUrl = $this->proxyUrl;
        if ($randomIp) {
            // Append a random ID to cause the proxy to use a different IP
            $randomId = mt_rand(1000, 9999);
            $proxyUrl .= "?rand={$randomId}";
            Log::info("Setting up SOCKS5 proxy with random ID {$randomId}");
        }
        
        // Set curl options
        $this->options = [
            CURLOPT_PROXY => $proxyUrl,
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
            CURLOPT_SSL_VERIFYPEER => false, // Don't verify SSL
            CURLOPT_TIMEOUT => 30, // Set a reasonable timeout
            CURLOPT_VERBOSE => true, // Enable verbose output for debugging
            CURLOPT_CONNECTTIMEOUT => 5, // Connection timeout in seconds
            // Force IPv4 to avoid IPv6 issues with some proxies
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        
        return $this;
    }
    
    /**
     * Query WHOIS server with retry mechanism
     * 
     * @param string $whoisHost
     * @param string $query
     * @return string
     * @throws ConnectionException
     */
    public function query($whoisHost, $query)
    {
        $this->requestCounter++;
        $attemptCount = 0;
        $lastException = null;
        
        Log::info("WHOIS Query #{$this->requestCounter} for host: {$whoisHost}");
        
        // Try with retries
        do {
            $attemptCount++;
            
            // For each new attempt, get a new random IP
            if ($attemptCount > 1) {
                $this->applyProxyOptions(true);
            }
            
            Log::info("WHOIS attempt #{$attemptCount} via SOCKS proxy");
            
            try {
                // Try to execute the actual query
                $result = $this->executeQuery($whoisHost, $query);
                Log::info("WHOIS query successful on attempt #{$attemptCount}");
                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("WHOIS query failed on attempt #{$attemptCount}: {$e->getMessage()}");
                
                // Only delay if we're going to retry
                if ($attemptCount < $this->maxRetries) {
                    // Calculate delay with exponential backoff
                    $delayMs = $this->retryDelay * pow(2, $attemptCount - 1);
                    
                    // Add random jitter (0-50% of the delay)
                    $jitterMs = mt_rand(0, (int)($delayMs * 0.5));
                    $totalDelayMs = $delayMs + $jitterMs;
                    
                    Log::info("Waiting {$totalDelayMs}ms before retry attempt");
                    usleep($totalDelayMs * 1000); // Convert to microseconds
                }
            }
        } while ($attemptCount < $this->maxRetries);
        
        // If we get here, all retries failed
        Log::error("All {$this->maxRetries} WHOIS query attempts failed");
        throw $lastException ?? new ConnectionException("All query attempts failed");
    }
    
    /**
     * Execute the actual WHOIS query using cURL
     * 
     * @param string $whoisHost
     * @param string $query
     * @return string
     * @throws ConnectionException
     */
    private function executeQuery($whoisHost, $query)
    {
        // Create input stream with query
        $input = fopen('php://temp', 'r+');
        fwrite($input, "{$query}\r\n");
        rewind($input);
        
        // Initialize cURL
        $curl = curl_init();
        
        // Set our base options
        $baseOptions = [
            CURLOPT_RETURNTRANSFER => true,
            // Use direct host:port instead of telnet protocol
            CURLOPT_URL => "{$whoisHost}:43",
            CURLOPT_INFILE => $input,
            CURLOPT_UPLOAD => true,
        ];
        
        // Merge with our custom options from the constructor
        $options = array_replace($baseOptions, $this->options);
        
        // Set all options
        curl_setopt_array($curl, $options);
        
        // Execute and get result
        $result = curl_exec($curl);
        $errstr = curl_error($curl);
        $errno = curl_errno($curl);
        
        // Log results
        Log::info("WHOIS Response", [
            'success' => ($result !== false),
            'error' => $errstr,
            'code' => $errno
        ]);
        
        curl_close($curl);
        fclose($input);
        
        if ($result === false) {
            throw new ConnectionException($errstr, $errno);
        }
        
        return $this->validateResponse(TextHelper::toUtf8($result));
    }
}

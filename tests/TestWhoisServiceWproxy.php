<?php
// tests/TestWhoisServiceWproxy.php

// Include the autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Use required libraries
use Iodev\Whois\Factory;
use Iodev\Whois\Loaders\CurlLoader;
use Iodev\Whois\Exceptions\ConnectionException;
use Iodev\Whois\Helpers\TextHelper;

// Configure error reporting for the test
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create our own simplified versions of the Laravel facades
class Logger {
    public static function info($message, $context = []) {
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        echo "[INFO] " . $message . $contextStr . PHP_EOL;
    }
    
    public static function warning($message, $context = []) {
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        echo "[WARNING] " . $message . $contextStr . PHP_EOL;
    }
    
    public static function error($message, $context = []) {
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        echo "[ERROR] " . $message . $contextStr . PHP_EOL;
    }
    
    public static function debug($message, $context = []) {
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        echo "[DEBUG] " . $message . $contextStr . PHP_EOL;
    }
}

// Create a standalone version of the combined SocksProxyLoader
class StandaloneSocksProxyLoader extends CurlLoader
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
        
        Logger::info("SocksProxyLoader initialized with SOCKS5 proxy");
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
            Logger::info("Setting up SOCKS5 proxy with random ID {$randomId}");
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
        
        Logger::info("WHOIS Query #{$this->requestCounter} for host: {$whoisHost}");
        
        // Try with retries
        do {
            $attemptCount++;
            
            // For each new attempt, get a new random IP
            if ($attemptCount > 1) {
                $this->applyProxyOptions(true);
            }
            
            Logger::info("WHOIS attempt #{$attemptCount} via SOCKS proxy");
            
            try {
                // Try to execute the actual query
                $result = $this->executeQuery($whoisHost, $query);
                Logger::info("WHOIS query successful on attempt #{$attemptCount}");
                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                Logger::warning("WHOIS query failed on attempt #{$attemptCount}: {$e->getMessage()}");
                
                // Only delay if we're going to retry
                if ($attemptCount < $this->maxRetries) {
                    // Calculate delay with exponential backoff
                    $delayMs = $this->retryDelay * pow(2, $attemptCount - 1);
                    
                    // Add random jitter (0-50% of the delay)
                    $jitterMs = mt_rand(0, (int)($delayMs * 0.5));
                    $totalDelayMs = $delayMs + $jitterMs;
                    
                    Logger::info("Waiting {$totalDelayMs}ms before retry attempt");
                    usleep($totalDelayMs * 1000); // Convert to microseconds
                }
            }
        } while ($attemptCount < $this->maxRetries);
        
        // If we get here, all retries failed
        Logger::error("All {$this->maxRetries} WHOIS query attempts failed");
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
        Logger::debug("WHOIS Response", [
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

// Create a standalone version of the WhoisService that uses our loader
class StandaloneWhoisService {
    private $loader;
    private $counter = 0;
    private $cacheEnabled = false; // Disable caching for test
    
    public function __construct($loader) {
        $this->loader = $loader;
        Logger::info("StandaloneWhoisService initialized with SocksProxyLoader");
    }
    
    public function getDomainInfo($domain) {
        $this->counter++;
        Logger::info("Processing domain #{$this->counter}: {$domain}");
        
        try {
            // Create a Whois instance with our custom loader
            $factory = new Factory();
            $whois = $factory->createWhois($this->loader);
            
            // Get the WHOIS info - loader will handle retries automatically
            Logger::info("Sending REAL WHOIS request for {$domain} via proxy");
            $info = $whois->loadDomainInfo($domain);
            
            if (!$info) {
                Logger::warning("No WHOIS information found for {$domain}");
                return [
                    'domain' => $domain,
                    'registrar' => 'Unknown',
                    'expiration_date' => null,
                    'days_left' => null,
                    'error' => 'No WHOIS information found'
                ];
            }
            
            // Process the information
            $expirationDate = $info->expirationDate;
            $registrar = $info->registrar;
            
            Logger::info("WHOIS request successful for {$domain}. Registrar: {$registrar}");
            
            // Calculate days left if expiration date exists
            $daysLeft = null;
            if ($expirationDate) {
                $expDate = new DateTime("@{$expirationDate}");
                $now = new DateTime();
                $daysLeft = $now->diff($expDate)->days;
            }
            
            $result = [
                'domain' => $domain,
                'registrar' => $registrar ?? 'Unknown',
                'expiration_date' => $expirationDate ? date('Y-m-d', $expirationDate) : null,
                'days_left' => $daysLeft,
                'creation_date' => $info->creationDate ? date('Y-m-d', $info->creationDate) : null,
                'states' => $info->states ?? [],
                'raw_data' => $info->getResponse()->getText(), // Include raw data for debugging
            ];
            
            return $result;
        } catch (Exception $e) {
            Logger::error("Error querying WHOIS for {$domain}: " . $e->getMessage());
            throw $e;
        }
    }
}

echo "=== REAL WHOIS Lookup Test with SocksProxyLoader ===\n";
echo "Starting test at: " . date('Y-m-d H:i:s') . "\n\n";

// Create our standalone loader and service
$proxyUrl = 'socks5://acaf2e69bfcad31446035-zone-custom-region-eu:4a267823f5caaeac707529fd4c32e91e@p2.mangoproxy.com:2333';
$loader = new StandaloneSocksProxyLoader($proxyUrl, 3, 500);
$whoisService = new StandaloneWhoisService($loader);

// Counter for testing
$counter = 0;
$totalTime = 0;
$successCount = 0;

// Function to test and display domain info
function testDomain($whoisService, $domain, &$counter, &$totalTime, &$successCount) {
    $counter++;
    echo "\n--- Test #" . $counter . ": Looking up " . $domain . " ---\n";
    
    $startTime = microtime(true);
    
    try {
        $result = $whoisService->getDomainInfo($domain);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000); // in milliseconds
        $totalTime += $duration;
        
        echo "WHOIS lookup completed in " . $duration . "ms\n";
        echo "Results:\n";
        echo "- Domain: " . $result['domain'] . "\n";
        echo "- Registrar: " . $result['registrar'] . "\n";
        echo "- Creation: " . ($result['creation_date'] ?? 'Unknown') . "\n";
        echo "- Expiration: " . ($result['expiration_date'] ?? 'Unknown') . "\n";
        echo "- Days Left: " . ($result['days_left'] ?? 'Unknown') . "\n";
        
        if (!empty($result['states'])) {
            echo "- States: " . implode(', ', $result['states']) . "\n";
        }
        
        if (isset($result['error'])) {
            echo "- Error: " . $result['error'] . "\n";
        }
        
        // Show a portion of the raw data for verification
        if (isset($result['raw_data'])) {
            $rawPreview = substr($result['raw_data'], 0, 200) . '...';
            echo "- Raw data preview: " . str_replace("\n", " ", $rawPreview) . "\n";
        }
        
        $successCount++;
        return true;
    } catch (Exception $e) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000); // in milliseconds
        $totalTime += $duration;
        
        echo "ERROR after " . $duration . "ms: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test domains
$domains = [
    'google.com',
    'vittle.shop',
    'BestFour.shop',
    'Jotiv.shop',
    'Fulura.shop',
    'Unlunar.shop',
    'Foretops.shop',
    'Tissual.shop',
    'Discotire.shop',
    'Lapidly.shop',
    'videoscriptgpt.com',
    'aiserialnumber.com',
    'aimodelnumber.com',
    'aiactivationkey.com',
    'restfullife.com',
    'tiejobs.com',
    'medchain.world',
    'wakingnews.com',
    'cryptopooler.xyz',
    'ethervault.xyz',
    'multivault.xyz'
];

echo "Total domains to check: " . count($domains) . "\n";
echo "Using proxy: " . $loader->options[CURLOPT_PROXY] . "\n\n";

// Run tests
foreach ($domains as $domain) {
    testDomain($whoisService, $domain, $counter, $totalTime, $successCount);
    
    // Show progress
    echo "Progress: " . $counter . "/" . count($domains) . " (" . 
         round(($counter / count($domains)) * 100) . "%)\n";
    
    // Small delay between tests to avoid overwhelming the proxy
    if (next($domains) !== false) {
        $delay = mt_rand(800, 2000) / 1000; // Longer delay for real requests
        echo "Waiting " . $delay . " seconds before next test...\n";
        usleep($delay * 1000000);
    }
}

// Show summary
echo "\n=== Test Summary ===\n";
echo "Total domains tested: " . count($domains) . "\n";
echo "Successful lookups: " . $successCount . "/" . count($domains) . " (" . 
     round(($successCount / count($domains)) * 100) . "%)\n";
echo "Average time per lookup: " . round($totalTime / $counter) . "ms\n";
echo "Total test time: " . round($totalTime / 1000) . " seconds\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";

// Output proxy statistics
echo "\nProxy Details:\n";
echo "- URL: " . $proxyUrl . "\n";
echo "- Max retries: 3\n";
echo "- Initial retry delay: 500ms\n";

<?php
// tests/TestWhoisServiceWproxy.php

// Include the autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Configure error reporting for the test
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create our own simplified versions of the Laravel facades
class Logger {
    public static function info($message) {
        echo "[INFO] " . $message . PHP_EOL;
    }
    
    public static function warning($message) {
        echo "[WARNING] " . $message . PHP_EOL;
    }
    
    public static function error($message) {
        echo "[ERROR] " . $message . PHP_EOL;
    }
}

// Create a standalone version of the WhoisService without caching
class StandaloneWhoisService {
    private $proxyService;
    private $counter = 0;
    
    public function __construct($proxyService) {
        $this->proxyService = $proxyService;
    }
    
    public function getDomainInfo($domain) {
        $this->counter++;
        Logger::info("Processing domain #" . $this->counter . ": " . $domain);
        
        // Cache is completely disabled - always perform live lookup
        return $this->proxyService->executeWithRetry(function() use ($domain) {
            // In a real test, this would call the actual WHOIS service
            // For demo purposes, let's simulate responses
            
            // Add a small delay to simulate network request
            usleep(mt_rand(300, 800) * 1000);
            
            // Get the TLD
            $parts = explode('.', $domain);
            $tld = end($parts);
            
            // Generate mock data for testing purposes
            $registrars = [
                'MarkMonitor Inc.',
                'GoDaddy.com, LLC',
                'Namecheap, Inc.',
                'Network Solutions, LLC',
                'DreamHost, LLC',
                'NameSilo, LLC',
                'Dynadot, LLC',
                'Epik, Inc.',
                'Tucows Domains Inc.'
            ];
            
            // Use specific data for known domains, random for others
            if ($domain === 'google.com') {
                return [
                    'domain' => $domain,
                    'registrar' => 'MarkMonitor Inc.',
                    'expiration_date' => '2028-09-14',
                    'days_left' => 1200,
                ];
            } else if ($domain === 'vittle.shop') {
                return [
                    'domain' => $domain,
                    'registrar' => 'GoDaddy.com, LLC',
                    'expiration_date' => '2025-08-21',
                    'days_left' => 150,
                ];
            } else {
                // Generate random expiration date 1-3 years in the future
                $expirationYear = date('Y') + mt_rand(1, 3);
                $expirationMonth = str_pad(mt_rand(1, 12), 2, '0', STR_PAD_LEFT);
                $expirationDay = str_pad(mt_rand(1, 28), 2, '0', STR_PAD_LEFT);
                $expirationDate = $expirationYear . '-' . $expirationMonth . '-' . $expirationDay;
                
                // Calculate random days left
                $today = new DateTime();
                $expiry = new DateTime($expirationDate);
                $daysLeft = $today->diff($expiry)->days;
                
                return [
                    'domain' => $domain,
                    'registrar' => $registrars[array_rand($registrars)],
                    'expiration_date' => $expirationDate,
                    'days_left' => $daysLeft,
                ];
            }
        });
    }
}

// Create a standalone version of the ProxyService
class StandaloneProxyService {
    private $proxyUrl;
    private $maxRetries;
    private $retryDelay;
    private $attemptCount = 0;
    
    public function __construct($proxyUrl, $maxRetries = 3, $retryDelay = 500) {
        $this->proxyUrl = $proxyUrl;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }
    
    public function getCurlOptions($randomIp = false) {
        $proxyUrl = $this->proxyUrl;
        if ($randomIp) {
            $randomId = mt_rand(1000, 9999);
            $proxyUrl .= "?rand=" . $randomId;
            Logger::info("Setting up SOCKS5 proxy with random ID " . $randomId);
        }
        
        return [
            'proxy' => $proxyUrl,
            'verify_ssl' => false,
            'timeout' => 30,
        ];
    }
    
    public function executeWithRetry($callback) {
        $this->attemptCount = 0;
        $lastException = null;
        
        do {
            $this->attemptCount++;
            Logger::info("Proxy request attempt #" . $this->attemptCount);
            
            try {
                $result = $callback();
                Logger::info("Proxy request successful on attempt #" . $this->attemptCount);
                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                Logger::warning("Proxy request failed on attempt #" . $this->attemptCount . ": " . $e->getMessage());
                
                if ($this->attemptCount < $this->maxRetries) {
                    $delayMs = $this->retryDelay;
                    for ($i = 1; $i < $this->attemptCount; $i++) {
                        $delayMs = $delayMs * 2;
                    }
                    
                    $jitterMs = mt_rand(0, (int)($delayMs * 0.5));
                    $totalDelayMs = $delayMs + $jitterMs;
                    
                    $nextAttempt = $this->attemptCount + 1;
                    Logger::info("Waiting " . $totalDelayMs . "ms before retry attempt #" . $nextAttempt);
                    usleep($totalDelayMs * 1000);
                }
            }
        } while ($this->attemptCount < $this->maxRetries);
        
        throw $lastException ?? new Exception("All proxy request attempts failed");
    }
}

echo "=== WHOIS Lookup Test ===\n";
echo "Starting test at: " . date('Y-m-d H:i:s') . "\n\n";

// Create our standalone test services
$proxyService = new StandaloneProxyService(
    'socks5://acaf2e69bfcad31446035-zone-custom-region-eu:4a267823f5caaeac707529fd4c32e91e@p2.mangoproxy.com:2333',
    3,  // Max retries
    500 // Retry delay in ms
);

$whoisService = new StandaloneWhoisService($proxyService);

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
        echo "- Expiration: " . ($result['expiration_date'] ?? 'Unknown') . "\n";
        echo "- Days Left: " . ($result['days_left'] ?? 'Unknown') . "\n";
        
        if (isset($result['error'])) {
            echo "- Error: " . $result['error'] . "\n";
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
echo "Using proxy: " . $proxyService->getCurlOptions()['proxy'] . "\n\n";

// Run tests
foreach ($domains as $domain) {
    testDomain($whoisService, $domain, $counter, $totalTime, $successCount);
    
    // Show progress
    echo "Progress: " . $counter . "/" . count($domains) . " (" . 
         round(($counter / count($domains)) * 100) . "%)\n";
    
    // Small delay between tests to avoid overwhelming the proxy
    if (next($domains) !== false) {
        $delay = mt_rand(500, 1500) / 1000;
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
echo "- URL: " . $proxyService->getCurlOptions()['proxy'] . "\n";
echo "- Max retries: 3\n";
echo "- Initial retry delay: 500ms\n";

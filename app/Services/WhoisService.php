<?php

namespace App\Services;

use Iodev\Whois\Factory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class WhoisService
{
    private $proxyService;
    private $counter = 0;
    private $cacheEnabled = true;
    private $cacheTtl = 8640000; // 100 days (was 86400 - 24 hours)

    /**
     * Create a new WHOIS service instance.
     * 
     * @param ProxyService $proxyService
     */
    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
    }

    /**
     * Get WHOIS information for a domain using a SOCKS5 proxy
     * 
     * @param string $domain Domain name to query
     * @return array WHOIS information
     * @throws \Exception If the WHOIS query fails after retries
     */
    public function getDomainInfo(string $domain): array
    {
        $this->counter++;
        Log::info("Processing domain #{$this->counter}: {$domain}");
        
        // Check cache first to reduce proxy usage
        $cacheKey = "whois_data_" . md5($domain);
        if ($this->cacheEnabled && Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            Log::info("Using cached WHOIS data for {$domain}");
            return $cachedData;
        }
        
        return $this->proxyService->executeWithRetry(function() use ($domain, $cacheKey) {
            // Get cURL options for the proxy with random IP
            $curlOptions = $this->proxyService->getCurlOptions(true); // true = use random IP
            
            // Create a custom Whois factory that uses our proxy
            $factory = new Factory();
            $whois = $factory->createWhois();
            
            // Set the cURL options directly on the Whois loader
            $loader = $whois->getLoader();
            foreach ($curlOptions as $option => $value) {
                $loader->setCurlOption($option, $value);
            }
            
            try {
                // Get the WHOIS info
                Log::info("Sending WHOIS request for {$domain} via proxy");
                $info = $whois->loadDomainInfo($domain);
                
                if (!$info) {
                    Log::warning("No WHOIS information found for {$domain}");
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
                
                Log::info("WHOIS request successful for {$domain}. Registrar: {$registrar}");
                
                // Calculate days left if expiration date exists
                $daysLeft = null;
                if ($expirationDate) {
                    $expDate = new \DateTime("@{$expirationDate}");
                    $now = new \DateTime();
                    $daysLeft = $now->diff($expDate)->days;
                }
                
                $result = [
                    'domain' => $domain,
                    'registrar' => $registrar ?? 'Unknown',
                    'expiration_date' => $expirationDate ? date('Y-m-d', $expirationDate) : null,
                    'days_left' => $daysLeft,
                ];
                
                // Cache the result
                if ($this->cacheEnabled) {
                    Cache::put($cacheKey, $result, $this->cacheTtl);
                    Log::info("Cached WHOIS data for {$domain}");
                }
                
                return $result;
            } catch (Exception $e) {
                Log::error("Error querying WHOIS for {$domain}: " . $e->getMessage());
                throw $e;
            }
        });
    }
    
    /**
     * Alias for getDomainInfo to match what DomainService expects
     * 
     * @param string $domain Domain name to query
     * @return array WHOIS information
     */
    public function fetchWhoisData(string $domain): array
    {
        return $this->getDomainInfo($domain);
    }
    
    /**
     * Process multiple domains in batch with proxy and retry logic
     * 
     * @param array $domains List of domain names
     * @return array Results for each domain
     */
    public function processDomainsBatch(array $domains): array
    {
        $results = [];
        $processedCount = 0;
        $totalDomains = count($domains);
        
        Log::info("Starting batch processing of {$totalDomains} domains with proxy");
        
        foreach ($domains as $domain) {
            $processedCount++;
            Log::info("Processing domain {$processedCount}/{$totalDomains}: {$domain}");
            
            try {
                $results[] = $this->getDomainInfo($domain);
            } catch (Exception $e) {
                // If all retries failed, add error result
                $results[] = [
                    'domain' => $domain,
                    'registrar' => 'Error',
                    'expiration_date' => null,
                    'days_left' => null,
                    'error' => $e->getMessage()
                ];
            }
            
            // Add a small delay between requests to reduce stress on proxy
            usleep(mt_rand(100, 300) * 1000); // 100-300ms random delay
        }
        
        Log::info("Completed batch processing of {$totalDomains} domains");
        return $results;
    }
    
    /**
     * Enable or disable caching
     * 
     * @param bool $enabled
     * @return $this
     */
    public function setCaching(bool $enabled)
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }
    
    /**
     * Set cache time-to-live in seconds
     * 
     * @param int $seconds
     * @return $this
     */
    public function setCacheTtl(int $seconds)
    {
        $this->cacheTtl = $seconds;
        return $this;
    }
} 
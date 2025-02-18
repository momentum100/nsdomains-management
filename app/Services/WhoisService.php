<?php

namespace App\Services;

use Iodev\Whois\Factory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhoisService
{
    private $whois;

    // ELI15: We initialize the WHOIS client when creating this service
    public function __construct()
    {
        $this->whois = Factory::get()->createWhois();
    }

    // ELI15: This function gets domain info and caches it to avoid hitting WHOIS servers too often
    public function fetchWhoisData(string $domain): ?array
    {
        $cacheKey = "whois_{$domain}";
        
        // Try to get from cache first
        if ($cachedData = Cache::get($cacheKey)) {
            Log::info("WHOIS info for domain {$domain} retrieved from cache.");
            return $cachedData;
        }

        try {
            $info = $this->whois->loadDomainInfo($domain);
            
            if ($info && $info->expirationDate) {
                Log::info("WHOIS info for domain {$domain}: ", (array) $info);

                $whoisData = [
                    'expiration_date' => $info->expirationDate ? Carbon::parse($info->expirationDate)->format('Y-m-d') : null,
                    'registrar' => $info->registrar ?? 'N/A',
                ];

                // Cache for 24 hours
                Cache::put($cacheKey, $whoisData, now()->addHours(24));

                return $whoisData;
            }
        } catch (\Exception $e) {
            Log::error("WHOIS fetch error for domain {$domain}: " . $e->getMessage());
        }

        return null;
    }
} 
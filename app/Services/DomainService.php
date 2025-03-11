<?php

namespace App\Services;

use App\Models\DomainResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DomainService
{
    private $whoisService;
    private $pricingService;

    public function __construct(WhoisService $whoisService, PricingService $pricingService)
    {
        $this->whoisService = $whoisService;
        $this->pricingService = $pricingService;
    }

    /**
     * Process a list of domains and store results
     *
     * @param array $domains
     * @param string $uuid
     * @param int|null $userId
     * @return array
     */
    public function processDomains(array $domains, string $uuid, ?int $userId = null)
    {
        // Log the number of domains being processed and user info
        Log::info("Processing " . count($domains) . " domains with UUID: {$uuid}" . 
                  ($userId ? " for user ID: {$userId}" : " for guest user"));
        
        $results = [];
        $totalPrice = 0;
        
        // Process each domain...
        foreach ($domains as $domain) {
            if (!$this->isValidDomain($domain)) {
                $results[] = [
                    'domain' => $domain,
                    'error' => 'Invalid domain format.',
                ];
                continue;
            }

            $domainInfo = $this->processSingleDomain($domain, $uuid);
            
            if (isset($domainInfo['error'])) {
                $results[] = $domainInfo;
                continue;
            }

            $totalPrice += floatval($domainInfo['price']);
            $results[] = $domainInfo;

            // When saving to the database, include the user_id
            DomainResult::create([
                'uuid' => $uuid,
                'domain' => $domain,
                'user_id' => $userId, // Store the user ID (null for guests)
                'expiration_date' => $domainInfo['expiration_date'],
                'days_left' => $domainInfo['days_left'],
                'price' => $domainInfo['price'],
                'registrar' => $domainInfo['registrar'],
                'newReg' => $domainInfo['newReg'],
            ]);
        }
        
        // Return results...
        return [
            'results' => $results,
            'total_price' => number_format($totalPrice, 2)
        ];
    }

    // ELI15: Process a single domain and return its information
    private function processSingleDomain(string $domain, string $uuid): array
    {
        $whoisData = $this->whoisService->fetchWhoisData($domain);

        if (!$whoisData) {
            return [
                'domain' => $domain,
                'error' => 'Unable to retrieve WHOIS data.',
            ];
        }

        $expirationDate = Carbon::parse($whoisData['expiration_date']);
        $daysLeft = Carbon::today()->diffInDays($expirationDate, false);
        $tld = strtolower(substr(strrchr($domain, '.'), 1));
        
        $calculatedPrice = $this->pricingService->calculatePrice($tld, $daysLeft);
        $registrationPrice = $this->pricingService->getRegistrationPrice($tld);

        return [
            'domain' => $domain,
            'expiration_date' => $expirationDate->toDateString(),
            'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
            'price' => number_format($calculatedPrice, 2),
            'registrar' => $whoisData['registrar'],
            'newReg' => number_format($registrationPrice, 2),
        ];
    }

    private function isValidDomain(string $domain): bool
    {
        return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
    }
} 
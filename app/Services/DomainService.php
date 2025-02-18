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

    // ELI15: Process a list of domains and return their information
    public function processDomains(array $domains, string $uuid): array
    {
        $results = [];
        $totalPrice = 0;

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
        }

        Log::info("Total price calculated: {$totalPrice}");

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

        // Save to database
        DomainResult::create([
            'uuid' => $uuid,
            'domain' => $domain,
            'expiration_date' => $expirationDate->toDateString(),
            'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
            'price' => $calculatedPrice,
            'registrar' => $whoisData['registrar'],
        ]);

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
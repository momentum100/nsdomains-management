<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PricingService
{
    private $jsonData;
    private $premiumTlds = ['com', 'net', 'org'];

    // ELI15: Load pricing data when service is created
    public function __construct()
    {
        $jsonPath = env('DOMAIN_PRICES_JSON_PATH', 'storage/app/namecheap.json');
        Log::debug("Loading domain prices from: {$jsonPath}");
        $this->jsonData = json_decode(file_get_contents(base_path($jsonPath)), true);
    }

    // ELI15: Calculate price based on TLD and days until expiration
    public function calculatePrice(string $tld, int $daysLeft): float
    {
        $registrationPrice = $this->getRegistrationPrice($tld);
        
        Log::info("Calculating price for TLD: {$tld}, Days left: {$daysLeft}, Registration price: {$registrationPrice}");

        if ($daysLeft <= 15) {
            Log::info("Domain expiring soon (<= 15 days), price set to 0");
            return 0.0;
        }

        $basePrice = $this->getBasePrice($daysLeft);
        $isPremium = in_array($tld, $this->premiumTlds);

        if (!$isPremium) {
            $maxAllowedPrice = $basePrice * 0.5;
            $finalPrice = min($basePrice, $maxAllowedPrice);
            
            Log::info("Non-premium TLD calculation - Base price: {$basePrice}, Max allowed: {$maxAllowedPrice}, Final: {$finalPrice}");
            
            return $finalPrice;
        }

        Log::info("Premium TLD calculation - Final price: {$basePrice}");
        return $basePrice;
    }

    // ELI15: Get the registration price for a specific TLD
    public function getRegistrationPrice(string $tld): float
    {
        foreach ($this->jsonData as $entry) {
            if ($entry['Tld'] === $tld) {
                return $entry['Register']['Price'];
            }
        }
        Log::warning("No registration price found for TLD: {$tld}");
        return 0.0;
    }

    private function getBasePrice(int $daysLeft): float
    {
        if ($daysLeft >= 15 && $daysLeft < 31) {
            return 1.5;
        } elseif ($daysLeft > 30 && $daysLeft < 91) {
            return 3.0;
        } else {
            return 3.5;
        }
    }
} 
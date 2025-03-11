<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * PricingService - Domain Pricing Calculator
 * 
 * PRICING ALGORITHM EXPLANATION:
 * 
 * 1. INPUTS:
 *    - TLD (Top Level Domain): like 'com', 'net', 'org', etc.
 *    - Days Left: number of days until domain expires
 * 
 * 2. SPECIAL CASE:
 *    - If domain is expiring soon (15 days or less) → Price = $0
 * 
 * 3. BASE PRICE CALCULATION:
 *    - 15-30 days left → $1.50
 *    - 31-90 days left → $3.00
 *    - 91+ days left → $3.50
 * 
 * 4. PREMIUM vs NON-PREMIUM ADJUSTMENT:
 *    - Premium TLDs ('com', 'net', 'org'):
 *      → Final price = Base price (no discount)
 *    - Non-premium TLDs (all others):
 *      → Max allowed price = Lower of (50% of base price OR 50% of registration price)
 *      → Final price = Lower of (base price OR max allowed price)
 * 
 * 5. REGISTRATION PRICE:
 *    - Used in price calculation for non-premium TLDs
 *    - Pulled from JSON data
 * 
 * ELI15: This service figures out how much to charge for domains based on:
 * - How soon the domain expires (cheaper if expiring soon)
 * - How popular the domain type is (com/net/org cost more than others)
 * - Free if about to expire in 15 days or less
 * - For less popular domains, we use the lower of half the base price or half the registration price
 */
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
            // ELI15: For non-premium domains, take the lower of half base price or half registration price
            $halfBasePrice = $basePrice * 0.5;
            $halfRegPrice = $registrationPrice * 0.5;
            $maxAllowedPrice = min($halfBasePrice, $halfRegPrice);
            
            // ELI15: Final price is the lower of base price or max allowed price
            $finalPrice = min($basePrice, $maxAllowedPrice);
            
            Log::info("Non-premium TLD calculation - Base price: {$basePrice}, Half base price: {$halfBasePrice}, Half reg price: {$halfRegPrice}, Max allowed: {$maxAllowedPrice}, Final: {$finalPrice}");
            
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
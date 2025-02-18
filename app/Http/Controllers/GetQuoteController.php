<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Iodev\Whois\Factory;
use Illuminate\Support\Str;
use App\Models\DomainResult;
use Illuminate\Support\Facades\Cache;

class GetQuoteController extends Controller
{
    /**
     * Display the Get Quote form.
     *
     * @return \Illuminate\View\View
     */
    public function showForm($uuid = null)
    {
        $results = collect(); // Initialize as an empty Collection
        $totalPrice = 0;
        $createdAt = null;

        if ($uuid) {
            // Fetch the results using the UUID from DomainResult and sort by 'days_left'
            $results = DomainResult::where('uuid', $uuid)
                ->orderBy('days_left', 'asc') // Sort by 'days_left' in ascending order
                ->get(['domain', 'registrar', 'expiration_date', 'days_left', 'price']); // Include 'registrar'

            // Calculate total price if results are found
            if (!$results->isEmpty()) {
                $createdAt = $results->first()->created_at; // Get the creation time of the first result

                // Calculate total price from the stored prices
                $totalPrice = $results->sum('price');
                \Log::info("Showing cached results with total price: {$totalPrice}");

                // Add registration prices
                $results = $this->addRegistrationPrices($results);
            }
        }

        return view('getquote', [
            'results' => $results,
            'total_price' => number_format($totalPrice, 2),
            'created_at' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : null, // Format the timestamp
        ]);
    }

    /**
     * Process the domain names and return WHOIS information with pricing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getQuote(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'domains' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please enter at least one domain name.',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Split the domains by new lines and sanitize
        $domains = preg_split('/\r\n|\r|\n/', $request->input('domains'));
        $domains = array_map('trim', $domains);
        $domains = array_filter($domains);

        $results = [];
        $totalPrice = 0; // Initialize total price

        // Initialize WHOIS client
        $whois = Factory::get()->createWhois();

        $uuid = Str::uuid(); // Generate a UUID for this set of results

        // Load domain registration prices from JSON file
        $jsonPath = storage_path('app/namecheap.json');
        $jsonData = json_decode(file_get_contents($jsonPath), true);

        foreach ($domains as $domain) {
            // Basic domain validation
            if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
                $results[] = [
                    'domain' => $domain,
                    'error' => 'Invalid domain format.',
                ];
                continue;
            }

            // Fetch WHOIS data
            $whoisData = $this->fetchWhois($whois, $domain);

            if (!$whoisData) {
                $results[] = [
                    'domain' => $domain,
                    'error' => 'Unable to retrieve WHOIS data.',
                ];
                continue;
            }

            // Calculate days left
            $expirationDate = Carbon::parse($whoisData['expiration_date']);
            $today = Carbon::today();
            $daysLeft = $today->diffInDays($expirationDate, false);

            // Extract TLD from domain
            $tld = strtolower(substr(strrchr($domain, '.'), 1));

            // Determine registration price from JSON data
            $registrationPrice = $this->getRegistrationPrice($jsonData, $tld);

            // Calculate price for this domain
            $calculatedPrice = $this->calculatePrice($tld, $daysLeft);
            $totalPrice += $calculatedPrice; // Add to total

            $results[] = [
                'domain' => $domain,
                'expiration_date' => $expirationDate->toDateString(),
                'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
                'price' => number_format($calculatedPrice, 2),
                'registrar' => $whoisData['registrar'] ?? 'N/A',
                'newReg' => number_format($registrationPrice, 2),
            ];

            // Save each result to the database
            DomainResult::create([
                'uuid' => $uuid,
                'domain' => $domain,
                'expiration_date' => $expirationDate->toDateString(),
                'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
                'price' => $calculatedPrice, // Save calculated price
                'registrar' => $whoisData['registrar'] ?? 'N/A', // Save registrar information
            ]);
        }

        \Log::info("Total price calculated: {$totalPrice}");

        // Add registration prices to the results
        $results = collect($results);
        $results = $this->addRegistrationPrices($results);

        return response()->json([
            'status' => 'success',
            'data' => $results,
            'uuid' => $uuid,
            'total_price' => number_format($totalPrice, 2),
            'link' => url("/getquote/{$uuid}"), // Generate a link to the results
        ]);
    }

    /**
     * Fetch WHOIS information for a given domain using the WHOIS library.
     *
     * @param \Iodev\Whois\Whois $whois
     * @param string $domain
     * @return array|null
     */
    private function fetchWhois($whois, $domain)
    {
        // Check if the WHOIS data is already cached
        $cacheKey = "whois_{$domain}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            \Log::info("WHOIS info for domain {$domain} retrieved from cache.");
            return $cachedData;
        }

        try {
            $info = $whois->loadDomainInfo($domain);
            if ($info && $info->expirationDate) { // Ensure expirationDate is valid
                \Log::info("WHOIS info for domain {$domain}: ", (array) $info);

                $expirationDate = $info->expirationDate;
                $registrar = $info->registrar ?? 'N/A';

                $whoisData = [
                    'expiration_date' => $expirationDate ? Carbon::parse($expirationDate)->format('Y-m-d') : null,
                    'registrar' => $registrar,
                ];

                // Store the WHOIS data in the cache for 24 hours
                Cache::put($cacheKey, $whoisData, now()->addHours(240));

                return $whoisData;
            }
        } catch (\Exception $e) {
            \Log::error("WHOIS fetch error for domain {$domain}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Calculate the price based on TLD and days left.
     *
     * @param  string  $tld
     * @param  int  $daysLeft
     * @return float
     */
    private function calculatePrice($tld, $daysLeft)
    {
        // Get registration price for the TLD
        $registrationPrice = $this->getRegistrationPrice(null, $tld);
        
        \Log::info("Calculating price for TLD: {$tld}, Days left: {$daysLeft}, Registration price: {$registrationPrice}");

        $premiumTlds = ['com', 'net', 'org'];
        $isPremium = in_array($tld, $premiumTlds);

        // Early return if domain is expiring soon
        if ($daysLeft < 14) {
            \Log::info("Domain expiring soon (< 14 days), price set to 0");
            return 0.0;
        }

        // Calculate base price based on time frames
        if ($daysLeft > 14 && $daysLeft < 31) {
            $basePrice = 1.5;
        } elseif ($daysLeft > 30 && $daysLeft < 91) {
            $basePrice = 3.0;
        } else { // $daysLeft > 90
            $basePrice = 3.5;
        }

        // For non-premium TLDs, calculate maximum allowed price
        if (!$isPremium) {
            $maxAllowedPrice = $registrationPrice * 0.5; // 50% of registration price
            $finalPrice = min($basePrice, $maxAllowedPrice);
            
            \Log::info("Non-premium TLD calculation - Base price: {$basePrice}, Max allowed (50% of reg): {$maxAllowedPrice}, Final: {$finalPrice}");
            
            return $finalPrice;
        }

        \Log::info("Premium TLD calculation - Final price: {$basePrice}");
        return $basePrice;
    }

    private function getRegistrationPrice($jsonData = null, $tld)
    {
        if ($jsonData === null) {
            $jsonPath = env('DOMAIN_PRICES_JSON_PATH', 'storage/app/namecheap.json');
            \Log::debug("Loading domain prices from: {$jsonPath}");
            $jsonData = json_decode(file_get_contents(base_path($jsonPath)), true);
        }

        foreach ($jsonData as $entry) {
            if ($entry['Tld'] === $tld) {
                return $entry['Register']['Price'];
            }
        }
        \Log::warning("No registration price found for TLD: {$tld}");
        return 0.0; // Default price if TLD not found
    }

    /**
     * Add registration prices to the results.
     *
     * @param \Illuminate\Support\Collection $results
     * @return \Illuminate\Support\Collection
     */
    private function addRegistrationPrices($results)
    {
        // Load domain registration prices once
        $jsonPath = env('DOMAIN_PRICES_JSON_PATH', 'storage/app/namecheap.json');
        $jsonData = json_decode(file_get_contents(base_path($jsonPath)), true);

        return $results->map(function ($result) use ($jsonData) {
            $domain = is_array($result) ? $result['domain'] : $result->domain;
            $tld = strtolower(substr(strrchr($domain, '.'), 1));
            $registrationPrice = $this->getRegistrationPrice($jsonData, $tld);

            if (is_array($result)) {
                $result['newReg'] = number_format($registrationPrice, 2);
            } else {
                $result->newReg = number_format($registrationPrice, 2);
            }

            return $result;
        });
    }

    public function showResults($uuid)
    {
        $results = DomainResult::where('uuid', $uuid)->get();

        if ($results->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No results found.'], 404);
        }

        return view('results', ['results' => $results]);
    }
}

#end of file
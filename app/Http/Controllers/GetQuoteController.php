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
            // Fetch the results using the UUID from DomainResult
            $results = DomainResult::where('uuid', $uuid)->get(['domain', 'registrar', 'expiration_date', 'days_left', 'price']); // Include 'registrar'

            // Calculate total price if results are found
            if (!$results->isEmpty()) {
                $totalPrice = $results->sum('price');
                $createdAt = $results->first()->created_at; // Get the creation time of the first result
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
        $totalPrice = 0;

        // Initialize WHOIS client
        $whois = Factory::get()->createWhois();

        $uuid = Str::uuid(); // Generate a UUID for this set of results

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

            // Determine price based on TLD and days left
            $tld = strtolower(substr(strrchr($domain, '.'), 1));
            $price = $this->calculatePrice($tld, $daysLeft);
            $totalPrice += $price;

            $results[] = [
                'domain' => $domain,
                'expiration_date' => $expirationDate->toDateString(),
                'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
                'price' => number_format($price, 2),
                'registrar' => $whoisData['registrar'] ?? 'N/A', // Ensure registrar is included in results
            ];

            // Save each result to the database
            DomainResult::create([
                'uuid' => $uuid,
                'domain' => $domain,
                'expiration_date' => $expirationDate->toDateString(),
                'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
                'price' => $price,
                'registrar' => $whoisData['registrar'] ?? 'N/A', // Save registrar information
            ]);
        }

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
            if ($info) {
                \Log::info("WHOIS info for domain {$domain}: ", (array) $info);

                $expirationDate = $info->expirationDate;
                $registrar = $info->registrar ?? 'N/A';

                $whoisData = [
                    'expiration_date' => $expirationDate ? Carbon::parse($expirationDate)->format('Y-m-d') : null,
                    'registrar' => $registrar,
                ];

                // Store the WHOIS data in the cache for 24 hours
                Cache::put($cacheKey, $whoisData, now()->addHours(24));

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
        $premiumTlds = ['com', 'net', 'org'];

        if (in_array($tld, $premiumTlds)) {
            if ($daysLeft < 14) {
                return 0.0;
            } elseif ($daysLeft > 14 && $daysLeft < 31) {
                return 1.5;
            } elseif ($daysLeft > 30 && $daysLeft < 91) {
                return 3.0;
            } else { // $daysLeft > 90
                return 3.5;
            }
        } else {
            if ($daysLeft < 14) {
                return 0.0;
            } elseif ($daysLeft > 14 && $daysLeft < 31) {
                return 0.75; // Half of 1.5
            } elseif ($daysLeft > 30 && $daysLeft < 91) {
                return 1.5; // Half of 3.0
            } else { // $daysLeft > 90
                return 1.75; // Half of 3.5
            }
        }
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

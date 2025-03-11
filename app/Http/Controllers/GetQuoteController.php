<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Iodev\Whois\Factory;
use Illuminate\Support\Str;
use App\Models\DomainResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\DomainService;
use App\Services\PricingService;
use App\Services\WhoisService;

class GetQuoteController extends Controller
{
    private $domainService;
    private $pricingService;

    public function __construct(DomainService $domainService, PricingService $pricingService)
    {
        $this->domainService = $domainService;
        $this->pricingService = $pricingService;
    }

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
        $domains = array_filter(array_map('trim', $domains));
        
        // Count original domains for logging
        $originalCount = count($domains);
        
        // Convert all domains to lowercase and remove duplicates
        $domains = array_unique(array_map('strtolower', $domains));
        
        // Count after deduplication for logging
        $afterDedupeCount = count($domains);
        $removedCount = $originalCount - $afterDedupeCount;
        
        // Log the deduplication results
        Log::info("Domain deduplication: Original count: {$originalCount}, After deduplication: {$afterDedupeCount}, Removed: {$removedCount}");

        // Get the authenticated user's ID or null if not logged in
        $userId = auth()->check() ? auth()->id() : null;
        Log::info("Processing domains for " . ($userId ? "user ID: {$userId}" : "guest user"));

        $uuid = Str::uuid(); // Generate a UUID for this set of results
        $processedData = $this->domainService->processDomains($domains, $uuid, $userId);

        return response()->json([
            'status' => 'success',
            'data' => $processedData['results'],
            'uuid' => $uuid,
            'total_price' => $processedData['total_price'],
            'link' => url("/getquote/{$uuid}"), // Generate a link to the results
        ]);
    }

    /**
     * Add registration prices to the results.
     *
     * @param \Illuminate\Support\Collection $results
     * @return \Illuminate\Support\Collection
     */
    private function addRegistrationPrices($results)
    {
        return $results->map(function ($result) {
            $domain = is_array($result) ? $result['domain'] : $result->domain;
            $tld = strtolower(substr(strrchr($domain, '.'), 1));
            $registrationPrice = $this->pricingService->getRegistrationPrice($tld);

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
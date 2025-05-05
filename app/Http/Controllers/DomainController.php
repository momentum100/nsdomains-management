<?php

// app/Http/Controllers/DomainController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    // Constants for better maintainability
    private const PREMIUM_EXTENSIONS = ['com', 'net', 'org'];
    private const PRICE_URGENT = 2.00;
    private const PRICE_SOON = 3.50;
    private const PRICE_NORMAL_PREMIUM = 5.00;
    private const PRICE_NORMAL_REGULAR = 4.00;
    private const PRICE_LONG_PREMIUM = 6.00;
    private const PRICE_LONG_REGULAR = 5.00;
    private const PRICE_VERY_LONG_PREMIUM = 9.00;
    private const PRICE_VERY_LONG_REGULAR = 5.00;

    public function index(Request $request)
    {
        $status = $request->query('status', 'ACTIVE'); // Get status from query, default to ACTIVE
        
        // Start building the query for the main table
        $query = Domain::select('*', DB::raw('DATEDIFF(FROM_UNIXTIME(exp_date), NOW()) as days_left'))
                        ->where('status', $status); // Filter by status
        
        // Check if domain list filter is provided for the main table
        if ($request->has('domain_list') && !empty($request->input('domain_list'))) {
            // Split the input by newlines and clean up each domain
            $domainList = explode("\n", $request->input('domain_list'));
            $domainList = array_map('trim', $domainList);
            $domainList = array_filter($domainList); // Remove empty entries
            
            // Log the domains being filtered
            \Log::info('Filtering by domain list. Count: ' . count($domainList));
            
            // Add the domain list filter to the query
            if (!empty($domainList)) {
                $query->whereIn('domain', $domainList);
            }
        }
        
        // Execute the query and process results for the main table
        $domains = $query->orderBy('exp_date')
                         ->get()
                         ->map(function ($domain) {
                             // Add suggested price to each domain
                             $domain->suggested_price = $this->calculatePrice($domain->days_left, $domain->domain);
                             return $domain;
                         });
        
        // --- Calculate Histogram Data ---
        // ELI15: Get all active domains just for the histogram, regardless of the table filter.
        $allActiveDomains = Domain::select(DB::raw('DATEDIFF(FROM_UNIXTIME(exp_date), NOW()) as days_left'))
                                  ->where('status', 'ACTIVE')
                                  ->get();

        // ELI15: Define the buckets (ranges) for our histogram graph.
        $buckets = [
            '0-29' => 0, '30-59' => 0, '60-89' => 0, '90-119' => 0, 
            '120-149' => 0, '150-179' => 0, '180-209' => 0, '210-239' => 0,
            '240-269' => 0, '270-299' => 0, '300-329' => 0, '330-359' => 0,
            '360+' => 0
        ];
        $bucketRanges = [
            29 => '0-29', 59 => '30-59', 89 => '60-89', 119 => '90-119',
            149 => '120-149', 179 => '150-179', 209 => '180-209', 239 => '210-239',
            269 => '240-269', 299 => '270-299', 329 => '300-329', 359 => '330-359'
        ];

        // ELI15: Count how many domains fall into each bucket based on their days_left.
        foreach ($allActiveDomains as $domain) {
            $days = $domain->days_left;
            if ($days < 0) continue; // Ignore expired domains for histogram

            $bucketKey = '360+'; // Default for domains expiring far in the future
            foreach ($bucketRanges as $maxDays => $key) {
                if ($days <= $maxDays) {
                    $bucketKey = $key;
                    break;
                }
            }
            $buckets[$bucketKey]++;
        }
        
        // ELI15: Prepare the data in a format the chart library can understand.
        $histogramData = [
            'labels' => array_keys($buckets),
            'counts' => array_values($buckets)
        ];
        \Log::info('Histogram Data Prepared:', $histogramData);
        // --- End Histogram Data Calculation ---

        $total = $domains->count(); // Count based on the potentially filtered $domains
        $active = Domain::where('status', 'ACTIVE')->count(); // Total active count
        $sold = Domain::where('status', 'SOLD')->count();     // Total sold count
        $activeDomainsByRegistrar = Domain::select('registrar', DB::raw('count(*) as total'))
                                           ->where('status', 'ACTIVE')
                                           ->groupBy('registrar')
                                           ->get();
        \Log::info('Total domains (filtered/view) count: ' . $total);
        \Log::info('Total active domains count: ' . $active);
        \Log::info('Total sold domains count: ' . $sold);

        // Pass additional variable to indicate if filtering is active
        $isFiltered = $request->has('domain_list') && !empty($request->input('domain_list'));
        
        // ELI15: Send all the data (domains for the table, counts, histogram data) to the page.
        return view('domains.index', compact('domains', 'total', 'status', 'active', 'sold', 'activeDomainsByRegistrar', 'isFiltered', 'histogramData'));
    }

    /**
     * Calculate suggested price based on days left and domain extension
     * @param int $daysLeft
     * @param string $domain
     * @return float
     */
    private function calculatePrice(int $daysLeft, string $domain): float
    {
        // Get domain extension (everything after the last dot)
        $extension = strtolower(substr(strrchr($domain, '.'), 1));
        
        // ELI15: Log calculation details to help understand pricing.
        // Log::info("Calculating price for domain: $domain (Days left: $daysLeft, Extension: $extension)");
        
        // Very urgent domains (less than 15 days)
        if ($daysLeft < 15) {
            // Log::info("Price set to URGENT: $" . self::PRICE_URGENT);
            return self::PRICE_URGENT;
        }
        
        // Soon expiring domains (15-30 days)
        if ($daysLeft < 30) {
            // Log::info("Price set to SOON: $" . self::PRICE_SOON);
            return self::PRICE_SOON;
        }
        
        // Check if it's a premium extension
        $isPremium = in_array($extension, self::PREMIUM_EXTENSIONS);
        
        // Normal expiration (30-91 days)
        if ($daysLeft < 91) {
            $price = $isPremium ? self::PRICE_NORMAL_PREMIUM : self::PRICE_NORMAL_REGULAR;
            // Log::info("Price set to NORMAL (" . ($isPremium ? 'Premium' : 'Regular') . "): $$price");
            return $price;
        }
        
        // Very long expiration (200+ days) with different pricing for premium domains
        if ($daysLeft >= 200) {
            $price = $isPremium ? self::PRICE_VERY_LONG_PREMIUM : self::PRICE_VERY_LONG_REGULAR;
            // Log::info("Price set to VERY LONG (" . ($isPremium ? 'Premium' : 'Regular') . "): $$price");
            return $price;
        }
        
        // Long expiration (91-199 days)
        $price = $isPremium ? self::PRICE_LONG_PREMIUM : self::PRICE_LONG_REGULAR;
        // Log::info("Price set to LONG (" . ($isPremium ? 'Premium' : 'Regular') . "): $$price");
        return $price;
    }

    public function exportCsv()
    {
        $domains = Domain::where('status', 'ACTIVE') // Filter by ACTIVE status
                         ->orderBy('exp_date')
                         ->get();
        $filename = "domains_" . date('Ymd_His') . ".csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];
        $columns = ['Domain', 'Expiration Date', 'Registrar'];

        $callback = function() use ($domains, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($domains as $domain) {
                fputcsv($file, [
                    $domain->domain,
                    date('Y-m-d H:i:s', $domain->exp_date),
                    $domain->registrar,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function destroy(Request $request)
    {
        \Log::info('Bulk mark as sold request received', $request->all());
        $domainIds = $request->input('domains');
        if ($domainIds) {
            Domain::whereIn('id', $domainIds)
                ->update([
                    'status' => 'SOLD',
                    'updated_at' => now()
                ]);
        }
        return redirect()->route('domains.index')->with('success', 'Selected domains marked as sold successfully.');
    }
    public function bulkDestroy(Request $request)
    {
        \Log::info('Bulk mark as sold request received', $request->all());
        $domainIds = $request->input('domains');
        if ($domainIds) {
            Domain::whereIn('id', $domainIds)
                ->update([
                    'status' => 'SOLD',
                    'updated_at' => now()
                ]);
        }
        return redirect()->route('domains.index')->with('success', 'Selected domains marked as sold successfully.');
    }

    public function getActiveDomainsByRegistrar()
    {
        $registrarCounts = Domain::select('registrar', DB::raw('count(*) as total'))
                                 ->where('status', 'ACTIVE')
                                 ->groupBy('registrar')
                                 ->get();

        return response()->json($registrarCounts);
    }

    public function markAsSold(Request $request)
    {
        $domains = explode("\n", $request->input('domains'));
        $domains = array_map('trim', $domains);
        $domains = array_filter($domains);

        if (!empty($domains)) {
            Domain::whereIn('domain', $domains)
                ->update([
                    'status' => 'SOLD',
                    'updated_at' => now()
                ]);
        }

        return redirect()->route('domains.index')->with('success', 'Domains marked as sold successfully.');
    }

    public function publicList()
    {
        // Get domains and calculate days left
        $domains = Domain::select('*', DB::raw('DATEDIFF(FROM_UNIXTIME(exp_date), NOW()) as days_left'))
            ->where('status', 'ACTIVE')
            ->whereRaw('DATEDIFF(FROM_UNIXTIME(exp_date), NOW()) > 0')  // Only future expiring domains
            ->orderBy('exp_date')
            ->limit(150)  // Limit to 150 domains
            ->get()
            ->map(function ($domain) {
                // Add suggested price to each domain
                $domain->suggested_price = $this->calculatePrice($domain->days_left, $domain->domain);
                return $domain;
            });

        \Log::info('Public list accessed. Showing ' . $domains->count() . ' domains');
        
        return view('domains.public', compact('domains'));
    }

    public function indexByRegistrar(Request $request, $registrar)
    {
        \Log::info("Filtering domains for registrar: $registrar");
        
        // Get domains filtered by registrar
        $domains = Domain::select('*', DB::raw('DATEDIFF(FROM_UNIXTIME(exp_date), NOW()) as days_left'))
                         ->where('registrar', $registrar)
                         ->where('status', 'ACTIVE')  // Default to active domains
                         ->orderBy('exp_date')
                         ->get()
                         ->map(function ($domain) {
                             $domain->suggested_price = $this->calculatePrice($domain->days_left, $domain->domain);
                             return $domain;
                         });

        // Get counts for the statistics
        $total = $domains->count();
        $active = Domain::where('status', 'ACTIVE')->count();
        $sold = Domain::where('status', 'SOLD')->count();
        $activeDomainsByRegistrar = Domain::select('registrar', DB::raw('count(*) as total'))
                                          ->where('status', 'ACTIVE')
                                          ->groupBy('registrar')
                                          ->get();

        \Log::info("Found $total domains for registrar: $registrar");

        return view('domains.index', compact(
            'domains',
            'total',
            'active',
            'sold',
            'activeDomainsByRegistrar',
            'registrar'  // Pass the current registrar to highlight it in the view
        ));
    }
}

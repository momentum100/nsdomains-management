<?php

// app/Http/Controllers/DomainController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // Start with all domains
        $query = Domain::query();
        
        // Track if we're filtering by domain list
        $isFiltering = false;
        $filteredDomains = null;
        
        // Handle domain list filtering
        if ($request->has('domain_list') && !empty($request->domain_list)) {
            Log::info('Filtering domains by list');
            
            // Parse and clean domain list
            $domainList = collect(explode("\n", $request->domain_list))
                ->map(fn($domain) => trim($domain))
                ->filter(fn($domain) => !empty($domain))
                ->map(fn($domain) => str_replace([',', '"', "'"], '', $domain))
                ->unique()
                ->values()
                ->toArray();
            
            Log::info('Searching for domains:', ['count' => count($domainList)]);
            
            // Store the filtered domains separately
            $filteredDomains = Domain::whereIn('domain', $domainList)->get();
            
            Log::info('Found domains:', ['count' => $filteredDomains->count()]);
            $isFiltering = true;
        }
        
        // Apply other existing filters (if any)
        // ...
        
        // Get statistics for all domains (regardless of filtering)
        $total = Domain::count();
        $active = Domain::where('status', 'ACTIVE')->count();
        $sold = Domain::where('status', 'SOLD')->count();
        
        // Get counts of active domains by registrar
        $activeDomainsByRegistrar = Domain::where('status', 'ACTIVE')
            ->select('registrar', DB::raw('count(*) as total'))
            ->groupBy('registrar')
            ->orderBy('total', 'desc')
            ->get();
        
        // Paginate results for the main table
        $domains = $query->paginate(10);
        
        return view('domains.index', [
            'domains' => $domains,
            'total' => $total,
            'active' => $active, 
            'sold' => $sold,
            'activeDomainsByRegistrar' => $activeDomainsByRegistrar,
            'isFiltering' => $isFiltering,
            'filteredDomains' => $filteredDomains,
            // Only calculate total price if we have filtered domains
            'totalPrice' => $filteredDomains ? $filteredDomains->sum('suggested_price') : 0
        ]);
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
        
        \Log::info("Calculating price for domain: $domain (Days left: $daysLeft, Extension: $extension)");
        
        // Very urgent domains (less than 15 days)
        if ($daysLeft < 15) {
            \Log::info("Price set to URGENT: $" . self::PRICE_URGENT);
            return self::PRICE_URGENT;
        }
        
        // Soon expiring domains (15-30 days)
        if ($daysLeft < 30) {
            \Log::info("Price set to SOON: $" . self::PRICE_SOON);
            return self::PRICE_SOON;
        }
        
        // Check if it's a premium extension
        $isPremium = in_array($extension, self::PREMIUM_EXTENSIONS);
        
        // Normal expiration (30-91 days)
        if ($daysLeft < 91) {
            $price = $isPremium ? self::PRICE_NORMAL_PREMIUM : self::PRICE_NORMAL_REGULAR;
            \Log::info("Price set to NORMAL (" . ($isPremium ? 'Premium' : 'Regular') . "): $$price");
            return $price;
        }
        
        // Very long expiration (200+ days) with different pricing for premium domains
        if ($daysLeft >= 200) {
            $price = $isPremium ? self::PRICE_VERY_LONG_PREMIUM : self::PRICE_VERY_LONG_REGULAR;
            \Log::info("Price set to VERY LONG (" . ($isPremium ? 'Premium' : 'Regular') . "): $$price");
            return $price;
        }
        
        // Long expiration (91-199 days)
        $price = $isPremium ? self::PRICE_LONG_PREMIUM : self::PRICE_LONG_REGULAR;
        \Log::info("Price set to LONG (" . ($isPremium ? 'Premium' : 'Regular') . "): $$price");
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

    public function filterByList(Request $request)
    {
        // Log the start of filtering
        Log::info('Starting domain list filtering');
        
        $request->validate([
            'domain_list' => 'required|string'
        ]);
        
        // Convert textarea input to array and clean it
        $domains = collect(explode("\n", $request->domain_list))
            ->map(fn($domain) => trim($domain)) // Remove whitespace
            ->filter(fn($domain) => !empty($domain)) // Remove empty lines
            ->map(fn($domain) => str_replace([',', '"', "'"], '', $domain)) // Remove any commas and quotes
            ->unique()
            ->values()
            ->toArray();
        
        // Log the cleaned domains
        Log::info('Searching for domains:', ['domains' => $domains]);
        
        // Use the correct column name 'domain'
        $filteredDomains = Domain::whereIn('domain', $domains)->get();
        
        // Log the results
        Log::info('Found domains:', [
            'count' => $filteredDomains->count(),
            'domains' => $filteredDomains->pluck('domain')->toArray()
        ]);
        
        // Get all domains for the regular table
        $domains = Domain::query();
        $domains = $domains->paginate(10);
        
        return view('domains.index', [
            'domains' => $domains,
            'filteredDomains' => $filteredDomains,
            'totalPrice' => $filteredDomains->sum('suggested_price')
        ]);
    }
}

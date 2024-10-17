<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Iodev\Whois\Factory;

class GetQuoteController extends Controller
{
    /**
     * Display the Get Quote form.
     *
     * @return \Illuminate\View\View
     */
    public function showForm()
    {
        return view('getquote');
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

        // Initialize WHOIS client
        $whois = Factory::get()->createWhois();

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

            $results[] = [
                'domain' => $domain,
                'registrant' => $whoisData['registrant'] ?? 'N/A',
                'expiration_date' => $expirationDate->toDateString(),
                'days_left' => $daysLeft >= 0 ? $daysLeft : 0,
                'price' => number_format($price, 2),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $results,
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
        try {
            $info = $whois->loadDomainInfo($domain);
            if ($info) {
                return [
                    'registrant' => $info->registrantOrganization ?? 'N/A',
                    'expiration_date' => $info->expirationDate ? $info->expirationDate->format('Y-m-d') : null,
                ];
            }
        } catch (\Exception $e) {
            // Log the error if needed
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
            if ($daysLeft <= 14) { // 2 weeks to 1 month
                return 1.5;
            } elseif ($daysLeft <= 90) { // 1-3 months
                return 3.0;
            } elseif ($daysLeft > 90) { // 3+ months
                return 3.5;
            }
        }

        // Other TLDs
        return 0.5;
    }
}

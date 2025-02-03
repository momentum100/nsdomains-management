<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadSavDomains extends Command
{
    // Define the signature of the command with an optional dry-run.
    protected $signature = 'domains:download-sav {--dry-run : Run without saving to database}';

    // A short description for the command.
    protected $description = 'Download domains from SAV.com API (updated to use the new response format)';

    // Base URL for the SAV.com API.
    protected $apiUrl = 'https://api.sav.com/domains_api_v1';

    /**
     * Execute the command.
     *
     * ELI15: This function downloads domain information from SAV.com. It checks your API key,
     * sends a request, and processes every domain using the new response format. It logs every step,
     * and if you choose dry-run mode, it will simply simulate adding the domains without saving.
     *
     * @return int
     */
    public function handle()
    {
        // Log the start of the SAV.com domain download process.
        Log::info('Starting SAV.com domains download', [
            'dry_run' => $this->option('dry-run'),
            'environment' => app()->environment()
        ]);

        // Retrieve the SAV.com API key from config or .env.
        $apiKey = config('services.sav_com.api_key') ?: env('SAV_COM_API_KEY');

        // Log API key status (keeping the key secret).
        Log::info('SAV API Key Check', [
            'key_exists' => !empty($apiKey),
            'key_last_4' => !empty($apiKey) ? '****' . substr($apiKey, -4) : 'EMPTY'
        ]);

        // If the API key is missing, log and output an error.
        if (empty($apiKey)) {
            Log::error('SAV.com API credentials missing');
            $this->error('SAV.com API credentials not found. Please check SAV_COM_API_KEY in your .env file.');
            return 1;
        }

        $this->info('Downloading domains from SAV.com...');

        try {
            // Log details about the HTTP request.
            Log::info('Sending request to SAV.com API', [
                'url'    => $this->apiUrl . '/get_active_domains_in_account',
                'header' => 'APIKEY: ****' . substr($apiKey, -4)
            ]);

            // Send a POST request to the API with SSL verification disabled.
            $response = Http::withoutVerifying() // ELI5: We are not checking the website certificate.
                ->withHeaders([
                    'APIKEY' => $apiKey,
                ])
                ->post("{$this->apiUrl}/get_active_domains_in_account", []);

            // If the response is not successful, log the details and abort.
            if (!$response->successful()) {
                $errorDetails = [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'headers' => $response->headers()
                ];
                Log::error('Failed to fetch domains from SAV.com API', $errorDetails);
                $this->error('Failed to fetch domains from SAV.com API');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
                return 1;
            }

            // Decode the JSON response.
            $jsonResponse = $response->json();
            Log::info('Complete SAV.com API Response', [
                'response' => $jsonResponse
            ]);

            // Output the API response so we can see it in the console.
            $this->info('Complete API Response:');
            $this->line(json_encode($jsonResponse, JSON_PRETTY_PRINT));

            // Validate that our expected structure exists.
            if (!isset($jsonResponse['response']['domains'])) {
                Log::error('Invalid response structure', ['json' => $jsonResponse]);
                $this->error('Invalid response structure from SAV.com API.');
                return 1;
            }

            // Get the domains list from the new response scheme.
            $domains = $jsonResponse['response']['domains'];
            $totalDomains   = count($domains);
            $newDomains     = 0;
            $skippedDomains = 0;

            Log::info('Processing retrieved domains', [
                'total_domains' => $totalDomains
            ]);

            // Loop through each domain and process it.
            foreach ($domains as $domainData) {
                $this->processDomain($domainData, 'sav.com', $newDomains, $skippedDomains);
            }

            // Prepare and log a final summary message.
            $message = "Process completed. Total domains from SAV.com: {$totalDomains}. New: {$newDomains}, Skipped: {$skippedDomains}";
            Log::info($message);
            $this->info($message);

        } catch (\Exception $e) {
            // Log any exceptions that occur.
            Log::error('Error processing SAV.com domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Process a single domain record.
     *
     * ELI15: This helper function takes one domain entry, checks if it has a valid name,
     * makes sure it isn't already saved in our database, and then either simulates or saves
     * the domain information using the new field names.
     *
     * @param array  $domainData      The data for the domain from SAV.com.
     * @param string $registrar       The registrar name (here, "sav.com").
     * @param int    &$newDomains     Counter for new domains added.
     * @param int    &$skippedDomains Counter for domains that were skipped.
     */
    private function processDomain(array $domainData, string $registrar, int &$newDomains, int &$skippedDomains): void
    {
        // Grab the domain name using the new key.
        $domainName = $domainData['domain_name'] ?? null;
        if (!$domainName) {
            Log::warning('Domain name missing in domain data', ['data' => $domainData]);
            return;
        }

        // Check if the domain already exists, and skip if true.
        if (Domain::where('domain', $domainName)->exists()) {
            $this->line("Skipping existing domain: {$domainName}");
            Log::info('Skipping existing domain', ['domain' => $domainName]);
            $skippedDomains++;
            return;
        }

        // Convert registration and expiration dates (they are already Unix timestamps as strings).
        $expTimestamp = isset($domainData['date_expiration']) ? (int) $domainData['date_expiration'] : time();
        $regTimestamp = isset($domainData['date_registered']) ? (int) $domainData['date_registered'] : time();

        // Determine the auto-renew flag. "1" means true.
        $autoRenew = isset($domainData['auto_renew_enabled']) && $domainData['auto_renew_enabled'] === "1";

        Log::info('Processing domain', [
            'domain_name'      => $domainName,
            'autoRenew'        => $autoRenew,
            'internal_status'  => $domainData['internal_status'] ?? 'unknown',
            'backend'          => $domainData['backend'] ?? 'unknown'
        ]);

        // Build our nameservers list from separate fields.
        $nameservers = [];
        if (!empty($domainData['ns_1'])) {
            $nameservers[] = $domainData['ns_1'];
        }
        if (!empty($domainData['ns_2'])) {
            $nameservers[] = $domainData['ns_2'];
        }

        // If it's a dry run, just output the details without saving.
        if ($this->option('dry-run')) {
            $this->info("Would add domain: {$domainName}");
            $this->line("  Registration: " . date('Y-m-d', $regTimestamp));
            $this->line("  Expiry: " . date('Y-m-d', $expTimestamp));
            $this->line("  Registrar: sav.com");
            $this->line("  Auto Renew: " . ($autoRenew ? 'Yes' : 'No'));
            $newDomains++;
            return;
        }

        // Save the domain using the new field mapping.
        Domain::create([
            'domain'      => $domainName,
            'exp_date'    => $expTimestamp,
            'reg_date'    => $regTimestamp,
            'registrar'   => $registrar,
            'auto_renew'  => $autoRenew,
            'status'      => $domainData['internal_status'] ?? 'registered',
            'nameservers' => json_encode($nameservers)
        ]);

        Log::info('Added new domain', ['domain' => $domainName]);
        $newDomains++;
    }
} 
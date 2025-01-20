<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadPorkbunDomains extends Command
{
    // Hey! I'm the command that downloads domains from Porkbun API
    protected $signature = 'domains:download-porkbun {--dry-run : Run without saving to database}';
    protected $description = 'Download domains from Porkbun API';

    // Porkbun API endpoint - they use HTTPS
    protected $apiUrl = 'https://api.porkbun.com/api/json/v3';

    public function handle()
    {
        // Let's start by logging what we're doing
        Log::info('Starting Porkbun domains download', [
            'dry_run' => $this->option('dry-run'),
            'environment' => app()->environment()
        ]);

        // Get our API credentials - we need both key and secret
        $apiKey = config('services.porkbun.api_key');
        $apiSecret = config('services.porkbun.api_secret');

        // Log that we have the credentials (but don't show them!)
        Log::info('API Credentials Check', [
            'key_exists' => !empty($apiKey),
            'key_length' => strlen($apiKey ?? ''),
            'secret_exists' => !empty($apiSecret),
            'secret_length' => strlen($apiSecret ?? ''),
        ]);

        // Make sure we have both credentials
        if (empty($apiKey) || empty($apiSecret)) {
            Log::error('Porkbun API credentials missing');
            $this->error('Porkbun API credentials not found. Please check your .env file for PORKBUN_API_KEY and PORKBUN_API_SECRET');
            return 1;
        }

        $this->info('Downloading domains from Porkbun...');

        try {
            // Keep track of what we're doing
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;

            // Make the API request - note we're not verifying SSL
            $response = Http::withoutVerifying()
                ->post("{$this->apiUrl}/domain/listAll", [
                    'apikey' => $apiKey,
                    'secretapikey' => $apiSecret
                ]);

            // Log what we got back (safely)
            Log::info('API Request Details', [
                'url' => "{$this->apiUrl}/domain/listAll",
                'status' => $response->status(),
                'has_response' => !empty($response->json())
            ]);

            if (!$response->successful()) {
                $errorDetails = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ];
                
                Log::error('Failed to fetch domains from Porkbun API', $errorDetails);
                $this->error('Failed to fetch domains from Porkbun API');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
                return 1;
            }

            $responseData = $response->json();
            
            // Porkbun specific response structure check
            if (!isset($responseData['status']) || $responseData['status'] !== 'SUCCESS') {
                Log::error('Porkbun API returned error', ['response' => $responseData]);
                $this->error('Porkbun API returned error: ' . ($responseData['message'] ?? 'Unknown error'));
                return 1;
            }

            $domains = $responseData['domains'] ?? [];
            $totalDomains = count($domains);

            // Show what we found
            $this->info("Processing {$totalDomains} domains...");

            // Log a sample domain if we have any
            if (!empty($domains)) {
                Log::info('Sample domain structure:', ['first_domain' => json_encode($domains[0], JSON_PRETTY_PRINT)]);
            }

            // Process each domain
            foreach ($domains as $domainData) {
                $domainName = $domainData['domain'];
                
                // Skip if we already have it
                if (Domain::where('domain', $domainName)->exists()) {
                    $this->line("Skipping existing domain: {$domainName}");
                    $skippedDomains++;
                    continue;
                }

                // Convert expiry date to timestamp
                $expTimestamp = strtotime($domainData['expireDate']);
                
                // In dry-run mode, just show what would happen
                if ($this->option('dry-run')) {
                    $this->info("Would add domain: {$domainName}");
                    $this->line("  Expiry: " . date('Y-m-d', $expTimestamp));
                    $this->line("  Registrar: porkbun");
                    $newDomains++;
                    continue;
                }

                // Actually add the domain to our database
                Domain::create([
                    'domain' => $domainName,
                    'exp_date' => $expTimestamp,
                    'registrar' => 'porkbun'
                ]);
                
                $newDomains++;
                Log::info("Added new domain: {$domainName}");
            }

            // Show the final results
            $message = ($this->option('dry-run') ? "[DRY RUN] " : "") .
                      "Process completed. " .
                      "Total domains: {$totalDomains}, " .
                      "New: {$newDomains}, " .
                      "Skipped: {$skippedDomains}";
            
            Log::info($message);
            $this->info($message);
            
        } catch (\Exception $e) {
            Log::error('Error processing Porkbun domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
} 
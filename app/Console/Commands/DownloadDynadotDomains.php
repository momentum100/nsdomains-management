<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadDynadotDomains extends Command
{
    protected $signature = 'domains:download-dynadot';
    protected $description = 'Download domains from Dynadot API';

    public function handle()
    {
        // Start logging the process
        Log::info('Starting Dynadot domains download');
        
        $apiKey = config('services.dynadot.api_key');
        
        if (!$apiKey) {
            Log::error('Dynadot API key not found');
            $this->error('Dynadot API key not found. Please check your .env file.');
            return 1;
        }

        $this->info('Downloading domains from Dynadot...');

        try {
            $response = Http::withoutVerifying()
                ->get('https://api.dynadot.com/api3.json', [
                    'key' => $apiKey,
                    'command' => 'list_domain',
                ]);

            if (!$response->successful()) {
                Log::error('Failed to fetch domains from Dynadot API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                $this->error('Failed to fetch domains from Dynadot API');
                return 1;
            }

            $data = $response->json();
            
            // Initialize counters
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;

            // Check if we have domains in the response
            if (!isset($data['ListDomainInfoResponse']['MainDomains'])) {
                Log::warning('No domains found in API response');
                $this->warn('No domains found in API response');
                return 1;
            }

            $domains = $data['ListDomainInfoResponse']['MainDomains'];
            $totalDomains = count($domains);
            
            $this->info("Processing {$totalDomains} domains...");
            
            // Process each domain
            foreach ($domains as $domainData) {
                // Convert milliseconds timestamp to Unix timestamp
                $expTimestamp = floor($domainData['Expiration'] / 1000);
                
                $domainName = $domainData['Name'];
                
                // Skip if domain already exists
                if (Domain::where('domain', $domainName)->exists()) {
                    $skippedDomains++;
                    continue;
                }

                // Create new domain
                Domain::create([
                    'domain' => $domainName,
                    'exp_date' => $expTimestamp,
                    'registrar' => 'dynadot'
                ]);
                
                $newDomains++;
                Log::info("Added new domain: {$domainName}");
            }

            // Log final statistics
            $message = "Process completed. " .
                      "Total domains: {$totalDomains}, " .
                      "New: {$newDomains}, " .
                      "Skipped: {$skippedDomains}";
            
            Log::info($message);
            $this->info($message);
            
        } catch (\Exception $e) {
            Log::error('Error processing Dynadot domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}

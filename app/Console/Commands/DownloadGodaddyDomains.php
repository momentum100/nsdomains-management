<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadGodaddyDomains extends Command
{
    // Command signature and description for artisan
    protected $signature = 'domains:download-godaddy {--dry-run : Run without saving to database}';
    protected $description = 'Download domains from GoDaddy API';

    // Base URL for GoDaddy API
    protected $apiUrl = 'https://api.godaddy.com/v1';

    public function handle()
    {
        // Start logging the process
        Log::info('Starting GoDaddy domains download' . ($this->option('dry-run') ? ' (DRY RUN)' : ''));
        
        // Get API credentials from .env
        $apiKey = config('services.godaddy.api_key');
        
        // Validate API key exists
        if (!$apiKey) {
            Log::error('GoDaddy API key not found');
            $this->error('GoDaddy API key not found. Please check your .env file.');
            return 1;
        }

        $this->info('Downloading domains from GoDaddy...');

        try {
            // Initialize counters for statistics
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;

            // Make API request with authentication and status filter
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => "sso-key {$apiKey}",
                    'Accept' => 'application/json'
                ])
                ->get("{$this->apiUrl}/domains", [
                    'statuses' => 'ACTIVE',  // Filter for active domains only
                    'limit' => 1000          // Add limit to ensure we get all domains
                ]);

            Log::info('API Request URL:', [
                'url' => "{$this->apiUrl}/domains",
                'params' => ['statuses' => 'ACTIVE', 'limit' => 1000]
            ]);

            if (!$response->successful()) {
                $errorDetails = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ];
                
                Log::error('Failed to fetch domains from GoDaddy API', $errorDetails);
                $this->error('Failed to fetch domains from GoDaddy API');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
                $this->error('Headers: ' . json_encode($response->headers()));
                return 1;
            }

            // Add raw response logging
            $domains = $response->json();
            Log::info('Raw API Response (ACTIVE domains only):', ['response' => json_encode($domains, JSON_PRETTY_PRINT)]);
            $this->line('Raw API Response (ACTIVE domains only):');
            $this->line(json_encode($domains, JSON_PRETTY_PRINT));
            
            $totalDomains = count($domains);
            
            // Log the total number of active domains found
            $this->info("Processing {$totalDomains} active domains...");

            // Add sample domain structure logging
            if (!empty($domains)) {
                Log::info('Sample domain structure:', ['first_domain' => json_encode($domains[0], JSON_PRETTY_PRINT)]);
                $this->line('Sample domain structure:');
                $this->line(json_encode($domains[0], JSON_PRETTY_PRINT));
            }

            // Process each domain from the response
            foreach ($domains as $domainData) {
                $domainName = $domainData['domain'];
                
                // Skip if domain already exists in our database
                if (Domain::where('domain', $domainName)->exists()) {
                    $this->line("Skipping existing domain: {$domainName}");
                    $skippedDomains++;
                    continue;
                }

                // Convert expiry date to timestamp
                $expTimestamp = strtotime($domainData['expires']);
                
                // In dry-run mode, just show what would happen
                if ($this->option('dry-run')) {
                    $this->info("Would add domain: {$domainName}");
                    $this->line("  Expiry: " . date('Y-m-d', $expTimestamp));
                    $this->line("  Registrar: godaddy");
                    $newDomains++;
                    continue;
                }

                // Create new domain record if not in dry-run mode
                Domain::create([
                    'domain' => $domainName,
                    'exp_date' => $expTimestamp,
                    'registrar' => 'godaddy'
                ]);
                
                $newDomains++;
                Log::info("Added new domain: {$domainName}");
            }

            // Update final message to indicate dry run
            $message = ($this->option('dry-run') ? "[DRY RUN] " : "") .
                      "Process completed. " .
                      "Total domains: {$totalDomains}, " .
                      "New: {$newDomains}, " .
                      "Skipped: {$skippedDomains}";
            
            Log::info($message);
            $this->info($message);
            
        } catch (\Exception $e) {
            Log::error('Error processing GoDaddy domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
} 
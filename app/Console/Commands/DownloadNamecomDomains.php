<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadNamecomDomains extends Command
{
    // Command signature and description for artisan
    protected $signature = 'domains:download-namecom {--dry-run : Run without saving to database}';
    protected $description = 'Download domains from Name.com API';

    // Base URL for Name.com API
    protected $apiUrl = 'https://api.name.com/v4';

    public function handle()
    {
        // Start logging the process with debug info
        Log::info('Starting Name.com domains download', [
            'dry_run' => $this->option('dry-run'),
            'environment' => app()->environment()
        ]);
        
        // Get API credentials from .env
        $apiKey = config('services.namecom.api_key');
        
        // Log masked API key for debugging
        Log::info('API Key Check', [
            'key_exists' => !empty($apiKey),
            'key_length' => strlen($apiKey),
            'key_last_4' => !empty($apiKey) ? '****' . substr($apiKey, -4) : 'EMPTY',
            'config_path' => 'services.namecom.api_key'
        ]);
        
        // Enhanced API key validation
        if (empty($apiKey)) {
            Log::error('Name.com API key not found or empty');
            $this->error('Name.com API key not found. Please check your .env file.');
            return 1;
        }

        $this->info('Downloading domains from Name.com...');

        try {
            // Initialize counters for statistics
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;
            
            // Make API request with Basic Auth and skip SSL verification
            $response = Http::withoutVerifying()
                ->withBasicAuth('joebravo2007@gmail.com', $apiKey)
                ->get("{$this->apiUrl}/domains", [
                    'page' => 1,
                    'perPage' => 1000
                ]);

            // Log the request details (safely)
            Log::info('API Request Details', [
                'url' => "{$this->apiUrl}/domains",
                'auth_type' => 'Basic Auth',
                'params' => ['page' => 1, 'perPage' => 1000]
            ]);

            if (!$response->successful()) {
                $errorDetails = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ];
                
                Log::error('Failed to fetch domains from Name.com API', $errorDetails);
                $this->error('Failed to fetch domains from Name.com API');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
                return 1;
            }

            // Add raw response logging
            $responseData = $response->json();
            $domains = $responseData['domains'] ?? [];
            
            Log::info('Raw API Response:', ['response' => json_encode($domains, JSON_PRETTY_PRINT)]);
            $this->line('Raw API Response:');
            $this->line(json_encode($domains, JSON_PRETTY_PRINT));
            
            $totalDomains = count($domains);
            
            // Log the total number of domains found
            $this->info("Processing {$totalDomains} domains...");

            // Add sample domain structure logging
            if (!empty($domains)) {
                Log::info('Sample domain structure:', ['first_domain' => json_encode($domains[0], JSON_PRETTY_PRINT)]);
                $this->line('Sample domain structure:');
                $this->line(json_encode($domains[0], JSON_PRETTY_PRINT));
            }

            // Process each domain from the response
            foreach ($domains as $domainData) {
                $domainName = $domainData['domainName'];
                
                // Skip if domain already exists in our database
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
                    $this->line("  Registrar: namecom");
                    $newDomains++;
                    continue;
                }

                // Create new domain record if not in dry-run mode
                Domain::create([
                    'domain' => $domainName,
                    'exp_date' => $expTimestamp,
                    'registrar' => 'namecom'
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
            Log::error('Error processing Name.com domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
} 
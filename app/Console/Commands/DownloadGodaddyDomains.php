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
        // Start logging the process with more debug info
        Log::info('Starting GoDaddy domains download', [
            'dry_run' => $this->option('dry-run'),
            'environment' => app()->environment()
        ]);
        
        // Get API credentials from .env with detailed logging
        $apiKey = config('services.godaddy.api_key');
        
        // Log masked API key for debugging
        Log::info('API Key Check', [
            'key_exists' => !empty($apiKey),
            'key_length' => strlen($apiKey),
            'key_last_4' => !empty($apiKey) ? '****' . substr($apiKey, -4) : 'EMPTY',
            'config_path' => 'services.godaddy.api_key'
        ]);
        
        // Enhanced API key validation
        if (empty($apiKey)) {
            Log::error('GoDaddy API key not found or empty');
            $this->error('GoDaddy API key not found. Please check your .env file.');
            return 1;
        }

        // Validate API key format (should contain : for key:secret format)
        if (!str_contains($apiKey, ':')) {
            Log::error('GoDaddy API key appears to be malformed - missing key:secret format');
            $this->error('GoDaddy API key should be in format "key:secret"');
            return 1;
        }

        $this->info('Downloading domains from GoDaddy...');

        try {
            // Initialize counters for statistics
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;

            // Properly format the API key for GoDaddy
            // The format should be exactly: sso-key KEY:SECRET (no spaces in KEY:SECRET)
            $apiKeyTrimmed = trim($apiKey); // Remove any accidental whitespace
            
            // Log the API key format (safely)
            Log::info('API Key Format Check', [
                'contains_spaces' => str_contains($apiKeyTrimmed, ' '),
                'key_parts_count' => count(explode(':', $apiKeyTrimmed)),
                'total_length' => strlen($apiKeyTrimmed),
                'starts_with_spaces' => $apiKeyTrimmed !== $apiKey
            ]);

            // Make API request with proper header format
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'sso-key ' . $apiKeyTrimmed, // Note the space after sso-key
                    'Accept' => 'application/json'
                ])
                ->get("{$this->apiUrl}/domains", [
                    'statuses' => 'ACTIVE',
                    'limit' => 1000
                ]);

            // Log the request details (safely)
            Log::info('API Request Details', [
                'url' => "{$this->apiUrl}/domains",
                'auth_header_format' => 'sso-key ****' . substr($apiKeyTrimmed, -4),
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
<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadSpaceshipDomains extends Command
{
    // Hey, I'm the command that downloads domains from Spaceship.com!
    protected $signature = 'domains:download-spaceship {--dry-run : Run without saving to database}';
    protected $description = 'Download domains from Spaceship.com API';

    // Base URL for Spaceship API - updated to match docs
    protected $apiUrl = 'https://spaceship.dev/api/v1';

    public function handle()
    {
        // Let's start by logging what we're doing
        Log::info('Starting Spaceship domains download', [
            'dry_run' => $this->option('dry-run'),
            'environment' => app()->environment()
        ]);

        // Get our API credentials - we need both key and secret
        $apiKey = config('services.spaceship.api_key');
        $apiSecret = config('services.spaceship.api_secret');

        // Log that we got the credentials (but don't show them!)
        Log::info('API Credentials Check', [
            'key_exists' => !empty($apiKey),
            'secret_exists' => !empty($apiSecret),
            'key_last_4' => !empty($apiKey) ? '****' . substr($apiKey, -4) : 'EMPTY',
            'secret_last_4' => !empty($apiSecret) ? '****' . substr($apiSecret, -4) : 'EMPTY'
        ]);

        // Make sure we have both credentials
        if (empty($apiKey) || empty($apiSecret)) {
            Log::error('Spaceship API credentials missing');
            $this->error('Spaceship API credentials not found. Please check SPACESHIP_API_KEY and SPACESHIP_API_SECRET in your .env file.');
            return 1;
        }

        $this->info('Downloading domains from Spaceship...');

        try {
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;
            $page = 0;
            $hasMorePages = true;

            while ($hasMorePages) {
                Log::info('Fetching page', ['page' => $page]);

                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'X-API-Key' => $apiKey,
                        'X-API-Secret' => $apiSecret,
                    ])
                    ->get("{$this->apiUrl}/domains", [
                        'take' => 100,
                        'skip' => $page * 100,
                        'orderBy' => ['name']
                    ]);

                // Log what we tried to do
                Log::info('API Request Details', [
                    'url' => "{$this->apiUrl}/domains",
                    'auth_type' => 'X-API headers',
                    'params' => [
                        'take' => 100,
                        'skip' => $page * 100,
                        'orderBy' => ['name']
                    ]
                ]);

                // If something went wrong, let's handle it
                if (!$response->successful()) {
                    $errorDetails = [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'headers' => $response->headers()
                    ];
                    
                    Log::error('Failed to fetch domains from Spaceship API', $errorDetails);
                    $this->error('Failed to fetch domains from Spaceship API');
                    $this->error('Status: ' . $response->status());
                    $this->error('Response: ' . $response->body());
                    return 1;
                }

                // Debug: Show complete API response
                $this->info('Complete API Response:');
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT));
                
                // Update to use 'items' instead of 'domains'
                $domains = $response->json()['items'] ?? [];
                $currentPageCount = count($domains);
                
                Log::info('Retrieved domains for page', [
                    'page' => $page,
                    'count' => $currentPageCount
                ]);

                foreach ($domains as $domainData) {
                    $domainName = $domainData['name'] ?? null;
                    
                    if (!$domainName) {
                        Log::warning('Domain name missing in response', ['data' => $domainData]);
                        continue;
                    }

                    // Skip existing domains
                    if (Domain::where('domain', $domainName)->exists()) {
                        $this->line("Skipping existing domain: {$domainName}");
                        $skippedDomains++;
                        continue;
                    }

                    // Convert expiry date to timestamp
                    $expTimestamp = strtotime($domainData['expirationDate'] ?? 'now');
                    $regTimestamp = strtotime($domainData['registrationDate'] ?? 'now');

                    // Log detailed domain info
                    Log::info('Processing domain', [
                        'name' => $domainName,
                        'isPremium' => $domainData['isPremium'] ?? false,
                        'autoRenew' => $domainData['autoRenew'] ?? false,
                        'lifecycle' => $domainData['lifecycleStatus'] ?? 'unknown',
                        'verification' => $domainData['verificationStatus'] ?? 'unknown',
                        'nameservers' => $domainData['nameservers']['hosts'] ?? []
                    ]);

                    if ($this->option('dry-run')) {
                        $this->info("Would add domain: {$domainName}");
                        $this->line("  Registration: " . date('Y-m-d', $regTimestamp));
                        $this->line("  Expiry: " . date('Y-m-d', $expTimestamp));
                        $this->line("  Registrar: spaceship");
                        $this->line("  Auto Renew: " . ($domainData['autoRenew'] ? 'Yes' : 'No'));
                        $newDomains++;
                        continue;
                    }

                    // Save with additional fields
                    Domain::create([
                        'domain' => $domainName,
                        'exp_date' => $expTimestamp,
                        'reg_date' => $regTimestamp,
                        'registrar' => 'spaceship',
                        'auto_renew' => $domainData['autoRenew'] ?? false,
                        'status' => $domainData['lifecycleStatus'] ?? 'registered',
                        'nameservers' => json_encode($domainData['nameservers']['hosts'] ?? [])
                    ]);
                    
                    $newDomains++;
                    Log::info("Added new domain: {$domainName}");
                }

                $totalDomains += $currentPageCount;
                
                // Check if we should continue pagination
                $hasMorePages = $currentPageCount >= 100;
                $page++;

                Log::info('Page processing complete', [
                    'current_page' => $page,
                    'has_more' => $hasMorePages,
                    'total_so_far' => $totalDomains
                ]);
            }

            // Tell everyone what we did
            $message = ($this->option('dry-run') ? "[DRY RUN] " : "") .
                      "Process completed. " .
                      "Total domains: {$totalDomains}, " .
                      "New: {$newDomains}, " .
                      "Skipped: {$skippedDomains}";
            
            Log::info($message);
            $this->info($message);
            
        } catch (\Exception $e) {
            Log::error('Error processing Spaceship domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
} 
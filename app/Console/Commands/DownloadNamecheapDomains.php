<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadNamecheapDomains extends Command
{
    // Hey, I'm the command that downloads domains from Namecheap! 
    protected $signature = 'domains:download-namecheap {--dry-run : Run without saving to database}';
    protected $description = 'Download domains from Namecheap API';

    // Base URL for Namecheap API - they use a different URL for production vs testing
    protected $apiUrl = 'https://api.namecheap.com/xml.response';

    public function handle()
    {
        // Start our journey! 🚀
        Log::info('Starting Namecheap domains download', [
            'dry_run' => $this->option('dry-run'),
            'environment' => app()->environment()
        ]);
        
        // Get our secret API key
        $apiKey = config('services.namecheap.api_key');
        
        // Log the API key info (but keep it secret! 🤫)
        Log::info('API Key Check', [
            'key_exists' => !empty($apiKey),
            'key_length' => strlen($apiKey),
            'key_last_4' => !empty($apiKey) ? '****' . substr($apiKey, -4) : 'EMPTY'
        ]);
        
        // Make sure we have an API key
        if (empty($apiKey)) {
            Log::error('Namecheap API key not found or empty');
            $this->error('Namecheap API key not found. Please check your .env file.');
            return 1;
        }

        $this->info('Downloading domains from Namecheap...');

        try {
            // Keep track of what we're doing
            $totalDomains = 0;
            $newDomains = 0;
            $skippedDomains = 0;

            // Namecheap needs these parameters for the API call
            $params = [
                'ApiUser' => config('services.namecheap.username', 'your_username'),
                'ApiKey' => $apiKey,
                'UserName' => config('services.namecheap.username', 'your_username'),
                'Command' => 'namecheap.domains.getList',
                'ClientIp' => config('services.namecheap.client_ip', '127.0.0.1'),
                'PageSize' => 100,  // Get 100 domains at a time
                'Page' => 1
            ];

            // Log what we're about to do
            Log::info('API Request Details', [
                'url' => $this->apiUrl,
                'params' => array_merge($params, ['ApiKey' => '****' . substr($apiKey, -4)])
            ]);

            // Make the API call - notice we're not verifying SSL (but be careful with this!)
            $response = Http::withoutVerifying()
                ->get($this->apiUrl, $params);

            if (!$response->successful()) {
                $errorDetails = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ];
                
                Log::error('Failed to fetch domains from Namecheap API', $errorDetails);
                $this->error('Failed to fetch domains from Namecheap API');
                return 1;
            }

            // Namecheap returns XML, so we need to convert it
            $xml = simplexml_load_string($response->body());
            $domains = $xml->CommandResponse->DomainGetListResult->Domain ?? [];

            // Log what we got back
            Log::info('Retrieved domains count:', ['count' => count($domains)]);
            
            foreach ($domains as $domain) {
                $domainName = (string)$domain['Name'];
                $totalDomains++;
                
                // Skip domains we already know about
                if (Domain::where('domain', $domainName)->exists()) {
                    $this->line("Skipping existing domain: {$domainName}");
                    $skippedDomains++;
                    continue;
                }

                // Convert expiry date to timestamp
                $expTimestamp = strtotime((string)$domain['Expires']);
                
                // If we're just practicing (dry run), don't actually save anything
                if ($this->option('dry-run')) {
                    $this->info("Would add domain: {$domainName}");
                    $this->line("  Expiry: " . date('Y-m-d', $expTimestamp));
                    $this->line("  Registrar: namecheap");
                    $newDomains++;
                    continue;
                }

                // Save the domain for real!
                Domain::create([
                    'domain' => $domainName,
                    'exp_date' => $expTimestamp,
                    'registrar' => 'namecheap'
                ]);
                
                $newDomains++;
                Log::info("Added new domain: {$domainName}");
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
            Log::error('Error processing Namecheap domains: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
} 
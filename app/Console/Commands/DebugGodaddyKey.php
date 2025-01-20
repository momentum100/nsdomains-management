<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugGodaddyKey extends Command
{
    protected $signature = 'debug:godaddy-key';
    protected $description = 'Debug GoDaddy API key format';

    public function handle()
    {
        $apiKey = config('services.godaddy.api_key');
        
        // Log detailed information about the key
        $this->info('API Key Details:');
        $this->line('Length: ' . strlen($apiKey));
        $this->line('Contains colon: ' . (str_contains($apiKey, ':') ? 'Yes' : 'No'));
        $this->line('Contains spaces: ' . (str_contains($apiKey, ' ') ? 'Yes' : 'No'));
        $this->line('Raw characters (each character separated by space):');
        $this->line(implode(' ', str_split($apiKey)));
        
        // Show ASCII values to detect hidden characters
        $this->line('ASCII values:');
        $this->line(implode(' ', array_map('ord', str_split($apiKey))));
        
        // Log to file for reference
        Log::info('GoDaddy API Key Debug', [
            'length' => strlen($apiKey),
            'contains_colon' => str_contains($apiKey, ':'),
            'contains_spaces' => str_contains($apiKey, ' '),
            'last_4_chars' => substr($apiKey, -4),
            'ascii_values' => implode(' ', array_map('ord', str_split($apiKey)))
        ]);
    }
} 
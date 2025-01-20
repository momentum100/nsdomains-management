<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Finder\Finder;

class DownloadAllDomains extends Command
{
    // Hey! This command will run all Download* commands in the app/Console/Commands directory
    protected $signature = 'domains:download-all';
    protected $description = 'Run all domain download commands';

    public function handle()
    {
        // Start logging our journey!
        Log::info('Starting to run all domain download commands');
        
        // Find all Download* commands in the Commands directory
        $finder = new Finder();
        $finder->files()
               ->in(app_path('Console/Commands'))
               ->name('Download*.php');
        
        // Initialize our counters and collectors
        $totalCommands = 0;
        $successfulCommands = 0;
        $failedCommands = [];
        $commandOutputs = []; // New array to store all outputs
        $processCompletedLines = []; // Initialize collector for Process completed lines
        
        $this->info('Searching for download commands...');
        
        $commands = [];
        foreach ($finder as $file) {
            // Get command name from filename (remove .php and convert to kebab-case)
            $className = $file->getBasename('.php');
            // Skip ourselves to avoid infinite loop
            if ($className === 'DownloadAllDomains') {
                continue;
            }
            
            // Convert DownloadSpaceshipDomains to domains:download-spaceship
            $commandName = 'domains:download-' . strtolower(preg_replace(
                ['/([a-z])([A-Z])/', '/^Download/', '/Domains$/', '/-$/'],
                ['$1-$2', '', '', ''],
                $className
            ));
            
            // Clean up any double hyphens and trailing hyphens
            $commandName = rtrim(str_replace('--', '-', $commandName), '-');
            
            $commands[] = $commandName;
            $totalCommands++;
        }
        
        if ($totalCommands === 0) {
            $message = 'No download commands found!';
            Log::warning($message);
            $this->warn($message);
            return 1;
        }
        
        $this->info("Found {$totalCommands} download commands to execute");
        
        // Run each command and collect output
        foreach ($commands as $index => $command) {
            $commandNumber = $index + 1;
            Log::info("Executing command {$commandNumber}/{$totalCommands}: {$command}");
            $this->info("\n[{$commandNumber}/{$totalCommands}] Running {$command}...");
            
            try {
                // Run the command and capture its exit code
                $startTime = microtime(true);
                $exitCode = Artisan::call($command);
                $executionTime = round(microtime(true) - $startTime, 2);
                
                // Get the output from the command
                $output = trim(Artisan::output());
                
                // Extract "Process completed" line if exists
                if (preg_match('/Process completed.*$/m', $output, $matches)) {
                    // Add command name before the Process completed line
                    $processCompletedLines[] = "[{$command}] " . $matches[0];
                }
                
                // Store the command results
                $commandOutputs[$command] = [
                    'status' => $exitCode === 0 ? 'SUCCESS' : 'FAILED',
                    'execution_time' => $executionTime,
                    'output' => $output,
                    'exit_code' => $exitCode
                ];
                
                if ($exitCode === 0) {
                    $successfulCommands++;
                    Log::info("Successfully executed {$command} in {$executionTime}s");
                } else {
                    $failedCommands[] = $command;
                    Log::error("Command {$command} failed with exit code {$exitCode} after {$executionTime}s");
                    Log::error("Output: {$output}");
                }
                
            } catch (\Exception $e) {
                $failedCommands[] = $command;
                $commandOutputs[$command] = [
                    'status' => 'ERROR',
                    'execution_time' => 0,
                    'output' => $e->getMessage(),
                    'exit_code' => -1
                ];
                Log::error("Exception running {$command}: " . $e->getMessage());
            }
        }
        
        // Display detailed summary
        $this->newLine(2);
        $this->info('=== EXECUTION SUMMARY ===');
        foreach ($commandOutputs as $command => $result) {
            $this->info("\n🔹 {$command}");
            $this->info("Status: {$result['status']}");
            $this->info("Time: {$result['execution_time']}s");
            $this->info("Exit Code: {$result['exit_code']}");
            $this->info("Output:");
            $this->line($result['output']);
            $this->line(str_repeat('-', 50));
        }
        
        // Show final statistics
        $message = sprintf(
            "\n📊 FINAL STATISTICS:\n" .
            "Total commands: %d\n" .
            "Successful: %d\n" .
            "Failed: %d",
            $totalCommands,
            $successfulCommands,
            count($failedCommands)
        );
        
        Log::info($message);
        $this->info($message);
        
        if (!empty($failedCommands)) {
            $this->error("❌ Failed commands: " . implode(', ', $failedCommands));
        }
        
        // Send Process completed lines to Telegram if we have any
        if (!empty($processCompletedLines)) {
            $telegramMessage = "Domain Download Summary:\n\n" . implode("\n", $processCompletedLines);
            
            // Initialize cURL
            $curl = curl_init();
            
            // Set cURL options
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.telegram.org/bot1790397670:AAFsAoJ5Qju9V4phdq5cflhJF938uS7t2QI/sendMessage',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => false, // As per your requirements to not verify SSL
                CURLOPT_POSTFIELDS => [
                    'chat_id' => '-4188687896',
                    'text' => $telegramMessage
                ]
            ]);
            
            // Execute the request
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            // Check for errors
            if ($response === false || $httpCode !== 200) {
                $error = curl_error($curl);
                Log::error('Failed to send message to Telegram: ' . $error);
                $this->error('Failed to send message to Telegram: ' . $error);
            } else {
                Log::info('Successfully sent summary to Telegram');
                $this->info('Successfully sent summary to Telegram');
            }
            
            // Close cURL session
            curl_close($curl);
        }
        
        return count($failedCommands) === 0 ? 0 : 1;
    }
} 
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
        
        // Keep track of what we're doing
        $totalCommands = 0;
        $successfulCommands = 0;
        $failedCommands = [];
        
        // Show a nice progress bar
        $this->info('Searching for download commands...');
        
        $commands = [];
        foreach ($finder as $file) {
            // Get command name from filename (remove .php and convert to kebab-case)
            $className = $file->getBasename('.php');
            // Skip ourselves to avoid infinite loop
            if ($className === 'DownloadAllDomains') {
                continue;
            }
            
            // Convert DownloadDynadotDomains to domains:download-dynadot
            $commandName = 'domains:' . strtolower(preg_replace(
                ['/([a-z])([A-Z])/', '/Download/', '/Domains$/'],
                ['$1-$2', '', ''],
                $className
            ));
            
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
        
        // Create a progress bar
        $progress = $this->output->createProgressBar($totalCommands);
        $progress->start();
        
        // Run each command
        foreach ($commands as $command) {
            Log::info("Executing command: {$command}");
            $this->newLine();
            $this->info("Running {$command}...");
            
            try {
                // Run the command and capture its exit code
                $exitCode = Artisan::call($command);
                
                // Get the output from the command
                $output = Artisan::output();
                
                if ($exitCode === 0) {
                    $successfulCommands++;
                    Log::info("Successfully executed {$command}");
                } else {
                    $failedCommands[] = $command;
                    Log::error("Command {$command} failed with exit code {$exitCode}");
                    Log::error("Output: " . trim($output));
                }
                
            } catch (\Exception $e) {
                $failedCommands[] = $command;
                Log::error("Exception running {$command}: " . $e->getMessage());
            }
            
            $progress->advance();
        }
        
        $progress->finish();
        
        // Show final results
        $this->newLine(2);
        $message = sprintf(
            "Finished running all commands!\n" .
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
            $this->error("Failed commands: " . implode(', ', $failedCommands));
        }
        
        return count($failedCommands) === 0 ? 0 : 1;
    }
} 
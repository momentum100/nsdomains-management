<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run domains:download-all every 3 hours
        $schedule->command('domains:download-all')
                ->everyThreeHours()
                ->withoutOverlapping() // Prevents running if previous instance is still running
                ->runInBackground()    // Runs in background to prevent blocking
                ->appendOutputTo(storage_path('logs/domains-download-all.log')); // Logs output to file
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        Commands\DownloadDynadotDomains::class,
    ];
}

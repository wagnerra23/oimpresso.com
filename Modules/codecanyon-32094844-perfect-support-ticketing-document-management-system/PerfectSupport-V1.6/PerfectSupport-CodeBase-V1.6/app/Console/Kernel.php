<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $env = config('app.env');
        $email = config('mail.username');

        if ($env === 'demo' && !empty($email)) {
            //IMPORTANT NOTE: This command will delete all data and create dummy business, run only in demo server.
            $schedule->command('support:dummy')
                    ->cron('0 */4 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }

        if ($env != 'demo') {

            $schedule->command('support:remind-ticket')
                ->dailyAt('1:00');
                
            $schedule->command('support:close-ticket')
                ->daily();

            $schedule->command('backup:clean')
                ->daily();
                
            $schedule->command('backup:run')
                ->daily();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
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

        if ($env === 'live') {
            //Scheduling backup, specify the time when the backup will get cleaned & time when it will run.
            
            $schedule->command('backup:clean')->daily()->at('01:00');
            $schedule->command('backup:run')->daily()->at('01:30');


            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

        }

        // MemCofre — sincroniza memória Claude pra dentro do repo todo dia 23:00
        $schedule->command('docvault:sync-memories')
            ->dailyAt('23:00')
            ->withoutOverlapping()
            ->environments(['local', 'live']);

        // MEM-MET-3 (ADRs 0050+0051) — apura 8 métricas obrigatórias + 3 RAGAS
        // por business + plataforma, gravando 1 linha/dia em
        // copiloto_memoria_metricas via upsert idempotente.
        // Roda 23:55 pra fechar o dia (após scout:import e antes da rotação de log).
        $schedule->command('copiloto:metrics:apurar --business=all')
            ->dailyAt('23:55')
            ->withoutOverlapping()
            ->environments(['local', 'live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule MEM-MET-3 (copiloto:metrics:apurar --business=all) FALHOU'
                );
            });

        if ($env === 'demo') {
            //IMPORTANT NOTE: This command will delete all business details and create dummy business, run only in demo server.
            $schedule->command('pos:dummyBusiness')
                    ->cron('0 */3 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}

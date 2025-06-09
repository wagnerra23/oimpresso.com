<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\System;
use Carbon\Carbon;
use App\Ticket;

class AutoCloseTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:close-ticket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close ticket automatically after given day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $auto_close_ticket_in_days = System::getProperty('auto_close_ticket_in_days');

        if ($auto_close_ticket_in_days > 0) {
            
            $tickets = Ticket::where('status', '!=', 'closed')
                        ->select('id', 'updated_at')
                        ->get();

            $now = Carbon::now();

            foreach ($tickets as $key => $ticket) {

                $diff_in_days = $now->diffInDays($ticket->updated_at);
                
                if ($diff_in_days >= $auto_close_ticket_in_days) {
                    Ticket::where('id', $ticket->id)
                        ->update(['status' => 'closed']);
                }
            }
        }
    }
}

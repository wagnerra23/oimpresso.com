<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\System;
use Carbon\Carbon;
use App\Ticket;
Use Illuminate\Support\Facades\DB;
use Notification;
use App\Notifications\SupportAgent\TicketReminderNotification;
use App\Http\Util\CommonUtil;
class TicketReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:remind-ticket';

    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send tickets reminder to support agents automatically after given day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CommonUtil $commonUtil)
    {
        parent::__construct();
        $this->commonUtil = $commonUtil;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $remind_ticket_in_days = System::getProperty('remind_ticket_in_days');

        if ($remind_ticket_in_days > 0) {

            $today = Carbon::today()->toDateTimeString();

            $tickets = Ticket::with(['supportAgents'])
                        ->where('status', '!=', 'closed')
                        ->select('id', DB::raw("DATEDIFF('".$today."', ".DB::raw('(SELECT created_at FROM tickets as t where t.id = tickets.id)').") as day_count"), DB::raw('(SELECT created_at FROM tickets as t where t.id = tickets.id) as created_at'), 'subject', 'ticket_ref')
                        ->groupBy('id')
                        ->having('day_count', '=', $remind_ticket_in_days)
                        ->get();

            $this->__sendReminderToSupportAgents($tickets);
        }
    }

    protected function __sendReminderToSupportAgents($tickets)
    {
        $mail_template = System::getProperty('ticket_reminder_mail_template');
        $mail_template = !empty($mail_template) ? json_decode($mail_template, true) : [];
        if(
            (count($tickets) > 0) && 
            isset($mail_template['subject']) && 
            !empty($mail_template['subject']) && 
            isset($mail_template['body']) &&
            !empty($mail_template['body'])
        ) {
            $admins = $this->commonUtil->getUsersByRole('admin');
            foreach ($tickets as $key => $ticket) {
                if(count($ticket->supportAgents) > 0){
                    Notification::send($ticket->supportAgents, new TicketReminderNotification($ticket, $mail_template, $admins->pluck('email')->toArray()));
                }
            }
        }
    }
}

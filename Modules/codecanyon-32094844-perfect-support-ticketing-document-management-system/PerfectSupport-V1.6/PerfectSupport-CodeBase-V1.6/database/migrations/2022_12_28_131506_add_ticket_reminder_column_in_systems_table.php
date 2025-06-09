<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\System;

class AddTicketReminderColumnInSystemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
    		'remind_ticket_in_days' => 0,
            'ticket_reminder_mail_template' => json_encode([
                'subject' => 'Reminder for ticket {ticket_id}',
                'body' => '<div>Hello {agent_name},

                <br><br>
                Please note that ticket with ref num {ticket_id} is still open.
                <br><br>
                
                Subject : {ticket_subject}</div>'
            ])
        ];

        foreach ($data as $key => $value) {
            System::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

<?php

use Illuminate\Database\Seeder;
use App\System;

class SystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {	
    	$datas = [
    		'ticket' => 0,
    		'ticket_prefix' => '',
    		'ticket_instruction' => '',
    		'cust_new_ticket_app_notif' => 0,
    		'cust_new_ticket_mail_notif' => 0,
    		'agent_replied_to_ticket_app_notif' => 0,
    		'agent_replied_to_ticket_mail_notif' => 0,
    		'agent_assigned_ticket_app_notif' => 0,
    		'agent_assigned_ticket_mail_notif' => 0,
    		'cust_replied_to_ticket_app_notif' => 0,
    		'cust_replied_to_ticket_mail_notif' => 0,
            'is_public_ticket_enabled' => 0,
            'default_ticket_type' => 'private'
        ];

        foreach ($datas as $key => $value) {
            System::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

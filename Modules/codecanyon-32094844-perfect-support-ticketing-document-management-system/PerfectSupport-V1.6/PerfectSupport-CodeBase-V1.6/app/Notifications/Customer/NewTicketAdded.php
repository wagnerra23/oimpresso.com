<?php

namespace App\Notifications\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTicketAdded extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public $ticket;
    public $system;
    public function __construct($ticket, $system)
    {
        $this->ticket = $ticket;
        $this->system = $system;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $channels = [];
        if ($this->system['cust_new_ticket_app_notif']) {
            $channels[] = 'database';
        }

        if ($this->system['cust_new_ticket_mail_notif']) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Ticket successfully added with reference '.$this->ticket['ticket_ref'])
            ->greeting('Hello '.$notifiable->name)
            ->line('You have successfully created a ticket with reference number '.$this->ticket['ticket_ref'])
            ->action('Login to view the ticket', url('/login'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket['id']
        ];
    }
}

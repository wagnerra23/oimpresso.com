<?php

namespace App\Notifications\SupportAgent;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRepliedToTicket extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public $ticket;
    public $comment;
    public $system;
    public function __construct($ticket, $comment, $system)
    {
        $this->ticket = $ticket;
        $this->comment = $comment;
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
        if ($this->system['cust_replied_to_ticket_app_notif']) {
            $channels[] = 'database';
        }

        if ($this->system['cust_replied_to_ticket_mail_notif']) {
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
            ->subject('New comment on ticket '.$this->ticket['ticket_ref'])
            ->greeting('Hello '.$notifiable->name)
            ->line($this->ticket->user['name']. ' has commented on ticket '.$this->ticket['ticket_ref'])
            ->line('<br><br><strong>'.$this->ticket['subject'].'</strong><br>')
            ->line($this->comment['comment'])
            ->action('Login to reply the comment', url('/login'));
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
            'ticket_id' => $this->ticket['id'],
            'comment_id' => $this->comment['id'],
            'commented_by' => $this->comment['user_id'],
        ];
    }
}

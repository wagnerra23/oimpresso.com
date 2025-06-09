<?php

namespace App\Notifications\SupportAgent;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public $ticket;
    public $template;
    public $admins;
    public function __construct($ticket, $template, $admins)
    {
        $this->ticket = $ticket;
        $this->template = $template;
        $this->admins = $admins;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $subject = preg_replace(['/{agent_name}/', '/{ticket_subject}/', '/{ticket_id}/'], [$notifiable->name, $this->ticket->subject, $this->ticket->ticket_ref],  $this->template['subject']);

        $body = preg_replace(['/{agent_name}/', '/{ticket_subject}/', '/{ticket_id}/'], [$notifiable->name, $this->ticket->subject, $this->ticket->ticket_ref],  $this->template['body']);

        return (new MailMessage)
            ->cc($this->admins)
            ->greeting('     ')
            ->subject($subject)
            ->line($body)
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
            //
        ];
    }
}

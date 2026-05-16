<?php

namespace Modules\Cms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Jana\Services\Privacy\PiiRedactor;

class NewLeadGeneratedNotification extends Notification
{
    use Queueable;

    protected $lead;

    /**
     * Create a new notification instance.
     *
     * D7.a LGPD — payload do lead (nome/email/mobile) é PII por definição;
     * o mail destinatário é o admin do site (notifiable_email), então o envio
     * é legítimo, mas qualquer log/falha de queue deve passar por PiiRedactor.
     * Ver `memory/requisitos/Cms/PII-REDACTION.md`.
     *
     * @return void
     */
    public function __construct($lead_details)
    {
        $this->lead = $lead_details;
    }

    /**
     * Representação redactada do lead pra log/exception — NÃO pra envio mail.
     * Mail toMail() continua usando $this->lead original (destino legítimo admin).
     */
    public function leadForLog(PiiRedactor $piiRedactor): array
    {
        return $piiRedactor->redactArray((array) $this->lead);
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
        $mail = (new MailMessage)
                ->greeting('Hello!')
                ->subject('New inquiry from '.$this->lead['name'])
                ->line($this->lead['message'])
                ->line('<br> <br> Other details are: <br>')
                ->line('Name: '.$this->lead['name'])
                ->line('Mobile: '.$this->lead['mobile'])
                ->line('Email: '.$this->lead['email']);

        return $mail;
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

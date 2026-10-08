<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail ao contador (playbook Fiscal thread 15b): o link de revisão OU o código de 6 dígitos.
 * Os dois vão para o MESMO endereço — é isso que impede quem recebeu o link encaminhado de aceitar
 * em nome do contador. Corpo inline, sem template.
 */
class RevisaoContadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $empresa,
        public readonly ?string $url = null,
        public readonly ?string $codigo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->codigo !== null
            ? "Código de acesso à revisão fiscal — {$this->empresa}"
            : "Regras fiscais para revisar — {$this->empresa}");
    }

    public function content(): Content
    {
        $linhas = $this->codigo !== null
            ? ['Seu código de acesso à revisão de regras fiscais:', '', "<strong>{$this->codigo}</strong>", '',
                'Vale 15 minutos e serve uma vez só. Se não foi você que abriu o link, ignore este e-mail.']
            : ["{$this->empresa} pediu sua revisão das regras fiscais.", '',
                '<a href="' . e((string) $this->url) . '">Abrir a revisão</a>', '',
                'O link vale 14 dias. Ao abrir, enviamos um código para este e-mail.'];

        return new Content(htmlString: '<p>' . implode('<br>', array_map(
            // Só as duas linhas montadas aqui (código e link) levam HTML; o resto é escapado.
            fn (string $l) => str_starts_with($l, '<') ? $l : e($l),
            $linhas
        )) . '</p>');
    }
}

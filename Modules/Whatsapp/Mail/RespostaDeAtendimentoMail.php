<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

/** Resposta do atendente a uma conversa de e-mail da Caixa Unificada (US-WA-329). Texto simples. */
class RespostaDeAtendimentoMail extends Mailable
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $conversationId,
        public readonly string $assunto,
        public readonly string $corpo,
        public readonly string $idDaMensagem,
    ) {}

    public function build(): self
    {
        return $this->subject($this->assunto)
            ->html(nl2br(e($this->corpo)))
            ->withSymfonyMessage(function (Email $m): void {
                $m->getHeaders()->addIdHeader('Message-ID', $this->idDaMensagem);
            });
    }
}

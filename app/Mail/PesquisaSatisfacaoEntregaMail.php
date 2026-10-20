<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** Pesquisa de satisfação depois da entrega (US-SELL-071 · UC-FLX-25). Texto simples. */
class PesquisaSatisfacaoEntregaMail extends Mailable
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
        public readonly string $corpo,
    ) {}

    public function build(): self
    {
        return $this->subject('Como foi a sua entrega?')
            ->html(nl2br(e($this->corpo)));
    }
}

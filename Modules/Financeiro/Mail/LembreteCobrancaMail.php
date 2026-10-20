<?php

declare(strict_types=1);

namespace Modules\Financeiro\Mail;

use Illuminate\Mail\Mailable;

/** Lembrete de título vencido (US-FIN-071 · UC-FLX-29). Texto simples. */
class LembreteCobrancaMail extends Mailable
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $tituloId,
        public readonly int $etapaDias,
        public readonly string $corpo,
    ) {}

    public function build(): self
    {
        return $this->subject('Lembrete: título vencido há '.$this->etapaDias.' dia(s)')
            ->html(nl2br(e($this->corpo)));
    }
}

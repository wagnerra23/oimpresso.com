<?php

declare(strict_types=1);

namespace Modules\Financeiro\Mail;

use Illuminate\Mail\Mailable;

/** Lembrete da régua: título vencido (US-FIN-071) ou a vencer (`fase = antes`, US-FIN-033). Texto simples. */
class LembreteCobrancaMail extends Mailable
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $tituloId,
        public readonly int $etapaDias,
        public readonly string $corpo,
        public readonly string $fase = 'apos',
    ) {}

    public function build(): self
    {
        $assunto = $this->fase === 'antes'
            ? 'Lembrete: título vence em '.$this->etapaDias.' dia(s)'
            : 'Lembrete: título vencido há '.$this->etapaDias.' dia(s)';

        return $this->subject($assunto)
            ->html(nl2br(e($this->corpo)));
    }
}

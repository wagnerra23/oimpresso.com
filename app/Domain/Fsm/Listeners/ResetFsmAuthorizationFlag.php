<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Listeners;

use App\Domain\Fsm\Support\FsmAuthorizationFlag;

/**
 * Zera o singleton FsmAuthorizationFlag no INÍCIO de cada operação Octane
 * (RequestReceived / TaskReceived / TickReceived), garantindo que uma flag
 * não-consumida de uma operação anterior NÃO vaze pra próxima no mesmo worker.
 *
 * Registrado em config/octane.php. Sob PHP-FPM esse listener nunca dispara
 * (o servidor Octane não roda) — e não precisa: o processo já morre por request.
 *
 * O par no lado de FILA é `Queue::before(...)` em AppServiceProvider.
 *
 * Reset no INÍCIO (não no fim): garante slate limpo mesmo que a operação
 * anterior tenha morrido por exceção antes de qualquer handler de término.
 *
 * @see app/Domain/Fsm/Support/FsmAuthorizationFlag.php
 * @see config/octane.php
 */
class ResetFsmAuthorizationFlag
{
    public function handle(mixed $event = null): void
    {
        FsmAuthorizationFlag::reset();
    }
}

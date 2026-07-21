<?php

declare(strict_types=1);

use App\Domain\Fsm\Listeners\ResetFsmAuthorizationFlag;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Queue;

/**
 * Catraca do parecer do juiz funcao-scorecard (2026-07-21, item #8), confirmado [W].
 *
 * O docblock de FsmAuthorizationFlag PROMETE "per-request scope (reset entre
 * requests)". Sob PHP-FPM/artisan one-shot isso vale por acidente do runtime
 * (o processo morre). Sob Octane worker / Horizon / queue:work PERSISTENTE o
 * `static` NÃO some sozinho → uma flag não-consumida vazaria autorização de
 * transição FSM pro request/job SEGUINTE (bypass do GuardsFsmTransitions).
 *
 * Estes testes DERIVAM DO CONTRATO (a promessa do docblock), não do código:
 * verificam que o mecanismo de reset EXISTE e está LIGADO nos dois runtimes
 * de processo longevo. Diferente dos testes existentes (property + observer),
 * que chamam reset() no beforeEach e por isso MASCARAM exatamente este
 * vazamento — aqui NÃO há beforeEach(reset()); cada cenário usa chave única.
 *
 * @see app/Domain/Fsm/Support/FsmAuthorizationFlag.php
 * @see app/Domain/Fsm/Listeners/ResetFsmAuthorizationFlag.php  (Octane)
 * @see app/Providers/AppServiceProvider.php  (Queue::before)
 * @see memory/proibicoes.md §"REGRA DE PRECEDÊNCIA" (teste verde > docblock)
 */

/** Job trivial que registra, no momento do handle, se a flag do "job anterior" sobreviveu. */
class FsmResetProbeJob implements ShouldQueue
{
    use Dispatchable;

    public static ?bool $flagSobreviveuAteHandle = null;

    public function handle(): void
    {
        // Se Queue::before rodou antes deste handle, a flag já foi zerada → false.
        self::$flagSobreviveuAteHandle = FsmAuthorizationFlag::consume('App\\Transaction', 300);
    }
}

afterEach(fn () => FsmAuthorizationFlag::reset());

it('controle: sem hook de reset, a flag SOBREVIVE no processo (o porquê do fix)', function () {
    FsmAuthorizationFlag::reset();
    FsmAuthorizationFlag::mark('App\\Transaction', 100);

    // Nada reseta entre o mark e o consume — espelha o worker longevo em prod.
    // Este controle-positivo prova que consume() PODE devolver true, então as
    // asserções ->toBeFalse() abaixo têm mordida (não passam por vacuidade).
    expect(FsmAuthorizationFlag::consume('App\\Transaction', 100))
        ->toBeTrue('static persiste no mesmo processo — é o que Octane/Horizon não zera sozinho');
});

it('Octane: ResetFsmAuthorizationFlag zera a flag no início da operação', function () {
    FsmAuthorizationFlag::mark('App\\Transaction', 200);

    // Listener registrado em config/octane.php (RequestReceived/TaskReceived/TickReceived).
    (new ResetFsmAuthorizationFlag)->handle();

    expect(FsmAuthorizationFlag::consume('App\\Transaction', 200))
        ->toBeFalse('listener Octane deveria ter zerado a flag da operação anterior');
});

it('Fila: Queue::before zera a flag antes de cada job (worker longevo)', function () {
    FsmResetProbeJob::$flagSobreviveuAteHandle = null;
    FsmAuthorizationFlag::mark('App\\Transaction', 300); // "job A" marcou e não consumiu (vazou)

    // "job B" no mesmo worker: a fila sync dispara JobProcessing → Queue::before
    // (registrado no AppServiceProvider::boot) roda reset() ANTES do handle.
    Queue::connection('sync')->push(new FsmResetProbeJob);

    expect(FsmResetProbeJob::$flagSobreviveuAteHandle)
        ->toBeFalse('Queue::before deveria ter zerado a flag do job anterior antes do handle');
});

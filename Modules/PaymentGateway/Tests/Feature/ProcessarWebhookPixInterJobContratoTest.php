<?php

declare(strict_types=1);

use Modules\PaymentGateway\Jobs\ProcessarWebhookPixInterJob;

/**
 * US-FIN-032 — contrato de CARGA do job do webhook PIX Inter.
 *
 * Por que este teste existe (e por que ele NAO toca banco):
 *
 * O trait `Illuminate\Bus\Queueable` declara `public $queue;` — sem tipo e sem
 * default. Re-declarar essa property na classe com QUALQUER default viola as
 * regras de composicao de trait do PHP e e FATAL na carga da classe:
 *
 *   "X and Queueable define the same property ($queue) in the composition of X.
 *    However, the definition differs and is considered incompatible."
 *
 * `php -l` NAO pega isso — e erro de composicao em tempo de carga, nao de sintaxe
 * (medido 2026-08-19: rc=0 no arquivo que fatalava). O caminho canonico e setar a
 * fila via `$this->onQueue()` no constructor.
 *
 * Este arquivo e deliberadamente livre de banco e de boot do app: os testes que ja
 * instanciavam o job (InterWebhookTest, Onda26InterWebhookIntegrationTest) montam
 * fixtures no `beforeEach`, entao dependem de DB — e teste que nao roda sai com
 * exit 0 e nao prova nada. Sem `uses(TestCase::class)` aqui, este roda como PHPUnit
 * puro: se a classe voltar a fatalar na carga, ele quebra em qualquer ambiente.
 *
 * @see Modules/NfeBrasil/Jobs/EmitirNfceJob.php — mesma pegadinha, mesmo fix
 */
it('carrega e instancia o job sem colisao de property com o trait Queueable', function () {
    $job = new ProcessarWebhookPixInterJob(10, 1);

    expect($job)->toBeInstanceOf(ProcessarWebhookPixInterJob::class);
});

it('mantem a fila paymentgateway via onQueue no constructor', function () {
    $job = new ProcessarWebhookPixInterJob(10, 1);

    // Comportamento preservado: a fila continua sendo a mesma de antes do fix —
    // o que mudou foi COMO ela e definida (onQueue no constructor, nao property).
    expect($job->queue)->toBe('paymentgateway');
});

it('preserva tries e backoff do contrato ShouldQueue', function () {
    $job = new ProcessarWebhookPixInterJob(10, 1);

    // $tries/$backoff vem do contrato ShouldQueue (nao do trait Queueable),
    // entao continuam como property declarada — sem risco de colisao.
    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(60);
});

it('propaga businessId e interWebhookLogId do constructor (ADR 0093)', function () {
    $job = new ProcessarWebhookPixInterJob(42, 7);

    expect($job->interWebhookLogId)->toBe(42)
        ->and($job->businessId)->toBe(7);
});

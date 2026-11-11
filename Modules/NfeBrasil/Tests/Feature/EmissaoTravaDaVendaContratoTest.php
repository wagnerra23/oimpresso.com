<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lote 1.2 da auditoria do NfeBrasil (N4-16) · CU-NFE-01 (emitir sem emitir duas vezes).
 *
 * Dois workers com a mesma venda passavam juntos pela checagem de emissão existente, que lê
 * sem trava, e cada um pegava um número. Agora a emissão de uma venda tem trava, e o job de
 * NFC-e não entra duas vezes na fila para a mesma venda. Tenant 98 (ADR 0358).
 */
const TRAVA_TX = 998877101;

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Schema do NfeBrasil ausente.');
    }
    Cache::lock(travaChave(TRAVA_TX), 1)->forceRelease();
});

function travaChave(int $tx): string
{
    return 'nfebrasil:emitir:' . (int) test()->seededTenant()->id . ':' . $tx;
}

/** Certificado ausente: a emissão para logo depois da checagem de emissão existente. */
function travaServico(): NfeService
{
    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andThrow(new RuntimeException('sem certificado no teste'));

    return new NfeService($cert);
}

function travaEmitir(?int $tx): string
{
    try {
        travaServico()->emitir((int) test()->seededTenant()->id, array_filter([
            'modelo' => '55', 'transaction_id' => $tx, 'valor_total' => 10,
        ], fn ($v) => $v !== null));
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

it('CU-NFE-01 · venda com emissão em andamento não começa outra', function () {
    $trava = Cache::lock(travaChave(TRAVA_TX), 60);
    expect($trava->get())->toBeTrue();

    $erro = travaEmitir(TRAVA_TX);

    expect($erro)->toContain('já está em andamento');
    expect(NfeEmissao::where('business_id', test()->seededTenant()->id)->where('transaction_id', TRAVA_TX)->count())->toBe(0);
    $trava->release();
});

it('CU-NFE-01 · a trava é solta quando a emissão falha, e a próxima tentativa entra', function () {
    $erro = travaEmitir(TRAVA_TX);

    expect($erro)->toContain('sem certificado');
    expect(Cache::lock(travaChave(TRAVA_TX), 1)->get())->toBeTrue();
});

it('CU-NFE-01 · emissão sem venda não usa a trava de venda', function () {
    $trava = Cache::lock(travaChave(TRAVA_TX), 60);
    $trava->get();

    expect(travaEmitir(null))->toContain('sem certificado');
    $trava->release();
});

it('CU-NFE-01 · duas gravações da mesma venda enfileiram um job de NFC-e só', function () {
    Queue::fake();
    $biz = (int) test()->seededTenant()->id;

    EmitirNfceJob::dispatch($biz, TRAVA_TX);
    EmitirNfceJob::dispatch($biz, TRAVA_TX);
    EmitirNfceJob::dispatch($biz, TRAVA_TX + 1);

    Queue::assertPushed(EmitirNfceJob::class, 2);
    Queue::assertPushed(EmitirNfceJob::class, fn ($job) => $job->transactionId === TRAVA_TX + 1);
});

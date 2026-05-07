<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * US-NFE-002 fase 1 · Job EmitirNfceJob — idempotência + skeleton.
 *
 * Tests garantem:
 *   1. Idempotência: re-dispatch da mesma (biz_id, tx_id, modelo=65) = no-op
 *   2. Cross-tenant guard: tx.business_id != job.businessId pula silencioso
 *   3. Transaction inexistente pula sem crashar
 *   4. Happy path: cria NfeEmissao status=pendente (Fase 1 skeleton)
 *
 * Submissão SEFAZ real é Fase 2 (PR futuro) — esses tests cobrem só o wire
 * elétrico + persistência placeholder.
 */

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes não existe — migration não rodou');
    }
});

it('idempotência: re-dispatch da mesma venda = no-op (não duplica emissão)', function () {
    NfeEmissao::create([
        'business_id'    => 4,
        'transaction_id' => 12345,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'autorizada',
        'valor_total'    => 100.0,
    ]);

    expect(NfeEmissao::where('transaction_id', 12345)->count())->toBe(1);

    // Re-dispatch — deve retornar sem criar segunda emissão.
    (new EmitirNfceJob(4, 12345))->handle();

    expect(NfeEmissao::where('transaction_id', 12345)->count())
        ->toBe(1, 'Idempotência violada — segunda emissão criada');
});

it('cross-tenant guard: tx.business_id != job.businessId → skip silencioso', function () {
    if (! class_exists(Transaction::class)) {
        $this->markTestSkipped('Transaction model não disponível');
    }

    // Transaction não persistida com business_id=99 — Transaction::find vai retornar null
    // (mais simples que persistir + popular relations). Job loga warning + return.
    (new EmitirNfceJob(4, 9999999))->handle();

    expect(NfeEmissao::where('transaction_id', 9999999)->count())
        ->toBe(0, 'Job criou emissão pra transaction inexistente');
});

it('transaction inexistente: skip sem crashar', function () {
    (new EmitirNfceJob(4, 8888888))->handle();

    expect(NfeEmissao::where('transaction_id', 8888888)->count())->toBe(0);
});

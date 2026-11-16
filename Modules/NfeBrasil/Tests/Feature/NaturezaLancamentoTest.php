<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — natureza de operação escolhida ao emitir a NF-e (onda 5a · RUNBOOK-tributacao §Naturezas).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Exceptions\NaturezaDoLancamentoInvalidaException;
use Modules\NfeBrasil\Services\NfeService;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Casos do plano da onda 5 (a natureza escolhida dá a tributação e o natOp; devolução, entrada e nota
 * referenciada têm fluxo próprio; flag desligada = como antes), não do código.
 * MySQL-only · tenant fictício 98 e adversário 99 (ADR 0358).
 */

function nlNatureza(int $biz, array $colunas = []): int
{
    return (int) DB::table('nfe_operacoes_fiscais')->insertGetId(array_merge([
        'business_id' => $biz, 'slug' => 'tela-nl-' . uniqid(), 'nome' => 'Venda de mercadoria', 'finalidade' => 1,
        'tipo' => 'saida', 'ativa' => true, 'pede_nota_referenciada' => false, 'cfop' => '5102', 'cfop_interestadual' => '6102',
        'config_tela' => json_encode(['icms' => ['cst' => '00', 'origem' => 'uf']]), 'padrao' => false,
        'created_at' => now(), 'updated_at' => now(),
    ], $colunas));
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela')) {
        $this->markTestSkipped('MySQL-only: nfe_operacoes_fiscais com as colunas das ondas 2 e 3.');
    }
    $this->biz = $this->seededTenant()->id;
    $this->outra = $this->seededSupportClientTenant()->id;
});

it('UC-NFTR-60 · a emissão aceita só natureza da tela, ativa, de saída e normal, na NF-e de venda comum', function () {
    $svc = app(NfeService::class);
    $ok = nlNatureza($this->biz);
    expect($svc->naturezaDoLancamento($this->biz, $ok, '55', false)->id)->toBe($ok);

    $recusa = fn (int $id, string $modelo = '55', bool $bonif = false, int $biz = 0) => fn () => $svc
        ->naturezaDoLancamento($biz ?: $this->biz, $id, $modelo, $bonif);
    $e = NaturezaDoLancamentoInvalidaException::class;

    expect($recusa(nlNatureza($this->outra)))->toThrow($e, 'não encontrada');
    expect($recusa($ok, '65'))->toThrow($e, 'modelo 55');
    expect($recusa($ok, '55', true))->toThrow($e, 'bonificação');
    expect($recusa(nlNatureza($this->biz, ['config_tela' => null])))->toThrow($e, 'tela de naturezas');
    expect($recusa(nlNatureza($this->biz, ['ativa' => false])))->toThrow($e, 'inativa');
    expect($recusa(nlNatureza($this->biz, ['tipo' => 'entrada'])))->toThrow($e, 'entrada');
    expect($recusa(nlNatureza($this->biz, ['finalidade' => 4])))->toThrow($e, 'finalidade normal');
    expect($recusa(nlNatureza($this->biz, ['pede_nota_referenciada' => true])))->toThrow($e, 'nota referenciada');
});

it('UC-NFTR-61 · o controller passa a natureza ao NfeService só com a flag ligada, e a recusa vira 422', function () {
    $tx = (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => EstoqueFixture::locationId($this->biz), 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'final_total' => 10.00, 'total_before_tax' => 10.00,
        'invoice_no' => 'NL5-' . uniqid(), 'transaction_date' => now(), 'created_by' => EstoqueFixture::userId($this->biz),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $natureza = nlNatureza($this->biz);
    $recebido = [];
    $this->mock(NfeService::class, function ($m) use (&$recebido) {
        $m->shouldReceive('emitirParaTransaction')->andReturnUsing(function (...$args) use (&$recebido) {
            $recebido[] = $args[5] ?? null;
            throw new NaturezaDoLancamentoInvalidaException('recusa simulada');
        });
    });
    $u = $this->usuarioComPermissoes(['nfebrasil.emit.manage']);
    $emitir = fn () => $this->actingAs($u)
        ->withSession(['business.id' => $this->biz, 'user.business_id' => $this->biz])
        ->postJson("/nfe-brasil/transactions/{$tx}/emitir", ['modelo' => '55', 'operacao_id' => $natureza]);

    config(['feature-flags.forced_on' => '']);
    $emitir()->assertStatus(422);
    config(['feature-flags.forced_on' => 'useV2NfeNaturezas']);
    $emitir()->assertStatus(422)->assertJsonPath('error', 'natureza_invalida')->assertJsonPath('message', 'recusa simulada');

    // Desligada: o campo é ignorado (null). Ligada: a natureza chega ao service.
    expect($recebido)->toBe([null, $natureza]);
});

it('UC-NFTR-62 · a lista do lançamento traz só as naturezas que a emissão aceita, da própria empresa', function () {
    config(['feature-flags.forced_on' => 'useV2NfeNaturezas']);
    $aceita = nlNatureza($this->biz, ['nome' => 'NL aceita']);
    $fora = [
        nlNatureza($this->biz, ['nome' => 'NL inativa', 'ativa' => false]),
        nlNatureza($this->biz, ['nome' => 'NL entrada', 'tipo' => 'entrada']),
        nlNatureza($this->biz, ['nome' => 'NL devolução', 'finalidade' => 4]),
        nlNatureza($this->biz, ['nome' => 'NL sem tela', 'config_tela' => null]),
        nlNatureza($this->outra, ['nome' => 'NL da vizinha']),
    ];
    $u = $this->usuarioComPermissoes(['nfebrasil.emit.manage']);

    $ids = collect($this->actingAs($u)->getJson('/nfe-brasil/tributacao/naturezas/lancamento')->assertOk()->json('naturezas'))
        ->pluck('id')->all();

    expect($ids)->toContain($aceita);
    foreach ($fora as $id) {
        expect($ids)->not->toContain($id);
    }

    config(['feature-flags.forced_on' => '']);
    $this->actingAs($u)->getJson('/nfe-brasil/tributacao/naturezas/lancamento')->assertNotFound();
});

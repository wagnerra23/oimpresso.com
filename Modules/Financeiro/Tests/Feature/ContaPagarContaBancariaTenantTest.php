<?php

declare(strict_types=1);
// Cobre UC-CP-01 (resources/js/Pages/Financeiro/ContasPagar/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-CP-01 — a conta bancária da baixa tem de ser da empresa da sessão; idem o cliente da cobrança.
 *
 * Antes do conserto, `ContaPagarController::pagar()` validava `exists:fin_contas_bancarias,id`
 * sem filtro de empresa e gravava o id em `fin_titulo_baixas`: a empresa 98 dava baixa num
 * título dela apontando a conta bancária da 99. O mesmo valia para `contact_id` em
 * `CobrancaController::store/storeCartao` (gravado na cobrança). Auditoria Financeiro
 * 2026-11-05, lote 0, PR 0.2.
 *
 * Tenant 98 (seededTenant) contra o 99 (ADR 0358). Nunca biz=4 nem biz=1.
 * ⚠️ Skip em SQLite: leia as assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requer MySQL (schema fin_* + UltimatePOS).');
    }
    foreach (['fin_titulos', 'fin_titulo_baixas', 'fin_contas_bancarias', 'accounts', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — financeiro:install pendente.");
        }
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes([
        'financeiro.contas_pagar.pagar', 'financeiro.dashboard.view',
    ]);

    session([
        'user.business_id' => $this->b98,
        'user.id'          => $this->user->id,
        'business.id'      => $this->b98,
    ]);
});

function cp12Conta(int $biz): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PEST-CP12-'.uniqid(), 'account_number' => '999',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $biz, 'account_id' => $accountId, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112',
        'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste CP12', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cp12Titulo(int $biz, int $userId): int
{
    return (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz, 'numero' => 'CP12-'.substr(uniqid(), -8), 'tipo' => 'pagar',
        'status' => 'aberto', 'cliente_descricao' => 'PEST CP12', 'valor_total' => 100.00,
        'valor_aberto' => 100.00, 'emissao' => '2031-06-01', 'vencimento' => '2031-06-10',
        'competencia_mes' => '2031-06', 'origem' => 'manual', 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cp12Contato(int $biz, int $userId): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente CP12 '.uniqid(), 'mobile' => '',
        'contact_id' => 'CP12-'.uniqid(), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cp12Pagar($test, int $tituloId, int $contaId)
{
    return $test->actingAs($test->user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->from('/financeiro/contas-pagar')
        ->post("/financeiro/contas-pagar/{$tituloId}/pagar", [
            'conta_bancaria_id' => $contaId,
            'valor_baixa'       => 40.00,
            'data_baixa'        => '2031-06-10',
            'meio_pagamento'    => 'pix',
        ]);
}

it('UC-CP-01 · baixa com conta bancária de outra empresa é recusada e não grava baixa nem mexe no título [T0]', function () {
    $titulo = cp12Titulo($this->b98, $this->user->id);
    $contaAlheia = cp12Conta($this->b99);

    $resp = cp12Pagar($this, $titulo, $contaAlheia);

    $resp->assertSessionHasErrors('conta_bancaria_id');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $titulo)->count())->toBe(0);
    $t = DB::table('fin_titulos')->where('id', $titulo)->first();
    expect((float) $t->valor_aberto)->toBe(100.0);
    expect($t->status)->toBe('aberto');

    // Controle positivo: mesma baixa com a conta da PRÓPRIA empresa passa.
    // Valor por dois caminhos: 100,00 − 40,00 = 60,00 (à mão) e 100,00 − soma das baixas gravadas.
    $contaPropria = cp12Conta($this->b98);
    $ok = cp12Pagar($this, $titulo, $contaPropria);

    $ok->assertSessionHasNoErrors();
    $baixas = DB::table('fin_titulo_baixas')->where('titulo_id', $titulo)->get();
    expect($baixas)->toHaveCount(1);
    expect((int) $baixas[0]->conta_bancaria_id)->toBe($contaPropria);
    $t2 = DB::table('fin_titulos')->where('id', $titulo)->first();
    expect((float) $t2->valor_aberto)->toBe(60.0);
    expect(round(100.0 - (float) $baixas->sum('valor_baixa'), 2))->toBe((float) $t2->valor_aberto);
    expect($t2->status)->toBe('parcial');
});

it('Tier 0 · cobrança com cliente de outra empresa é recusada na validação (CobrancaController)', function () {
    $conta = cp12Conta($this->b98);
    $payload = fn (int $contactId) => [
        // tipo=card devolve erro antes de chamar o gateway — o teste não sai para a rede.
        'tipo' => 'card', 'valor_centavos' => 1000, 'vencimento' => now()->addDays(5)->toDateString(),
        'account_id' => $conta, 'contact_id' => $contactId, 'payer_name' => 'Pagador teste',
    ];

    $alheio = $this->actingAs($this->user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->from('/financeiro/cobranca')
        ->post('/financeiro/cobranca/emitir', $payload(cp12Contato($this->b99, $this->user->id)));
    $alheio->assertSessionHasErrors('contact_id');

    // Controle: com cliente da própria empresa a validação passa e o fluxo chega ao `tipo`.
    $proprio = $this->actingAs($this->user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->from('/financeiro/cobranca')
        ->post('/financeiro/cobranca/emitir', $payload(cp12Contato($this->b98, $this->user->id)));
    $proprio->assertSessionDoesntHaveErrors('contact_id');
    $proprio->assertSessionHasErrors('tipo');
});

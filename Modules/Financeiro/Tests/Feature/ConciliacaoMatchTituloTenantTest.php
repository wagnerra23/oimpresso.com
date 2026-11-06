<?php

declare(strict_types=1);
// Cobre UC-FCC-23 (resources/js/Pages/Financeiro/Conciliacao/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-FCC-23 — o título recebido em `match()` tem de ser da empresa da sessão.
 *
 * O UC-FCC-13 prova que a LINHA de outra empresa não é conciliada. Este prova o outro lado:
 * a linha é da empresa certa, o TÍTULO é de outra. Antes do conserto, `match()` validava só
 * `titulo_id required|integer` e gravava o id na linha, ligando o extrato da empresa 98 a um
 * título da empresa 99 (auditoria Financeiro 2026-11-05, lote 0, PR 0.1).
 *
 * Tenant 98 (seededTenant) contra o 99 (ADR 0358). Nunca biz=4 nem biz=1.
 * ⚠️ Skip em SQLite: leia as assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requer MySQL (schema fin_* + UltimatePOS).');
    }
    foreach (['fin_bank_statement_lines', 'fin_extrato_lancamentos', 'fin_titulos', 'fin_contas_bancarias'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — financeiro:install pendente.");
        }
    }
    if (! Schema::hasColumn('fin_extrato_lancamentos', 'status')) {
        $this->markTestSkipped('Migration Fase 1 da conciliação (status em fin_extrato_lancamentos) não aplicada.');
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.conciliacao.manage']);

    session([
        'user.business_id' => $this->b98,
        'user.id'          => $this->user->id,
        'business.id'      => $this->b98,
    ]);
});

function fcc23Titulo(int $biz, int $userId): int
{
    return (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz, 'numero' => 'FCC23-'.substr(uniqid(), -8), 'tipo' => 'receber',
        'status' => 'aberto', 'cliente_descricao' => 'PEST FCC23', 'valor_total' => 77.00,
        'valor_aberto' => 77.00, 'emissao' => '2031-05-01', 'vencimento' => '2031-05-10',
        'competencia_mes' => '2031-05', 'origem' => 'manual', 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function fcc23LinhaOfx(int $biz): int
{
    return (int) DB::table('fin_bank_statement_lines')->insertGetId([
        'business_id' => $biz, 'fitid' => 'pest-fcc23-'.uniqid('', true), 'data_movimento' => '2031-05-10',
        'descricao' => 'PEST FCC23 OFX', 'valor' => 77.00, 'tipo' => 'credit', 'status' => 'pendente',
        'source_file' => 'pest.ofx', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function fcc23LinhaApi(int $biz): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PEST-FCC23-'.uniqid(), 'account_number' => '999',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $contaId = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $biz, 'account_id' => $accountId, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112',
        'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste FCC23', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_extrato_lancamentos')->insertGetId([
        'business_id' => $biz, 'conta_bancaria_id' => $contaId, 'data' => '2031-05-10', 'valor' => 77.00,
        'tipo' => 'C', 'descricao' => 'PEST FCC23 API', 'idempotency_key' => 'PEST-FCC23-'.uniqid(),
        'raw_payload' => json_encode(['source' => 'test']), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-FCC-23 · match com título de outra empresa devolve 404 e não grava nada na linha (OFX) [T0]', function () {
    $tituloAlheio = fcc23Titulo($this->b99, (int) (\App\User::where('business_id', $this->b99)->value('id') ?? $this->user->id));
    $linha = fcc23LinhaOfx($this->b98);

    $resp = $this->actingAs($this->user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$linha}/match", ['titulo_id' => $tituloAlheio]);

    expect($resp->status())->toBe(404);
    $row = DB::table('fin_bank_statement_lines')->where('id', $linha)->first();
    expect($row->status)->toBe('pendente');
    expect($row->titulo_id)->toBeNull();
    expect($row->conciliado_at)->toBeNull();

    // Controle positivo: com título da MESMA empresa, o mesmo usuário concilia.
    // Prova que o 404 acima vem do título alheio, não da permissão nem da rota.
    $tituloProprio = fcc23Titulo($this->b98, $this->user->id);
    $linha2 = fcc23LinhaOfx($this->b98);
    $ok = $this->actingAs($this->user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$linha2}/match", ['titulo_id' => $tituloProprio]);

    expect($ok->status())->toBeIn([302, 303]);
    $row2 = DB::table('fin_bank_statement_lines')->where('id', $linha2)->first();
    expect($row2->status)->toBe('conciliado');
    expect((int) $row2->titulo_id)->toBe($tituloProprio);
});

it('UC-FCC-23 · match com título de outra empresa devolve 404 e não grava nada na linha (API) [T0]', function () {
    $tituloAlheio = fcc23Titulo($this->b99, (int) (\App\User::where('business_id', $this->b99)->value('id') ?? $this->user->id));
    $linha = fcc23LinhaApi($this->b98);

    $resp = $this->actingAs($this->user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$linha}/match", ['titulo_id' => $tituloAlheio, 'origem' => 'api']);

    expect($resp->status())->toBe(404);
    $row = DB::table('fin_extrato_lancamentos')->where('id', $linha)->first();
    expect($row->status)->toBeNull();
    expect($row->titulo_id)->toBeNull();
});

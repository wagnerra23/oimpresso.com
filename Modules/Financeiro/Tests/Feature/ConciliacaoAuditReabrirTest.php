<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * B2 — Auditoria + Reabrir (undo) da Conciliação OFX.
 *
 * Cobre invariantes:
 *  (a) match()/ignorar() escrevem entrada de auditoria via FinanceiroAuditLogger
 *      (sink = Log facade; espiamos com Log::spy + shouldHaveReceived).
 *  (b) reabrir() volta status pra `pendente` e zera titulo_id/match_score.
 *  (c) reabrir() de linha de OUTRO business → 404 (Tier 0 ADR 0093 IRREVOGÁVEL).
 *
 * Padrão Financeiro (CaixaControllerTest/UnificadoControllerTest): sem
 * RefreshDatabase (UltimatePOS tem 100+ migrations + triggers), roda contra DB
 * dev real, skip gracioso quando greenfield ou módulo não instalado.
 *
 * Rodar local: `vendor/bin/pest Modules/Financeiro/Tests/Feature/ConciliacaoAuditReabrirTest.php`
 */

function conciliacaoBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    // Tabela alvo precisa existir neste env (módulo instalado).
    if (! \Illuminate\Support\Facades\Schema::hasTable('fin_bank_statement_lines')) {
        test()->markTestSkipped('Tabela fin_bank_statement_lines ausente — financeiro:install pendente.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.conciliacao.manage', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.conciliacao.manage')) {
        $user->givePermissionTo('financeiro.conciliacao.manage');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'is_admin'         => true,
    ]);

    return $user;
}

/** Insere uma linha de extrato pendente pro business do user e devolve o id. */
function inserirLinhaConciliacao(int $businessId, array $overrides = []): int
{
    return (int) DB::table('fin_bank_statement_lines')->insertGetId(array_merge([
        'business_id'    => $businessId,
        'fitid'          => 'pest-'.uniqid('', true),
        'data_movimento' => now()->toDateString(),
        'descricao'      => 'Lançamento de teste B2',
        'valor'          => -123.45,
        'tipo'           => 'debit',
        'status'         => 'pendente',
        'source_file'    => 'pest.ofx',
        'created_at'     => now(),
        'updated_at'     => now(),
    ], $overrides));
}

it('match() escreve entrada de auditoria via FinanceiroAuditLogger', function () {
    $user = conciliacaoBootstrap();
    $businessId = (int) session('user.business_id');

    $lineId = inserirLinhaConciliacao($businessId);

    Log::spy();

    $response = $this->actingAs($user)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$lineId}/match", ['titulo_id' => 999999]);

    expect($response->status())->toBeIn([302, 303]);

    // Sink do FinanceiroAuditLogger é o Log facade (Log::info após redação PII).
    Log::shouldHaveReceived('info')
        ->withArgs(function (string $msg, array $ctx) use ($lineId, $businessId) {
            return str_contains($msg, 'conciliacao.match')
                && ($ctx['business_id'] ?? null) === $businessId
                && ($ctx['line_id'] ?? null) === $lineId
                && ($ctx['status'] ?? null) === 'conciliado'
                && ($ctx['titulo_id'] ?? null) === 999999;
        })
        ->once();

    // Cleanup.
    DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
});

it('ignorar() escreve entrada de auditoria via FinanceiroAuditLogger', function () {
    $user = conciliacaoBootstrap();
    $businessId = (int) session('user.business_id');

    $lineId = inserirLinhaConciliacao($businessId);

    Log::spy();

    $response = $this->actingAs($user)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$lineId}/ignorar");

    expect($response->status())->toBeIn([302, 303]);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $msg, array $ctx) use ($lineId, $businessId) {
            return str_contains($msg, 'conciliacao.ignorar')
                && ($ctx['business_id'] ?? null) === $businessId
                && ($ctx['line_id'] ?? null) === $lineId
                && ($ctx['status'] ?? null) === 'ignorado';
        })
        ->once();

    DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
});

it('reabrir() volta status pra pendente e zera titulo_id/match_score', function () {
    $user = conciliacaoBootstrap();
    $businessId = (int) session('user.business_id');

    // Linha já conciliada (com titulo_id + match_score) pra ter o que desfazer.
    $lineId = inserirLinhaConciliacao($businessId, [
        'status'        => 'conciliado',
        'titulo_id'     => 12345,
        'match_score'   => 0.85,
        'conciliado_by' => $user->id,
        'conciliado_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$lineId}/reabrir");

    expect($response->status())->toBeIn([302, 303]);

    $linha = DB::table('fin_bank_statement_lines')->where('id', $lineId)->first();
    expect($linha->status)->toBe('pendente');
    expect($linha->titulo_id)->toBeNull();
    expect($linha->match_score)->toBeNull();

    DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
});

it('reabrir() é idempotente — linha já pendente continua pendente (sem erro)', function () {
    $user = conciliacaoBootstrap();
    $businessId = (int) session('user.business_id');

    $lineId = inserirLinhaConciliacao($businessId); // já pendente

    $response = $this->actingAs($user)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$lineId}/reabrir");

    expect($response->status())->toBeIn([302, 303]);

    $linha = DB::table('fin_bank_statement_lines')->where('id', $lineId)->first();
    expect($linha->status)->toBe('pendente');

    DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
});

it('Tier 0: reabrir() de linha de OUTRO business retorna 404 (ADR 0093)', function () {
    $user = conciliacaoBootstrap();
    $businessId = (int) session('user.business_id');

    $businessB = Business::where('id', '!=', $businessId)->first();
    if (! $businessB) {
        test()->markTestSkipped('Apenas 1 business no DB — não há cross-tenant pra testar.');
    }

    // Linha pertencente ao businessB (NÃO ao tenant logado).
    $lineId = inserirLinhaConciliacao($businessB->id, ['status' => 'conciliado']);

    $response = $this->actingAs($user)
        ->from('/financeiro/conciliacao')
        ->post("/financeiro/conciliacao/{$lineId}/reabrir");

    expect($response->status())->toBe(404);

    // Garante que NÃO mutou a linha do outro tenant (continua conciliado).
    $linha = DB::table('fin_bank_statement_lines')->where('id', $lineId)->first();
    expect($linha->status)->toBe('conciliado');

    DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
});

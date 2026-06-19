<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Guard do ResyncFromCoreCommand — re-sincroniza fin_titulos inflados
 * (resíduo do incidente num_uf #2279/#2280) com o final_total corrigido do core.
 *
 * Invariantes:
 *  1. --business obrigatório (Tier 0 IRREVOGÁVEL ADR 0093)
 *  2. DRY-RUN (default, sem --apply) não escreve nada
 *  3. --apply: valor_total ← core final_total + estorna baixa-lixo (append-only)
 *     + recalcula valor_aberto/status
 *  4. Idempotente — 2ª run não re-estorna nem re-mexe
 *  5. Não toca título de outro business (Tier 0 multi-tenant)
 *
 * Padrão skip gracioso (convenção Financeiro) quando DB greenfield.
 */
function resyncBootstrap(): Business
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }

    return $business;
}

/**
 * Cria uma venda core correta (final_total) + um fin_titulo INFLADO espelhando
 * o valor-lixo histórico + uma baixa-lixo. Retorna [txId, tituloId, baixaId].
 *
 * @return array{0:int,1:int,2:int}
 */
function seedTituloInflado(int $businessId, int $userId, float $coreTotal, float $garbageTotal): array
{
    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $businessId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'final_total' => $coreTotal,
        'total_remaining_amount' => $coreTotal,
        'transaction_date' => '2026-06-01 10:00:00',
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tituloId = DB::table('fin_titulos')->insertGetId([
        'business_id' => $businessId,
        'numero' => 'R-RESYNCTEST'.$txId,
        'tipo' => 'receber',
        'status' => 'aberto',
        'cliente_descricao' => 'Teste resync · #V-'.$txId,
        'valor_total' => $garbageTotal,
        'valor_aberto' => $garbageTotal,
        'moeda' => 'BRL',
        'emissao' => '2026-06-01',
        'vencimento' => '2026-06-30',
        'competencia_mes' => '2026-06',
        'origem' => 'venda',
        'origem_id' => $txId,
        'parcela_numero' => null,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $baixaId = DB::table('fin_titulo_baixas')->insertGetId([
        'business_id' => $businessId,
        'titulo_id' => $tituloId,
        'conta_bancaria_id' => null,
        'valor_baixa' => $garbageTotal - 5,
        'juros' => 0,
        'multa' => 0,
        'desconto' => 0,
        'data_baixa' => '2026-06-01',
        'meio_pagamento' => 'pix',
        'idempotency_key' => 'resynctest_'.$txId,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$txId, $tituloId, $baixaId];
}

function cleanupResync(int $businessId, int $txId, int $tituloId): void
{
    DB::table('fin_titulo_baixas')->where('titulo_id', $tituloId)->delete();
    DB::table('activity_log')
        ->where('log_name', 'financeiro-resync-from-core')
        ->where('subject_id', $tituloId)
        ->delete();
    DB::table('fin_titulos')->where('id', $tituloId)->delete();
    DB::table('transactions')->where('id', $txId)->delete();
}

it('bloqueia execução sem --business (Tier 0)', function () {
    $exit = $this->artisan('financeiro:resync-from-core')->run();

    expect($exit)->toBe(1); // FAILURE
});

it('dry-run (default) não escreve nada', function () {
    $business = resyncBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user no business.');
    }

    [$txId, $tituloId] = seedTituloInflado((int) $business->id, (int) $userId, 220.90, 209004535.00);

    $this->artisan("financeiro:resync-from-core --business={$business->id}")
        ->assertExitCode(0);

    $titulo = DB::table('fin_titulos')->where('id', $tituloId)->first();
    expect((float) $titulo->valor_total)->toBe(209004535.00); // intacto

    $baixasCount = DB::table('fin_titulo_baixas')->where('titulo_id', $tituloId)->count();
    expect($baixasCount)->toBe(1); // nenhum estorno criado

    cleanupResync((int) $business->id, $txId, $tituloId);
});

it('--apply corrige valor_total ao core + estorna baixa-lixo + recalcula', function () {
    $business = resyncBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user.');
    }

    [$txId, $tituloId] = seedTituloInflado((int) $business->id, (int) $userId, 220.90, 209004535.00);

    $this->artisan("financeiro:resync-from-core --business={$business->id} --apply")
        ->assertExitCode(0);

    $titulo = DB::table('fin_titulos')->where('id', $tituloId)->first();
    expect((float) $titulo->valor_total)->toBe(220.90);
    expect((float) $titulo->valor_aberto)->toBe(220.90); // baixa-lixo estornada → aberto cheio
    expect($titulo->status)->toBe('aberto');

    // Estorno append-only criado (negativo) — baixas líquidas somam ~0.
    $netBaixas = (float) DB::table('fin_titulo_baixas')->where('titulo_id', $tituloId)->sum('valor_baixa');
    expect(round($netBaixas, 2))->toBe(0.0);

    $estornoExiste = DB::table('fin_titulo_baixas')
        ->where('titulo_id', $tituloId)
        ->whereNotNull('estorno_de_id')
        ->exists();
    expect($estornoExiste)->toBeTrue();

    cleanupResync((int) $business->id, $txId, $tituloId);
});

it('é idempotente — 2ª run não re-estorna nem re-mexe', function () {
    $business = resyncBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user.');
    }

    [$txId, $tituloId] = seedTituloInflado((int) $business->id, (int) $userId, 100.00, 100000.00);

    $this->artisan("financeiro:resync-from-core --business={$business->id} --apply")->assertExitCode(0);
    $count1 = DB::table('fin_titulo_baixas')->where('titulo_id', $tituloId)->count();

    $this->artisan("financeiro:resync-from-core --business={$business->id} --apply")->assertExitCode(0);
    $count2 = DB::table('fin_titulo_baixas')->where('titulo_id', $tituloId)->count();

    expect($count2)->toBe($count1); // nenhum estorno novo na 2ª run

    $titulo = DB::table('fin_titulos')->where('id', $tituloId)->first();
    expect((float) $titulo->valor_total)->toBe(100.00);

    cleanupResync((int) $business->id, $txId, $tituloId);
});

it('não toca título de outro business (Tier 0 multi-tenant)', function () {
    $business = resyncBootstrap();
    $other = Business::where('id', '!=', $business->id)->first();
    if (! $other) {
        $this->markTestSkipped('Precisa 2+ businesses no banco.');
    }
    $userId = DB::table('users')->where('business_id', $other->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user no outro business.');
    }

    [$txId, $tituloId] = seedTituloInflado((int) $other->id, (int) $userId, 220.90, 209004535.00);

    // Roda pro business DIFERENTE
    $this->artisan("financeiro:resync-from-core --business={$business->id} --apply")
        ->assertExitCode(0);

    $titulo = DB::table('fin_titulos')->where('id', $tituloId)->first();
    expect((float) $titulo->valor_total)->toBe(209004535.00); // intacto (outro tenant)

    cleanupResync((int) $other->id, $txId, $tituloId);
});

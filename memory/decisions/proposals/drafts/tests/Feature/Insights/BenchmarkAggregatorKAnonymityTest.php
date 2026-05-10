<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * ⭐ ARQUIVO CRÍTICO LGPD ⭐
 *
 * Cobertura: tabela `benchmark_aggregates` + service BenchmarkAggregator
 * com k-anonymity (k>=5) — gap CRÍTICO LGPD.
 *
 * Princípios LGPD (Art. 6º, Art. 7º LGPD + Art. 14):
 *   1. Anonimização irreversível — agregado NUNCA pode permitir reidentificação
 *   2. k-anonymity k>=5 — mínimo 5 businesses por agregado (best-practice 2026)
 *   3. CHECK constraint no banco impede INSERT com n_businesses<5
 *   4. Service skip silencioso (log.warning) se n<5 em compute()
 *   5. Zero RAZAOSOCIAL/CNPJ/nome do cliente em campo computed
 *
 * Restrição Wagner 2026-05-09:
 *   "Pest local antes de PR mesmo defensivas. Tenancy é Tier 0 (ADR 0093)."
 *
 * Pré-requisitos:
 *   - Migration create_benchmark_aggregates_table.php (com CHECK n_businesses>=5)
 *   - Modules/Insights/Models/BenchmarkAggregate.php
 *   - Modules/Insights/Services/BenchmarkAggregator.php
 *   - Stub Transaction/Sell pra simular dados cross-business
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Business;
use App\Models\Transaction;
use Modules\Insights\Models\Vertical;
use Modules\Insights\Models\BenchmarkAggregate;
use Modules\Insights\Services\BenchmarkAggregator;
use Modules\Insights\Database\Seeders\VerticalsSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(VerticalsSeeder::class);
    $this->vertical = Vertical::where('slug', 'comunicacao_visual')->first();
    $this->aggregator = app(BenchmarkAggregator::class);
});

// ============================================================================
// CHECK CONSTRAINT — impede INSERT com n_businesses<5 no banco
// ============================================================================

it('CHECK constraint bloqueia INSERT com n_businesses=4', function () {
    expect(fn () => DB::table('benchmark_aggregates')->insert([
        'vertical_id' => $this->vertical->id,
        'metric' => 'ticket_medio',
        'period' => '2026-04',
        'n_businesses' => 4, // VIOLA k-anonymity
        'value_avg' => 1500.00,
        'value_p50' => 1400.00,
        'value_p90' => 2500.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('CHECK constraint permite INSERT com n_businesses=5', function () {
    DB::table('benchmark_aggregates')->insert([
        'vertical_id' => $this->vertical->id,
        'metric' => 'ticket_medio',
        'period' => '2026-04',
        'n_businesses' => 5, // OK — limiar mínimo
        'value_avg' => 1500.00,
        'value_p50' => 1400.00,
        'value_p90' => 2500.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(BenchmarkAggregate::where('metric', 'ticket_medio')->count())->toBe(1);
});

it('CHECK constraint bloqueia INSERT com n_businesses=0', function () {
    expect(fn () => DB::table('benchmark_aggregates')->insert([
        'vertical_id' => $this->vertical->id,
        'metric' => 'ticket_medio',
        'period' => '2026-04',
        'n_businesses' => 0,
        'value_avg' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

// ============================================================================
// SERVICE — BenchmarkAggregator->compute()
// ============================================================================

it('service compute() pula vertical com 0 clientes (retorna empty)', function () {
    Log::spy();

    $result = $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    expect($result)->toBeEmpty();
    expect(BenchmarkAggregate::count())->toBe(0);
});

it('service compute() pula vertical com 4 clientes (k-anonymity violation)', function () {
    Log::spy();

    // criar 4 businesses na vertical comunicacao_visual com vendas
    $businesses = Business::factory()->count(4)->create([
        'vertical_id' => $this->vertical->id,
    ]);

    foreach ($businesses as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id,
            'type' => 'sell',
            'final_total' => 1500.00,
            'transaction_date' => '2026-04-15',
        ]);
    }

    $result = $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    expect($result)->toBeEmpty();
    expect(BenchmarkAggregate::count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains(strtolower($msg), 'k-anonymity'))
        ->once();
});

it('service compute() agrega corretamente vertical com 5 clientes (limiar mínimo)', function () {
    $businesses = Business::factory()->count(5)->create([
        'vertical_id' => $this->vertical->id,
    ]);

    foreach ($businesses as $i => $b) {
        Transaction::factory()->create([
            'business_id' => $b->id,
            'type' => 'sell',
            'final_total' => 1000 + ($i * 100), // 1000, 1100, 1200, 1300, 1400
            'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    $agg = BenchmarkAggregate::where('vertical_id', $this->vertical->id)
        ->where('metric', 'ticket_medio')
        ->where('period', '2026-04')
        ->first();

    expect($agg)->not->toBeNull();
    expect($agg->n_businesses)->toBe(5);
    expect((float) $agg->value_avg)->toBe(1200.00);
});

it('service compute() agrega corretamente vertical com 50 clientes', function () {
    $businesses = Business::factory()->count(50)->create([
        'vertical_id' => $this->vertical->id,
    ]);

    foreach ($businesses as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id,
            'type' => 'sell',
            'final_total' => fake()->randomFloat(2, 500, 3000),
            'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    $agg = BenchmarkAggregate::where('vertical_id', $this->vertical->id)->first();

    expect($agg)->not->toBeNull();
    expect($agg->n_businesses)->toBe(50);
    expect($agg->value_p50)->not->toBeNull();
    expect($agg->value_p90)->not->toBeNull();
    expect((float) $agg->value_p90)->toBeGreaterThanOrEqual((float) $agg->value_p50);
});

// ============================================================================
// MULTI-TENANT — agregação cross-business respeita scope
// ============================================================================

it('agregação cross-business soma transactions de TODOS businesses da vertical', function () {
    $businesses = Business::factory()->count(6)->create([
        'vertical_id' => $this->vertical->id,
    ]);

    foreach ($businesses as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id,
            'type' => 'sell',
            'final_total' => 1000.00,
            'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    $agg = BenchmarkAggregate::first();
    expect($agg->n_businesses)->toBe(6);
});

it('agregação NÃO mistura vendas de outras verticais', function () {
    $outra = Vertical::factory()->create(['slug' => 'outra', 'name' => 'Outra']);

    $bizCV = Business::factory()->count(5)->create(['vertical_id' => $this->vertical->id]);
    $bizOutra = Business::factory()->count(5)->create(['vertical_id' => $outra->id]);

    foreach ($bizCV as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id, 'type' => 'sell',
            'final_total' => 1000.00, 'transaction_date' => '2026-04-15',
        ]);
    }
    foreach ($bizOutra as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id, 'type' => 'sell',
            'final_total' => 9999.00, 'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    $agg = BenchmarkAggregate::where('vertical_id', $this->vertical->id)->first();

    expect((float) $agg->value_avg)->toBe(1000.00);
    expect((float) $agg->value_avg)->not->toBe(9999.00);
});

it('benchmark_aggregates NÃO tem business_id (é agregado global por vertical)', function () {
    expect(Schema::hasColumn('benchmark_aggregates', 'business_id'))->toBeFalse();
});

// ============================================================================
// ⭐ LGPD COMPLIANCE — zero PII em agregado
// ============================================================================

it('LGPD: nenhuma RAZAOSOCIAL vaza no agregado', function () {
    $businesses = Business::factory()->count(5)->create([
        'vertical_id' => $this->vertical->id,
        'name' => fn () => 'CLIENTE_SECRETO_'.fake()->uuid(),
    ]);

    foreach ($businesses as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id, 'type' => 'sell',
            'final_total' => 1000.00, 'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    $agg = BenchmarkAggregate::first();
    $serialized = json_encode($agg->toArray());

    expect($serialized)->not->toContain('CLIENTE_SECRETO_');
    foreach ($businesses as $b) {
        expect($serialized)->not->toContain($b->name);
    }
});

it('LGPD: nenhum tax_number_1 (CNPJ) vaza no agregado', function () {
    $businesses = Business::factory()->count(5)->create([
        'vertical_id' => $this->vertical->id,
        'tax_number_1' => fn () => fake()->numerify('##.###.###/####-##'),
    ]);

    foreach ($businesses as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id, 'type' => 'sell',
            'final_total' => 1000.00, 'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    $agg = BenchmarkAggregate::first();
    $serialized = json_encode($agg->toArray());

    foreach ($businesses as $b) {
        expect($serialized)->not->toContain($b->tax_number_1);
    }
});

it('LGPD: schema benchmark_aggregates só tem agregados (sem campo *_id de business)', function () {
    $cols = Schema::getColumnListing('benchmark_aggregates');

    // colunas permitidas
    expect($cols)->toContain('vertical_id');
    expect($cols)->toContain('metric');
    expect($cols)->toContain('period');
    expect($cols)->toContain('n_businesses');
    expect($cols)->toContain('value_avg');

    // proibido: qualquer coluna que aponte pra business individual
    expect($cols)->not->toContain('business_id');
    expect($cols)->not->toContain('client_name');
    expect($cols)->not->toContain('top_business_id');
});

it('LGPD: log de violação k-anonymity NÃO contém business.name (PII)', function () {
    Log::spy();

    $businesses = Business::factory()->count(3)->create([
        'vertical_id' => $this->vertical->id,
        'name' => fn () => 'CLIENTE_REAL_'.fake()->uuid(),
    ]);

    foreach ($businesses as $b) {
        Transaction::factory()->create([
            'business_id' => $b->id, 'type' => 'sell',
            'final_total' => 1000.00, 'transaction_date' => '2026-04-15',
        ]);
    }

    $this->aggregator->compute($this->vertical->id, 'ticket_medio', '2026-04');

    Log::shouldHaveReceived('warning')->withArgs(function ($msg, $context = []) {
        $payload = $msg.json_encode($context);
        // log pode citar n=3 ou vertical_id, mas NUNCA business.name
        return ! str_contains($payload, 'CLIENTE_REAL_');
    });
});

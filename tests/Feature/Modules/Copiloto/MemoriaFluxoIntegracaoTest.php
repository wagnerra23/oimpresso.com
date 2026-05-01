<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;
use Modules\Copiloto\Services\Memoria\HitTrackerService;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

/**
 * Pipeline completo das 8 fases da memória — teste de integração.
 *
 * Valida o ciclo de vida end-to-end:
 *   Fase 1 Captura → 2 Classificação → 3 Persistência → 4 Indexação →
 *   5 Recall → 6 Uso (hit tracking) → 7 Evolução (temporal) → 8 Esquecimento
 *
 * Roda contra MySQL dev real. DatabaseTransactions faz rollback após cada teste.
 *
 * Metas vs estado-da-arte (ADR 0062):
 *   - Recall@3  ≥ 0.80  (atual ~0.26 biz=1, medido via copiloto:eval)
 *   - hit_rate  ≥ 0.30  (fatos usados / fatos ativos)
 *   - bloat_ratio < 0.20 (fatos removidos / total antes de cleanup)
 *   - temporal > 0       (fatos com valid_until setado existem no pipeline)
 */

beforeEach(function () {
    try {
        DB::table('copiloto_memoria_facts')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('copiloto_memoria_facts indisponível: ' . $e->getMessage());
    }
    config(['scout.driver' => 'null']);
});

// ── Fases 1-3: Captura, Classificação, Persistência ──────────────────────────

it('Pipeline Fases 1-3: fato capturado persiste com metadados corretos', function () {
    $fato = CopilotoMemoriaFato::create([
        'business_id' => 1,
        'user_id'     => 66666,
        'fato'        => 'Faturamento bruto abril 2026: R$ 31.513,29',
        'metadata'    => [
            'categoria'  => 'faturamento',
            'relevancia' => 5,
            'origem'     => 'chat',
        ],
        'valid_from'  => now(),
        'valid_until' => null,
    ]);

    $fato->refresh(); // Carrega defaults do DB (hits_count=0, core_memory=false)

    expect($fato->id)->toBeInt()->toBeGreaterThan(0)
        ->and($fato->metadata['categoria'])->toBe('faturamento')
        ->and($fato->metadata['relevancia'])->toBe(5)
        ->and($fato->valid_until)->toBeNull()
        ->and($fato->hits_count)->toBe(0)
        ->and($fato->core_memory)->toBeFalse();
});

// ── Fase 4: Indexação — shouldBeSearchable ────────────────────────────────────

it('Pipeline Fase 4: fato ativo deve ser indexável', function () {
    $fato = new CopilotoMemoriaFato([
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'Ticket médio: R$ 450',
        'valid_until' => null,
    ]);

    expect($fato->shouldBeSearchable())->toBeTrue();
});

it('Pipeline Fase 4: toSearchableArray inclui campos de recall', function () {
    $fato = new CopilotoMemoriaFato([
        'id'          => 42,
        'business_id' => 1,
        'user_id'     => 66666,
        'fato'        => 'Meta anual: R$ 5 milhões',
        'metadata'    => ['categoria' => 'meta'],
        'valid_from'  => now()->subDays(10),
        'valid_until' => null,
    ]);

    $arr = $fato->toSearchableArray();

    expect($arr)->toHaveKey('id')
        ->and($arr)->toHaveKey('business_id')
        ->and($arr)->toHaveKey('user_id')
        ->and($arr)->toHaveKey('fato')
        ->and($arr)->toHaveKey('valid_from')
        ->and($arr)->toHaveKey('valid_until')
        ->and($arr['business_id'])->toBe(1)
        ->and($arr['fato'])->toBe('Meta anual: R$ 5 milhões');
});

// ── Fase 5: Recall — scopeAtivos ─────────────────────────────────────────────

it('Pipeline Fase 5: scopeAtivos retorna apenas fatos elegíveis pro recall', function () {
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 66666, 'fato' => 'Fato ativo', 'valid_until' => null]);
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 66666, 'fato' => 'Fato com validade', 'valid_until' => now()->addDays(10)]);
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 66666, 'fato' => 'Fato expirado', 'valid_until' => now()->subDay()]);

    $recall = CopilotoMemoriaFato::doUser(1, 66666)->ativos()->get();

    expect($recall)->toHaveCount(2)
        ->and($recall->pluck('fato')->all())->not->toContain('Fato expirado');
});

// ── Fase 6: Uso — HitTrackerService com isolamento multi-tenant ──────────────

it('Pipeline Fase 6: hits acumulam e promovem core_memory no threshold', function () {
    config(['copiloto.hits.core_memory_threshold' => 3]);
    $fato = CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 66666,
        'fato' => 'Dado recorrente sobre cliente',
    ]);

    $svc = app(HitTrackerService::class);

    $svc->registrarUso([$fato->id], 1);
    $svc->registrarUso([$fato->id], 1);
    expect($fato->refresh()->core_memory)->toBeFalse()
        ->and($fato->hits_count)->toBe(2);

    $svc->registrarUso([$fato->id], 1);
    expect($fato->refresh()->core_memory)->toBeTrue()
        ->and($fato->hits_count)->toBe(3);
});

it('Pipeline Fase 6: contaminação cross-business é impossível via HitTracker', function () {
    $fato = CopilotoMemoriaFato::create([
        'business_id' => 4, 'user_id' => 66666,
        'fato' => 'Dado confidencial ROTA LIVRE',
    ]);

    // biz=1 tenta incrementar fato de biz=4 — deve ser bloqueado
    app(HitTrackerService::class)->registrarUso([$fato->id], businessId: 1);

    expect($fato->refresh()->hits_count)->toBe(0);
});

// ── Fase 7: Evolução temporal ─────────────────────────────────────────────────

it('Pipeline Fase 7: superseder um fato seta valid_until + cria sucessor', function () {
    $original = CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 66666,
        'fato'        => 'Faturamento março: R$ 25.000',
        'valid_from'  => now()->subMonth(),
    ]);

    $original->update(['valid_until' => now()]);

    $novo = CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 66666,
        'fato'        => 'Faturamento abril: R$ 31.513,29',
        'valid_from'  => now(),
        'metadata'    => ['supersede_id' => $original->id],
    ]);

    $original->refresh();
    expect($original->shouldBeSearchable())->toBeFalse();
    expect($novo->shouldBeSearchable())->toBeTrue();

    $ativos = CopilotoMemoriaFato::doUser(1, 66666)->ativos()->get();
    expect($ativos)->toHaveCount(1)
        ->and($ativos->first()->id)->toBe($novo->id);
});

// ── Fase 8: Esquecimento ──────────────────────────────────────────────────────

it('Pipeline Fase 8: ciclo completo remove bloat mas preserva fatos com hits', function () {
    // Fato bloat: velho, nunca usado
    $idBloat = DB::table('copiloto_memoria_facts')->insertGetId([
        'business_id' => 1, 'user_id' => 66666, 'fato' => 'ADR antiga sem uso',
        'metadata' => '{}', 'valid_from' => now(), 'valid_until' => null,
        'hits_count' => 0, 'core_memory' => false,
        'created_at' => now()->subDays(45), 'updated_at' => now(), 'deleted_at' => null,
    ]);

    // Fato útil: tem hits, também é antigo
    $util = CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 66666, 'fato' => 'Dado recorrente',
    ]);
    app(HitTrackerService::class)->registrarUso([$util->id], 1);
    DB::table('copiloto_memoria_facts')->where('id', $util->id)->update(['created_at' => now()->subDays(45)]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    // Bloat: soft-deleted
    expect(DB::table('copiloto_memoria_facts')->where('id', $idBloat)->whereNull('deleted_at')->count())->toBe(0);
    // Útil: preservado
    expect(CopilotoMemoriaFato::where('id', $util->id)->count())->toBe(1);
});

// ── Métricas alvo (documentação das metas ADR 0062) ──────────────────────────

it('Meta hit_rate: cenário simulado ≥ 0.30', function () {
    // 10 fatos, 4 usados → hit_rate = 0.40 ≥ 0.30
    $ids = [];
    for ($i = 1; $i <= 10; $i++) {
        $ids[] = CopilotoMemoriaFato::create([
            'business_id' => 1, 'user_id' => 66666,
            'fato'        => "Fato pipeline $i",
        ])->id;
    }

    $usados = array_slice($ids, 0, 4);
    app(HitTrackerService::class)->registrarUso($usados, 1);

    $totalAtivos = CopilotoMemoriaFato::where('user_id', 66666)->count();
    $comHits     = CopilotoMemoriaFato::where('user_id', 66666)->where('hits_count', '>', 0)->count();
    $hitRate     = $totalAtivos > 0 ? $comHits / $totalAtivos : 0;

    expect($hitRate)->toBeGreaterThanOrEqual(0.30);
});

it('Meta bloat_ratio: ≤ 0.20 após cleanup criterioso em cenário típico', function () {
    // 8 fatos recentes (úteis) + 2 bloats velhos → ratio = 2/10 = 0.20
    for ($i = 1; $i <= 8; $i++) {
        CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 66666, 'fato' => "Fato recente $i"]);
    }

    DB::table('copiloto_memoria_facts')->insert([
        ['business_id' => 1, 'user_id' => 66666, 'fato' => 'Bloat 1', 'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0, 'core_memory' => false, 'created_at' => now()->subDays(40), 'updated_at' => now(), 'deleted_at' => null],
        ['business_id' => 1, 'user_id' => 66666, 'fato' => 'Bloat 2', 'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0, 'core_memory' => false, 'created_at' => now()->subDays(50), 'updated_at' => now(), 'deleted_at' => null],
    ]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    $removidos  = DB::table('copiloto_memoria_facts')->where('user_id', 66666)->whereNotNull('deleted_at')->count();
    $total      = DB::table('copiloto_memoria_facts')->where('user_id', 66666)->count();
    $bloatRatio = $total > 0 ? $removidos / $total : 0;

    expect($bloatRatio)->toBeLessThanOrEqual(0.20);
});

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Entities\MemoriaMetrica;

/**
 * MEM-MET-1 (ADRs 0050+0051) — schema da tabela `copiloto_memoria_metricas`,
 * casts da Entity, scopes (doBusinessOuPlataforma + ultimosDias), e
 * helpers metricasObrigatorias() / metricasRagas().
 *
 * NOTA: não usa RefreshDatabase — migrations do core UltimatePOS têm
 * `ALTER TABLE ... MODIFY COLUMN ENUM(...)` que SQLite não suporta. Em vez
 * disso, criamos só a tabela alvo no SQLite in-memory antes de cada teste.
 */

beforeEach(function () {
    Schema::create('copiloto_memoria_metricas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->date('apurado_em');
        $t->unsignedInteger('business_id')->nullable();
        $t->decimal('recall_at_3', 4, 3)->nullable();
        $t->decimal('precision_at_3', 4, 3)->nullable();
        $t->decimal('mrr', 4, 3)->nullable();
        $t->unsignedInteger('latencia_p95_ms')->nullable();
        $t->unsignedInteger('tokens_medio_interacao')->nullable();
        $t->decimal('memory_bloat_ratio', 4, 3)->nullable();
        $t->decimal('taxa_contradicoes_pct', 5, 2)->nullable();
        $t->unsignedInteger('cross_tenant_violations')->default(0);
        $t->decimal('faithfulness', 4, 3)->nullable();
        $t->decimal('answer_relevancy', 4, 3)->nullable();
        $t->decimal('context_precision', 4, 3)->nullable();
        $t->unsignedInteger('total_interacoes_dia')->default(0);
        $t->unsignedInteger('total_memorias_ativas')->default(0);
        $t->json('detalhes')->nullable();
        $t->timestamps();
        $t->unique(['apurado_em', 'business_id'], 'mem_metr_ux');
    });
});

afterEach(function () {
    Schema::dropIfExists('copiloto_memoria_metricas');
});

it('Entity MemoriaMetrica grava e lê 1 linha com casts corretos', function () {
    $m = MemoriaMetrica::create([
        'apurado_em'              => '2026-04-29',
        'business_id'             => 4,
        'recall_at_3'             => 0.85,
        'precision_at_3'          => 0.65,
        'mrr'                     => 0.78,
        'latencia_p95_ms'         => 1450,
        'tokens_medio_interacao'  => 850,
        'memory_bloat_ratio'      => 0.72,
        'taxa_contradicoes_pct'   => 1.5,
        'cross_tenant_violations' => 0,
        'faithfulness'            => 0.92,
        'answer_relevancy'        => 0.88,
        'context_precision'       => 0.74,
        'total_interacoes_dia'    => 12,
        'total_memorias_ativas'   => 2,
        'detalhes'                => ['perguntas_falhas' => 3, 'modelo' => 'gpt-4o-mini'],
    ]);

    $m->refresh();

    expect($m->apurado_em)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($m->apurado_em->toDateString())->toBe('2026-04-29');
    expect((float) $m->recall_at_3)->toBe(0.85);
    expect($m->latencia_p95_ms)->toBe(1450);
    expect($m->tokens_medio_interacao)->toBe(850);
    expect((float) $m->faithfulness)->toBe(0.92);
    expect($m->detalhes)->toBe(['perguntas_falhas' => 3, 'modelo' => 'gpt-4o-mini']);
});

it('unique (apurado_em, business_id) impede duplicata no mesmo dia/tenant', function () {
    MemoriaMetrica::create([
        'apurado_em'  => '2026-04-29',
        'business_id' => 4,
        'recall_at_3' => 0.80,
    ]);

    expect(fn () => MemoriaMetrica::create([
        'apurado_em'  => '2026-04-29',
        'business_id' => 4,
        'recall_at_3' => 0.99,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('mesmo apurado_em mas business_id diferente coexiste', function () {
    MemoriaMetrica::create(['apurado_em' => '2026-04-29', 'business_id' => 4, 'recall_at_3' => 0.80]);
    MemoriaMetrica::create(['apurado_em' => '2026-04-29', 'business_id' => 8, 'recall_at_3' => 0.70]);
    MemoriaMetrica::create(['apurado_em' => '2026-04-29', 'business_id' => null, 'recall_at_3' => 0.75]); // plataforma

    expect(MemoriaMetrica::count())->toBe(3);
});

it('scope doBusinessOuPlataforma filtra por tenant ou null', function () {
    MemoriaMetrica::create(['apurado_em' => '2026-04-29', 'business_id' => 4, 'recall_at_3' => 0.80]);
    MemoriaMetrica::create(['apurado_em' => '2026-04-29', 'business_id' => 8, 'recall_at_3' => 0.70]);
    MemoriaMetrica::create(['apurado_em' => '2026-04-29', 'business_id' => null, 'recall_at_3' => 0.75]);

    expect(MemoriaMetrica::doBusinessOuPlataforma(4)->count())->toBe(1);
    expect(MemoriaMetrica::doBusinessOuPlataforma(null)->count())->toBe(1);
    expect(MemoriaMetrica::doBusinessOuPlataforma(99)->count())->toBe(0);
});

it('scope ultimosDias retorna janela ordenada do mais recente pro mais antigo', function () {
    MemoriaMetrica::create(['apurado_em' => now()->subDays(45)->toDateString(), 'business_id' => 4, 'recall_at_3' => 0.50]);
    MemoriaMetrica::create(['apurado_em' => now()->subDays(10)->toDateString(), 'business_id' => 4, 'recall_at_3' => 0.70]);
    MemoriaMetrica::create(['apurado_em' => now()->subDays(2)->toDateString(),  'business_id' => 4, 'recall_at_3' => 0.85]);
    MemoriaMetrica::create(['apurado_em' => now()->toDateString(),               'business_id' => 4, 'recall_at_3' => 0.90]);

    $rows = MemoriaMetrica::doBusinessOuPlataforma(4)->ultimosDias(30)->get();

    expect($rows)->toHaveCount(3); // 45d ago foi excluído
    expect((float) $rows[0]->recall_at_3)->toBe(0.90); // mais recente
    expect((float) $rows[2]->recall_at_3)->toBe(0.70);
});

it('helper metricasObrigatorias devolve 8 chaves do ADR 0050', function () {
    $m = new MemoriaMetrica([
        'recall_at_3'             => 0.85,
        'precision_at_3'          => 0.65,
        'mrr'                     => 0.78,
        'latencia_p95_ms'         => 1450,
        'tokens_medio_interacao'  => 850,
        'memory_bloat_ratio'      => 0.72,
        'taxa_contradicoes_pct'   => 1.5,
        'cross_tenant_violations' => 0,
    ]);

    $obrig = $m->metricasObrigatorias();

    expect($obrig)->toHaveKeys([
        'recall_at_3', 'precision_at_3', 'mrr', 'latencia_p95_ms',
        'tokens_medio_interacao', 'memory_bloat_ratio',
        'taxa_contradicoes_pct', 'cross_tenant_violations',
    ]);
    expect($obrig)->toHaveCount(8);
});

it('helper metricasRagas devolve 3 chaves do ADR 0051', function () {
    $m = new MemoriaMetrica([
        'faithfulness'      => 0.92,
        'answer_relevancy'  => 0.88,
        'context_precision' => 0.74,
    ]);

    $ragas = $m->metricasRagas();

    expect($ragas)->toHaveKeys(['faithfulness', 'answer_relevancy', 'context_precision']);
    expect($ragas)->toHaveCount(3);
    expect((float) $ragas['faithfulness'])->toBe(0.92);
});

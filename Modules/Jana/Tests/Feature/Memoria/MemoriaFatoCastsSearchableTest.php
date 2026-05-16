<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\MemoriaFato;

uses(Tests\TestCase::class);

/**
 * Wave Memoria Semanal 2026-05-16 — Pendência #1 do RUNBOOK + P1-A.0 prep.
 *
 * Cobre:
 *   1. Casts Eloquent corretos pros 3 campos legacy adicionados pelo HitTracker
 *      (hits_count, core_memory, ultimo_hit_em) — sem casts, condicionais `=== true`
 *      falham e accessors retornam tipo errado (int vez de bool).
 *   2. toSearchableArray expõe atributos flat metadata_* + core_memory + hits_count
 *      pra Meilisearch poder filtrar (destrava P1-A `metadata_relevancia >= 3`
 *      e P1-B `core_memory = true`).
 *   3. Cross-tenant isolation Tier 0 (ADR 0093) — biz=1 ≠ biz=99 via scope
 *      `doUser()` + global BusinessScope (HasBusinessScope trait).
 *
 * Contribui pra rubrica module-grade-v1:
 *   - D1.b (cross-tenant Pest pattern) — +pontos pra D1 (atual 13/15)
 *   - D2.b (Pest canônico MultiTenant) — +pontos pra D2 (atual 3/8)
 *
 * @see memory/requisitos/Jana/RUNBOOK-MEMORIA-SEMANAL.md
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */

// ── 1. CASTS ELOQUENT ────────────────────────────────────────────────────

it('casts hits_count como integer', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes(['hits_count' => '7']);

    expect($fato->hits_count)
        ->toBe(7)
        ->and($fato->hits_count)->toBeInt();
});

it('casts core_memory como boolean', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes(['core_memory' => 1]);

    expect($fato->core_memory)
        ->toBeTrue()
        ->and($fato->core_memory)->toBeBool();
});

it('casts ultimo_hit_em como Carbon datetime', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes(['ultimo_hit_em' => '2026-05-16 10:30:00']);

    expect($fato->ultimo_hit_em)->toBeInstanceOf(Carbon::class)
        ->and($fato->ultimo_hit_em->format('Y-m-d H:i:s'))->toBe('2026-05-16 10:30:00');
});

it('core_memory false quando coluna é 0', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes(['core_memory' => 0]);

    expect($fato->core_memory)->toBeFalse();
});

// ── 2. toSearchableArray FLAT METADATA ──────────────────────────────────

it('toSearchableArray expõe metadata_relevancia flat (P1-A prep)', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes([
        'id' => 100,
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'larissa prefere boleto',
        'metadata' => json_encode(['relevancia' => 4, 'tipo' => 'preferencia', 'fonte' => 'chat']),
        'hits_count' => 3,
        'core_memory' => 0,
    ]);

    $arr = $fato->toSearchableArray();

    expect($arr)->toHaveKey('metadata_relevancia')
        ->and($arr['metadata_relevancia'])->toBe(4)
        ->and($arr)->toHaveKey('metadata_tipo')
        ->and($arr['metadata_tipo'])->toBe('preferencia')
        ->and($arr)->toHaveKey('metadata_fonte')
        ->and($arr['metadata_fonte'])->toBe('chat');
});

it('toSearchableArray expõe core_memory + hits_count flat (P1-B prep + bloat reducer prep)', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes([
        'id' => 200,
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'fato muito usado',
        'hits_count' => 12,
        'core_memory' => 1,
    ]);

    $arr = $fato->toSearchableArray();

    expect($arr)->toHaveKey('hits_count')
        ->and($arr['hits_count'])->toBe(12)
        ->and($arr)->toHaveKey('core_memory')
        ->and($arr['core_memory'])->toBeTrue();
});

it('toSearchableArray tolera metadata null sem crash', function () {
    $fato = new MemoriaFato();
    $fato->setRawAttributes([
        'id' => 300,
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'sem metadata',
        'metadata' => null,
    ]);

    $arr = $fato->toSearchableArray();

    expect($arr['metadata_relevancia'])->toBeNull()
        ->and($arr['metadata_tipo'])->toBeNull()
        ->and($arr['metadata_fonte'])->toBeNull()
        ->and($arr['core_memory'])->toBeFalse()
        ->and($arr['hits_count'])->toBe(0);
});

// ── 3. CROSS-TENANT ISOLATION TIER 0 (ADR 0093 + 0101) ────────────────

it('scope doUser isola business_id biz=1 vs biz=99 (cross-tenant Tier 0)', function () {
    $now = Carbon::now();

    // Cria 2 fatos isolados por business — biz=1 (Wagner) vs biz=99 (synthetic)
    $id1 = DB::table('jana_memoria_facts')->insertGetId([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'fato-test-tenant-iso-biz1',
        'metadata' => json_encode(['relevancia' => 5, 'tipo' => 'fato']),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $id99 = DB::table('jana_memoria_facts')->insertGetId([
        'business_id' => 99,
        'user_id' => 1,
        'fato' => 'fato-test-tenant-iso-biz99',
        'metadata' => json_encode(['relevancia' => 5, 'tipo' => 'fato']),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    try {
        // Scope doUser(biz=1, user=1) NÃO PODE ver biz=99
        $factsBiz1 = MemoriaFato::withoutGlobalScopes()
            ->doUser(1, 1)
            ->whereIn('id', [$id1, $id99])
            ->get();

        expect($factsBiz1)->toHaveCount(1)
            ->and($factsBiz1->first()->id)->toBe($id1)
            ->and($factsBiz1->first()->business_id)->toBe(1);

        // Scope doUser(biz=99, user=1) NÃO PODE ver biz=1
        $factsBiz99 = MemoriaFato::withoutGlobalScopes()
            ->doUser(99, 1)
            ->whereIn('id', [$id1, $id99])
            ->get();

        expect($factsBiz99)->toHaveCount(1)
            ->and($factsBiz99->first()->id)->toBe($id99)
            ->and($factsBiz99->first()->business_id)->toBe(99);
    } finally {
        // Cleanup — sempre roda, mesmo em failure
        DB::table('jana_memoria_facts')->whereIn('id', [$id1, $id99])->delete();
    }
});

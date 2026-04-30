<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;
use Modules\Copiloto\Services\Memoria\HitTrackerService;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

/**
 * FASE 6 — HitTrackerService: rastreio de uso e promoção a core_memory.
 *
 * Roda contra MySQL dev real. DatabaseTransactions faz rollback após cada teste.
 * Skip automático se tabela copiloto_memoria_facts não existir.
 *
 * Contrato de isolamento multi-tenant: registrarUso(ids, businessId) NUNCA
 * incrementa fatos de outra empresa mesmo que os IDs sejam passados.
 */

beforeEach(function () {
    try {
        DB::table('copiloto_memoria_facts')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('copiloto_memoria_facts indisponível: ' . $e->getMessage());
    }
    config(['scout.driver' => 'null']);
});

function hitCriarFato(array $attrs = []): CopilotoMemoriaFato
{
    $fato = CopilotoMemoriaFato::create(array_merge([
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'Fato de teste ' . uniqid(),
    ], $attrs));
    // Carrega DB defaults (hits_count=0, core_memory=false) que Eloquent não preenche no create
    return $fato->fresh();
}

// ── Incremento básico ─────────────────────────────────────────────────────────

it('Fase6: registrarUso incrementa hits_count em +1', function () {
    $fato = hitCriarFato();
    expect($fato->hits_count)->toBe(0);

    app(HitTrackerService::class)->registrarUso([$fato->id], 1);

    expect($fato->refresh()->hits_count)->toBe(1);
});

it('Fase6: registrarUso atualiza ultimo_hit_em', function () {
    $fato = hitCriarFato();
    expect($fato->ultimo_hit_em)->toBeNull();

    app(HitTrackerService::class)->registrarUso([$fato->id], 1);

    expect($fato->refresh()->ultimo_hit_em)->not->toBeNull();
});

it('Fase6: registrarUso multi-id incrementa todos em lote', function () {
    $a = hitCriarFato(['fato' => 'Fato A']);
    $b = hitCriarFato(['fato' => 'Fato B']);
    $c = hitCriarFato(['fato' => 'Fato C']);

    app(HitTrackerService::class)->registrarUso([$a->id, $b->id, $c->id], 1);

    expect($a->refresh()->hits_count)->toBe(1)
        ->and($b->refresh()->hits_count)->toBe(1)
        ->and($c->refresh()->hits_count)->toBe(1);
});

it('Fase6: registrarUso acumulado soma corretamente', function () {
    $fato = hitCriarFato();
    $svc  = app(HitTrackerService::class);

    $svc->registrarUso([$fato->id], 1);
    $svc->registrarUso([$fato->id], 1);
    $svc->registrarUso([$fato->id], 1);

    expect($fato->refresh()->hits_count)->toBe(3);
});

// ── Promoção a core_memory ────────────────────────────────────────────────────

it('Fase6: core_memory permanece false antes do threshold', function () {
    config(['copiloto.hits.core_memory_threshold' => 5]);
    $fato = hitCriarFato();
    $svc  = app(HitTrackerService::class);

    foreach (range(1, 4) as $_) {
        $svc->registrarUso([$fato->id], 1);
    }

    expect($fato->refresh()->core_memory)->toBeFalse();
});

it('Fase6: core_memory promovido ao atingir threshold', function () {
    config(['copiloto.hits.core_memory_threshold' => 5]);
    $fato = hitCriarFato();
    $svc  = app(HitTrackerService::class);

    foreach (range(1, 5) as $_) {
        $svc->registrarUso([$fato->id], 1);
    }

    expect($fato->refresh()->core_memory)->toBeTrue();
});

it('Fase6: promoção com threshold personalizado via config', function () {
    config(['copiloto.hits.core_memory_threshold' => 3]);
    $fato = hitCriarFato();
    $svc  = app(HitTrackerService::class);

    $svc->registrarUso([$fato->id], 1);
    $svc->registrarUso([$fato->id], 1);
    expect($fato->refresh()->core_memory)->toBeFalse();

    $svc->registrarUso([$fato->id], 1);
    expect($fato->refresh()->core_memory)->toBeTrue();
});

it('Fase6: fato já core_memory não regride — hits_count segue subindo', function () {
    config(['copiloto.hits.core_memory_threshold' => 5]);
    $fato = hitCriarFato();
    $svc  = app(HitTrackerService::class);

    foreach (range(1, 5) as $_) {
        $svc->registrarUso([$fato->id], 1);
    }
    expect($fato->refresh()->core_memory)->toBeTrue();

    $svc->registrarUso([$fato->id], 1);
    $svc->registrarUso([$fato->id], 1);
    $svc->registrarUso([$fato->id], 1);

    $fato->refresh();
    expect($fato->core_memory)->toBeTrue()
        ->and($fato->hits_count)->toBe(8);
});

// ── MULTI-TENANT: business_id impede contaminação cruzada ────────────────────

it('Fase6: registrarUso com businessId errado NÃO incrementa fato de outra empresa', function () {
    // Fato pertence a biz=4 (ROTA LIVRE)
    $fato = hitCriarFato(['business_id' => 4]);

    // biz=1 tenta registrar uso dos IDs (simula bug: recall retornou IDs misturados)
    app(HitTrackerService::class)->registrarUso([$fato->id], businessId: 1);

    // hits_count deve continuar 0 — o filtro business_id bloqueia
    expect($fato->refresh()->hits_count)->toBe(0);
});

it('Fase6: registrarUso com businessId correto incrementa apenas fatos da empresa certa', function () {
    $fatoBiz1 = hitCriarFato(['business_id' => 1]);
    $fatoBiz4 = hitCriarFato(['business_id' => 4]);

    // biz=1 registra uso com seus próprios IDs + um ID de biz=4 por acidente
    app(HitTrackerService::class)->registrarUso(
        [$fatoBiz1->id, $fatoBiz4->id],
        businessId: 1
    );

    expect($fatoBiz1->refresh()->hits_count)->toBe(1)   // incrementado
        ->and($fatoBiz4->refresh()->hits_count)->toBe(0); // protegido
});

// ── Isolamento: soft-deleted não é incrementado ───────────────────────────────

it('Fase6: soft-deleted não recebe hits_count', function () {
    $fato = hitCriarFato();
    $id   = $fato->id;
    $fato->delete();

    app(HitTrackerService::class)->registrarUso([$id], 1);

    expect(CopilotoMemoriaFato::withTrashed()->find($id)->hits_count)->toBe(0);
});

// ── Resiliência ───────────────────────────────────────────────────────────────

it('Fase6: registrarUso com array vazio é silente', function () {
    expect(fn () => app(HitTrackerService::class)->registrarUso([], 1))->not->toThrow(\Throwable::class);
});

it('Fase6: ID inexistente não gera exception', function () {
    expect(fn () => app(HitTrackerService::class)->registrarUso([999999999], 1))->not->toThrow(\Throwable::class);
});

<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

/**
 * FASE 1 + FASE 7 — CopilotoMemoriaFato: entidade, scopes, temporal validity.
 *
 * Roda contra MySQL dev real (UltimatePOS tem migrations MySQL-specific que
 * não migram em SQLite). DatabaseTransactions faz rollback após cada teste.
 *
 * Skip automático se tabela não existir (branch ainda não migrado em dev).
 */

beforeEach(function () {
    try {
        DB::table('copiloto_memoria_facts')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('copiloto_memoria_facts indisponível — rode php artisan migrate: ' . $e->getMessage());
    }
    // Scout sync desabilitado — Meilisearch não está disponível em ambiente de teste
    config(['scout.driver' => 'null']);
});

// ── Fase 1: Schema / entidade ────────────────────────────────────────────────

it('Fase1: cria fato com campos obrigatórios', function () {
    $fato = CopilotoMemoriaFato::create([
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'Faturamento bruto abril 2026 foi R$ 31.513,29',
        'metadata'    => ['categoria' => 'faturamento', 'relevancia' => 4],
        'valid_from'  => now(),
    ]);

    $fato->refresh();
    expect($fato->id)->toBeInt()->toBeGreaterThan(0)
        ->and($fato->business_id)->toBe(1)
        ->and($fato->user_id)->toBe(1)
        ->and($fato->valid_until)->toBeNull()
        ->and($fato->hits_count)->toBe(0)
        ->and($fato->core_memory)->toBeFalse();
});

it('Fase1: metadata é castado como array', function () {
    $fato = CopilotoMemoriaFato::create([
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'Ticket médio R$ 450',
        'metadata'    => ['categoria' => 'ticket', 'relevancia' => 3],
    ]);

    $fato->refresh();
    expect($fato->metadata)->toBeArray()
        ->and($fato->metadata['categoria'])->toBe('ticket');
});

it('Fase1: scopeAtivos exclui fatos com valid_until no passado', function () {
    CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 999,
        'fato' => 'Fato ativo',
        'valid_until' => null,
    ]);
    CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 999,
        'fato' => 'Fato expirado',
        'valid_until' => now()->subDay(),
    ]);

    $ativos = CopilotoMemoriaFato::where('user_id', 999)->ativos()->get();
    expect($ativos)->toHaveCount(1)
        ->and($ativos->first()->fato)->toBe('Fato ativo');
});

it('Fase1: scopeAtivos inclui valid_until no futuro', function () {
    CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 998,
        'fato' => 'Fato válido por mais 30d',
        'valid_until' => now()->addDays(30),
    ]);

    expect(CopilotoMemoriaFato::where('user_id', 998)->ativos()->count())->toBe(1);
});

it('Fase1: scopeDoUser isola por business_id + user_id', function () {
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 997, 'fato' => 'Biz1 User997']);
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 996, 'fato' => 'Biz1 User996']);
    CopilotoMemoriaFato::create(['business_id' => 4, 'user_id' => 997, 'fato' => 'Biz4 User997']);

    $resultados = CopilotoMemoriaFato::doUser(1, 997)->get();
    expect($resultados)->toHaveCount(1)
        ->and($resultados->first()->fato)->toBe('Biz1 User997');
});

it('Fase1: soft-delete não aparece em query normal', function () {
    $fato = CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 995, 'fato' => 'Para ser deletado',
    ]);
    $id = $fato->id;
    $fato->delete();

    expect(CopilotoMemoriaFato::find($id))->toBeNull()
        ->and(CopilotoMemoriaFato::withTrashed()->find($id))->not->toBeNull();
});

// ── Fase 7: Temporal validity (unit-level — sem DB) ───────────────────────────

it('Fase7: shouldBeSearchable retorna false para valid_until preenchido', function () {
    $fato = new CopilotoMemoriaFato([
        'business_id' => 1, 'user_id' => 1,
        'fato'        => 'ADR supersedida',
        'valid_until' => now()->subDay(),
    ]);

    expect($fato->shouldBeSearchable())->toBeFalse();
});

it('Fase7: shouldBeSearchable retorna true para fato ativo', function () {
    $fato = new CopilotoMemoriaFato([
        'business_id' => 1, 'user_id' => 1,
        'fato'        => 'Fato ativo',
        'valid_until' => null,
    ]);

    expect($fato->shouldBeSearchable())->toBeTrue();
});

it('Fase7: shouldBeSearchable retorna false para soft-deleted', function () {
    $fato = new CopilotoMemoriaFato([
        'business_id' => 1, 'user_id' => 1,
        'fato'        => 'Deletado LGPD',
        'valid_until' => null,
    ]);
    // deleted_at não está em $fillable — atribuição direta
    $fato->deleted_at = now();

    expect($fato->shouldBeSearchable())->toBeFalse();
});

it('Fase7: valid_until grava e lê como Carbon', function () {
    $supersedidoEm = now()->addDays(7);

    $fato = CopilotoMemoriaFato::create([
        'business_id' => 1, 'user_id' => 994,
        'fato'        => 'ADR 0031 aceita',
        'valid_until' => $supersedidoEm,
    ]);

    $fato->refresh();
    expect($fato->valid_until)->not->toBeNull()
        ->and($fato->valid_until->toDateString())->toBe($supersedidoEm->toDateString());
});

it('Fase7: toSearchableArray inclui valid_from e valid_until como timestamps', function () {
    $validFrom  = now()->subDays(5);
    $validUntil = now()->addDays(30);

    $fato = new CopilotoMemoriaFato([
        'id'          => 99,
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'Fato com datas',
        'metadata'    => [],
        'valid_from'  => $validFrom,
        'valid_until' => $validUntil,
    ]);

    $arr = $fato->toSearchableArray();
    expect($arr)->toHaveKey('valid_from')
        ->and($arr)->toHaveKey('valid_until')
        ->and($arr['business_id'])->toBe(1)
        ->and($arr['fato'])->toBe('Fato com datas');
});

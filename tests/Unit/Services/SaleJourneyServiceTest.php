<?php

declare(strict_types=1);

use App\Services\SaleJourneyService;

/**
 * Jornada/breadcrumb READ-ONLY da venda de oficina (Wagner 2026-06-05).
 *
 * Fluxo real (ADR 0192): OS nasce na oficina e VIRA venda ao concluir
 * (source='oficina'). O breadcrumb na venda só indica o estágio pós-oficina —
 * Oficina → Venda → Faturamento → Entrega — e SÓ pra venda de origem oficina.
 * Função pura → roda no lane SQLite do CI. Cobre o gate (protege ROTA LIVRE) +
 * a progressão dos estágios.
 */

function journey(array $state): array
{
    return (new SaleJourneyService())->build($state);
}

function keys(array $built): array
{
    return array_map(fn ($n) => $n['key'], $built['nodes']);
}

function stateOf(array $built, string $key): ?string
{
    foreach ($built['nodes'] as $n) {
        if ($n['key'] === $key) {
            return $n['state'];
        }
    }

    return null;
}

// ─── Gate (ROTA LIVRE / balcão protegidos) ──────────────────────────────────

it('varejo SEM OficinaAuto nunca mostra o breadcrumb (ROTA LIVRE intocada)', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => false]);
    expect($b['show'])->toBeFalse();
});

it('venda de BALCÃO (mesmo com OficinaAuto e veículo) NÃO mostra — não é origem oficina', function () {
    $b = journey(['source' => 'balcao', 'has_oficina_auto' => true, 'has_vehicle' => true, 'has_os' => true]);
    expect($b['show'])->toBeFalse();
});

it('venda de ORIGEM oficina + OficinaAuto mostra o breadcrumb', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true]);
    expect($b['show'])->toBeTrue();
});

// ─── Jornada fixa pós-oficina ───────────────────────────────────────────────

it('a jornada é Oficina → Venda → Faturamento → Entrega (sempre oficina-first)', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true]);
    expect(keys($b))->toBe(['oficina', 'venda', 'faturamento', 'entrega']);
    expect($b['direction'])->toBe('oficina');
});

it('venda recém-gerada (sem NFe/entrega) está em Venda, com Oficina já concluída', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true]);
    expect($b['current'])->toBe('venda');
    expect(stateOf($b, 'oficina'))->toBe('done');
    expect(stateOf($b, 'venda'))->toBe('current');
    expect(stateOf($b, 'faturamento'))->toBe('todo');
});

it('faturada (NFe autorizada) marca Faturamento como atual', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true, 'invoiced' => true]);
    expect($b['current'])->toBe('faturamento');
    expect(stateOf($b, 'venda'))->toBe('done');
});

it('entregue marca Entrega como atual (último nó)', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true, 'invoiced' => true, 'delivered' => true]);
    expect($b['current'])->toBe('entrega');
    expect(stateOf($b, 'faturamento'))->toBe('done');
    expect(stateOf($b, 'entrega'))->toBe('current');
});

// ─── os_ref (link de origem) ────────────────────────────────────────────────

it('propaga o os_ref (origem da OS) quando presente', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true, 'os_ref' => 'SO-42']);
    expect($b['os_ref'])->toBe('SO-42');
});

it('os_ref vazio vira null', function () {
    $b = journey(['source' => 'oficina', 'has_oficina_auto' => true, 'os_ref' => '']);
    expect($b['os_ref'])->toBeNull();
});

// ─── Robustez ───────────────────────────────────────────────────────────────

it('estado vazio não explode (defaults seguros, show=false)', function () {
    $b = journey([]);
    expect($b['show'])->toBeFalse();
    expect($b['nodes'])->not->toBeEmpty();
});

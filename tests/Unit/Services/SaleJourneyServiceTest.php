<?php

declare(strict_types=1);

use App\Services\SaleJourneyService;

/**
 * Jornada/breadcrumb da venda de oficina (Wagner 2026-06-05). Função pura →
 * roda no lane SQLite do CI. Cobre as duas direções + progressão dos estágios
 * + o gate que protege a ROTA LIVRE (varejo nunca mostra o breadcrumb).
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

// ─── Gate (ROTA LIVRE protegida) ────────────────────────────────────────────

it('varejo SEM OficinaAuto nunca mostra o breadcrumb (ROTA LIVRE intocada)', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => false]);
    expect($b['show'])->toBeFalse();
});

it('venda de oficina SEM veículo/OS e não-oficina-first não mostra (sem ruído)', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true,
        'has_vehicle' => false, 'has_os' => false]);
    expect($b['show'])->toBeFalse();
});

it('venda com veículo + OficinaAuto mostra o breadcrumb', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true, 'has_vehicle' => true]);
    expect($b['show'])->toBeTrue();
});

// ─── Direção balcão (orçamento-first) ───────────────────────────────────────

it('balcão segue Orçamento → Venda → Oficina → Faturamento → Entrega', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true, 'has_vehicle' => true]);
    expect(keys($b))->toBe(['orcamento', 'venda', 'oficina', 'faturamento', 'entrega']);
    expect($b['direction'])->toBe('balcao');
});

it('orçamento (status quotation) marca Orçamento como atual', function () {
    $b = journey(['source' => 'balcao', 'status' => 'quotation', 'has_oficina_auto' => true, 'has_vehicle' => true]);
    expect($b['current'])->toBe('orcamento');
    expect(stateOf($b, 'orcamento'))->toBe('current');
    expect(stateOf($b, 'venda'))->toBe('todo');
});

it('venda final (sem OS ainda) marca Venda como atual e Orçamento como done', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true, 'has_vehicle' => true]);
    expect($b['current'])->toBe('venda');
    expect(stateOf($b, 'orcamento'))->toBe('done');
    expect(stateOf($b, 'venda'))->toBe('current');
    expect(stateOf($b, 'oficina'))->toBe('todo');
});

it('enviada pra oficina (OS criada) marca Oficina como atual', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true,
        'has_vehicle' => true, 'has_os' => true]);
    expect($b['current'])->toBe('oficina');
    expect(stateOf($b, 'venda'))->toBe('done');
    expect(stateOf($b, 'oficina'))->toBe('current');
});

it('faturada (NFe) marca Faturamento como atual', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true,
        'has_vehicle' => true, 'has_os' => true, 'invoiced' => true]);
    expect($b['current'])->toBe('faturamento');
    expect(stateOf($b, 'oficina'))->toBe('done');
});

it('entregue marca Entrega como atual (último nó)', function () {
    $b = journey(['source' => 'balcao', 'status' => 'final', 'has_oficina_auto' => true,
        'has_vehicle' => true, 'has_os' => true, 'invoiced' => true, 'delivered' => true]);
    expect($b['current'])->toBe('entrega');
    expect(stateOf($b, 'faturamento'))->toBe('done');
    expect(stateOf($b, 'entrega'))->toBe('current');
});

// ─── Direção oficina-first (OS → venda, ADR 0192) ───────────────────────────

it('oficina-first segue Oficina → Venda → Faturamento → Entrega (sem Orçamento)', function () {
    $b = journey(['source' => 'oficina', 'status' => 'final', 'has_oficina_auto' => true, 'has_os' => true]);
    expect(keys($b))->toBe(['oficina', 'venda', 'faturamento', 'entrega']);
    expect($b['direction'])->toBe('oficina');
    expect($b['show'])->toBeTrue();
});

it('oficina-first com venda final marca Venda como atual', function () {
    $b = journey(['source' => 'oficina', 'status' => 'final', 'has_oficina_auto' => true, 'has_os' => true]);
    expect($b['current'])->toBe('venda');
    expect(stateOf($b, 'oficina'))->toBe('done');
});

it('oficina-first faturada+entregue chega em Entrega', function () {
    $b = journey(['source' => 'oficina', 'status' => 'final', 'has_oficina_auto' => true,
        'has_os' => true, 'invoiced' => true, 'delivered' => true]);
    expect($b['current'])->toBe('entrega');
});

// ─── Robustez ───────────────────────────────────────────────────────────────

it('estado vazio não explode (defaults seguros)', function () {
    $b = journey([]);
    expect($b['show'])->toBeFalse();
    expect($b['nodes'])->not->toBeEmpty();
});

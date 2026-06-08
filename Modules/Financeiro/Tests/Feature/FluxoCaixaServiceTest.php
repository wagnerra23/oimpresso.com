<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use App\Business;
use Modules\Financeiro\Services\FluxoCaixaService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Smoke da FluxoCaixaService (read-side projeção de fluxo de caixa).
 *
 * Tier 0 (ADR 0093): biz=1 sempre (ADR 0101 — nunca cliente real biz=4).
 * SQLite guard — UltimatePOS tem 100+ migrations + triggers, suite roda
 * contra DB dev real (FinanceiroTestCase pattern).
 *
 * Cobertura mínima:
 *  1. shape — retorna chaves esperadas pelo Inertia
 *  2. multi-tenant — biz inexistente devolve estrutura zerada (sem leak)
 *  3. dias — array contém histórico + hoje + projeção (>= dias + 1)
 */
beforeEach(function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('SQLite guard — UltimatePOS migrations/triggers incompatíveis.');
    }

    $this->business = Business::find(1);
    if (! $this->business) {
        $this->markTestSkipped('biz=1 ausente — precisa seeder UltimatePOS.');
    }
});

it('retorna shape canônico esperado pelo Inertia', function () {
    $service = new FluxoCaixaService();

    $result = $service->projetar(businessId: 1, dias: 7);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys([
            'saldo_hoje',
            'saldo_30d',
            'pior_dia',
            'margem_minima',
            'conta',
            'dias',
        ])
        ->and($result['margem_minima'])->toBe(5000.0)
        ->and($result['dias'])->toBeArray()
        ->and($result['pior_dia'])->toHaveKeys(['saldo', 'data_label']);
});

it('respeita multi-tenant — biz inexistente não vaza dados (Tier 0 ADR 0093)', function () {
    $service = new FluxoCaixaService();

    // biz=999999 nunca deve existir — qualquer dado retornado é vazamento
    $result = $service->projetar(businessId: 999999, dias: 5);

    expect($result['saldo_hoje'])->toBe(0.0)
        ->and($result['conta'])->toBe('Sem conta cadastrada');

    // Nenhum dia deve ter evento de outro tenant
    foreach ($result['dias'] as $dia) {
        expect($dia['eventos'])->toBe([]);
    }
});

it('gera array de dias cobrindo histórico + hoje + projeção', function () {
    $service = new FluxoCaixaService();

    $dias = 10;
    $result = $service->projetar(businessId: 1, dias: $dias);

    // HISTORICO_DIAS=2 + dia atual + $dias projeção = 13 entradas
    expect(count($result['dias']))->toBeGreaterThanOrEqual($dias + 1)
        ->and(collect($result['dias'])->pluck('is_today')->contains(true))->toBeTrue()
        ->and(collect($result['dias'])->pluck('is_past')->contains(true))->toBeTrue();
});

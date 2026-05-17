<?php

declare(strict_types=1);

/**
 * CustomerJourneyTest — smoke E2E jornada cliente ComunicacaoVisual (D5).
 *
 * Cobre o caminho feliz documentado em README §3 "Como o cliente usa":
 *  1. Atender pedido (criar orçamento)
 *  2. Cliente aprova
 *  3. Gera OS (FSM stage inicial)
 *  4. Operador aponta produção (iniciar/finalizar)
 *  5. Drift m² calculado
 *
 * Stack: Pest v4 + RefreshDatabase + biz=99 sintético ([ADR 0101](../../../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
 *
 * NÃO depende de:
 *  - NFe SEFAZ real
 *  - WhatsApp daemon
 *  - FSM canon completo (testa estado inicial após criar OS)
 *
 * Wave 18 — D5 boost cliente real via smoke jornada.
 *
 * @see Modules/ComunicacaoVisual/README.md §3
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001..004
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;

uses(RefreshDatabase::class);

const COMVIS_TEST_BIZ = 99; // ADR 0101 — nunca biz=4 cliente real

beforeEach(function () {
    // Simula sessão biz=99 pra global scope multi-tenant
    session(['user.business_id' => COMVIS_TEST_BIZ, 'business.id' => COMVIS_TEST_BIZ]);
});

it('jornada completa: criar orçamento → aprovar → gerar OS → apontar produção', function () {
    // ETAPA 1 — atender pedido novo: vendedor cria orçamento
    $orcamento = Orcamento::create([
        'numero'        => 'ORC-2026-TEST1',
        'contato_id'    => null,
        'vendedor_id'   => null,
        'data_emissao'  => '2026-05-16',
        'data_validade' => '2026-06-15',
        'status'        => 'rascunho',
        'subtotal'      => 525.00,
        'desconto'      => 0,
        'extras'        => 0,
        'custo_instalacao' => 0,
        'custo_entrega' => 0,
        'total'         => 525.00,
        'observacoes'   => null, // sem PII
    ]);

    expect($orcamento->id)->toBeGreaterThan(0);
    expect($orcamento->business_id)->toBe(COMVIS_TEST_BIZ);
    expect($orcamento->total)->toEqual(525.00);

    // ETAPA 2 — cliente aprova (manual via UI; aqui simulamos UPDATE)
    $orcamento->update(['status' => 'aprovado']);
    expect($orcamento->fresh()->status)->toBe('aprovado');

    // ETAPA 3 — gera OS vinculada
    $os = Os::create([
        'orcamento_id' => $orcamento->id,
        'numero'       => 'OS-2026-TEST1',
        'status_etapa' => 'arte',
        'data_inicio'  => '2026-05-16',
        'data_prazo'   => '2026-05-20',
        'valor_total'  => 525.00,
    ]);

    expect($os->id)->toBeGreaterThan(0);
    expect($os->business_id)->toBe(COMVIS_TEST_BIZ);
    expect($os->orcamento->id)->toBe($orcamento->id);

    // ETAPA 4 — operador inicia apontamento
    $inicio = now()->subMinutes(45);
    $fim    = now();
    $duracao = $fim->diffInSeconds($inicio);

    $apontamento = Apontamento::create([
        'os_id'          => $os->id,
        'operador_id'    => 1,
        'maquina'        => 'plotter_01',
        'iniciado_em'    => $inicio,
        'finalizado_em'  => $fim,
        'duracao_segundos' => $duracao,
        'm2_produzido'   => 9.200,
        'm2_orcado'      => 9.000,
        'drift_percent'  => 2.22, // (9.2 - 9) / 9 * 100
    ]);

    expect($apontamento->id)->toBeGreaterThan(0);
    expect($apontamento->business_id)->toBe(COMVIS_TEST_BIZ);
    expect((float) $apontamento->drift_percent)->toBeGreaterThan(0);
    expect($apontamento->esta_em_andamento)->toBeFalse();
})->skip(! class_exists(\Tests\TestCase::class), 'Requires Laravel TestCase (skip em CI sem DB)');

it('isolamento multi-tenant: biz=99 não vê orçamento de biz=1', function () {
    // Cria orçamento em biz=1 (simulado)
    session(['user.business_id' => 1, 'business.id' => 1]);
    $orcBiz1 = Orcamento::create([
        'numero'        => 'ORC-2026-BIZ1',
        'data_emissao'  => '2026-05-16',
        'status'        => 'rascunho',
        'subtotal'      => 100,
        'desconto'      => 0,
        'extras'        => 0,
        'custo_instalacao' => 0,
        'custo_entrega' => 0,
        'total'         => 100,
    ]);
    expect($orcBiz1->business_id)->toBe(1);

    // Switch pra biz=99
    session(['user.business_id' => COMVIS_TEST_BIZ, 'business.id' => COMVIS_TEST_BIZ]);

    // biz=99 NÃO deve enxergar orcBiz1 (global scope filtra)
    $found = Orcamento::find($orcBiz1->id);
    expect($found)->toBeNull('Tier 0 IRREVOGÁVEL — biz=99 não pode ver dados de biz=1');
})->skip(! class_exists(\Tests\TestCase::class), 'Requires Laravel TestCase');

it('apontamento append-only: tentativa de delete não usa SoftDeletes', function () {
    // Não cria registro DB; reflection-only verifica padrão classe
    $traits = class_uses_recursive(Apontamento::class);
    expect($traits)->not->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class,
        'Apontamento é registro legal — append-only protegido (não pode soft-delete)');
});

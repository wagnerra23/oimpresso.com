<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;

uses(Tests\TestCase::class);

/**
 * Testes do OrcamentoCalculator — cálculo authoritative server-side.
 *
 * US-COMVIS-001: fórmulas canônicas m².
 *   area_m2  = largura_m × altura_m × quantidade  (3 casas PHP_ROUND_HALF_UP)
 *   subtotal_item = area_m2 × preco_unitario_m2   (2 casas PHP_ROUND_HALF_UP)
 *   total    = subtotal - desconto + extras + custo_instalacao + custo_entrega
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 *
 * @see Modules\ComunicacaoVisual\Services\OrcamentoCalculator
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// ------------------------------------------------------------------
// Helper: payload base com 1 item
// ------------------------------------------------------------------

function payloadBase(array $overrides = [], array $itemOverrides = []): array
{
    $item = array_merge([
        'descricao'        => 'Banner 3x1.5 lona front',
        'largura_m'        => 3.000,
        'altura_m'         => 1.500,
        'quantidade'       => 1,
        'preco_unitario_m2' => 60.00,
    ], $itemOverrides);

    return array_merge([
        'data_emissao'    => '2026-05-10',
        'data_validade'   => '2026-05-25',
        'contato_id'      => null,
        'vendedor_id'     => 7,
        'desconto'        => 0.00,
        'extras'          => 50.00,
        'custo_instalacao' => 200.00,
        'custo_entrega'   => 80.00,
        'observacoes'     => 'banner externo',
        'itens'           => [$item],
    ], $overrides);
}

// ------------------------------------------------------------------
// Cenário 1: banner 3×1.5 lona R$60/m² — resultado canônico
// area=4.5, subtotal=270, total=600
// ------------------------------------------------------------------

it('Cenário 1 — banner 3x1.5 R$60/m²: area=4.5, subtotal=270, total=600', function () {
    $calc      = new OrcamentoCalculator();
    $resultado = $calc->calcular(payloadBase());

    // Item calculado
    $item = $resultado['itens'][0];
    expect($item['area_m2'])->toBe(4.500);
    expect($item['preco_unitario_m2'])->toBe(60.00);
    expect($item['subtotal'])->toBe(270.00);

    // Cabeçalho
    expect($resultado['subtotal'])->toBe(270.00);
    expect($resultado['desconto'])->toBe(0.00);
    expect($resultado['extras'])->toBe(50.00);
    expect($resultado['custo_instalacao'])->toBe(200.00);
    expect($resultado['custo_entrega'])->toBe(80.00);
    // total = 270 - 0 + 50 + 200 + 80 = 600
    expect($resultado['total'])->toBe(600.00);
});

// ------------------------------------------------------------------
// Cenário 2: vinil adesivo 2×0.8 qtd=10 R$45/m²
// area = 2 × 0.8 × 10 = 16, subtotal = 16 × 45 = 720
// ------------------------------------------------------------------

it('Cenário 2 — vinil 2x0.8 qtd=10 R$45/m²: area=16.000, subtotal=720', function () {
    $calc    = new OrcamentoCalculator();
    $payload = payloadBase(
        ['desconto' => 0, 'extras' => 0, 'custo_instalacao' => 0, 'custo_entrega' => 0],
        ['descricao' => 'Vinil adesivo', 'largura_m' => 2.000, 'altura_m' => 0.800, 'quantidade' => 10, 'preco_unitario_m2' => 45.00]
    );

    $resultado = $calc->calcular($payload);

    expect($resultado['itens'][0]['area_m2'])->toBe(16.000);
    expect($resultado['itens'][0]['subtotal'])->toBe(720.00);
    expect($resultado['subtotal'])->toBe(720.00);
    expect($resultado['total'])->toBe(720.00);
});

// ------------------------------------------------------------------
// Cenário 3: ACM com material_id (preco_unitario_m2 null → resolve do material)
// ------------------------------------------------------------------

it('Cenário 3 — ACM com material_id sem preco override: resolve do catálogo', function () {
    // CI SQLite :memory: — pula gracioso se migrate não criou tabela.
    if (! Schema::hasTable('comvis_materiais')) {
        $this->markTestSkipped('Tabela comvis_materiais ausente — rode migrate primeiro.');
    }

    // Criar material no biz=1 via withoutGlobalScopes (SUPERADMIN: setup de teste)
    session(['user.business_id' => 1]);
    $material = Material::withoutGlobalScopes()->create([
        'business_id'    => 1,
        'nome'           => 'ACM 3mm Teste',
        'categoria'      => 'acm',
        'unidade'        => 'm2',
        'preco_custo_m2' => 40.00,
        'preco_venda_m2' => 80.00,
        'ativo'          => true,
    ]);

    $calc    = new OrcamentoCalculator();
    $payload = payloadBase(
        ['desconto' => 0, 'extras' => 0, 'custo_instalacao' => 0, 'custo_entrega' => 0],
        [
            'material_id'      => $material->id,
            'descricao'        => 'ACM 1.2x2.4',
            'largura_m'        => 1.200,
            'altura_m'         => 2.400,
            'quantidade'       => 1,
            'preco_unitario_m2' => null, // deve buscar do material
        ]
    );

    $resultado = $calc->calcular($payload);

    // preco deve vir do material (R$80/m²)
    expect($resultado['itens'][0]['preco_unitario_m2'])->toBe(80.00);
    // area = 1.2 × 2.4 × 1 = 2.88
    expect($resultado['itens'][0]['area_m2'])->toBe(2.880);
    // subtotal = 2.88 × 80 = 230.40
    expect($resultado['itens'][0]['subtotal'])->toBe(230.40);

    // Limpar após teste
    Material::withoutGlobalScopes()->where('id', $material->id)->forceDelete();
});

// ------------------------------------------------------------------
// Cenário 4: desconto absoluto > 0
// ------------------------------------------------------------------

it('Cenário 4 — desconto absoluto: total = subtotal - desconto + demais', function () {
    $calc    = new OrcamentoCalculator();
    $payload = payloadBase(['desconto' => 30.00, 'extras' => 0, 'custo_instalacao' => 0, 'custo_entrega' => 0]);

    $resultado = $calc->calcular($payload);

    // subtotal = 4.5 × 60 = 270; total = 270 - 30 = 240
    expect($resultado['desconto'])->toBe(30.00);
    expect($resultado['total'])->toBe(240.00);
});

// ------------------------------------------------------------------
// Cenário 5: múltiplos itens (3 banners diferentes) — soma correta
// ------------------------------------------------------------------

it('Cenário 5 — múltiplos itens: soma de subtotais calculada corretamente', function () {
    $calc = new OrcamentoCalculator();

    $payload = [
        'data_emissao'    => '2026-05-10',
        'desconto'        => 0,
        'extras'          => 0,
        'custo_instalacao' => 0,
        'custo_entrega'   => 0,
        'itens'           => [
            // Banner 1: 3×1.5×1 × R$60 = 270
            ['descricao' => 'Banner 1', 'largura_m' => 3.0, 'altura_m' => 1.5, 'quantidade' => 1, 'preco_unitario_m2' => 60.00],
            // Banner 2: 2×1×2 × R$50 = 200
            ['descricao' => 'Banner 2', 'largura_m' => 2.0, 'altura_m' => 1.0, 'quantidade' => 2, 'preco_unitario_m2' => 50.00],
            // Banner 3: 1×0.5×5 × R$100 = 250
            ['descricao' => 'Banner 3', 'largura_m' => 1.0, 'altura_m' => 0.5, 'quantidade' => 5, 'preco_unitario_m2' => 100.00],
        ],
    ];

    $resultado = $calc->calcular($payload);

    expect($resultado['itens'][0]['subtotal'])->toBe(270.00);  // 4.5 × 60
    expect($resultado['itens'][1]['area_m2'])->toBe(4.000);    // 2 × 1 × 2
    expect($resultado['itens'][1]['subtotal'])->toBe(200.00);  // 4 × 50
    expect($resultado['itens'][2]['area_m2'])->toBe(2.500);    // 1 × 0.5 × 5
    expect($resultado['itens'][2]['subtotal'])->toBe(250.00);  // 2.5 × 100

    // subtotal total = 270 + 200 + 250 = 720; total = 720
    expect($resultado['subtotal'])->toBe(720.00);
    expect($resultado['total'])->toBe(720.00);
});

// ------------------------------------------------------------------
// Cenário 6: validação throw quando largura ≤ 0
// ------------------------------------------------------------------

it('Cenário 6 — throw quando largura_m <= 0', function () {
    $calc    = new OrcamentoCalculator();
    $payload = payloadBase([], ['largura_m' => 0]);

    expect(fn () => $calc->calcular($payload))
        ->toThrow(\InvalidArgumentException::class, 'largura_m deve ser maior que zero');
});

it('Cenário 6b — throw quando altura_m <= 0', function () {
    $calc    = new OrcamentoCalculator();
    $payload = payloadBase([], ['altura_m' => -1]);

    expect(fn () => $calc->calcular($payload))
        ->toThrow(\InvalidArgumentException::class, 'altura_m deve ser maior que zero');
});

// ------------------------------------------------------------------
// Cenário 7: throw quando preco_unitario_m2 null E material sem preco_venda_m2
// ------------------------------------------------------------------

it('Cenário 7 — throw quando sem material_id e sem preco_unitario_m2', function () {
    $calc    = new OrcamentoCalculator();
    $payload = payloadBase(
        [],
        ['material_id' => null, 'preco_unitario_m2' => null]
    );

    expect(fn () => $calc->calcular($payload))
        ->toThrow(\InvalidArgumentException::class, 'preco_unitario_m2 é obrigatório quando material_id não é informado');
});

it('Cenário 7b — throw quando material_id não existe no business', function () {
    // CI SQLite :memory: — pula gracioso se migrate não criou tabela.
    if (! Schema::hasTable('comvis_materiais')) {
        $this->markTestSkipped('Tabela comvis_materiais ausente — rode migrate primeiro.');
    }

    session(['user.business_id' => 1]);

    $calc    = new OrcamentoCalculator();
    $payload = payloadBase(
        [],
        ['material_id' => 999999, 'preco_unitario_m2' => null]
    );

    expect(fn () => $calc->calcular($payload))
        ->toThrow(\InvalidArgumentException::class, 'não encontrado ou não pertence a este business');
});

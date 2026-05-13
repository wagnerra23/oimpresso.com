<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Acabamento;
use Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo;
use Modules\ComunicacaoVisual\Entities\Substrato;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;

uses(Tests\TestCase::class);

/**
 * OrcamentoCalculator V2 — testes Fase 2 do ROADMAP (US-COMVIS-001 estendida).
 *
 * Cobre regras NOVAS adicionadas em Fase 2:
 *   - Emenda banner large-format >1,60m (opt-in via `calcular_emenda: true`)
 *   - Acabamentos catalogados via cv_acabamentos (m_linear / unitario / m2 / fixo)
 *   - Substrato (cv_substratos) — preço + minimo_m2 + tributação NCM/CFOP/CSOSN snapshot
 *   - Instalação catalogada via cv_instalacoes_catalogo (preco_base + preco_m2 + preco_km)
 *   - NR-35 enforcement (altura >2m com instalação exige ART + treinamento + ASO — alertas soft)
 *
 * Tests biz=1 conforme ADR 0101 — nunca biz=4 (ROTA LIVRE cliente).
 *
 * @see Modules\ComunicacaoVisual\Services\OrcamentoCalculator
 * @see memory/requisitos/ComunicacaoVisual/ROADMAP.md Fase 2 entrega 2.1
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const V2_BIZ = 1;

beforeEach(function () {
    session(['user.business_id' => V2_BIZ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// 1. EMENDA banner large-format >1,60m
// ──────────────────────────────────────────────────────────────────────────────

function payloadEmenda(array $itemOverrides = [], array $headerOverrides = []): array
{
    $item = array_merge([
        'descricao'        => 'Banner emenda',
        'largura_m'        => 3.000,
        'altura_m'         => 1.500,
        'quantidade'       => 1,
        'preco_unitario_m2' => 60.00,
    ], $itemOverrides);

    return array_merge([
        'data_emissao'    => '2026-05-13',
        'calcular_emenda' => true,
        'desconto'        => 0,
        'extras'          => 0,
        'custo_instalacao' => 0,
        'custo_entrega'   => 0,
        'itens'           => [$item],
    ], $headerOverrides);
}

it('Emenda: banner 3x1.5 em plotter 1,60m → 2 tiras + 1 emenda de 1,5m × R$12 = R$18', function () {
    $r = (new OrcamentoCalculator())->calcular(payloadEmenda());

    expect($r['itens'][0]['emenda']['n_tiras'])->toBe(2);
    expect($r['itens'][0]['emenda']['lado_maior_m'])->toBe(3.0);
    expect($r['itens'][0]['emenda']['lado_menor_m'])->toBe(1.5);
    expect($r['itens'][0]['emenda']['custo'])->toBe(18.00);   // 1 × 1.5 × 1 × 12

    // subtotal_substrato 270 + emenda 18 = 288
    expect($r['itens'][0]['subtotal_substrato'])->toBe(270.00);
    expect($r['itens'][0]['subtotal'])->toBe(288.00);
    expect($r['total'])->toBe(288.00);
});

it('Emenda: banner 6x3 qtd=2 plotter 1,60m → 4 tiras × 3 emendas × 3m × 2 × R$12 = R$216', function () {
    $r = (new OrcamentoCalculator())->calcular(payloadEmenda([
        'largura_m' => 6.0, 'altura_m' => 3.0, 'quantidade' => 2, 'preco_unitario_m2' => 60.00,
    ]));

    expect($r['itens'][0]['emenda']['n_tiras'])->toBe(4);    // ceil(6/1.6)=4
    expect($r['itens'][0]['emenda']['custo'])->toBe(216.00); // (4-1) × 3 × 2 × 12 = 216
    // subtotal_substrato = (6×3×2) × 60 = 2160
    expect($r['itens'][0]['subtotal_substrato'])->toBe(2160.00);
    expect($r['itens'][0]['subtotal'])->toBe(2376.00);
});

it('Emenda: plotter UV 3,20m absorve banner 3x1.5 sem emenda', function () {
    $r = (new OrcamentoCalculator())->calcular(payloadEmenda([], ['largura_plotter_m' => 3.20]));

    expect($r['itens'][0]['emenda']['n_tiras'])->toBe(1);
    expect($r['itens'][0]['emenda']['custo'])->toBe(0.0);
    expect($r['itens'][0]['subtotal'])->toBe(270.00);
});

it('Emenda DESLIGADA (backward compat): banner 3x1.5 sem flag → 0 emenda', function () {
    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
        ]],
    ]);

    // Sem calcular_emenda → backward compat Sprint 1
    expect($r['itens'][0]['emenda']['custo'])->toBe(0.0);
    expect($r['itens'][0]['subtotal'])->toBe(270.00);
    expect($r['total'])->toBe(270.00);
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. ACABAMENTOS inline (sem catálogo — dados inline)
// ──────────────────────────────────────────────────────────────────────────────

it('Acabamento m_linear (bainha): perímetro 9m × R$8/m × qtd=1 = R$72', function () {
    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
            'acabamentos' => [
                ['descricao' => 'Bainha solda', 'tipo' => 'm_linear', 'preco' => 8.00],
            ],
        ]],
    ]);

    $acab = $r['itens'][0]['acabamentos'][0];
    expect($acab['tipo'])->toBe('m_linear');
    expect($acab['qtd_aplicada'])->toBe(9.0);   // perímetro = 2*(3+1.5) = 9
    expect($acab['custo'])->toBe(72.00);         // 9 × 8 × 1
    expect($r['itens'][0]['custo_acabamentos'])->toBe(72.00);
    expect($r['itens'][0]['subtotal'])->toBe(342.00); // 270 + 72
});

it('Acabamento unitario (ilhós) sem qtd: default perímetro/0,5 = 18 ilhós × R$1,50 = R$27', function () {
    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
            'acabamentos' => [
                ['descricao' => 'Ilhós metálico', 'tipo' => 'unitario', 'preco' => 1.50],
            ],
        ]],
    ]);

    $acab = $r['itens'][0]['acabamentos'][0];
    expect($acab['qtd_aplicada'])->toBe(18);   // ceil(9/0.5)
    expect($acab['custo'])->toBe(27.00);         // 18 × 1.50 × 1
});

it('Acabamento m2 (laminação): área 4.5m² × R$15 = R$67,50', function () {
    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Adesivo', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 30.00,
            'acabamentos' => [
                ['descricao' => 'Laminação UV', 'tipo' => 'm2', 'preco' => 15.00],
            ],
        ]],
    ]);

    expect($r['itens'][0]['acabamentos'][0]['custo'])->toBe(67.50);
    expect($r['itens'][0]['subtotal'])->toBe(202.50); // 4.5×30 = 135 + 67.50
});

it('Acabamento fixo (setup arte): preço one-shot R$50 independente dimensões/qtd', function () {
    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 2.0, 'altura_m' => 1.0,
            'quantidade' => 5, 'preco_unitario_m2' => 50.00,
            'acabamentos' => [
                ['descricao' => 'Setup arte', 'tipo' => 'fixo', 'preco' => 50.00],
            ],
        ]],
    ]);

    expect($r['itens'][0]['acabamentos'][0]['custo'])->toBe(50.00);
});

it('Acabamento inline inválido: tipo desconhecido throw', function () {
    expect(fn () => (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'X', 'largura_m' => 1.0, 'altura_m' => 1.0,
            'quantidade' => 1, 'preco_unitario_m2' => 10.00,
            'acabamentos' => [
                ['descricao' => 'X', 'tipo' => 'xpto', 'preco' => 10.00],
            ],
        ]],
    ]))->toThrow(InvalidArgumentException::class, 'catalogo_id OU');
});

it('Acabamentos múltiplos: soma corretamente em custo_acabamentos do item', function () {
    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner full',
            'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
            'acabamentos' => [
                ['descricao' => 'Bainha',     'tipo' => 'm_linear', 'preco' => 8.00],   // 72
                ['descricao' => 'Ilhós',      'tipo' => 'unitario', 'preco' => 1.50],   // 27
                ['descricao' => 'Laminação',  'tipo' => 'm2',       'preco' => 15.00],  // 67.50
                ['descricao' => 'Setup arte', 'tipo' => 'fixo',     'preco' => 50.00],  // 50
            ],
        ]],
    ]);

    expect($r['itens'][0]['custo_acabamentos'])->toBe(216.50); // 72+27+67.50+50
    expect($r['itens'][0]['subtotal'])->toBe(486.50);          // 270 + 216.50
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. SUBSTRATO (cv_substratos) — DB-dependent (skip gracioso em SQLite)
// ──────────────────────────────────────────────────────────────────────────────

it('Substrato resolve preço + NCM/CFOP/CSOSN snapshot no item output', function () {
    if (! Schema::hasTable('cv_substratos')) {
        $this->markTestSkipped('cv_substratos table missing — rode migrate primeiro.');
    }

    $sub = Substrato::withoutGlobalScopes()->create([
        'business_id'    => V2_BIZ,
        'nome'           => 'Lona FrontLight 440g V2',
        'categoria'      => 'lona',
        'gramatura_g_m2' => 440,
        'preco_venda_m2' => 25.00,
        'ncm'            => '4911.10',
        'cfop_padrao'    => '5101',
        'csosn_padrao'   => '102',
        'ativo'          => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner FL', 'largura_m' => 2.0, 'altura_m' => 1.0,
            'quantidade' => 1, 'substrato_id' => $sub->id,
        ]],
    ]);

    expect($r['itens'][0]['preco_unitario_m2'])->toBe(25.00);
    expect($r['itens'][0]['subtotal'])->toBe(50.00);    // 2m² × 25
    expect($r['itens'][0]['ncm'])->toBe('4911.10');
    expect($r['itens'][0]['cfop_padrao'])->toBe('5101');
    expect($r['itens'][0]['csosn_padrao'])->toBe('102');

    Substrato::withoutGlobalScopes()->where('id', $sub->id)->forceDelete();
});

it('Substrato minimo_m2: peça 0.3 × 0.5 cobra mínimo 0,5m² (regra Calcgraf)', function () {
    if (! Schema::hasTable('cv_substratos')) {
        $this->markTestSkipped('cv_substratos table missing.');
    }

    $sub = Substrato::withoutGlobalScopes()->create([
        'business_id'    => V2_BIZ,
        'nome'           => 'Vinil mínimo V2',
        'categoria'      => 'vinil',
        'preco_venda_m2' => 40.00,
        'minimo_m2'      => 0.500,
        'ativo'          => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Adesivo pequeno', 'largura_m' => 0.3, 'altura_m' => 0.5,
            'quantidade' => 1, 'substrato_id' => $sub->id,
        ]],
    ]);

    // area_m2 real = 0.150, area_cobrar = 0.500
    expect($r['itens'][0]['area_m2'])->toBe(0.150);
    expect($r['itens'][0]['area_cobrar_m2'])->toBe(0.500);
    expect($r['itens'][0]['aplicou_minimo_m2'])->toBeTrue();
    expect($r['itens'][0]['subtotal'])->toBe(20.00);    // 0.5 × 40

    Substrato::withoutGlobalScopes()->where('id', $sub->id)->forceDelete();
});

it('Substrato override: preco_unitario_m2 ganha de substrato.preco_venda_m2', function () {
    if (! Schema::hasTable('cv_substratos')) {
        $this->markTestSkipped('cv_substratos table missing.');
    }

    $sub = Substrato::withoutGlobalScopes()->create([
        'business_id'    => V2_BIZ,
        'nome'           => 'Lona override V2',
        'categoria'      => 'lona',
        'preco_venda_m2' => 30.00,
        'ativo'          => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner',
            'largura_m' => 2.0, 'altura_m' => 1.0, 'quantidade' => 1,
            'substrato_id' => $sub->id,
            'preco_unitario_m2' => 100.00,  // override
        ]],
    ]);

    expect($r['itens'][0]['preco_unitario_m2'])->toBe(100.00);
    expect($r['itens'][0]['subtotal'])->toBe(200.00);

    Substrato::withoutGlobalScopes()->where('id', $sub->id)->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. ACABAMENTOS via catálogo (cv_acabamentos)
// ──────────────────────────────────────────────────────────────────────────────

it('Acabamento via catálogo: lookup catalogo_id resolve tipo+preço', function () {
    if (! Schema::hasTable('cv_acabamentos')) {
        $this->markTestSkipped('cv_acabamentos table missing.');
    }

    $acab = Acabamento::withoutGlobalScopes()->create([
        'business_id' => V2_BIZ,
        'nome'        => 'Bainha solda catálogo V2',
        'tipo'        => 'm_linear',
        'preco'       => 10.00,
        'ativo'       => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao' => '2026-05-13',
        'itens' => [[
            'descricao' => 'Banner',
            'largura_m' => 2.0, 'altura_m' => 1.0, 'quantidade' => 1,
            'preco_unitario_m2' => 30.00,
            'acabamentos' => [
                ['catalogo_id' => $acab->id],
            ],
        ]],
    ]);

    expect($r['itens'][0]['acabamentos'][0]['catalogo_id'])->toBe($acab->id);
    expect($r['itens'][0]['acabamentos'][0]['nome'])->toBe('Bainha solda catálogo V2');
    expect($r['itens'][0]['acabamentos'][0]['tipo'])->toBe('m_linear');
    expect($r['itens'][0]['acabamentos'][0]['custo'])->toBe(60.00); // perímetro 6 × 10

    Acabamento::withoutGlobalScopes()->where('id', $acab->id)->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// 5. INSTALAÇÃO catalogada + NR-35
// ──────────────────────────────────────────────────────────────────────────────

it('Instalação catálogo: preco_base + área×preco_m2 + km×preco_km', function () {
    if (! Schema::hasTable('cv_instalacoes_catalogo')) {
        $this->markTestSkipped('cv_instalacoes_catalogo table missing.');
    }

    $cat = InstalacaoCatalogo::withoutGlobalScopes()->create([
        'business_id' => V2_BIZ,
        'nome'        => 'Fachada simples V2',
        'preco_base'  => 100.00,
        'preco_m2'    => 10.00,
        'preco_km'    => 2.00,
        'exige_nr35'  => false,
        'ativo'       => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao'           => '2026-05-13',
        'instalacao_catalogo_id' => $cat->id,
        'distancia_km'           => 30,
        'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
        ]],
    ]);

    // 100 + (4.5 × 10) + (30 × 2) = 100 + 45 + 60 = 205
    expect($r['custo_instalacao'])->toBe(205.00);
    expect($r['instalacao_breakdown']['preco_base'])->toBe(100.00);
    expect($r['instalacao_breakdown']['preco_m2_aplicado'])->toBe(45.00);
    expect($r['instalacao_breakdown']['preco_km_aplicado'])->toBe(60.00);
    expect($r['alertas'])->toBe([]);

    InstalacaoCatalogo::withoutGlobalScopes()->where('id', $cat->id)->forceDelete();
});

it('NR-35: instalação >2m sem ART/treinamento/ASO gera 3 alertas soft', function () {
    if (! Schema::hasTable('cv_instalacoes_catalogo')) {
        $this->markTestSkipped('cv_instalacoes_catalogo table missing.');
    }

    $cat = InstalacaoCatalogo::withoutGlobalScopes()->create([
        'business_id' => V2_BIZ,
        'nome'        => 'Fachada NR-35 V2',
        'preco_base'  => 250.00,
        'preco_m2'    => 12.00,
        'preco_km'    => 0,
        'exige_nr35'  => true,
        'ativo'       => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao'           => '2026-05-13',
        'instalacao_catalogo_id' => $cat->id,
        'altura_instalacao_m'    => 4.50,  // >2m exige NR-35
        // Sem art_id, sem nr35_validade_instalador, sem aso_validade_instalador → alertas
        'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
        ]],
    ]);

    expect($r['alertas'])->toHaveCount(3);
    expect($r['alertas'][0])->toContain('ART');
    expect($r['alertas'][1])->toContain('treinamento NR-35');
    expect($r['alertas'][2])->toContain('ASO');
    // Custo calculado mesmo com alertas (soft warning, não bloqueia)
    expect($r['custo_instalacao'])->toBe(304.00); // 250 + (4.5×12)

    InstalacaoCatalogo::withoutGlobalScopes()->where('id', $cat->id)->forceDelete();
});

it('NR-35: altura ≤2m mesmo com exige_nr35=true não gera alerta de altura', function () {
    if (! Schema::hasTable('cv_instalacoes_catalogo')) {
        $this->markTestSkipped('cv_instalacoes_catalogo table missing.');
    }

    $cat = InstalacaoCatalogo::withoutGlobalScopes()->create([
        'business_id' => V2_BIZ,
        'nome'        => 'Fachada baixa V2',
        'preco_base'  => 80.00,
        'preco_m2'    => 5.00,
        'preco_km'    => 0,
        'exige_nr35'  => true,
        'ativo'       => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao'           => '2026-05-13',
        'instalacao_catalogo_id' => $cat->id,
        'altura_instalacao_m'    => 1.80,  // <2m
        'itens' => [[
            'descricao' => 'Letreiro pequeno', 'largura_m' => 1.0, 'altura_m' => 0.5,
            'quantidade' => 1, 'preco_unitario_m2' => 100.00,
        ]],
    ]);

    expect($r['alertas'])->toBe([]); // altura abaixo do limiar 2m

    InstalacaoCatalogo::withoutGlobalScopes()->where('id', $cat->id)->forceDelete();
});

it('NR-35: instalação com docs completos não gera alerta mesmo >2m', function () {
    if (! Schema::hasTable('cv_instalacoes_catalogo')) {
        $this->markTestSkipped('cv_instalacoes_catalogo table missing.');
    }

    $cat = InstalacaoCatalogo::withoutGlobalScopes()->create([
        'business_id' => V2_BIZ,
        'nome'        => 'Fachada NR-35 docs OK V2',
        'preco_base'  => 250.00,
        'preco_m2'    => 12.00,
        'preco_km'    => 0,
        'exige_nr35'  => true,
        'ativo'       => true,
    ]);

    $r = (new OrcamentoCalculator())->calcular([
        'data_emissao'             => '2026-05-13',
        'instalacao_catalogo_id'   => $cat->id,
        'altura_instalacao_m'      => 5.00,
        'art_id'                   => 12345,
        'nr35_validade_instalador' => '2027-12-31',
        'aso_validade_instalador'  => '2026-12-31',
        'itens' => [[
            'descricao' => 'Painel fachada', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'preco_unitario_m2' => 60.00,
        ]],
    ]);

    expect($r['alertas'])->toBe([]); // todos docs preenchidos

    InstalacaoCatalogo::withoutGlobalScopes()->where('id', $cat->id)->forceDelete();
});

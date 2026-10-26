<?php
// @covers-us US-COMVIS-001

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-001 — mínimo cobrado por peça e acabamentos no cálculo do orçamento.
 *
 * Âncora: DoD da US-COMVIS-001 no SPEC (*"mínimo cobrado configurável (`material.minimo_m2` —
 * ex: 0,5m² mesmo se peça é menor)"* e *"extras = sum(acabamentos.preco)"*) + a tabela de
 * acabamentos do SPEC §12.1 (tipo m_linear | unitario | m2 | fixo). Casos UC-CV-13 e UC-CV-14
 * do `Index.casos.md`.
 *
 * REGRA MESTRE de valor: cada valor esperado é conta à mão, refeita pelo segundo caminho
 * (`contaMinimo`/`contaAcabamento`, sem banco). O caso 1 prova que um orçamento SEM os campos
 * novos sai com os mesmos números de antes.
 *
 * Tenant 98 contra 99 (ADR 0358). Nunca biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (! Schema::hasColumn('comvis_materiais', 'minimo_m2') || ! Schema::hasTable('cv_acabamentos')) {
        $this->markTestSkipped('Schema da CV sem minimo_m2/cv_acabamentos.');
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $agora = Carbon::now();
    $this->material = fn (int $biz, ?float $minimo) => DB::table('comvis_materiais')->insertGetId([
        'business_id' => $biz, 'nome' => 'PS 2mm ' . uniqid(), 'categoria' => 'placa', 'unidade' => 'm2',
        'preco_custo_m2' => 10, 'preco_venda_m2' => 40, 'minimo_m2' => $minimo, 'ativo' => 1,
        'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $this->acabamento = fn (int $biz, string $tipo, float $preco, bool $ativo = true) => DB::table('cv_acabamentos')->insertGetId([
        'business_id' => $biz, 'nome' => $tipo . ' ' . uniqid(), 'tipo' => $tipo, 'preco' => $preco, 'ativo' => $ativo,
        'created_at' => $agora, 'updated_at' => $agora,
    ]);
});

function cvPayload(array $itens, float $extras = 0.0): array
{
    return [
        'data_emissao' => '2026-10-26', 'data_validade' => null, 'contato_id' => null, 'vendedor_id' => null,
        'desconto' => 0.0, 'extras' => $extras, 'custo_instalacao' => 0.0, 'custo_entrega' => 0.0,
        'observacoes' => null, 'itens' => $itens,
    ];
}

/** Segundo caminho do mínimo: área cobrada por peça = max(L × A, mínimo), vezes a quantidade. */
function contaMinimo(float $l, float $a, int $q, ?float $minimo, float $precoM2): float
{
    $peca = ($minimo !== null && $l * $a < $minimo) ? $minimo : $l * $a;

    return round(round($peca * $q, 3) * $precoM2, 2);
}

it('UC-CV-13 · sem mínimo nem acabamento, o orçamento sai com os mesmos números de antes', function () {
    $r = app(OrcamentoCalculator::class)->calcular(cvPayload([[
        'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5, 'quantidade' => 1, 'preco_unitario_m2' => 60.0,
    ]], 50.0), $this->biz);

    // antes desta mudança: área 4,5 × 60 = 270,00; total = 270 + 50 = 320,00
    expect($r['itens'][0]['area_m2'])->toBe(4.5)
        ->and($r['itens'][0]['area_cobrada_m2'])->toBe(4.5)
        ->and($r['itens'][0]['subtotal'])->toBe(270.0)
        ->and($r['acabamentos'])->toBe(0.0)
        ->and($r['total'])->toBe(320.0);
});

it('UC-CV-13 · peça menor que o mínimo do material é cobrada pelo mínimo; a área física não muda', function () {
    $mat = ($this->material)($this->biz, 0.5);
    $r = app(OrcamentoCalculator::class)->calcular(cvPayload([[
        'material_id' => $mat, 'descricao' => 'Plaquinha', 'largura_m' => 0.3, 'altura_m' => 0.4, 'quantidade' => 5,
    ]]), $this->biz);

    // conta à mão: peça 0,12 m² < 0,5 → cobra 0,5 × 5 = 2,5 m² × 40 = 100,00 (sem mínimo seria 0,6 × 40 = 24,00)
    expect(contaMinimo(0.3, 0.4, 5, 0.5, 40.0))->toBe(100.0);
    expect($r['itens'][0]['area_m2'])->toBe(0.6)
        ->and($r['itens'][0]['area_cobrada_m2'])->toBe(2.5)
        ->and($r['itens'][0]['minimo_m2'])->toBe(0.5)
        ->and($r['itens'][0]['subtotal'])->toBe(100.0)
        ->and($r['total'])->toBe(100.0);
});

it('UC-CV-13 · peça maior que o mínimo segue pela área dela', function () {
    $mat = ($this->material)($this->biz, 0.5);
    $r = app(OrcamentoCalculator::class)->calcular(cvPayload([[
        'material_id' => $mat, 'descricao' => 'Placa', 'largura_m' => 1.0, 'altura_m' => 1.0, 'quantidade' => 2,
    ]]), $this->biz);

    expect(contaMinimo(1.0, 1.0, 2, 0.5, 40.0))->toBe(80.0);
    expect($r['itens'][0]['area_cobrada_m2'])->toBe(2.0)->and($r['itens'][0]['subtotal'])->toBe(80.0);
});

it('UC-CV-13 · material de outra empresa não empresta o mínimo dele [T0]', function () {
    $matOutra = ($this->material)($this->outra, 0.5);
    $r = app(OrcamentoCalculator::class)->calcular(cvPayload([[
        'material_id' => $matOutra, 'descricao' => 'Plaquinha', 'largura_m' => 0.3, 'altura_m' => 0.4,
        'quantidade' => 5, 'preco_unitario_m2' => 40.0,
    ]]), $this->biz);

    expect($r['itens'][0]['minimo_m2'])->toBeNull()
        ->and($r['itens'][0]['area_cobrada_m2'])->toBe(0.6)
        ->and($r['itens'][0]['subtotal'])->toBe(24.0);
});

it('UC-CV-14 · os 4 tipos de acabamento entram no total pela base de cada um', function () {
    $bainha = ($this->acabamento)($this->biz, 'm_linear', 3.0);
    $ilhos = ($this->acabamento)($this->biz, 'unitario', 0.8);
    $lamina = ($this->acabamento)($this->biz, 'm2', 12.0);
    $arte = ($this->acabamento)($this->biz, 'fixo', 35.0);

    $r = app(OrcamentoCalculator::class)->calcular(cvPayload([[
        'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.5, 'quantidade' => 2, 'preco_unitario_m2' => 60.0,
        'acabamentos' => [
            ['acabamento_id' => $bainha],                      // perímetro 2 × (3 + 1,5) × 2 = 18 m × 3,00 = 54,00
            ['acabamento_id' => $ilhos, 'quantidade' => 16],   // 16 × 0,80 = 12,80
            ['acabamento_id' => $lamina],                      // 9 m² × 12,00 = 108,00
            ['acabamento_id' => $arte],                        // 1 × 35,00 = 35,00
        ],
    ]]), $this->biz);

    $item = $r['itens'][0];
    expect(array_column($item['acabamentos'], 'valor'))->toBe([54.0, 12.8, 108.0, 35.0])
        ->and(array_column($item['acabamentos'], 'base'))->toBe([18.0, 16.0, 9.0, 1.0])
        ->and($item['valor_acabamentos'])->toBe(209.8)
        ->and($item['subtotal'])->toBe(540.0)          // o material não muda: 9 m² × 60
        ->and($r['acabamentos'])->toBe(209.8)
        ->and($r['total'])->toBe(749.8);               // 540 + 209,80
});

it('UC-CV-14 · acabamento de outra empresa, inativo ou sem empresa conhecida é recusado [T0]', function () {
    $item = fn (int $id) => [[
        'descricao' => 'Banner', 'largura_m' => 1.0, 'altura_m' => 1.0, 'quantidade' => 1, 'preco_unitario_m2' => 60.0,
        'acabamentos' => [['acabamento_id' => $id]],
    ]];
    $calc = app(OrcamentoCalculator::class);
    $daOutra = ($this->acabamento)($this->outra, 'fixo', 35.0);
    $inativo = ($this->acabamento)($this->biz, 'fixo', 35.0, false);
    $meu = ($this->acabamento)($this->biz, 'fixo', 35.0);

    expect(fn () => $calc->calcular(cvPayload($item($daOutra)), $this->biz))->toThrow(InvalidArgumentException::class, 'não pertence');
    expect(fn () => $calc->calcular(cvPayload($item($inativo)), $this->biz))->toThrow(InvalidArgumentException::class, 'não pertence');
    session()->forget(['user.business_id', 'business.id']);
    expect(fn () => $calc->calcular(cvPayload($item($meu))))->toThrow(InvalidArgumentException::class, 'sem empresa');
    // âncora positiva: o mesmo acabamento, com a empresa certa, passa
    expect($calc->calcular(cvPayload($item($meu)), $this->biz)['acabamentos'])->toBe(35.0);
});

it('UC-CV-14 · gravar guarda a área cobrada, os acabamentos do dia e o total com eles', function () {
    $mat = ($this->material)($this->biz, 0.5);
    $arte = ($this->acabamento)($this->biz, 'fixo', 35.0);
    $calculado = app(OrcamentoCalculator::class)->calcular(cvPayload([[
        'material_id' => $mat, 'descricao' => 'Plaquinha', 'largura_m' => 0.3, 'altura_m' => 0.4, 'quantidade' => 5,
        'acabamentos' => [['acabamento_id' => $arte]],
    ]]), $this->biz);

    $orc = app(OrcamentoGravador::class)->gravar($this->biz, $calculado);
    $cab = DB::table('comvis_orcamentos')->where('id', $orc->id)->first();
    $linha = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->first();

    expect(number_format((float) $cab->acabamentos, 2, '.', ''))->toBe('35.00')
        ->and(number_format((float) $cab->total, 2, '.', ''))->toBe('135.00')   // 100,00 + 35,00
        ->and(number_format((float) $linha->area_m2, 3, '.', ''))->toBe('0.600')
        ->and(number_format((float) $linha->area_cobrada_m2, 3, '.', ''))->toBe('2.500')
        ->and(number_format((float) $linha->valor_acabamentos, 2, '.', ''))->toBe('35.00')
        ->and(json_decode($linha->acabamentos_json, true)[0]['acabamento_id'])->toBe($arte);
});

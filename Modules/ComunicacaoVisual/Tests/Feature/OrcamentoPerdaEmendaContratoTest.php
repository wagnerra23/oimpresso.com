<?php
// @covers-us US-COMVIS-001

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-001 — perda % e emenda no preço da gráfica (UC-CVPRC-03 e UC-CVPRC-04).
 *
 * Âncora: decisão [W] 2026-10-26 registrada no SPEC — perda % por material soma na área cobrada;
 * emenda quando a peça passa da largura da bobina: (N − 1) emendas × lado × preço do metro de solda.
 * Os dois campos são opcionais e orçamento antigo não muda.
 *
 * REGRA MESTRE de valor: cada número é conta à mão e é refeito por `contaPeca`/`contaEmenda`, sem banco.
 * Tenant 98 contra 99 (ADR 0358). Nunca biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (! Schema::hasColumn('comvis_materiais', 'perda_pct') || ! Schema::hasColumn('comvis_materiais', 'minimo_m2')) {
        $this->markTestSkipped('Schema da CV sem perda_pct/minimo_m2.');
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->material = function (int $biz, array $regras): int {
        $agora = Carbon::now();

        return DB::table('comvis_materiais')->insertGetId(array_merge([
            'business_id' => $biz, 'nome' => 'Lona ' . uniqid(), 'categoria' => 'lona', 'unidade' => 'm2',
            'preco_custo_m2' => 10, 'preco_venda_m2' => 40, 'ativo' => 1, 'created_at' => $agora, 'updated_at' => $agora,
        ], $regras));
    };
    $this->calcula = fn (int $mat, float $l, float $a, int $q, array $extra = []) => app(OrcamentoCalculator::class)->calcular([
        'data_emissao' => '2026-10-26', 'data_validade' => null, 'contato_id' => null, 'vendedor_id' => null,
        'desconto' => 0.0, 'extras' => 0.0, 'custo_instalacao' => 0.0, 'custo_entrega' => 0.0, 'observacoes' => null,
        'itens' => [array_merge(['material_id' => $mat, 'descricao' => 'Peça', 'largura_m' => $l, 'altura_m' => $a, 'quantidade' => $q], $extra)],
    ], $this->biz);
});

/** Segundo caminho: valor do material com mínimo e perda, sem banco. */
function contaPeca(float $l, float $a, int $q, ?float $minimo, ?float $perda, float $precoM2): float
{
    $peca = ($minimo !== null && $l * $a < $minimo) ? $minimo : $l * $a;

    return round(round($peca * (1 + ($perda ?? 0) / 100) * $q, 3) * $precoM2, 2);
}

/** Segundo caminho: valor da emenda, sem banco. */
function contaEmenda(float $l, float $a, int $q, float $bobina, float $solda): float
{
    $menor = min($l, $a);
    if ($menor <= $bobina) {
        return 0.0;
    }

    return round(((int) ceil(round($menor / $bobina, 6)) - 1) * max($l, $a) * $q * $solda, 2);
}

it('UC-CVPRC-03 · perda % soma na área cobrada; a área física não muda', function () {
    $mat = ($this->material)($this->biz, ['perda_pct' => 10]);
    $r = ($this->calcula)($mat, 3.0, 1.5, 1)['itens'][0];

    // conta à mão: 4,5 m² × 1,10 = 4,95 m² × 40 = 198,00 (sem perda seria 180,00)
    expect(contaPeca(3.0, 1.5, 1, null, 10, 40))->toBe(198.0);
    expect($r['area_m2'])->toBe(4.5)
        ->and($r['area_cobrada_m2'])->toBe(4.95)
        ->and($r['perda_pct'])->toBe(10.0)
        ->and($r['subtotal'])->toBe(198.0);
});

it('UC-CVPRC-03 · com mínimo e perda, a perda incide sobre o mínimo', function () {
    $mat = ($this->material)($this->biz, ['minimo_m2' => 0.5, 'perda_pct' => 10]);
    $r = ($this->calcula)($mat, 0.3, 0.4, 5)['itens'][0];

    // conta à mão: peça 0,12 < 0,5 → 0,5 × 1,10 × 5 = 2,75 m² × 40 = 110,00
    expect(contaPeca(0.3, 0.4, 5, 0.5, 10, 40))->toBe(110.0);
    expect($r['area_cobrada_m2'])->toBe(2.75)->and($r['subtotal'])->toBe(110.0);
});

it('UC-CVPRC-04 · peça maior que a bobina nos dois lados leva emenda no maior lado', function () {
    $mat = ($this->material)($this->biz, ['largura_bobina_m' => 1.6, 'preco_solda_m' => 2.5]);

    // 5 × 3: menor lado 3 > 1,6 → 2 tiras, 1 emenda de 5 m × 2,50 = 12,50; material 15 m² × 40 = 600,00
    $r = ($this->calcula)($mat, 5.0, 3.0, 1);
    $emenda = collect($r['itens'][0]['acabamentos'])->firstWhere('tipo', 'emenda');
    expect(contaEmenda(5.0, 3.0, 1, 1.6, 2.5))->toBe(12.5);
    expect($emenda['base'])->toBe(5.0)
        ->and($emenda['valor'])->toBe(12.5)
        ->and($r['itens'][0]['subtotal'])->toBe(600.0)
        ->and($r['total'])->toBe(612.5);

    // 4 × 4, 2 peças: 3 tiras, 2 emendas de 4 m × 2 peças = 16 m × 2,50 = 40,00
    $r2 = ($this->calcula)($mat, 4.0, 4.0, 2);
    expect(contaEmenda(4.0, 4.0, 2, 1.6, 2.5))->toBe(40.0);
    expect(collect($r2['itens'][0]['acabamentos'])->firstWhere('tipo', 'emenda')['valor'])->toBe(40.0);

    // 3,2 × 5: exatamente 2 bobinas → 1 emenda (sem erro de ponto flutuante)
    expect(collect(($this->calcula)($mat, 3.2, 5.0, 1)['itens'][0]['acabamentos'])->firstWhere('tipo', 'emenda')['base'])->toBe(5.0);
});

it('UC-CVPRC-04 · sem emenda quando a peça cabe girada, sem preço de solda ou material de outra empresa', function () {
    $mat = ($this->material)($this->biz, ['largura_bobina_m' => 1.6, 'preco_solda_m' => 2.5]);
    $semSolda = ($this->material)($this->biz, ['largura_bobina_m' => 1.6]);
    $daOutra = ($this->material)($this->outra, ['largura_bobina_m' => 1.6, 'preco_solda_m' => 2.5, 'perda_pct' => 10]);

    expect(($this->calcula)($mat, 5.0, 1.5, 1)['itens'][0]['acabamentos'])->toBe([]);      // cabe girada
    expect(($this->calcula)($mat, 1.6, 9.0, 1)['itens'][0]['acabamentos'])->toBe([]);      // igual à bobina
    expect(($this->calcula)($semSolda, 5.0, 3.0, 1)['itens'][0]['acabamentos'])->toBe([]);
    $r = ($this->calcula)($daOutra, 5.0, 3.0, 1, ['preco_unitario_m2' => 40.0])['itens'][0];
    expect($r['acabamentos'])->toBe([])->and($r['area_cobrada_m2'])->toBe(15.0)->and($r['perda_pct'])->toBeNull();
});

it('UC-CVPRC-04 · material sem as regras novas cobra como antes', function () {
    $mat = ($this->material)($this->biz, []);
    $r = ($this->calcula)($mat, 5.0, 3.0, 1);

    expect($r['itens'][0]['area_cobrada_m2'])->toBe(15.0)
        ->and($r['itens'][0]['acabamentos'])->toBe([])
        ->and($r['total'])->toBe(600.0);
});

it('UC-CVPRC-04 · gravar guarda a emenda e reabrir não a devolve como acabamento escolhido', function () {
    $mat = ($this->material)($this->biz, ['largura_bobina_m' => 1.6, 'preco_solda_m' => 2.5]);
    $orc = app(OrcamentoGravador::class)->gravar($this->biz, ($this->calcula)($mat, 5.0, 3.0, 1));

    $linha = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->first();
    expect(number_format((float) $linha->valor_acabamentos, 2, '.', ''))->toBe('12.50')
        ->and(json_decode($linha->acabamentos_json, true)[0]['tipo'])->toBe('emenda')
        ->and(number_format((float) DB::table('comvis_orcamentos')->where('id', $orc->id)->value('total'), 2, '.', ''))->toBe('612.50');
    expect(OrcamentoController::paraTela($this->biz, (int) $orc->id)['itens'][0]['acabamentos'])->toBe([]);
});

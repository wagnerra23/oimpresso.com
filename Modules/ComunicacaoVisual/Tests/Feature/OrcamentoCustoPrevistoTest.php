<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-019 · UC-FLX-33 — o item do orçamento da CV guarda o custo do material no dia.
 *
 * Âncora: memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-019. Os valores esperados são
 * conta à mão (área × custo do m² do cadastro) e cada caso refaz a conta pelo segundo caminho
 * (custoItemEsperado, sem banco). O preço, o subtotal e o total do orçamento não podem mudar.
 *
 * Arranjo (tenant 98):
 *   Lona     custo 8,50/m²   →  item 2 × 1,5 × 2 = 6,000 m²  → 51,00
 *                               item 0,8 × 0,6 × 3 = 1,440 m² → 12,24
 *   Vinil    custo 0 (cadastro sem custo)  → nulo
 *   sem material                            → nulo
 *   ACM do tenant 99, custo 3,00            → nulo (nunca usa material de outra empresa)
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $agora = Carbon::now();
    $material = fn (int $biz, string $nome, float $custo) => DB::table('comvis_materiais')->insertGetId([
        'business_id' => $biz, 'nome' => $nome.' '.uniqid(), 'categoria' => 'lona', 'unidade' => 'm2',
        'preco_custo_m2' => $custo, 'preco_venda_m2' => 30, 'ativo' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $this->lona = $material($this->biz->id, 'Lona', 8.5);
    $this->vinil = $material($this->biz->id, 'Vinil', 0);
    $this->acmOutra = $material($this->outra->id, 'ACM', 3);

    $item = fn (?int $materialId, float $l, float $a, int $q) => [
        'material_id' => $materialId, 'descricao' => 'Item', 'largura_m' => $l, 'altura_m' => $a, 'quantidade' => $q,
        'area_m2' => round($l * $a * $q, 3), 'preco_unitario_m2' => 30.0,
        'subtotal' => round(round($l * $a * $q, 3) * 30, 2), 'observacoes' => null,
    ];
    $this->itens = [
        $item($this->lona, 2, 1.5, 2),
        $item($this->lona, 0.8, 0.6, 3),
        $item($this->vinil, 1, 1, 1),
        $item(null, 1, 1, 1),
        $item($this->acmOutra, 1, 1, 1),
    ];
    $subtotal = round(array_sum(array_column($this->itens, 'subtotal')), 2);
    $this->calculado = [
        'data_emissao' => '2026-10-19', 'data_validade' => null, 'contato_id' => null, 'vendedor_id' => null,
        'observacoes' => null, 'subtotal' => $subtotal, 'desconto' => 0.0, 'extras' => 0.0,
        'custo_instalacao' => 0.0, 'custo_entrega' => 0.0, 'total' => $subtotal, 'itens' => $this->itens,
    ];
});

/** Segundo caminho: área × custo do m², arredondado a 2 casas, sem banco. */
function custoItemEsperado(float $area, ?float $custoM2): ?string
{
    return $custoM2 === null ? null : number_format(round($area * $custoM2, 2, PHP_ROUND_HALF_UP), 2, '.', '');
}

it('US-COMVIS-019 · item com material grava o custo do m² do dia e o custo do item', function () {
    $orc = app(OrcamentoGravador::class)->gravar($this->biz->id, $this->calculado);
    $itens = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->get();

    expect(custoItemEsperado(6.0, 8.5))->toBe('51.00')->and(custoItemEsperado(1.44, 8.5))->toBe('12.24');
    expect(number_format((float) $itens[0]->custo_previsto, 2, '.', ''))->toBe('51.00')
        ->and(number_format((float) $itens[0]->custo_previsto_m2, 2, '.', ''))->toBe('8.50')
        ->and(number_format((float) $itens[1]->custo_previsto, 2, '.', ''))->toBe('12.24')
        ->and(Carbon::parse($itens[0]->custo_previsto_em)->toDateString())->toBe(now()->toDateString());
});

it('US-COMVIS-019 · custo zero, item sem material e material de outra empresa ficam sem custo', function () {
    $orc = app(OrcamentoGravador::class)->gravar($this->biz->id, $this->calculado);
    $itens = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->get();

    foreach ([2, 3, 4] as $i) {
        expect($itens[$i]->custo_previsto)->toBeNull()
            ->and($itens[$i]->custo_previsto_m2)->toBeNull()
            ->and($itens[$i]->custo_previsto_em)->toBeNull();
    }
});

it('US-COMVIS-019 · mudar o custo no cadastro depois não mexe no orçamento gravado', function () {
    $orc = app(OrcamentoGravador::class)->gravar($this->biz->id, $this->calculado);

    DB::table('comvis_materiais')->where('id', $this->lona)->update(['preco_custo_m2' => 12]);

    $item = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->first();
    expect(number_format((float) $item->custo_previsto, 2, '.', ''))->toBe('51.00')
        ->and(number_format((float) $item->custo_previsto_m2, 2, '.', ''))->toBe('8.50');
});

it('US-COMVIS-019 · preço, subtotal e total do orçamento continuam os calculados', function () {
    $orc = app(OrcamentoGravador::class)->gravar($this->biz->id, $this->calculado);
    $gravado = DB::table('comvis_orcamentos')->where('id', $orc->id)->first();
    $itens = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->get();

    // 6,000 + 1,440 + 1 + 1 + 1 = 10,44 m² × 30,00 = 313,20
    expect(number_format((float) $gravado->subtotal, 2, '.', ''))->toBe('313.20')
        ->and(number_format((float) $gravado->total, 2, '.', ''))->toBe('313.20')
        ->and(number_format((float) $itens[0]->subtotal, 2, '.', ''))->toBe('180.00')
        ->and(number_format((float) $itens[0]->preco_unitario_m2, 2, '.', ''))->toBe('30.00');
});

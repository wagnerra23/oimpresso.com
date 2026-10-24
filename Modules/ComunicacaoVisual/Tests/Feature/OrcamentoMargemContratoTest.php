<?php
// @covers-us US-COMVIS-022

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\MargemDoOrcamento;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-022 · UC-FLX-03 — custo e margem por item na calculadora de m².
 *
 * Âncora: UC-FLX-03 do catálogo de fluxos (margem por item no orçamento) e SPEC US-COMVIS-022.
 * Contrato: Index.casos.md UC-CVMRG-01..06. Regra mestre de valor: preço, subtotal e total
 * NÃO mudam; cada número é conferido à mão e refeito pela fórmula (dois caminhos).
 *
 * Arranjo (tenant 98 contra 99), Lona custo 8,50/m², venda 30,00/m²:
 *   item A 2,000 × 1,500 × 2 = 6,000 m² → preço 180,00 · custo 51,00 · margem 129,00 (71,7%)
 *   item B 0,800 × 0,600 × 3 = 1,440 m² → preço  43,20 · custo 12,24 · margem  30,96 (71,7%)
 *   subtotal 223,20 − desconto 20,00 = receita 203,20 · custo 63,24 · margem 139,96 (68,9%)
 *   extras 50,00 → total 253,20 (fora da margem)
 * ⚠️ SKIP em SQLite (lane sem as tabelas comvis_*): leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_os_itens')) {
        $this->markTestSkipped('Requer MySQL com as tabelas comvis_* migradas.');
    }
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
    $this->semCusto = $material($this->biz->id, 'Vinil', 0);
    $this->daOutra = $material($this->outra->id, 'ACM', 3);

    $this->item = fn (?int $m, float $l, float $a, int $q) => [
        'material_id' => $m, 'descricao' => 'Peça', 'largura_m' => $l, 'altura_m' => $a, 'quantidade' => $q, 'preco_unitario_m2' => 30,
    ];
    $this->payload = ['data_emissao' => '2026-10-21', 'desconto' => 20, 'extras' => 50,
        'itens' => [($this->item)($this->lona, 2, 1.5, 2), ($this->item)($this->lona, 0.8, 0.6, 3)]];
});

/** Segundo caminho: a fórmula da US-COMVIS-022 sem banco nem serviço. */
function cvmrgMargem(float $area, float $precoM2, float $custoM2): array
{
    $preco = round(round($area, 3) * $precoM2, 2);
    $custo = round($area * $custoM2, 2);

    return [$preco, $custo, round($preco - $custo, 2), round(($preco - $custo) / $preco * 100, 1)];
}

function cvmrgAplicar($test, array $payload, ?int $biz = null): array
{
    return app(MargemDoOrcamento::class)->aplicar($biz ?? $test->biz->id, app(OrcamentoCalculator::class)->calcular($payload));
}

it('UC-CVMRG-01 · cada peça e o orçamento mostram custo e margem, conferidos por dois caminhos', function () {
    $r = cvmrgAplicar($this, $this->payload);

    expect(cvmrgMargem(6.0, 30, 8.5))->toBe([180.0, 51.0, 129.0, 71.7])
        ->and(cvmrgMargem(1.44, 30, 8.5))->toBe([43.2, 12.24, 30.96, 71.7]);

    expect($r['itens'][0]['custo'])->toBe(51.0)
        ->and($r['itens'][0]['margem'])->toBe(129.0)
        ->and($r['itens'][0]['margem_pct'])->toBe(71.7)
        ->and($r['itens'][1]['custo'])->toBe(12.24)
        ->and($r['itens'][1]['margem'])->toBe(30.96)
        ->and($r['margem'])->toBe([
            'itens_com_custo' => 2, 'itens' => 2,
            'custo' => 63.24, 'receita' => 203.2, 'margem' => 139.96, 'margem_pct' => 68.9,
        ])
        // 2º caminho do total: (223,20 − 20 − 63,24) e a % sobre a receita.
        ->and(round(223.2 - 20 - (51 + 12.24), 2))->toBe(139.96)
        ->and(round(139.96 / 203.2 * 100, 1))->toBe(68.9);
});

it('UC-CVMRG-02 · preço, subtotal e total são os mesmos com e sem a margem', function () {
    $sem = app(OrcamentoCalculator::class)->calcular($this->payload);
    $com = cvmrgAplicar($this, $this->payload);

    expect($com['total'])->toBe($sem['total'])
        ->and($com['total'])->toBe(253.2)
        ->and($com['subtotal'])->toBe($sem['subtotal'])
        ->and(array_column($com['itens'], 'subtotal'))->toBe(array_column($sem['itens'], 'subtotal'))
        ->and(array_column($com['itens'], 'preco_unitario_m2'))->toBe(array_column($sem['itens'], 'preco_unitario_m2'));
});

it('UC-CVMRG-03 · peça sem custo cadastrado fica sem margem, e o orçamento diz quantas têm custo', function () {
    $payload = $this->payload;
    $payload['itens'][] = ($this->item)($this->semCusto, 1, 1, 1);
    $payload['itens'][] = ($this->item)(null, 1, 1, 1);
    $r = cvmrgAplicar($this, $payload);

    expect($r['itens'][2]['custo'])->toBeNull()
        ->and($r['itens'][2]['margem'])->toBeNull()
        ->and($r['itens'][3]['custo'])->toBeNull()
        ->and($r['margem']['itens_com_custo'])->toBe(2)
        ->and($r['margem']['itens'])->toBe(4)
        ->and($r['margem']['margem'])->toBeNull()
        ->and($r['margem']['custo'])->toBeNull();
});

it('UC-CVMRG-04 · o custo de material de outra empresa nunca entra', function () {
    $r = cvmrgAplicar($this, ['data_emissao' => '2026-10-21', 'itens' => [($this->item)($this->daOutra, 1, 1, 1)]]);

    expect($r['itens'][0]['custo'])->toBeNull()
        ->and($r['margem']['margem'])->toBeNull()
        // A mesma peça, lida pela empresa dona do material, tem custo.
        ->and(cvmrgAplicar($this, ['data_emissao' => '2026-10-21', 'itens' => [($this->item)($this->daOutra, 1, 1, 1)]], $this->outra->id)['itens'][0]['custo'])
        ->toBe(3.0);
});

it('UC-CVMRG-05 · só quem gerencia materiais recebe custo e margem ao conferir', function () {
    $calcular = function (array $permissoes) {
        $user = $this->usuarioComPermissoes($permissoes, $this->biz);
        $this->actingAs($user);
        session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id, 'user.id' => $user->id]);

        return app()->call([app(OrcamentoController::class), 'calcular'], [
            'request' => Request::create('/comunicacao-visual/api/calcular', 'POST', $this->payload),
        ])->getData(true);
    };

    $balcao = $calcular(['comvis.orcamento.create']);
    $gestor = $calcular(['comvis.orcamento.create', 'comvis.material.manage']);

    expect($balcao)->not->toHaveKey('margem')
        ->and($balcao['itens'][0])->not->toHaveKey('custo')
        ->and($gestor['margem']['margem'])->toEqual(139.96)
        ->and($gestor['total'])->toEqual($balcao['total']);
});

it('UC-CVMRG-06 · o custo que a tela mostra é o que o orçamento grava', function () {
    $calculado = app(OrcamentoCalculator::class)->calcular($this->payload);
    $mostrado = app(MargemDoOrcamento::class)->aplicar($this->biz->id, $calculado);
    $orc = app(OrcamentoGravador::class)->gravar($this->biz->id, $calculado);

    $gravado = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->pluck('custo_previsto')
        ->map(fn ($v) => (float) $v)->all();
    expect($gravado)->toBe(array_column($mostrado['itens'], 'custo'));
});

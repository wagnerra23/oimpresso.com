<?php
// @covers-us US-COMVIS-019
// Cobre UC-CVMAQ-01..05 (resources/js/Pages/ComunicacaoVisual/Index.casos.md) — UC-FLX-33.

declare(strict_types=1);

use App\Contracts\Producao\CustoHoraDoPosto;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-019 · UC-FLX-33 — o item do orçamento da CV guarda o custo da hora de máquina no dia.
 *
 * Âncora: memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-019 (aceite "hora de máquina").
 * Os esperados são conta à mão e cada caso refaz a conta pelo segundo caminho
 * (custoMaquinaEsperado, sem banco). O preço, o subtotal e o total do cliente não mudam.
 *
 * Arranjo (tenant 98 contra 99):
 *   Plotter  máquina 42,00/h  → item com 2,50 h = 2,5 × 42 = 105,00
 *                               item com 0,75 h = 0,75 × 42 = 31,50
 *   Acabador pessoa   30,00/h → nulo (hora de pessoa não entra)
 *   Router   máquina 0,00/h   → nulo (custo não informado)
 *   Plotter  do tenant 99     → nulo (nunca usa posto de outra empresa)
 *   sem posto                 → nulo
 * ⚠️ SKIP sem as tabelas pcp_* (lane sem Modules/Producao): leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('pcp_postos')) {
        $this->markTestSkipped('Requer MySQL com as tabelas pcp_* (Modules/Producao).');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $posto = fn (int $biz, string $tipo, string $custo) => DB::table('pcp_postos')->insertGetId([
        'business_id' => $biz, 'codigo' => 'P'.uniqid(), 'nome' => 'Posto', 'tipo_custo' => $tipo,
        'custo_hora' => $custo, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->plotter = $posto($this->biz->id, 'maquina', '42.00');
    $this->acabador = $posto($this->biz->id, 'pessoa', '30.00');
    $this->router = $posto($this->biz->id, 'maquina', '0.00');
    $this->plotterOutra = $posto($this->outra->id, 'maquina', '42.00');

    $item = fn (?int $postoId, ?float $horas) => [
        'descricao' => 'Banner', 'largura_m' => 2, 'altura_m' => 1, 'quantidade' => 1,
        'preco_unitario_m2' => 30.0, 'posto_id' => $postoId, 'horas_maquina' => $horas,
    ];
    $this->payload = [
        'data_emissao' => '2026-10-20', 'custo_instalacao' => 50, 'custo_entrega' => 0,
        'itens' => [
            $item($this->plotter, 2.5),
            $item($this->plotter, 0.75),
            $item($this->acabador, 2),
            $item($this->router, 2),
            $item($this->plotterOutra, 2),
            $item(null, null),
        ],
    ];
});

/** Segundo caminho: horas × custo da hora, arredondado a 2 casas, sem banco. */
function custoMaquinaEsperado(float $horas, float $custoHora): string
{
    return number_format(round($horas * $custoHora, 2, PHP_ROUND_HALF_UP), 2, '.', '');
}

function gravarComMaquina(array $payload, int $biz): array
{
    $orc = app(OrcamentoGravador::class)->gravar($biz, app(OrcamentoCalculator::class)->calcular($payload));

    return [$orc, DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->get()];
}

it('UC-CVMAQ-01 · item com posto de máquina grava horas, custo da hora e custo da máquina do dia', function () {
    expect(app()->bound(CustoHoraDoPosto::class))->toBeTrue();
    [, $itens] = gravarComMaquina($this->payload, $this->biz->id);

    expect(custoMaquinaEsperado(2.5, 42))->toBe('105.00')->and(custoMaquinaEsperado(0.75, 42))->toBe('31.50');
    expect((int) $itens[0]->posto_id)->toBe($this->plotter)
        ->and(number_format((float) $itens[0]->horas_maquina_previstas, 2, '.', ''))->toBe('2.50')
        ->and(number_format((float) $itens[0]->custo_hora_previsto, 2, '.', ''))->toBe('42.00')
        ->and(number_format((float) $itens[0]->custo_maquina_previsto, 2, '.', ''))->toBe('105.00')
        ->and(number_format((float) $itens[1]->custo_maquina_previsto, 2, '.', ''))->toBe('31.50')
        ->and(Carbon::parse($itens[0]->custo_previsto_em)->toDateString())->toBe(now()->toDateString());
});

it('UC-CVMAQ-02 · posto de pessoa, sem custo, de outra empresa ou item sem posto ficam sem custo de máquina', function () {
    [, $itens] = gravarComMaquina($this->payload, $this->biz->id);

    foreach ([2, 3, 4, 5] as $i) {
        expect($itens[$i]->posto_id)->toBeNull()
            ->and($itens[$i]->horas_maquina_previstas)->toBeNull()
            ->and($itens[$i]->custo_hora_previsto)->toBeNull()
            ->and($itens[$i]->custo_maquina_previsto)->toBeNull()
            ->and($itens[$i]->custo_previsto_em)->toBeNull();
    }
});

it('UC-CVMAQ-03 · mudar o custo da hora do posto depois não mexe no orçamento gravado', function () {
    [$orc] = gravarComMaquina($this->payload, $this->biz->id);

    DB::table('pcp_postos')->where('id', $this->plotter)->update(['custo_hora' => '99.00']);

    $item = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->orderBy('ordem')->first();
    expect(number_format((float) $item->custo_hora_previsto, 2, '.', ''))->toBe('42.00')
        ->and(number_format((float) $item->custo_maquina_previsto, 2, '.', ''))->toBe('105.00');
});

it('UC-CVMAQ-04 · hora de máquina não muda preço, subtotal nem total do cliente', function () {
    $semMaquina = $this->payload;
    foreach ($semMaquina['itens'] as $k => $it) {
        unset($semMaquina['itens'][$k]['posto_id'], $semMaquina['itens'][$k]['horas_maquina']);
    }
    $com = app(OrcamentoCalculator::class)->calcular($this->payload);
    $sem = app(OrcamentoCalculator::class)->calcular($semMaquina);

    // 6 itens × (2 × 1 × 1 = 2 m²) × 30,00 = 360,00; + instalação 50,00 = 410,00
    expect($com['subtotal'])->toBe(360.0)->and($com['total'])->toBe(410.0)
        ->and($sem['total'])->toBe($com['total'])
        ->and(array_column($sem['itens'], 'subtotal'))->toBe(array_column($com['itens'], 'subtotal'));

    [$orc] = gravarComMaquina($this->payload, $this->biz->id);
    $gravado = DB::table('comvis_orcamentos')->where('id', $orc->id)->first();
    expect(number_format((float) $gravado->total, 2, '.', ''))->toBe('410.00')
        ->and(number_format((float) $gravado->subtotal, 2, '.', ''))->toBe('360.00');
});

it('UC-CVMAQ-05 · a validação recusa posto de outra empresa e aceita o da empresa', function () {
    session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id]);
    $validar = function (int $postoId) {
        $m = new ReflectionMethod(OrcamentoController::class, 'validarPayload');
        $payload = ['data_emissao' => '2026-10-20', 'itens' => [[
            'descricao' => 'Banner', 'largura_m' => 1, 'altura_m' => 1, 'quantidade' => 1,
            'preco_unitario_m2' => 30, 'posto_id' => $postoId, 'horas_maquina' => 1,
        ]]];

        return $m->invoke(app(OrcamentoController::class), Request::create('/x', 'POST', $payload));
    };

    expect($validar($this->plotter)['itens'][0]['posto_id'])->toBe($this->plotter);

    try {
        $validar($this->plotterOutra);
        $this->fail('Posto de outra empresa deveria ser recusado.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('itens.0.posto_id');
    }
});

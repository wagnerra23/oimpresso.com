<?php

declare(strict_types=1);

use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Modelos\CatalogoModelos;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * "Usar modelo" (ADR 0435 itens 5–8, decisão [W] 2026-10-16).
 *
 * Contrato vindo da ADR, não do código: aplicar monta no motor os processos do modelo (etapas na
 * ordem do protótipo, passo, Cancelado, avançar/cancelar, regras como dado); aplicar de novo não
 * duplica nada; o processo legado e a venda em andamento ficam intactos; outra empresa não é
 * tocada; nenhuma ação recebe side effect enquanto as regras estão planejadas.
 */
uses(DatabaseTransactions::class);

/** @return array<string, int> contagens do motor de um business */
function flxContagem(int $biz): array
{
    $proc = DB::table('sale_processes')->where('business_id', $biz)->pluck('id');
    $stages = DB::table('sale_process_stages')->whereIn('process_id', $proc)->pluck('id');
    $acoes = DB::table('sale_stage_actions')->whereIn('stage_id', $stages)->pluck('id');

    return [
        'processos' => $proc->count(),
        'etapas' => $stages->count(),
        'acoes' => $acoes->count(),
        'papeis' => DB::table('sale_stage_action_roles')->whereIn('action_id', $acoes)->count(),
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('fsm_modelos_empresa') || ! Schema::hasColumn('sale_process_stages', 'passo')) {
        $this->markTestSkipped('Migrations do modelo de fluxo ausentes.');
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = (int) $ctx['business']->id;
    $this->venda = (int) $ctx['transactionId'];
    $this->outroBiz = (int) (\App\Business::where('id', '!=', $this->biz)->value('id') ?? 0);
    if ($this->outroBiz === 0) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    // Empresa no legado, com uma venda em andamento na etapa "Em produção".
    (new FsmProcessoVendaComProducaoSeeder())->runForBusiness($this->biz);
    $this->legado = DB::table('sale_processes')->where('business_id', $this->biz)->where('key', 'venda_com_producao')->first();
    $emProducao = DB::table('sale_process_stages')->where('process_id', $this->legado->id)->where('key', 'in_production')->value('id');
    DB::table('transactions')->where('id', $this->venda)->update(['process_id' => $this->legado->id, 'current_stage_id' => $emProducao]);
});

it('monta os processos do modelo com etapas na ordem, passo, Cancelado e regras planejadas', function () {
    $ids = app(AplicarModelo::class)->aplicar($this->biz, 'cv-pequena', 'pequena', 'cv');

    expect(array_keys($ids))->toBe(['venda', 'producao', 'compra']);

    $producao = DB::table('sale_processes')->find($ids['producao']);
    expect($producao->business_id)->toBe($this->biz)
        ->and($producao->key)->toBe('flx_cv_pequena_producao')
        ->and($producao->dominio)->toBe('producao')
        ->and($producao->modelo)->toBe('cv-pequena');

    $etapas = DB::table('sale_process_stages')->where('process_id', $ids['producao'])->orderBy('sort_order')->get();
    expect($etapas->pluck('name')->all())
        ->toBe(['Arte', 'Aprovação do cliente', 'Impressão', 'Acabamento', 'Instalação', 'Entregue', 'Cancelado'])
        ->and($etapas->pluck('passo_ordem')->all())->toBe([0, 1, 2, 3, 4, 5, null])
        ->and((bool) $etapas[0]->is_initial)->toBeTrue()
        ->and((bool) $etapas[5]->is_terminal)->toBeTrue()
        ->and((bool) $etapas[6]->is_cancelled)->toBeTrue()
        ->and((bool) $etapas[2]->is_producao)->toBeTrue();

    $regras = json_decode((string) $etapas[1]->regras_ao_entrar, true);
    expect($regras)->toEqual([['uc' => 'UC-FLX-02', 'efeito' => 'Link de aprovação; recusa volta para Arte', 'estado' => 'planejado']]);

    // Arte → Aprovação do cliente: avançar leva à vizinha; cancelar é crítico e tem papel.
    $acoes = DB::table('sale_stage_actions')->where('stage_id', $etapas[0]->id)->get()->keyBy('key');
    expect($acoes['avancar']->target_stage_id)->toBe($etapas[1]->id)
        ->and($acoes['cancelar']->target_stage_id)->toBe($etapas[6]->id)
        ->and((bool) $acoes['cancelar']->is_critical)->toBeTrue()
        ->and(DB::table('sale_stage_action_roles')->where('action_id', $acoes['cancelar']->id)->count())->toBe(1);

    // Regras todas planejadas: nenhuma ação dos processos do modelo executa side effect.
    $stagesModelo = DB::table('sale_process_stages')->whereIn('process_id', array_values($ids))->pluck('id');
    expect(DB::table('sale_stage_actions')->whereIn('stage_id', $stagesModelo)->whereNotNull('side_effect_class')->count())->toBe(0);

    $escolha = DB::table('fsm_modelos_empresa')->where('business_id', $this->biz)->first();
    expect($escolha->modelo)->toBe('cv-pequena')->and($escolha->porte)->toBe('pequena')->and($escolha->atividade)->toBe('cv');
});

it('aplicar duas vezes reaproveita tudo — nada duplica', function () {
    $servico = app(AplicarModelo::class);
    $primeira = $servico->aplicar($this->biz, 'balcao', 'micro', 'rapida');
    $antes = flxContagem($this->biz);

    $segunda = $servico->aplicar($this->biz, 'balcao', 'micro', 'rapida');

    expect($segunda)->toBe($primeira)
        ->and(flxContagem($this->biz))->toBe($antes)
        ->and(DB::table('fsm_modelos_empresa')->where('business_id', $this->biz)->count())->toBe(1);
});

it('o processo legado e a venda em andamento ficam como estavam', function () {
    $etapasLegado = fn () => DB::table('sale_process_stages')->where('process_id', $this->legado->id)->orderBy('id')->get()->map(fn ($s) => (array) $s)->all();
    $acoesLegado = fn () => DB::table('sale_stage_actions')->whereIn('stage_id', DB::table('sale_process_stages')->where('process_id', $this->legado->id)->pluck('id'))->orderBy('id')->get()->map(fn ($a) => (array) $a)->all();
    $venda = fn () => DB::table('transactions')->where('id', $this->venda)->first(['process_id', 'current_stage_id', 'status']);

    [$e, $a, $v, $p] = [$etapasLegado(), $acoesLegado(), $venda(), (array) $this->legado];

    app(AplicarModelo::class)->aplicar($this->biz, 'cv-pequena', 'pequena', 'cv');

    expect($etapasLegado())->toBe($e)
        ->and($acoesLegado())->toBe($a)
        ->and($venda())->toEqual($v)
        ->and((array) DB::table('sale_processes')->find($this->legado->id))->toBe($p);
});

it('isola por empresa: a outra empresa não ganha processo nem escolha', function () {
    $antes = flxContagem($this->outroBiz);
    $escolhaAntes = DB::table('fsm_modelos_empresa')->where('business_id', $this->outroBiz)->first();

    app(AplicarModelo::class)->aplicar($this->biz, 'grafica', 'media', 'offset');

    expect(flxContagem($this->outroBiz))->toBe($antes)
        ->and(DB::table('sale_processes')->where('business_id', $this->outroBiz)->where('key', 'like', 'flx_%')->count())->toBe(0)
        ->and(DB::table('fsm_modelos_empresa')->where('business_id', $this->outroBiz)->first())->toEqual($escolhaAntes)
        ->and(DB::table('sale_processes')->where('business_id', $this->biz)->where('key', 'like', 'flx_grafica_%')->count())
        ->toBe(count(CatalogoModelos::modelo('grafica')['processos']));
});

it('recusa modelo, porte ou empresa desconhecidos sem gravar nada', function () {
    $antes = flxContagem($this->biz);
    $servico = app(AplicarModelo::class);

    expect(fn () => $servico->aplicar($this->biz, 'nao-existe'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $servico->aplicar($this->biz, 'balcao', 'gigante'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $servico->aplicar(999999, 'balcao'))->toThrow(InvalidArgumentException::class)
        ->and(flxContagem($this->biz))->toBe($antes);
});

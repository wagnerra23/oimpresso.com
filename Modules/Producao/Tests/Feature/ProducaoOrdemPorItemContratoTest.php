<?php

declare(strict_types=1);
// Cobre o processo "OS e produção por item" de fluxos-processos.jsx (id producao) — ADR 0436, PR 1.

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Scopes\ScopeByBusiness;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * A fonte é o protótipo aprovado pelo [W] em 16/10/2026 (#9517): estados e transições do processo
 * `producao` em fluxos-processos.jsx — não o código. Tenant 98 contra 99 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
});

function ordemNova(int $biz, int $stageId, int $linha): Ordem
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    return Ordem::withoutGlobalScopes()->create(['business_id' => $biz, 'transaction_id' => 900000 + $linha,
        'transaction_sell_line_id' => $linha, 'product_id' => 1, 'quantidade' => '2.0000', 'current_stage_id' => $stageId]);
}

it('monta os 8 estados e as 12 transições do protótipo (+ o Liberar automático pelo sinal, US-PCP-025), e aplicar de novo não duplica', function () {
    $svc = app(ProcessoDaOrdem::class);
    $p1 = $svc->garantir($this->b98);
    $p2 = $svc->garantir($this->b98);

    expect($p2->id)->toBe($p1->id);
    $estados = SaleProcessStage::where('process_id', $p1->id)->orderBy('sort_order')->pluck('name')->all();
    // Aguardando material (US-PCP-032): estado aprovado por [W] em 2026-10-21 (D3). Entrou no protótipo
    // fluxos-processos.jsx em 2026-10-28, com 3 ações: aguardar, conferir (no motor, "Material
    // disponível") e cancelar item.
    expect($estados)->toBe(['Aguardando liberação', 'Aguardando material', 'Na fila', 'Em execução', 'Pausada', 'Retrabalho', 'Concluída', 'Cancelada']);
    $acoes = SaleStageAction::whereIn('stage_id', SaleProcessStage::where('process_id', $p1->id)->pluck('id'))->count();
    expect($acoes)->toBe(13);
    expect(SaleProcessStage::where('process_id', $p1->id)->where('key', 'cancelada')->value('is_cancelled'))->toBeTruthy();
});

it('Tier 0: cada empresa tem o próprio processo e o motor recusa OS de uma empresa na etapa da outra', function () {
    $svc = app(ProcessoDaOrdem::class);
    $p98 = $svc->garantir($this->b98);
    $p99 = $svc->garantir($this->b99);
    expect($p99->id)->not->toBe($p98->id);
    expect((int) $p99->business_id)->toBe($this->b99);

    $ordem = ordemNova($this->b98, $svc->etapaInicial($this->b99)->id, 7101);
    $user = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    expect(fn () => app(ExecuteStageActionService::class)->execute($ordem, 'liberar', $user))
        ->toThrow(UnauthorizedActionException::class, 'Cross-tenant');
});

it('Liberar leva Aguardando liberação a Na fila pelo motor e grava o histórico da empresa', function () {
    $svc = app(ProcessoDaOrdem::class);
    $ordem = ordemNova($this->b98, $svc->etapaInicial($this->b98)->id, 7102);
    $user = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $user->assignRole('producao.liberar#'.$this->b98);

    app(ExecuteStageActionService::class)->execute($ordem, 'liberar', $user);

    expect($ordem->fresh()->currentStage->name)->toBe('Na fila');
    $hist = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->where('transaction_id', $ordem->id)
        ->where('business_id', $this->b98)->latest('id')->first();
    expect($hist)->not->toBeNull();
    expect((int) $hist->to_stage_id)->toBe((int) $ordem->fresh()->current_stage_id);
});

it('sem o papel, Liberar é recusado e a situação não muda', function () {
    $svc = app(ProcessoDaOrdem::class);
    $inicial = $svc->etapaInicial($this->b98);
    $ordem = ordemNova($this->b98, $inicial->id, 7103);
    $user = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    expect(fn () => app(ExecuteStageActionService::class)->execute($ordem, 'liberar', $user))
        ->toThrow(UnauthorizedActionException::class);
    expect((int) $ordem->fresh()->current_stage_id)->toBe((int) $inicial->id);
});

it('a situação não muda por UPDATE direto (só pelo motor)', function () {
    $svc = app(ProcessoDaOrdem::class);
    $ordem = ordemNova($this->b98, $svc->etapaInicial($this->b98)->id, 7104);
    $fila = SaleProcessStage::where('process_id', $svc->garantir($this->b98)->id)->where('key', 'na_fila')->firstOrFail();

    $ordem->current_stage_id = $fila->id;
    expect(fn () => $ordem->save())->toThrow(UnauthorizedActionException::class);
});

it('uma OS por item: a mesma linha da venda não gera duas OS na empresa', function () {
    $inicial = app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id;
    ordemNova($this->b98, $inicial, 7105);

    expect(fn () => ordemNova($this->b98, $inicial, 7105))->toThrow(QueryException::class);
});

it('a programação do PCP guarda a posição da etapa no roteiro e o posto é opcional', function () {
    foreach (['roteiro_etapa_id', 'bloco', 'trilha', 'ordem', 'nome', 'junta'] as $col) {
        expect(Schema::hasColumn('pcp_programacoes', $col))->toBeTrue();
    }
    $posto = collect(Schema::getColumns('pcp_programacoes'))->firstWhere('name', 'posto_id');
    expect($posto['nullable'])->toBeTrue();
});

it('as ações do apontamento não pedem papel no motor; as de tela pedem', function () {
    $p = app(ProcessoDaOrdem::class)->garantir($this->b98);
    $acoes = SaleStageAction::with('roles')->whereIn('stage_id', SaleProcessStage::where('process_id', $p->id)->pluck('id'))->get()->keyBy('key');

    foreach (['iniciar_etapa', 'pausar', 'retomar', 'concluir_etapa', 'concluir_item', 'liberar_pelo_sinal'] as $k) {
        expect($acoes[$k]->roles)->toHaveCount(0);
        expect((bool) $acoes[$k]->is_critical)->toBeFalse();
    }
    foreach (['liberar', 'reprovar', 'refazer', 'cancelar_item'] as $k) {
        expect($acoes[$k]->roles->pluck('role_name')->first())->toEndWith('#'.$this->b98);
    }
});

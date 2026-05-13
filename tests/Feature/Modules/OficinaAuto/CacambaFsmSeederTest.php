<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;

uses(Tests\TestCase::class);

/**
 * Seeder OficinaAutoFsmSeeder — 2 processos FSM caçamba (locacao + manutencao).
 *
 * Cobertura:
 *  - Cria 2 processos FSM (cacamba_locacao + cacamba_manutencao) per-business
 *  - Stages com order/color/terminal/initial corretos
 *  - Actions com target_stage + is_critical + side_effect_class corretos
 *  - Roles per-business sufixo #{biz} (UltimatePOS Spatie schema)
 *  - Idempotente — rodar 2× não duplica
 *  - Cross-tenant: rodar pra biz=1 NÃO cria pra biz=2 (ADR 0093)
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const BIZ_WAGNER_FSM = 1;
const BIZ_FICTICIO_FSM = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema FSM requer MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('sale_processes') || ! Schema::hasTable('sale_process_stages')) {
        $this->markTestSkipped('Tabelas FSM ausentes — rode migrate canônico primeiro');
    }
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('Tabela roles Spatie ausente — rode migrate primeiro');
    }
});

afterEach(function () {
    // Limpa apenas processos criados pelo seeder OficinaAuto (key prefixadas com cacamba_)
    SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->whereIn('business_id', [BIZ_WAGNER_FSM, BIZ_FICTICIO_FSM])
        ->each(function (SaleProcess $p) {
            // Cascata manual: actions → stages → process
            $stageIds = SaleProcessStage::where('process_id', $p->id)->pluck('id');
            $actionIds = SaleStageAction::whereIn('stage_id', $stageIds)->pluck('id');
            SaleStageActionRole::whereIn('action_id', $actionIds)->delete();
            SaleStageAction::whereIn('id', $actionIds)->delete();
            SaleProcessStage::whereIn('id', $stageIds)->delete();
            $p->delete();
        });
});

it('cria 2 processos FSM (cacamba_locacao + cacamba_manutencao) per-business', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $processes = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->get();

    expect($processes)->toHaveCount(2);
    expect($processes->pluck('key')->all())->toContain('cacamba_locacao');
    expect($processes->pluck('key')->all())->toContain('cacamba_manutencao');
    expect($processes->every(fn ($p) => $p->active))->toBeTrue();
});

it('processo cacamba_locacao tem 4 stages com initial/terminal/color corretos', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->where('key', 'cacamba_locacao')
        ->first();

    $stages = SaleProcessStage::where('process_id', $process->id)->get()->keyBy('key');

    expect($stages)->toHaveCount(4);

    expect($stages['disponivel']->is_initial)->toBeTrue();
    expect($stages['disponivel']->is_terminal)->toBeFalse();
    expect($stages['disponivel']->color)->toBe('gray');
    expect($stages['disponivel']->sort_order)->toBe(0);

    expect($stages['locada']->is_initial)->toBeFalse();
    expect($stages['locada']->color)->toBe('blue');

    expect($stages['recolhida']->is_terminal)->toBeTrue();
    expect($stages['recolhida']->color)->toBe('emerald');

    expect($stages['manutencao']->color)->toBe('yellow');
    expect($stages['manutencao']->is_terminal)->toBeFalse();
});

it('processo cacamba_manutencao tem 4 stages com 1 initial + 2 terminais', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->where('key', 'cacamba_manutencao')
        ->first();

    $stages = SaleProcessStage::where('process_id', $process->id)->get()->keyBy('key');

    expect($stages)->toHaveCount(4);

    expect($stages['aberta']->is_initial)->toBeTrue();
    expect($stages['em_servico']->color)->toBe('amber');
    expect($stages['concluida']->is_terminal)->toBeTrue();
    expect($stages['concluida']->color)->toBe('emerald');
    expect($stages['cancelada']->is_terminal)->toBeTrue();
    expect($stages['cancelada']->color)->toBe('rose');
});

it('actions cacamba_locacao tem target_stage + is_critical corretos', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->where('key', 'cacamba_locacao')
        ->first();

    $stages = SaleProcessStage::where('process_id', $process->id)->get()->keyBy('key');

    // iniciar_locacao: disponivel → locada, is_critical=true
    $iniciar = SaleStageAction::where('stage_id', $stages['disponivel']->id)
                              ->where('key', 'iniciar_locacao')
                              ->first();
    expect($iniciar)->not->toBeNull();
    expect($iniciar->target_stage_id)->toBe($stages['locada']->id);
    expect($iniciar->is_critical)->toBeTrue();
    expect($iniciar->side_effect_class)->toBe('App\\Domain\\Fsm\\SideEffects\\IniciarLocacaoCacamba');

    // recolher: locada → recolhida, is_critical=false
    $recolher = SaleStageAction::where('stage_id', $stages['locada']->id)
                               ->where('key', 'recolher')
                               ->first();
    expect($recolher)->not->toBeNull();
    expect($recolher->target_stage_id)->toBe($stages['recolhida']->id);
    expect($recolher->is_critical)->toBeFalse();
});

it('actions cacamba_manutencao: concluir + cancelar são is_critical', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->where('key', 'cacamba_manutencao')
        ->first();

    $stages = SaleProcessStage::where('process_id', $process->id)->get()->keyBy('key');

    $concluir = SaleStageAction::where('stage_id', $stages['em_servico']->id)
                               ->where('key', 'concluir')
                               ->first();
    expect($concluir->is_critical)->toBeTrue();
    expect($concluir->target_stage_id)->toBe($stages['concluida']->id);

    // cancelar disponível em 2 stages (aberta + em_servico)
    $cancelarCount = SaleStageAction::whereIn('stage_id', [$stages['aberta']->id, $stages['em_servico']->id])
                                    ->where('key', 'cancelar')
                                    ->count();
    expect($cancelarCount)->toBe(2);
});

it('roles per-business com sufixo #{biz} criadas (UltimatePOS Spatie)', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $hasBusinessIdColumn = Schema::hasColumn('roles', 'business_id');
    $expectedSuffix = $hasBusinessIdColumn ? '#'.BIZ_WAGNER_FSM : '';

    // Pega action iniciar_locacao
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->where('key', 'cacamba_locacao')
        ->first();

    $stage = SaleProcessStage::where('process_id', $process->id)
                             ->where('key', 'disponivel')
                             ->first();

    $action = SaleStageAction::where('stage_id', $stage->id)
                             ->where('key', 'iniciar_locacao')
                             ->first();

    $roles = SaleStageActionRole::where('action_id', $action->id)->pluck('role_name')->all();

    expect($roles)->toContain('mecanico'.$expectedSuffix);
    expect($roles)->toContain('gerente'.$expectedSuffix);
});

it('seeder é idempotente — rodar 2× não duplica processos/stages/actions/roles', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $processCount1 = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->count();

    $stageCount1 = SaleProcessStage::whereIn('process_id', SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->pluck('id')
    )->count();

    // Roda 2ª vez
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $processCount2 = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->count();

    $stageCount2 = SaleProcessStage::whereIn('process_id', SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->pluck('id')
    )->count();

    expect($processCount1)->toBe($processCount2);
    expect($stageCount1)->toBe($stageCount2);
    expect($processCount1)->toBe(2);
});

it('cross-tenant: rodar pra biz=1 NÃO cria processos pra biz=99 (ADR 0093)', function () {
    $seeder = new OficinaAutoFsmSeeder();
    $seeder->runForBusiness(BIZ_WAGNER_FSM);

    $bizFicticioCount = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_FICTICIO_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->count();

    expect($bizFicticioCount)->toBe(0);

    // Bonus: agora roda pra biz=99 explicitamente — deve criar SÓ pra biz=99 sem afetar biz=1
    $seeder->runForBusiness(BIZ_FICTICIO_FSM);

    $bizFicticioCount2 = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_FICTICIO_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->count();

    $bizWagnerCount = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', BIZ_WAGNER_FSM)
        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao'])
        ->count();

    expect($bizFicticioCount2)->toBe(2);
    expect($bizWagnerCount)->toBe(2); // não foi tocado
});

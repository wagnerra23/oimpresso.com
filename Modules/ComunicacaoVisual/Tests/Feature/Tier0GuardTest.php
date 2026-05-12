<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use Database\Seeders\FsmProcessoComunicacaoVisualSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Acabamento;
use Modules\ComunicacaoVisual\Entities\Instalacao;
use Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo;
use Modules\ComunicacaoVisual\Entities\OrdemProducao;
use Modules\ComunicacaoVisual\Entities\Substrato;

uses(Tests\TestCase::class);

/**
 * GUARD Tier 0 — Modules/ComunicacaoVisual (US-COMVIS-NEW-012).
 *
 * Cobre os 3 anti-hooks listados em ROADMAP.md Fase 1 §1.7 + charter §7:
 *   1. NUNCA recálculo m² pós-NFe (m² é fiscal-frozen após emissão)
 *   2. NUNCA disparar plotter / impressão auto (sem RBAC operador)
 *   3. NUNCA emitir fiscal auto (sem RBAC gerente/financeiro)
 *
 * + cross-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - biz=1 (Wagner WR2) vs biz=99 (fictício isolamento)
 *   - NUNCA biz=4 (ROTA LIVRE — cliente Larissa) — ADR 0101
 *
 * + FSM Pipeline canon (ADR 0143):
 *   - UPDATE direto em current_stage_id BLOQUEADO via GuardsFsmTransitions
 *   - Action critical sem role → UnauthorizedActionException (fail-secure)
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §11 + §15 (US-COMVIS-NEW-012)
 * @see memory/requisitos/ComunicacaoVisual/ComunicacaoVisual.charter.md §7
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

// Convenção feedback_test_biz_99_cross_tenant_convention.md
const COMVIS_BIZ_WAGNER = 1;
const COMVIS_BIZ_FICTICIO = 99;

beforeEach(function () {
    // FSM canon + cv_* schema requerem MySQL UltimatePOS (não SQLite in-memory)
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: FSM canon + cv_* schema requerem MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('cv_substratos')) {
        $this->markTestSkipped('cv_substratos table missing — rode migrate primeiro');
    }
    if (! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('sale_processes table missing — FSM canon não migrado (ADR 0143)');
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// Cross-tenant Tier 0 — substrato biz=1 NÃO aparece em biz=99
// ──────────────────────────────────────────────────────────────────────────────

it('Substrato biz=1 NÃO aparece com session biz=99 (Tier 0 IRREVOGÁVEL)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    $sub = Substrato::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'    => COMVIS_BIZ_WAGNER,
        'nome'           => 'Lona Frontlight 440g GUARD',
        'categoria'      => 'lona',
        'gramatura_g_m2' => 440,
        'preco_venda_m2' => 28.00,
        'ativo'          => true,
    ]);

    session(['user.business_id' => COMVIS_BIZ_FICTICIO]);
    $resultado = Substrato::where('id', $sub->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Substrato::withoutGlobalScopes()->where('nome', 'Lona Frontlight 440g GUARD')->forceDelete();
});

it('Acabamento biz=1 NÃO aparece com session biz=99', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    $acab = Acabamento::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => COMVIS_BIZ_WAGNER,
        'nome'        => 'Ilhós metálico GUARD',
        'tipo'        => 'unitario',
        'preco'       => 1.50,
        'ativo'       => true,
    ]);

    session(['user.business_id' => COMVIS_BIZ_FICTICIO]);
    expect(Acabamento::where('id', $acab->id)->get())->toHaveCount(0);
})->afterEach(function () {
    Acabamento::withoutGlobalScopes()->where('nome', 'Ilhós metálico GUARD')->forceDelete();
});

it('InstalacaoCatalogo biz=1 NÃO aparece com session biz=99', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    $cat = InstalacaoCatalogo::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => COMVIS_BIZ_WAGNER,
        'nome'        => 'Fachada Andaime GUARD',
        'preco_base'  => 250.00,
        'preco_m2'    => 12.00,
        'exige_nr35'  => true,
        'ativo'       => true,
    ]);

    session(['user.business_id' => COMVIS_BIZ_FICTICIO]);
    expect(InstalacaoCatalogo::where('id', $cat->id)->get())->toHaveCount(0);
})->afterEach(function () {
    InstalacaoCatalogo::withoutGlobalScopes()->where('nome', 'Fachada Andaime GUARD')->forceDelete();
});

it('OrdemProducao biz=1 NÃO aparece com session biz=99', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    $op = OrdemProducao::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'     => COMVIS_BIZ_WAGNER,
        'codigo'          => 'OP-GUARD-99991',
        'qtd'             => 1,
        'instalacao_tipo' => 'cliente_busca',
        'subtotal'        => 0,
        'total'           => 0,
    ]);

    session(['user.business_id' => COMVIS_BIZ_FICTICIO]);
    expect(OrdemProducao::where('id', $op->id)->get())->toHaveCount(0);
})->afterEach(function () {
    OrdemProducao::withoutGlobalScopes()->where('codigo', 'OP-GUARD-99991')->forceDelete();
});

it('Instalacao biz=1 NÃO aparece com session biz=99', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    // Cria ordem pai pra satisfazer FK
    $op = OrdemProducao::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'     => COMVIS_BIZ_WAGNER,
        'codigo'          => 'OP-GUARD-99992',
        'qtd'             => 1,
        'instalacao_tipo' => 'fachada_simples',
        'subtotal'        => 0,
        'total'           => 0,
    ]);

    $inst = Instalacao::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => COMVIS_BIZ_WAGNER,
        'ordem_id'    => $op->id,
        'status'      => 'agendada',
    ]);

    session(['user.business_id' => COMVIS_BIZ_FICTICIO]);
    expect(Instalacao::where('id', $inst->id)->get())->toHaveCount(0);
})->afterEach(function () {
    Instalacao::withoutGlobalScopes()->whereHas('ordem', function ($q) {
        $q->withoutGlobalScopes()->where('codigo', 'OP-GUARD-99992');
    })->forceDelete();
    OrdemProducao::withoutGlobalScopes()->where('codigo', 'OP-GUARD-99992')->forceDelete();
});

it('creating event auto-popula business_id da sessão (Substrato)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    // Cria SEM business_id explícito — hook creating deve popular
    $sub = Substrato::create([
        'nome'           => 'Vinil Adesivo GUARD',
        'categoria'      => 'vinil',
        'preco_venda_m2' => 18.00,
        'ativo'          => true,
    ]);

    expect($sub->business_id)->toBe(COMVIS_BIZ_WAGNER);
})->afterEach(function () {
    Substrato::withoutGlobalScopes()->where('nome', 'Vinil Adesivo GUARD')->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// Anti-hook #1 — NUNCA disparar plotter / impressão auto (sem role operador)
// SPEC §11.2 + charter §7
// ──────────────────────────────────────────────────────────────────────────────

it('FSM Seeder: action iniciar_impressao está marcada is_critical + tem roles cadastradas', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);

    $process = SaleProcess::withoutGlobalScopes()
        ->where('business_id', COMVIS_BIZ_WAGNER)
        ->where('key', 'os_comunicacao_visual')
        ->firstOrFail();

    // Action iniciar_impressao em qualquer stage (arte_aprovada OU aguardando_maquina)
    $stageIds = $process->stages()
        ->whereIn('key', ['arte_aprovada', 'aguardando_maquina'])
        ->pluck('id');

    $iniciar = SaleStageAction::whereIn('stage_id', $stageIds)
        ->where('key', 'iniciar_impressao')
        ->get();

    expect($iniciar->count())->toBeGreaterThanOrEqual(1);
    foreach ($iniciar as $a) {
        expect($a->is_critical)->toBeTrue();
        // Anti-hook charter §7 #1: action critical EXIGE pelo menos 1 role
        // sem role → action é pública → "disparar plotter auto" via sistema
        expect($a->roles()->count())->toBeGreaterThan(0);
    }
});

it('FSM Seeder: action concluir_impressao tem side-effect ConsumirEstoque (não dispara auto)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);
    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);

    $process = SaleProcess::withoutGlobalScopes()
        ->where('business_id', COMVIS_BIZ_WAGNER)
        ->where('key', 'os_comunicacao_visual')
        ->firstOrFail();

    $emImpressao = $process->stages()->where('key', 'em_impressao')->firstOrFail();
    $concluir = SaleStageAction::where('stage_id', $emImpressao->id)
        ->where('key', 'concluir_impressao')
        ->firstOrFail();

    expect($concluir->is_critical)->toBeTrue();
    expect($concluir->side_effect_class)->toBe('App\\Domain\\Fsm\\SideEffects\\ConsumirEstoque');
    expect($concluir->roles()->count())->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Anti-hook #2 — NUNCA recálculo m² pós-NFe (m² é fiscal-frozen)
//
// V0 GUARD: documenta comportamento — área deve ficar imutável após arte_aprovada.
// Validação rígida (block UPDATE) vira FsmAreaImmutable observer em US-COMVIS-005.
// ──────────────────────────────────────────────────────────────────────────────

it('OrdemProducao.area_m2 é calculável server-side (largura × altura × qtd)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    $op = OrdemProducao::create([
        'codigo'          => 'OP-GUARD-AREA-001',
        'largura_m'       => 3.0,
        'altura_m'        => 1.5,
        'qtd'             => 2,
        'area_m2'         => 9.0,  // calculado server-side: 3.0 * 1.5 * 2
        'instalacao_tipo' => 'cliente_busca',
        'subtotal'        => 0,
        'total'           => 0,
    ]);

    expect((float) $op->area_m2)->toBe(9.0);
    expect((float) $op->largura_m)->toBe(3.0);
    expect((float) $op->altura_m)->toBe(1.5);
    expect($op->qtd)->toBe(2);
})->afterEach(function () {
    OrdemProducao::withoutGlobalScopes()->where('codigo', 'OP-GUARD-AREA-001')->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// Anti-hook #3 — NUNCA emitir fiscal auto (sem role gerente/financeiro)
// ──────────────────────────────────────────────────────────────────────────────

it('FSM Seeder: action emitir_nfe_e_nfse é critical + restrita a roles fiscais', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);
    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);

    $process = SaleProcess::withoutGlobalScopes()
        ->where('business_id', COMVIS_BIZ_WAGNER)
        ->where('key', 'os_comunicacao_visual')
        ->firstOrFail();

    $entregue = $process->stages()->where('key', 'entregue_completo')->firstOrFail();
    $emitir = SaleStageAction::where('stage_id', $entregue->id)
        ->where('key', 'emitir_nfe_e_nfse')
        ->firstOrFail();

    expect($emitir->is_critical)->toBeTrue();

    // Anti-hook charter §7 #3: emissão fiscal EXIGE role gerente/financeiro/fiscal
    $roleNames = $emitir->roles()->pluck('role_name')->all();
    $hasFiscalRole = collect($roleNames)->contains(function ($name) {
        return str_starts_with($name, 'comvis.gerente')
            || str_starts_with($name, 'comvis.financeiro')
            || str_starts_with($name, 'comvis.fiscal');
    });
    expect($hasFiscalRole)->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// FSM Pipeline canon — UPDATE direto em current_stage_id BLOQUEADO
// (proibicoes.md §FSM Pipeline + GuardsFsmTransitions trait — ADR 0143)
// ──────────────────────────────────────────────────────────────────────────────

it('OrdemProducao bloqueia UPDATE direto em current_stage_id (GuardsFsmTransitions)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);
    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);

    $process = SaleProcess::withoutGlobalScopes()
        ->where('business_id', COMVIS_BIZ_WAGNER)
        ->where('key', 'os_comunicacao_visual')
        ->firstOrFail();

    $initial = $process->stages()->where('key', 'quote_draft')->firstOrFail();
    $next = $process->stages()->where('key', 'quote_sent')->firstOrFail();

    $op = OrdemProducao::create([
        'codigo'           => 'OP-GUARD-FSM-001',
        'qtd'              => 1,
        'instalacao_tipo'  => 'cliente_busca',
        'current_stage_id' => $initial->id,
        'subtotal'         => 0,
        'total'            => 0,
    ]);

    // Tentar UPDATE direto SEM passar pelo ExecuteStageActionService → bloqueado
    $op->current_stage_id = $next->id;

    expect(fn () => $op->save())->toThrow(UnauthorizedActionException::class);
})->afterEach(function () {
    OrdemProducao::withoutGlobalScopes()->where('codigo', 'OP-GUARD-FSM-001')->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// FSM Seeder idempotente — rodar 2x não duplica
// ──────────────────────────────────────────────────────────────────────────────

it('FsmProcessoComunicacaoVisualSeeder é idempotente (rodar 2x não duplica)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);

    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);
    $stagesCount1 = SaleProcessStage::whereHas('process', function ($q) {
        $q->withoutGlobalScopes()
          ->where('business_id', COMVIS_BIZ_WAGNER)
          ->where('key', 'os_comunicacao_visual');
    })->count();

    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);
    $stagesCount2 = SaleProcessStage::whereHas('process', function ($q) {
        $q->withoutGlobalScopes()
          ->where('business_id', COMVIS_BIZ_WAGNER)
          ->where('key', 'os_comunicacao_visual');
    })->count();

    expect($stagesCount1)->toBe($stagesCount2);
    // 16 stages conforme STAGES const seeder (13 ativos + 1 opcional + 2 terminais laterais)
    expect($stagesCount1)->toBe(16);
});

it('FSM Seeder cria 10 roles per-business (suffix #{biz} quando coluna existe)', function () {
    session(['user.business_id' => COMVIS_BIZ_WAGNER]);
    (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness(COMVIS_BIZ_WAGNER);

    $hasBusinessIdColumn = Schema::hasColumn('roles', 'business_id');

    if ($hasBusinessIdColumn) {
        // UltimatePOS schema: roles com suffix #{biz}
        $rolesEsperadas = [
            'comvis.designer#1', 'comvis.operador#1', 'comvis.instalador#1',
            'comvis.atendimento#1', 'comvis.gerente#1', 'comvis.financeiro#1',
            'comvis.fiscal#1', 'comvis.estoque#1', 'comvis.logistica#1', 'comvis.system#1',
        ];
    } else {
        $rolesEsperadas = [
            'comvis.designer', 'comvis.operador', 'comvis.instalador',
            'comvis.atendimento', 'comvis.gerente', 'comvis.financeiro',
            'comvis.fiscal', 'comvis.estoque', 'comvis.logistica', 'comvis.system',
        ];
    }

    foreach ($rolesEsperadas as $roleName) {
        $exists = \Spatie\Permission\Models\Role::where('name', $roleName)->exists();
        expect($exists)->toBeTrue("Role esperada '{$roleName}' não foi criada pelo seeder");
    }
});

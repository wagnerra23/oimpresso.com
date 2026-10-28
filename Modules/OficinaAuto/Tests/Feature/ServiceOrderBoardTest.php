<?php
// @covers-us US-OFICINA-004
// @covers-us US-AUTO-005

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Http\Controllers\ServiceOrderController;

uses(Tests\TestCase::class);

/**
 * GUARD + smoke do Board (Kanban) de OS de mecânica — port do protótipo Cowork do
 * carro ([W] 2026-06-02). Processo FSM real `oficina_mecanica_os` (NÃO caçamba).
 *
 * Canon coberto (review ProducaoOficina/Index.review.md):
 *   1. Seeder cria oficina_mecanica_os com 6 etapas de board + 3 terminais.
 *   2. GUARD: drag entre colunas avança via ExecuteStageActionService (move
 *      current_stage_id + grava sale_stage_history) — NUNCA UPDATE direto.
 *   3. Transição inválida lança InvalidActionForCurrentStageException.
 *   4. Multi-tenant Tier 0 (ADR 0093): subject de outro tenant não transiciona no processo do tenant de teste.
 *   5. board() agrupa OS por etapa em colunas (só não-terminais) + exclui terminal.
 *   6. Card aplica mods [W]: dvi_done/dvi_total + thumb_url null sem foto.
 *   7. Smoke resiliente: fluxo feliz recepcao→…→pronto_retirada (5 transições).
 *
 * Pattern: skip SQLite (ADR 0101 — requer schema MySQL UltimatePOS).
 *
 * Tenant 98 (ADR 0358) e adversário 2 (2º tenant do seed da lane, criado pra Tier 0
 * cross-tenant). Outros arquivos da suíte também deixam OS no 98, então contagem e KPI
 * são medidos como DELTA (depois − antes) e os cards são achados pelo id da OS criada
 * aqui, nunca pela posição na coluna.
 */

const BIZ_BOARD = 98;
const BIZ_BOARD_FICT = 2;
const PLATE_PREFIX = 'BRD';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    foreach (['service_orders', 'vehicles', 'sale_processes', 'sale_process_stages', 'sale_stage_actions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode migrate OficinaAuto/FSM primeiro");
        }
    }
    if (! Schema::hasColumn('service_orders', 'current_stage_id')) {
        $this->markTestSkipped('Migration current_stage_id pendente');
    }

    $user = new User();
    $user->id = 1;
    $user->username = 'board-test-user';
    $user->business_id = BIZ_BOARD;
    $user->exists = true;
    $this->actingAs($user);
    Gate::before(fn () => true);
});

// ─── Helpers ────────────────────────────────────────────────────────────────

function boardProcess(int $bizId): SaleProcess
{
    return SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)
        ->where('key', 'oficina_mecanica_os')
        ->firstOrFail();
}

/** @return array<string, SaleProcessStage> key → stage */
function boardStages(int $bizId): array
{
    return SaleProcessStage::where('process_id', boardProcess($bizId)->id)
        ->get()->keyBy('key')->all();
}

function boardVehicle(int $bizId, string $plate): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([
        'business_id' => $bizId, 'plate' => $plate, 'vehicle_type' => 'caminhao',
    ]);
}

function boardOs(int $bizId, string $plate, ?int $stageId, string $orderType = 'mecanica'): ServiceOrder
{
    $v = boardVehicle($bizId, $plate);
    // current_stage_id não é fillable: num create([...]) ele é descartado em silêncio e a
    // OS nasce sem etapa. Atribuir direto no INSERT é permitido (a trava GuardsFsmTransitions
    // só olha UPDATE) — mesmo caminho do OficinaBoardDemoCommand.
    $os = new ServiceOrder();
    $os->forceFill([
        'business_id'      => $bizId,
        'vehicle_id'       => $v->id,
        'order_type'       => $orderType,
        'status'           => 'aberta',
        'entered_at'       => now(),
        'current_stage_id' => $stageId,
    ])->save();

    return $os;
}

/** total_items é accessor (soma dos itens): o valor da OS vem de um item, não de update(). */
function boardItem(ServiceOrder $os, float $valor): void
{
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => $os->business_id,
        'service_order_id' => $os->id,
        'tipo'             => 'mao_obra',
        'descricao'        => 'Item board-test',
        'quantidade'       => 1,
        'valor_unitario'   => $valor,
    ]);
}

/** Card da OS no quadro, achado pelo id (a coluna também tem OS de outros arquivos). */
function boardCard(array $props, string $columnKey, int $osId): ?array
{
    $col = collect($props['columns'])->firstWhere('key', $columnKey);

    return collect($col['cards'] ?? [])->firstWhere('id', $osId);
}

/** @return array<string, int> key da coluna → count */
function boardCounts(array $props): array
{
    return collect($props['columns'])->mapWithKeys(fn ($c) => [$c['key'] => $c['count']])->all();
}

/** Cria user real e atribui EXATAMENTE as roles exigidas pela action (suffix-agnóstico). */
function boardUserForAction(int $bizId, SaleStageAction $action): User
{
    $u = User::forceCreate([
        'surname'     => 'Mec',
        'first_name'  => 'Teste' . substr((string) random_int(1000, 9999), 0, 4),
        'username'    => 'boardmec_' . uniqid(),
        'password'    => bcrypt('x'),
        'business_id' => $bizId,
    ]);
    foreach ($action->roles->pluck('role_name')->all() as $rn) {
        $u->assignRole($rn); // roles já criadas pelo seeder
    }
    return $u;
}

function boardCleanup(): void
{
    ServiceOrder::withoutGlobalScopes()
        ->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'like', PLATE_PREFIX . '%'))
        ->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'like', PLATE_PREFIX . '%')->forceDelete();
    User::withoutGlobalScopes()->where('username', 'like', 'boardmec_%')->forceDelete();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. seeder cria oficina_mecanica_os com 6 etapas de board + 3 terminais', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);

    $process = boardProcess(BIZ_BOARD);
    expect($process)->not->toBeNull();

    $stages = $process->stages()->orderBy('sort_order')->get();
    $boardKeys = $stages->where('is_terminal', false)->pluck('key')->values()->all();
    expect($boardKeys)->toBe([
        'recepcao', 'em_diagnostico', 'aguardando_aprovacao',
        'aguardando_pecas', 'em_execucao', 'pronto_retirada',
    ]);

    $terminals = $stages->where('is_terminal', true)->pluck('key')->sort()->values()->all();
    expect($terminals)->toBe(['cancelado', 'entregue', 'garantia_acionada']);

    expect($stages->firstWhere('is_initial', true)->key)->toBe('recepcao');
})->afterEach(fn () => boardCleanup());

it('2. GUARD — drag avança via ExecuteStageActionService (move stage + grava history)', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $os = boardOs(BIZ_BOARD, PLATE_PREFIX . '001', $stages['recepcao']->id);

    $action = SaleStageAction::with('roles')
        ->where('stage_id', $stages['recepcao']->id)->where('key', 'iniciar_diagnostico')->firstOrFail();
    $user = boardUserForAction(BIZ_BOARD, $action);

    $history = (new ExecuteStageActionService)->execute($os, 'iniciar_diagnostico', $user);

    // Stage moveu pela via canônica (nunca UPDATE direto)
    expect($os->fresh()->current_stage_id)->toBe($stages['em_diagnostico']->id);
    expect($history->from_stage_id)->toBe($stages['recepcao']->id);
    expect($history->to_stage_id)->toBe($stages['em_diagnostico']->id);

    // Auditoria gravada em sale_stage_history
    $logged = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('transaction_id', $os->id)->where('to_stage_id', $stages['em_diagnostico']->id)->exists();
    expect($logged)->toBeTrue();
})->afterEach(fn () => boardCleanup());

it('3. transição inválida lança InvalidActionForCurrentStageException', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $os = boardOs(BIZ_BOARD, PLATE_PREFIX . '002', $stages['recepcao']->id);
    $action = SaleStageAction::with('roles')
        ->where('stage_id', $stages['recepcao']->id)->where('key', 'iniciar_diagnostico')->firstOrFail();
    $user = boardUserForAction(BIZ_BOARD, $action);

    // concluir_servico não existe a partir de recepcao
    expect(fn () => (new ExecuteStageActionService)->execute($os, 'concluir_servico', $user))
        ->toThrow(InvalidActionForCurrentStageException::class);

    expect($os->fresh()->current_stage_id)->toBe($stages['recepcao']->id);
})->afterEach(fn () => boardCleanup());

it('4. multi-tenant Tier 0 — subject de outro tenant não transiciona em processo do tenant de teste', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    // OS forjada em outro tenant apontando pra stage do processo do tenant de teste (ataque cross-tenant)
    $os = boardOs(BIZ_BOARD_FICT, PLATE_PREFIX . '099', $stages['recepcao']->id);
    $action = SaleStageAction::with('roles')
        ->where('stage_id', $stages['recepcao']->id)->where('key', 'iniciar_diagnostico')->firstOrFail();
    $user = boardUserForAction(BIZ_BOARD, $action);

    expect(fn () => (new ExecuteStageActionService)->execute($os, 'iniciar_diagnostico', $user))
        ->toThrow(UnauthorizedActionException::class);
})->afterEach(fn () => boardCleanup());

it('5. board() agrupa OS por etapa em colunas (só não-terminais)', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $antes = boardCounts(invokeBoard());

    $osRecepcao = boardOs(BIZ_BOARD, PLATE_PREFIX . '010', $stages['recepcao']->id);
    $osExecucao = boardOs(BIZ_BOARD, PLATE_PREFIX . '011', $stages['em_execucao']->id);
    // OS em etapa terminal NÃO deve aparecer no quadro
    $entregueOs = boardOs(BIZ_BOARD, PLATE_PREFIX . '012', $stages['entregue']->id);
    // OS mecanica sem pipeline cai na coluna inicial com in_pipeline=false
    $osSemPipeline = boardOs(BIZ_BOARD, PLATE_PREFIX . '013', null);

    $props = invokeBoard();
    $depois = boardCounts($props);

    $columns = collect($props['columns']);
    expect($columns)->toHaveCount(6); // só etapas não-terminais
    expect($columns->pluck('key')->all())->toBe([
        'recepcao', 'em_diagnostico', 'aguardando_aprovacao',
        'aguardando_pecas', 'em_execucao', 'pronto_retirada',
    ]);

    // recepcao ganhou a OS em recepcao + a OS sem pipeline; execução ganhou 1; a terminal não entra
    expect($depois['recepcao'] - $antes['recepcao'])->toBe(2);
    expect($depois['em_execucao'] - $antes['em_execucao'])->toBe(1);
    expect(array_sum($depois) - array_sum($antes))->toBe(3);
    expect(boardCard($props, 'recepcao', $osRecepcao->id))->not->toBeNull();
    expect(boardCard($props, 'em_execucao', $osExecucao->id))->not->toBeNull();

    // a OS entregue (terminal) sumiu do board
    $allCardIds = $columns->flatMap(fn ($c) => collect($c['cards'])->pluck('id'))->all();
    expect($allCardIds)->not->toContain($entregueOs->id);

    // in_pipeline=false pra OS sem stage
    $semPipeline = boardCard($props, 'recepcao', $osSemPipeline->id);
    expect($semPipeline)->not->toBeNull();
    expect($semPipeline['in_pipeline'])->toBeFalse();
})->afterEach(fn () => boardCleanup());

it('6. card aplica mods [W] — dvi x/y + thumb_url null sem foto', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $os = boardOs(BIZ_BOARD, PLATE_PREFIX . '020', $stages['em_diagnostico']->id);

    // 3 itens DVI: 2 decididos (approved/rejected), 1 pending
    if (Schema::hasTable('oa_inspection_items')) {
        \Modules\OficinaAuto\Entities\OaInspectionItem::withoutGlobalScopes()->insert([
            ['business_id' => BIZ_BOARD, 'service_order_id' => $os->id, 'categoria' => 'freios', 'descricao' => 'Pastilha', 'severity' => 'critico', 'client_decision' => 'approved', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => BIZ_BOARD, 'service_order_id' => $os->id, 'categoria' => 'motor', 'descricao' => 'Correia', 'severity' => 'atencao', 'client_decision' => 'rejected', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => BIZ_BOARD, 'service_order_id' => $os->id, 'categoria' => 'pneus', 'descricao' => 'Pneu dianteiro', 'severity' => 'ok', 'client_decision' => 'pending', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    } else {
        $this->markTestSkipped('oa_inspection_items ausente');
    }

    $card = boardCard(invokeBoard(), 'em_diagnostico', $os->id);

    expect($card)->not->toBeNull();
    expect($card['dvi_total'])->toBe(3);
    expect($card['dvi_done'])->toBe(2);     // approved + rejected
    expect($card['dvi_critico'])->toBe(1);
    expect($card['thumb_url'])->toBeNull();  // sem foto → null (frontend esconde, sem placeholder)
})->afterEach(fn () => boardCleanup());

it('7. smoke resiliente — fluxo feliz recepcao → pronto_retirada (5 transições)', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $os = boardOs(BIZ_BOARD, PLATE_PREFIX . '030', $stages['recepcao']->id);

    // user com TODAS as roles do processo (suffix-agnóstico): coleta union das roles
    $allActions = SaleStageAction::with('roles')
        ->whereIn('stage_id', collect($stages)->pluck('id'))->get();
    $u = User::forceCreate([
        'surname' => 'Mec', 'first_name' => 'Smoke', 'username' => 'boardmec_smoke_' . uniqid(),
        'password' => bcrypt('x'), 'business_id' => BIZ_BOARD,
    ]);
    $allActions->flatMap(fn ($a) => $a->roles->pluck('role_name'))->unique()
        ->each(fn ($rn) => $u->assignRole($rn));

    $service = new ExecuteStageActionService;
    $flow = [
        'iniciar_diagnostico' => 'em_diagnostico',
        'enviar_orcamento'    => 'aguardando_aprovacao',
        'aprovar_pedir_pecas' => 'aguardando_pecas',
        'pecas_chegaram'      => 'em_execucao',
        'concluir_servico'    => 'pronto_retirada',
    ];
    foreach ($flow as $actionKey => $expectedStage) {
        $service->execute($os, $actionKey, $u);
        expect($os->fresh()->current_stage_id)->toBe($stages[$expectedStage]->id);
    }
})->afterEach(fn () => boardCleanup());

it('8. Onda 1.5 — KPIs do protótipo (recepcao, em_diagnostico, valor_em_curso, boxes_total)', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $antes = invokeBoard()['kpis'];
    // 2 em recepção, 1 em diagnóstico, 1 em execução — com valores pra somar.
    boardItem(boardOs(BIZ_BOARD, PLATE_PREFIX . '040', $stages['recepcao']->id), 100);
    boardItem(boardOs(BIZ_BOARD, PLATE_PREFIX . '041', $stages['recepcao']->id), 200);
    boardItem(boardOs(BIZ_BOARD, PLATE_PREFIX . '042', $stages['em_diagnostico']->id), 300);
    boardItem(boardOs(BIZ_BOARD, PLATE_PREFIX . '043', $stages['em_execucao']->id), 400);

    $kpis = invokeBoard()['kpis'];

    expect($kpis['recepcao'] - $antes['recepcao'])->toBe(2);
    expect($kpis['em_diagnostico'] - $antes['em_diagnostico'])->toBe(1);
    expect($kpis['em_execucao'] - $antes['em_execucao'])->toBe(1);
    // valor em curso = soma do total_items das OS não-terminais do quadro.
    expect(round((float) $kpis['valor_em_curso'] - (float) $antes['valor_em_curso'], 2))->toBe(1000.0);
    // chaves backward-compat continuam presentes
    expect($kpis)->toHaveKeys(['total', 'aguardando_aprovacao', 'aguardando_pecas', 'pronto_retirada', 'atrasadas', 'boxes_total']);
})->afterEach(fn () => boardCleanup());

it('9. Onda 1.5 — card carrega km, progress e last_activity (dado real)', function () {
    (new OficinaAutoFsmSeeder)->runForBusiness(BIZ_BOARD);
    session(['user.business_id' => BIZ_BOARD]);

    $stages = boardStages(BIZ_BOARD);
    $v = boardVehicle(BIZ_BOARD, PLATE_PREFIX . '050');
    $v->update(['mileage_at_entry' => 84220]);
    $os = new ServiceOrder(); // forceFill: current_stage_id não é fillable (ver boardOs)
    $os->forceFill([
        'business_id' => BIZ_BOARD, 'vehicle_id' => $v->id, 'order_type' => 'mecanica',
        'status' => 'aberta', 'current_stage_id' => $stages['em_diagnostico']->id,
        'entered_at' => now()->subDay(),
    ])->save();

    // 1 transição auditada → last_activity preenchido (via execute real, dado honesto).
    $action = SaleStageAction::where('stage_id', $stages['em_diagnostico']->id)
        ->where('key', 'enviar_orcamento')->firstOrFail();
    $u = boardUserForAction(BIZ_BOARD, $action);
    (new ExecuteStageActionService)->execute($os, 'enviar_orcamento', $u);

    // DVI 1 de 2 decidido → progress 50.
    if (Schema::hasTable('oa_inspection_items')) {
        \Modules\OficinaAuto\Entities\OaInspectionItem::withoutGlobalScopes()->insert([
            ['business_id' => BIZ_BOARD, 'service_order_id' => $os->id, 'categoria' => 'freios', 'descricao' => 'A', 'severity' => 'ok', 'client_decision' => 'approved', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => BIZ_BOARD, 'service_order_id' => $os->id, 'categoria' => 'motor', 'descricao' => 'B', 'severity' => 'ok', 'client_decision' => 'pending', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // a OS agora está em aguardando_aprovacao (transicionou) — pega o card lá.
    $card = boardCard(invokeBoard(), 'aguardando_aprovacao', $os->id);

    expect($card)->not->toBeNull();
    expect($card['km'])->toBe(84220);
    if (Schema::hasTable('oa_inspection_items')) {
        expect($card['progress'])->toBe(50);
    }
    expect($card['last_activity'])->not->toBeNull();
    expect($card['last_activity']['label'])->toBeString();
})->afterEach(fn () => boardCleanup());

/**
 * Invoca ServiceOrderController@board e extrai os props do Inertia\Response
 * (header X-Inertia força resposta JSON — sem precisar do stack de middleware).
 *
 * @return array<string, mixed>
 */
function invokeBoard(string $q = ''): array
{
    $request = Request::create('/oficina-auto/ordens-servico/board', 'GET', $q !== '' ? ['q' => $q] : []);
    $request->headers->set('X-Inertia', 'true');
    $request->headers->set('X-Inertia-Version', '1');
    $inertia = app(ServiceOrderController::class)->board($request);
    $json = $inertia->toResponse($request);
    return $json->getData(true)['props'] ?? [];
}

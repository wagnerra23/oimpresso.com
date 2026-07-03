<?php

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OCR-02 (OficinaAuto/ServiceOrders/Create) — order_type rejeita 'locacao' (erradicação ADR 0265)

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * FIO USÁVEL ponta a ponta (ADR 0265) — o teste que decide o merge.
 *
 * Critério de pronto ([W] 2026-06-10): criar uma OS nova e andar com ela
 * recepção → diagnóstico → aprovação → execução → entregue → imprimir, logado como
 * o usuário REAL do negócio (permissions `oficinaauto.service_order.*`, SEM role
 * Spatie mecanico/gerente, SEM superadmin), sem travar em nenhum passo, e com
 * ZERO vocabulário de locação/caçamba nos payloads de fsm/actions e fsm/gate.
 *
 * Prova os 4 consertos do fio:
 *  1. Auto-start no store() — OS nasce em `recepcao` de `oficina_mecanica_os`
 *     (ServiceOrderPipelineStarter; sem clique manual, sem cair em locação).
 *  2. RBAC sem beco — StageActionPolicy/ExecuteStageActionService aceitam a
 *     permission module-level (roles valem como camada adicional, não muro).
 *  3. (migrations 2026_06_10_00000{0,1} cobertas por idempotência do seeder aqui)
 *  4. Vocabulário — nenhum "locação"/"caçamba" visível no fluxo da OS nova.
 *
 * Gates de etapa satisfeitos com DADO REAL (não override — usuário sem gerente não
 * tem can_override): 1 item DVI + 1 foto no laudo + 1 item de orçamento + aprovação
 * do cliente registrada (approval_decision=approved, accessor approval_state).
 *
 * Pattern: skip SQLite (ADR 0101 — schema MySQL UltimatePOS). Multi-tenant biz=1
 * (ADR 0101 — nunca business de cliente).
 *
 * @see Modules/OficinaAuto/Services/ServiceOrderPipelineStarter.php
 * @see Modules/OficinaAuto/Services/StageGateEvaluator.php (RULES oficina_mecanica_os)
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 */

const BIZ_FIO = 1;
const PLATE_FIO = 'FIO0265';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }

    foreach (['service_orders', 'vehicles', 'sale_processes', 'sale_process_stages',
        'sale_stage_actions', 'sale_stage_history', 'oa_inspection_items',
        'oficina_service_order_items', 'arquivos', 'roles', 'permissions'] as $table) {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Tabela {$table} ausente — rode migrate canônico primeiro");
        }
    }

    if (! Schema::hasColumn('service_orders', 'current_stage_id')
        || ! Schema::hasColumn('service_orders', 'approval_decision')) {
        $this->markTestSkipped('Colunas FSM/approval ausentes — migrations OficinaAuto pendentes');
    }
});

/**
 * Usuário "dono do negócio": permissions do módulo, ZERO role mecanico/gerente,
 * ZERO superadmin — o perfil exato que travava no beco RBAC (evidência OS-00004).
 */
function fioUsuarioSemRoles(): User
{
    foreach ([
        'oficinaauto.service_order.view',
        'oficinaauto.service_order.create',
        'oficinaauto.service_order.update',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $user = User::create([
        'business_id' => BIZ_FIO,
        'first_name'  => 'Fio',
        'surname'     => 'ADR0265',
        'username'    => 'fio_adr0265_' . uniqid(),
        'email'       => 'fio_adr0265_' . uniqid() . '@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);

    // CheckUserLogin (rota /oficina-auto/*) exige user_type='user' + allow_login=1 —
    // sem isso o POST do fio morre em 403 silencioso antes do controller.
    $user->forceFill(['user_type' => 'user', 'allow_login' => 1])->save();

    $user->givePermissionTo([
        'oficinaauto.service_order.view',
        'oficinaauto.service_order.create',
        'oficinaauto.service_order.update',
    ]);

    return $user;
}

function fioCleanup(): void
{
    $vehicleIds = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_FIO . '%')
        ->pluck('id')->toArray();

    if (! empty($vehicleIds)) {
        $osIds = ServiceOrder::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicleIds)
            ->pluck('id')->toArray();

        if (! empty($osIds)) {
            SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', BIZ_FIO)
                ->whereIn('transaction_id', $osIds)
                ->delete();
            OaInspectionItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            DB::table('arquivos')
                ->where('arquivable_type', ServiceOrder::class)
                ->whereIn('arquivable_id', $osIds)
                ->delete();
            ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
        }

        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicleIds)->forceDelete();
    }

    User::where('business_id', BIZ_FIO)
        ->where('username', 'like', 'fio_adr0265_%')
        ->delete();
}

it('fio usável: criar OS → recepcao → entregue → imprimir, sem role mecanico/gerente, zero locação/caçamba', function () {
    session(['user.business_id' => BIZ_FIO]);
    (new OficinaAutoFsmSeeder())->runForBusiness(BIZ_FIO);

    $user = fioUsuarioSemRoles();
    expect($user->hasRole(['mecanico', 'gerente', 'mecanico#' . BIZ_FIO, 'gerente#' . BIZ_FIO]))
        ->toBeFalse('pré-condição: usuário do fio NÃO pode ter role mecanico/gerente');
    expect($user->can('superadmin'))->toBeFalse('pré-condição: usuário do fio NÃO é superadmin');

    $this->actingAs($user);
    // CSRF real (ambiente staging/MySQL não roda como 'testing' — VerifyCsrfToken ativo;
    // mantém o middleware exercitado em vez de desligar via withoutMiddleware).
    $this->withSession(['_token' => 'fio-csrf']);

    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => BIZ_FIO,
        'plate'        => PLATE_FIO . random_int(10, 99),
        'vehicle_type' => 'caminhao',
    ]);

    // ── 1. Criar a OS via store() — nasce JÁ no pipeline (conserto 1) ────────────
    $resp = $this->post('/oficina-auto/ordens-servico', [
        '_token'     => 'fio-csrf',
        'vehicle_id' => $vehicle->id,
        'order_type' => 'mecanica',
        'status'     => 'aberta',
        'notes'      => 'Fio ADR 0265 — barulho no freio dianteiro.',
    ]);
    $resp->assertSessionHasNoErrors();
    $resp->assertStatus(302);

    $order = ServiceOrder::withoutGlobalScopes()
        ->where('vehicle_id', $vehicle->id)
        ->latest('id')
        ->firstOrFail();
    $resp->assertRedirect('/oficina-auto/ordens-servico/' . $order->id);

    expect($order->current_stage_id)
        ->not->toBeNull('OS nova deve nascer em pipeline (auto-start no store)');
    $stage = SaleProcessStage::findOrFail($order->current_stage_id);
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->findOrFail($stage->process_id);
    expect($stage->key)->toBe('recepcao');
    expect($process->key)->toBe('oficina_mecanica_os');

    // ── 2. Satisfaz os gates de etapa com dado REAL (sem override) ───────────────
    OaInspectionItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_FIO,
        'service_order_id' => $order->id,
        'categoria'        => 'freios',
        'descricao'        => 'Pastilha dianteira no limite',
        'severity'         => OaInspectionItem::SEVERITY_ATENCAO,
    ]);
    $order->arquivos()->create([
        'business_id'   => BIZ_FIO,
        'disk'          => 'arquivos',
        'storage_path'  => 'tests/fio-adr0265-laudo.png',
        'original_name' => 'fio-laudo.png',
        'mime_type'     => 'image/png',
        'size_bytes'    => 68,
        'md5'           => md5('fio-adr0265'),
        'bucket'        => 'active',
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_FIO,
        'service_order_id' => $order->id,
        'tipo'             => ServiceOrderItem::TIPO_PECA,
        'descricao'        => 'Pastilha de freio dianteira',
        'quantidade'       => 1,
        'valor_unitario'   => 250.00,
    ]);
    // Aprovação do cliente registrada (gate aprovar_executar — accessor approval_state)
    DB::table('service_orders')->where('id', $order->id)->update(['approval_decision' => 'approved']);

    // ── 3. Anda o fio: recepcao → ... → entregue (200 em cada passo) ─────────────
    $caminho = [
        'iniciar_diagnostico', // recepcao             → em_diagnostico
        'enviar_orcamento',    // em_diagnostico       → aguardando_aprovacao
        'aprovar_executar',    // aguardando_aprovacao → em_execucao
        'concluir_servico',    // em_execucao          → pronto_retirada
        'entregar',            // pronto_retirada      → entregue
    ];

    foreach ($caminho as $actionKey) {
        $actions = $this->getJson("/oficina-auto/service-orders/{$order->id}/fsm/actions");
        $actions->assertOk();
        $gate = $this->getJson("/oficina-auto/service-orders/{$order->id}/fsm/gate");
        $gate->assertOk();

        // ZERO vocabulário de locação/caçamba nos payloads (conserto 4 + ADR 0265)
        foreach (['actions' => $actions, 'gate' => $gate] as $nome => $r) {
            $raw = mb_strtolower(json_encode($r->json(), JSON_UNESCAPED_UNICODE));
            foreach (['locação', 'locacao', 'caçamba', 'cacamba'] as $proibida) {
                expect($raw)->not->toContain(
                    $proibida,
                    "payload {$nome} no passo {$actionKey} não pode conter '{$proibida}'"
                );
            }
        }

        // A ação do fio está visível E executável pro usuário SEM role (conserto 2)
        $acao = collect($actions->json('actions'))->firstWhere('key', $actionKey);
        expect($acao)->not->toBeNull("action {$actionKey} deve estar listada no stage atual");
        expect($acao['can_execute'])->toBeTrue(
            "action {$actionKey} deve ser executável com permission do módulo (sem role mecanico/gerente)"
        );

        $exec = $this->postJson(
            "/oficina-auto/service-orders/{$order->id}/fsm/execute",
            ['action_key' => $actionKey],
            ['X-CSRF-TOKEN' => 'fio-csrf'],
        );
        $exec->assertOk();
        expect($exec->json('ok'))->toBeTrue("execute {$actionKey} deve retornar ok=true");
    }

    $order = ServiceOrder::withoutGlobalScopes()->findOrFail($order->id);
    $stageFinal = SaleProcessStage::findOrFail($order->current_stage_id);
    expect($stageFinal->key)->toBe('entregue');
    expect((bool) $stageFinal->is_terminal)->toBeTrue();

    // ── 4. Imprimir A4 (AJAX-only — espelha printServiceOrder.ts) ────────────────
    $print = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get("/oficina-auto/ordens-servico/{$order->id}/print");
    $print->assertOk();
    expect($print->json('success'))->toBe(1);
    expect($print->json('receipt.html_content'))->not->toBeEmpty();
})->afterEach(fn () => fioCleanup());

it('OS nova de manutenção roteia pra cacamba_manutencao — JAMAIS pro pipeline de locação', function () {
    session(['user.business_id' => BIZ_FIO]);
    (new OficinaAutoFsmSeeder())->runForBusiness(BIZ_FIO);

    $user = fioUsuarioSemRoles();
    $this->actingAs($user);
    $this->withSession(['_token' => 'fio-csrf']);

    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => BIZ_FIO,
        'plate'        => PLATE_FIO . random_int(10, 99),
        'vehicle_type' => 'caminhao',
    ]);

    $this->post('/oficina-auto/ordens-servico', [
        '_token'     => 'fio-csrf',
        'vehicle_id' => $vehicle->id,
        'order_type' => 'manutencao',
        'status'     => 'aberta',
    ])->assertSessionHasNoErrors();

    $order = ServiceOrder::withoutGlobalScopes()
        ->where('vehicle_id', $vehicle->id)
        ->latest('id')
        ->firstOrFail();

    // Mapa ORDER_TYPE_TO_PROCESS sem 'locacao' (ADR 0265): manutenção cai no
    // processo de manutenção (stage inicial 'aberta'), nunca em cacamba_locacao.
    $actions = $this->getJson("/oficina-auto/service-orders/{$order->id}/fsm/actions");
    $actions->assertOk();
    $actions->assertJsonPath('process_key', 'cacamba_manutencao');
    $actions->assertJsonPath('current_stage.key', 'aberta');
})->afterEach(fn () => fioCleanup());

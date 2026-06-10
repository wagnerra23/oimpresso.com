<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * O FIO USÁVEL da Oficina (ADR 0265 — critério de pronto [W] 2026-06-10).
 *
 * "Criar uma OS nova e andar com ela recepção → diagnóstico → aprovação → execução →
 * entregue, logado como o usuário real do negócio, SEM travar em nenhum passo."
 *
 * Este teste é o que decide o merge do PR-1 (não snapshot — o FIO):
 *  1. store() → OS nasce JÁ em `recepcao` do `oficina_mecanica_os` (auto-start,
 *     nunca no pipeline legado de locação — causa raiz da OS-00004 órfã).
 *  2. Usuário SÓ com permissões `oficinaauto.service_order.*` (SEM role
 *     mecanico/gerente — o muro default que escondia o fluxo do dono do negócio)
 *     executa CADA transição até `entregue` com 200 (StageActionPolicy +
 *     ExecuteStageActionService destravados por permission module-level).
 *  3. ZERO string "locação"/"caçamba" (case/accent-insensitive) nos payloads de
 *     fsm/actions e fsm/gate em todos os passos.
 *
 * Gates de etapa satisfeitos DE VERDADE (não override): DVI + foto + item de
 * orçamento antes de enviar_orcamento; aprovação registrada antes de executar.
 *
 * Multi-tenant Tier 0 (ADR 0093): tudo em biz=1. MySQL-only (ADR 0101).
 */

const BIZ_FIO = 1;
const PLATE_FIO = 'WFIO0';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasColumn('service_orders', 'current_stage_id')) {
        $this->markTestSkipped('Wave 7 migration current_stage_id pendente');
    }
});

/** Usuário do negócio: permissões do módulo, NENHUMA role FSM (mecanico/gerente). */
function fio_user(): \App\User
{
    $user = \App\User::factory()->create(['business_id' => BIZ_FIO]);

    foreach (['view', 'create', 'update'] as $ability) {
        Permission::firstOrCreate([
            'name'       => "oficinaauto.service_order.{$ability}",
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo("oficinaauto.service_order.{$ability}");
    }

    return $user;
}

function fio_cleanup(): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_FIO . '%')
        ->pluck('id')->toArray();
    if (empty($vehicles)) {
        return;
    }
    $osIds = ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicles)->pluck('id')->toArray();
    if (! empty($osIds)) {
        OaInspectionItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
        Arquivo::withoutGlobalScopes()
            ->where('arquivable_type', ServiceOrder::class)
            ->whereIn('arquivable_id', $osIds)
            ->forceDelete();
        DB::table('oficina_service_order_items')->whereIn('service_order_id', $osIds)->delete();
        DB::table('sale_stage_history')
            ->where('business_id', BIZ_FIO)
            ->whereIn('transaction_id', $osIds)
            ->whereRaw("payload_snapshot LIKE '%service_order_id%'")
            ->delete();
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    }
    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

/** Zero vocabulário de locação user-facing (ADR 0265): fold acento+caixa e procura. */
function fio_expectSemLocacao(array $payload, string $contexto): void
{
    $texto = mb_strtolower(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    $folded = strtr($texto, ['ç' => 'c', 'ã' => 'a', 'á' => 'a', 'â' => 'a']);
    expect(str_contains($folded, 'locacao'))->toBeFalse("'locação' vazou no payload de {$contexto}");
    expect(str_contains($folded, 'cacamba'))->toBeFalse("'caçamba' vazou no payload de {$contexto}");
}

it('FIO COMPLETO: store → recepcao → ... → entregue com usuário sem role FSM, zero locação', function () {
    session(['user.business_id' => BIZ_FIO]);

    // Processos FSM canônicos do business (idempotente — staging biz=1 já tem).
    (new OficinaAutoFsmSeeder())->runForBusiness(BIZ_FIO);

    $user = fio_user();
    expect($user->hasAnyRole(['mecanico', 'gerente', 'mecanico#' . BIZ_FIO, 'gerente#' . BIZ_FIO]))
        ->toBeFalse('pré-condição: usuário do fio NÃO pode ter role FSM');

    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => BIZ_FIO,
        'plate'        => PLATE_FIO . 'A',
        'vehicle_type' => 'caminhao',
    ]);

    // ── 1. CREATE: OS nasce JÁ no pipeline correto (auto-start ADR 0265) ──
    $resp = $this->actingAs($user)->post('/oficina-auto/ordens-servico', [
        'vehicle_id' => $vehicle->id,
        'order_type' => 'mecanica',
        'status'     => 'aberta',
    ]);
    $resp->assertRedirect();

    $os = ServiceOrder::withoutGlobalScopes()
        ->where('vehicle_id', $vehicle->id)
        ->orderByDesc('id')
        ->firstOrFail();

    $actions = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}/fsm/actions");
    $actions->assertOk();
    $actions->assertJsonPath('in_pipeline', true);
    $actions->assertJsonPath('process_key', 'oficina_mecanica_os');
    $actions->assertJsonPath('current_stage.key', 'recepcao');
    fio_expectSemLocacao($actions->json(), 'actions@recepcao');

    // ── 2. Caminhar o fio: cada transição com 200, can_execute=true ──
    $passos = [
        ['action' => 'iniciar_diagnostico', 'stage_depois' => 'em_diagnostico'],
        ['action' => 'enviar_orcamento',    'stage_depois' => 'aguardando_aprovacao'],
        ['action' => 'aprovar_executar',    'stage_depois' => 'em_execucao'],
        ['action' => 'concluir_servico',    'stage_depois' => 'pronto_retirada'],
        ['action' => 'entregar',            'stage_depois' => 'entregue'],
    ];

    foreach ($passos as $passo) {
        // Satisfaz o gate REAL da etapa (não override) antes do passo que exige.
        if ($passo['action'] === 'enviar_orcamento') {
            OaInspectionItem::withoutGlobalScopes()->create([
                'business_id'      => BIZ_FIO,
                'service_order_id' => $os->id,
                'categoria'        => 'motor',
                'descricao'        => 'Vazamento de óleo no cabeçote',
                'severity'         => 'critico',
            ]);
            Arquivo::withoutGlobalScopes()->create([
                'business_id'     => BIZ_FIO,
                'arquivable_type' => ServiceOrder::class,
                'arquivable_id'   => $os->id,
                'disk'            => 'local',
                'storage_path'    => 'oficina/fio-test/foto.jpg',
                'original_name'   => 'foto.jpg',
                'mime_type'       => 'image/jpeg',
                'size_bytes'      => 1024,
                'bucket'          => 'general',
            ]);
            DB::table('oficina_service_order_items')->insert([
                'business_id'      => BIZ_FIO,
                'service_order_id' => $os->id,
                'tipo'             => 'peca',
                'descricao'        => 'Junta do cabeçote',
                'quantidade'       => 1,
                'valor_unitario'   => 350.00,
                'valor_total'      => 350.00,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
        if ($passo['action'] === 'aprovar_executar') {
            // Cliente aprovou (gate 'aprovado' — approval_state accessor).
            ServiceOrder::withoutGlobalScopes()->whereKey($os->id)
                ->update(['approval_decision' => 'approved', 'approval_decided_at' => now()]);
        }

        // A action do passo aparece em fsm/actions com can_execute=true (Policy).
        $lista = $this->actingAs($user)
            ->getJson("/oficina-auto/service-orders/{$os->id}/fsm/actions");
        $lista->assertOk();
        $disponivel = collect($lista->json('actions'))->firstWhere('key', $passo['action']);
        expect($disponivel)->not->toBeNull("action '{$passo['action']}' não listada no stage");
        expect($disponivel['can_execute'])->toBeTrue(
            "can_execute=false pra '{$passo['action']}' — beco RBAC voltou (ADR 0265)"
        );
        fio_expectSemLocacao($lista->json(), "actions antes de {$passo['action']}");

        // Gate payload também sem vocabulário de locação.
        $gate = $this->actingAs($user)
            ->getJson("/oficina-auto/service-orders/{$os->id}/fsm/gate");
        $gate->assertOk();
        fio_expectSemLocacao($gate->json(), "gate antes de {$passo['action']}");

        // Executa a transição — 200, sem 403 (role) e sem 422 (gate).
        $exec = $this->actingAs($user)
            ->postJson("/oficina-auto/service-orders/{$os->id}/fsm/execute", [
                'action_key' => $passo['action'],
            ]);
        $exec->assertOk();
        $exec->assertJsonPath('ok', true);
        $exec->assertJsonPath('to_stage.key', $passo['stage_depois']);
        fio_expectSemLocacao($exec->json(), "execute {$passo['action']}");
    }

    // ── 3. Terminal: entregue ──
    $final = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}/fsm/actions");
    $final->assertOk();
    $final->assertJsonPath('current_stage.key', 'entregue');
    $final->assertJsonPath('current_stage.is_terminal', true);

    // ── 4. JSON do drawer (show) sem campos/vocabulário de locação ──
    $drawer = $this->actingAs($user)->getJson("/oficina-auto/ordens-servico/{$os->id}");
    $drawer->assertOk();
    expect($drawer->json())->not->toHaveKeys([
        'delivery_address', 'expected_return_date', 'daily_rate', 'dias_locacao',
    ]);
})->afterEach(fn () => fio_cleanup());

it('OS nova de manutenção NUNCA cai no pipeline de locação (map sem locacao)', function () {
    session(['user.business_id' => BIZ_FIO]);
    (new OficinaAutoFsmSeeder())->runForBusiness(BIZ_FIO);
    $user = fio_user();

    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => BIZ_FIO,
        'plate'        => PLATE_FIO . 'B',
        'vehicle_type' => 'caminhao',
    ]);

    $this->actingAs($user)->post('/oficina-auto/ordens-servico', [
        'vehicle_id' => $vehicle->id,
        'order_type' => 'manutencao',
        'status'     => 'aberta',
    ])->assertRedirect();

    $os = ServiceOrder::withoutGlobalScopes()
        ->where('vehicle_id', $vehicle->id)->orderByDesc('id')->firstOrFail();

    $actions = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}/fsm/actions");
    $actions->assertOk();
    // manutenção roteia pro processo de manutenção — JAMAIS cacamba_locacao.
    $actions->assertJsonPath('process_key', 'cacamba_manutencao');
    $actions->assertJsonPath('current_stage.key', 'aberta');
})->afterEach(fn () => fio_cleanup());

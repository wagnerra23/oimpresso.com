<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Role;

/**
 * US-SELL-033 — Processo "Venda Com Produção" canônico (ADR 0129).
 *
 * Pipeline empresarial completo Orçamento → Produção → Venda → Faturamento
 * com FSM sub-estados RBAC granular. Resolve pain point Wagner 2026-05-12:
 *   "produção iniciada sem pessoas ter autorizado"
 *
 * Stages canônicos (11 = 9 lineares + 2 laterais terminais):
 *   quote_draft (initial) → quote_sent → quote_approved → in_production →
 *   ready_for_invoice → invoiced → paid → delivered → completed (terminal)
 *   Laterais: cancelled (terminal), on_hold
 *
 * Actions (13 com roles e is_critical conforme US-SELL-031):
 *   enviar_orcamento, cliente_aprovou, cliente_rejeitou,
 *   iniciar_producao, pausar_producao, concluir_producao,
 *   faturar, emitir_nfe, marcar_pago,
 *   entregar, concluir,
 *   cancelar_venda (qualquer stage não-terminal),
 *   reabrir_para_revisao (quote_approved → quote_sent)
 *
 * Idempotente — rodar múltiplas vezes não cria duplicatas.
 */
class FsmProcessoVendaComProducaoSeeder extends Seeder
{
    /** @var array<string, string> Mapping key → label */
    private const STAGES = [
        'quote_draft' => 'Orçamento — Rascunho',
        'quote_sent' => 'Orçamento — Enviado',
        'quote_approved' => 'Aprovado pelo cliente',
        'in_production' => 'Em produção',
        'ready_for_invoice' => 'Pronto pra faturar',
        'invoiced' => 'Faturada',
        'paid' => 'Paga',
        'delivered' => 'Entregue',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
        'on_hold' => 'Em espera',
    ];

    /** @var array<string, array{order:int, terminal?:bool, color:string}> */
    private const STAGE_META = [
        'quote_draft' => ['order' => 0, 'color' => 'gray'],
        'quote_sent' => ['order' => 1, 'color' => 'blue'],
        'quote_approved' => ['order' => 2, 'color' => 'cyan'],
        'in_production' => ['order' => 3, 'color' => 'amber'],
        'ready_for_invoice' => ['order' => 4, 'color' => 'violet'],
        'invoiced' => ['order' => 5, 'color' => 'indigo'],
        'paid' => ['order' => 6, 'color' => 'emerald'],
        'delivered' => ['order' => 7, 'color' => 'green'],
        'completed' => ['order' => 8, 'terminal' => true, 'color' => 'green'],
        'cancelled' => ['order' => 9, 'terminal' => true, 'color' => 'red'],
        'on_hold' => ['order' => 10, 'color' => 'slate'],
    ];

    /** @var list<string> Roles que precisam existir antes do seed (idempotente) */
    private const ROLES = [
        'vendas.enviar', 'vendas.confirmar_aprovacao', 'vendas.gerente',
        'producao.iniciar', 'producao.pausar', 'producao.concluir',
        'financeiro.faturar', 'financeiro.baixar',
        'fiscal.emitir',
        'logistica.entregar',
    ];

    public function run(): void
    {
        $businessIds = \DB::table('business')->pluck('id');
        foreach ($businessIds as $bizId) {
            $this->runForBusiness((int) $bizId);
        }
    }

    public function runForBusiness(int $businessId): void
    {
        $roleMap = $this->ensureRoles($businessId);

        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', 'venda_com_producao')
            ->first();

        if (! $process) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => $businessId,
                'key' => 'venda_com_producao',
                'name' => 'Venda Com Produção',
                'description' => 'Pipeline empresarial Orçamento → Produção → Venda → Faturamento com RBAC granular',
                'default_for_contact_type' => 'any',
                'active' => true,
            ]);
        }

        $stages = $this->ensureStages($process);
        $this->ensureActions($stages, $roleMap);
    }

    /**
     * Cria roles globais OU per-business conforme schema da tabela `roles`.
     *
     * UltimatePOS estende Spatie Permission com coluna `roles.business_id`
     * (NOT NULL + FK pra business). Quando essa coluna existe, criamos role
     * per-business com nome único \"role.key#{business_id}\" (convenção UPos).
     *
     * Quando NÃO existe (Pest in-memory SQLite usa schema Spatie puro), cria
     * role global com nome puro.
     *
     * SaleStageActionRole.role_name guarda o nome resolvido — Service consulta
     * via $user->hasAnyRole($roleNames) que casa o que estiver registrado.
     */
    private function ensureRoles(int $businessId): array
    {
        $hasBusinessIdColumn = Schema::hasColumn('roles', 'business_id');
        $resolved = [];

        foreach (self::ROLES as $role) {
            $roleName = $hasBusinessIdColumn ? "{$role}#{$businessId}" : $role;

            $attrs = ['name' => $roleName, 'guard_name' => 'web'];
            if ($hasBusinessIdColumn) {
                $attrs['business_id'] = $businessId;
            }

            Role::firstOrCreate($attrs);
            $resolved[$role] = $roleName;
        }

        return $resolved;
    }

    /** @return array<string, SaleProcessStage> */
    private function ensureStages(SaleProcess $process): array
    {
        $stages = [];
        foreach (self::STAGES as $key => $name) {
            $meta = self::STAGE_META[$key];
            $stages[$key] = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => $key],
                [
                    'name' => $name,
                    'sort_order' => $meta['order'],
                    'is_initial' => $key === 'quote_draft',
                    'is_terminal' => $meta['terminal'] ?? false,
                    'color' => $meta['color'],
                ],
            );
        }
        return $stages;
    }

    /**
     * @param array<string, SaleProcessStage> $stages
     * @param array<string, string> $roleMap key canônica → nome resolvido (com/sem sufixo #biz)
     */
    private function ensureActions(array $stages, array $roleMap): void
    {
        $defs = [
            // [stage_origem, key, label, target, roles[], is_critical, side_effect]
            ['quote_draft', 'enviar_orcamento', 'Enviar orçamento ao cliente', 'quote_sent', ['vendas.enviar'], false, null],
            ['quote_sent', 'cliente_aprovou', 'Cliente aprovou', 'quote_approved', ['vendas.confirmar_aprovacao'], true, 'App\\Domain\\Fsm\\SideEffects\\ReservarEstoque'],
            ['quote_sent', 'cliente_rejeitou', 'Cliente rejeitou', 'cancelled', ['vendas.confirmar_aprovacao'], false, null],
            ['quote_approved', 'iniciar_producao', 'Iniciar produção', 'in_production', ['producao.iniciar'], true, null],
            ['quote_approved', 'reabrir_para_revisao', 'Reabrir para revisão (volta orçamento)', 'quote_sent', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['in_production', 'pausar_producao', 'Pausar produção', 'on_hold', ['producao.pausar'], false, null],
            ['in_production', 'concluir_producao', 'Concluir produção', 'ready_for_invoice', ['producao.concluir'], true, 'App\\Domain\\Fsm\\SideEffects\\ConsumirEstoque'],
            ['ready_for_invoice', 'faturar', 'Faturar', 'invoiced', ['financeiro.faturar'], true, null],
            ['invoiced', 'emitir_nfe', 'Emitir NF-e', null, ['fiscal.emitir'], true, null],
            ['invoiced', 'marcar_pago', 'Marcar como pago', 'paid', ['financeiro.baixar'], true, null],
            ['paid', 'entregar', 'Entregar ao cliente', 'delivered', ['logistica.entregar'], false, null],
            ['delivered', 'concluir', 'Concluir venda', 'completed', ['vendas.gerente'], false, null],
            // Cancelamento — disponível de qualquer stage não-terminal (criar 1 action por stage)
            ['quote_draft', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['quote_sent', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['quote_approved', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['in_production', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['ready_for_invoice', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['invoiced', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['paid', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['delivered', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['on_hold', 'cancelar_venda', 'Cancelar venda', 'cancelled', ['vendas.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
        ];

        foreach ($defs as [$stageKey, $key, $label, $targetKey, $roles, $isCritical, $sideEffect]) {
            $stage = $stages[$stageKey];
            $action = SaleStageAction::firstOrCreate(
                ['stage_id' => $stage->id, 'key' => $key],
                [
                    'label' => $label,
                    'target_stage_id' => $targetKey ? $stages[$targetKey]->id : null,
                    'side_effect_class' => $sideEffect,
                    'requires_confirmation' => $isCritical,
                    'is_critical' => $isCritical,
                ],
            );

            // Idempotente: sync roles (cria as faltantes, mantém as existentes).
            // Usa nome resolvido do roleMap (com sufixo #{biz} em UltimatePOS).
            $existingRoles = $action->roles->pluck('role_name')->all();
            foreach ($roles as $roleKey) {
                $resolvedName = $roleMap[$roleKey] ?? $roleKey;
                if (! in_array($resolvedName, $existingRoles, true)) {
                    SaleStageActionRole::create([
                        'action_id' => $action->id,
                        'role_name' => $resolvedName,
                    ]);
                }
            }
        }
    }
}

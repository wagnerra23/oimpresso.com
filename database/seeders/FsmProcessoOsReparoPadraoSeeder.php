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
 * US-REP-FSM-001 — Processo "OS Reparo Padrão" canônico (ADR 0129 + SPEC-FSM-WIREUP §2).
 *
 * Espelha o pattern do FsmProcessoVendaComProducaoSeeder pra entidade JobSheet
 * (Modules/Repair) — pipeline OS de reparo com RBAC granular per-action.
 *
 * Stages canônicos (13 = 9 lineares + 4 terminais/laterais):
 *   recebido_para_diagnostico (initial) → em_diagnostico →
 *   diagnosticado_aguardando_aprovacao →
 *     [orcamento_aprovado | orcamento_rejeitado (terminal)] →
 *     aguardando_pecas → pecas_chegadas → em_execucao →
 *     [pausado ↔ em_execucao] →
 *     concluido_aguardando_retirada → entregue_completo (terminal)
 *   Laterais terminais: cancelado, garantia_acionada
 *
 * Roles per-business (Spatie Permission UltimatePOS — coluna roles.business_id):
 *   repair.recepcao, repair.tecnico, repair.vendedor,
 *   repair.estoque, repair.logistica, repair.gerente
 *
 * Idempotente — rodar múltiplas vezes não cria duplicatas.
 *
 * IMPORTANTE: convive com a state machine legacy (RepairStatus + status_id).
 * Fase D (RepairFsmActionController) adiciona path FSM em paralelo — controller
 * legacy JobSheetController não é tocado nesta US.
 */
class FsmProcessoOsReparoPadraoSeeder extends Seeder
{
    /** @var array<string, string> Mapping key → label PT-BR */
    private const STAGES = [
        'recebido_para_diagnostico' => 'Recebido pra diagnóstico',
        'em_diagnostico' => 'Em diagnóstico',
        'diagnosticado_aguardando_aprovacao' => 'Diagnosticado — aguardando aprovação',
        'orcamento_aprovado' => 'Orçamento aprovado',
        'orcamento_rejeitado' => 'Orçamento rejeitado',
        'aguardando_pecas' => 'Aguardando peças',
        'pecas_chegadas' => 'Peças chegaram',
        'em_execucao' => 'Em execução',
        'pausado' => 'Pausado',
        'concluido_aguardando_retirada' => 'Concluído — aguardando retirada',
        'entregue_completo' => 'Entregue ao cliente',
        'cancelado' => 'Cancelado',
        'garantia_acionada' => 'Garantia acionada',
    ];

    /** @var array<string, array{order:int, terminal?:bool, color:string}> */
    private const STAGE_META = [
        'recebido_para_diagnostico' => ['order' => 0, 'color' => 'gray'],
        'em_diagnostico' => ['order' => 1, 'color' => 'blue'],
        'diagnosticado_aguardando_aprovacao' => ['order' => 2, 'color' => 'amber'],
        'orcamento_aprovado' => ['order' => 3, 'color' => 'cyan'],
        'orcamento_rejeitado' => ['order' => 4, 'terminal' => true, 'color' => 'red'],
        'aguardando_pecas' => ['order' => 5, 'color' => 'violet'],
        'pecas_chegadas' => ['order' => 6, 'color' => 'indigo'],
        'em_execucao' => ['order' => 7, 'color' => 'emerald'],
        'pausado' => ['order' => 8, 'color' => 'gray'],
        'concluido_aguardando_retirada' => ['order' => 9, 'color' => 'green'],
        'entregue_completo' => ['order' => 10, 'terminal' => true, 'color' => 'green'],
        'cancelado' => ['order' => 11, 'terminal' => true, 'color' => 'red'],
        'garantia_acionada' => ['order' => 12, 'terminal' => true, 'color' => 'red'],
    ];

    /** @var list<string> Roles canônicas Repair (sufixo #{biz} aplicado no ensureRoles) */
    private const ROLES = [
        'repair.recepcao',
        'repair.tecnico',
        'repair.vendedor',
        'repair.estoque',
        'repair.logistica',
        'repair.gerente',
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
            ->where('key', 'os_reparo_padrao')
            ->first();

        if (! $process) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => $businessId,
                'key' => 'os_reparo_padrao',
                'name' => 'OS Reparo Padrão',
                'description' => 'Pipeline OS de reparo Recepção → Diagnóstico → Orçamento → Execução → Entrega com RBAC granular',
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
     * Espelha pattern do FsmProcessoVendaComProducaoSeeder (UltimatePOS estende
     * Spatie Permission com coluna `roles.business_id` NOT NULL + FK pra business).
     * Quando essa coluna existe, criamos role per-business com nome único
     * "role.key#{business_id}". Quando NÃO existe (Pest in-memory SQLite usa
     * schema Spatie puro), cria role global com nome puro.
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
                    'is_initial' => $key === 'recebido_para_diagnostico',
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
        // [stage_origem, key, label, target, roles[], is_critical, side_effect]
        // is_critical=true → exige confirmation + side-effect autorização (ADR 0129)
        $defs = [
            // Diagnóstico
            ['recebido_para_diagnostico', 'iniciar_diagnostico', 'Iniciar diagnóstico', 'em_diagnostico', ['repair.tecnico'], false, null],
            ['em_diagnostico', 'finalizar_diagnostico', 'Finalizar diagnóstico e gerar orçamento', 'diagnosticado_aguardando_aprovacao', ['repair.tecnico'], false, null],

            // Orçamento — aprovação cliente (crítica — reserva estoque)
            ['diagnosticado_aguardando_aprovacao', 'cliente_aprovou_orcamento', 'Cliente aprovou orçamento', 'orcamento_aprovado', ['repair.vendedor'], true, 'App\\Domain\\Fsm\\SideEffects\\ReservarEstoque'],
            ['diagnosticado_aguardando_aprovacao', 'cliente_rejeitou_orcamento', 'Cliente rejeitou orçamento', 'orcamento_rejeitado', ['repair.vendedor'], false, null],

            // Peças
            ['orcamento_aprovado', 'pedir_pecas', 'Pedir peças ao fornecedor', 'aguardando_pecas', ['repair.estoque'], false, null],
            ['aguardando_pecas', 'confirmar_chegada_pecas', 'Confirmar chegada das peças', 'pecas_chegadas', ['repair.estoque'], false, null],

            // Execução — pode iniciar a partir de 'orcamento_aprovado' OU 'pecas_chegadas'
            // (modelo canônico: 1 action por stage origem mesma key — espelha pattern cancelar_venda)
            ['orcamento_aprovado', 'iniciar_execucao', 'Iniciar execução do reparo', 'em_execucao', ['repair.tecnico'], true, null],
            ['pecas_chegadas', 'iniciar_execucao', 'Iniciar execução do reparo', 'em_execucao', ['repair.tecnico'], true, null],

            // Pausa / retomada
            ['em_execucao', 'pausar_execucao', 'Pausar execução', 'pausado', ['repair.tecnico'], false, null],
            ['pausado', 'retomar_execucao', 'Retomar execução', 'em_execucao', ['repair.tecnico'], false, null],

            // Conclusão técnica — crítica (consome estoque + dispara Whatsapp via Listener legacy)
            ['em_execucao', 'concluir_execucao', 'Concluir execução do reparo', 'concluido_aguardando_retirada', ['repair.tecnico'], true, 'App\\Domain\\Fsm\\SideEffects\\ConsumirEstoque'],

            // Entrega — recepção OU logística (cliente busca OU entrega externa).
            // Espelha pattern stages duplos: 1 action por origem.
            ['concluido_aguardando_retirada', 'entregar_ao_cliente', 'Entregar ao cliente', 'entregue_completo', ['repair.recepcao', 'repair.logistica'], false, null],
            ['orcamento_rejeitado', 'entregar_ao_cliente', 'Entregar ao cliente (sem conserto)', 'entregue_completo', ['repair.recepcao', 'repair.logistica'], false, null],

            // Cancelamento — override gerente, disponível de qualquer stage não-terminal.
            // Stages terminais (orcamento_rejeitado/entregue_completo/cancelado/garantia_acionada) NÃO recebem.
            ['recebido_para_diagnostico', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['em_diagnostico', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['diagnosticado_aguardando_aprovacao', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['orcamento_aprovado', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['aguardando_pecas', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['pecas_chegadas', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['em_execucao', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['pausado', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],
            ['concluido_aguardando_retirada', 'cancelar_os', 'Cancelar OS', 'cancelado', ['repair.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\LiberarReserva'],

            // Garantia — re-entrada pós-entrega.
            // TODO US-REP-FSM-002+: emit event RepairWarrantyTriggered pra criar OS filha
            // em 'recebido_para_diagnostico' linkada via parent_job_sheet_id (coluna nova).
            // Hoje é só transição terminal — branch nova de OS fica como follow-up.
            ['entregue_completo', 'registrar_garantia', 'Registrar acionamento de garantia', 'garantia_acionada', ['repair.gerente'], true, null],
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

<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Database\Seeders;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Role;

/**
 * Seeder FSM canônico OficinaAuto — Martinho piloto.
 *
 * **Atualizado pós-ADR 0194 (2026-05-26):** Martinho é sub-vertical 4 mecânica
 * pesada caminhão basculante CNAE 4520 (pré-correção dizia "caçamba avulsa
 * estacionária" sub-vertical 3 locação CNAE 4581). Keys de processo (`cacamba_locacao`,
 * `cacamba_manutencao`) preservadas por compat backwards (seeder em prod biz=164
 * desde 2026-05-13). Próximo seeder canon usa `mecanica_pesada_basculante` quando
 * US-OFICINA-027 catálogo peça hidráulica chegar.
 *
 * Cria 2 processos FSM idempotentes per-business:
 *
 *  1) cacamba_locacao   — fluxo de locação caçamba (Vehicle.current_status sync)
 *     Stages (4): disponivel (initial) → locada → recolhida (terminal)
 *                 lateral: manutencao
 *     Actions (4): iniciar_locacao | recolher | enviar_manutencao | voltar_disponivel
 *
 *  2) cacamba_manutencao — fluxo de manutenção caçamba (oficina simples)
 *     Stages (4): aberta (initial) → em_servico → concluida (terminal)
 *                 lateral: cancelada (terminal)
 *     Actions (3): iniciar_servico | concluir | cancelar
 *
 * Roles per-business sufixo #{biz} (UltimatePOS Spatie schema — proibições FSM):
 *  - mecanico, gerente
 *
 * Side-effect classes apontam pra namespace canônico App\Domain\Fsm\SideEffects\*
 * (implementação fica pra Wave 6 — aqui apenas registramos o FQCN).
 *
 * Idempotente — rodar múltiplas vezes não cria duplicatas (firstOrCreate +
 * withoutGlobalScope ScopeByBusiness pra evitar miss em ambiente sem session).
 *
 * Pattern espelha FsmProcessoVendaComProducaoSeeder + FsmProcessoOsReparoPadraoSeeder.
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see database/seeders/FsmProcessoVendaComProducaoSeeder.php (pattern canônico)
 */
class OficinaAutoFsmSeeder extends Seeder
{
    /** @var list<string> Roles canônicas OficinaAuto (sufixo #{biz} aplicado em ensureRoles). */
    private const ROLES = [
        'mecanico',
        'gerente',
    ];

    // ----- Processo 1: cacamba_locacao ----------------------------------

    /** @var array<string, string> stage_key → label PT-BR */
    private const LOCACAO_STAGES = [
        'disponivel' => 'Disponível',
        'locada'     => 'Locada (com cliente)',
        'recolhida'  => 'Recolhida',
        'manutencao' => 'Em manutenção',
    ];

    /** @var array<string, array{order:int, terminal?:bool, color:string, initial?:bool}> */
    private const LOCACAO_STAGE_META = [
        'disponivel' => ['order' => 0, 'color' => 'gray',    'initial' => true],
        'locada'     => ['order' => 1, 'color' => 'blue'],
        'recolhida'  => ['order' => 2, 'color' => 'emerald', 'terminal' => true],
        'manutencao' => ['order' => 3, 'color' => 'yellow'],
    ];

    // ----- Processo 2: cacamba_manutencao -------------------------------

    /** @var array<string, string> */
    private const MANUTENCAO_STAGES = [
        'aberta'     => 'Aberta',
        'em_servico' => 'Em serviço',
        'concluida'  => 'Concluída',
        'cancelada'  => 'Cancelada',
    ];

    /** @var array<string, array{order:int, terminal?:bool, color:string, initial?:bool}> */
    private const MANUTENCAO_STAGE_META = [
        'aberta'     => ['order' => 0, 'color' => 'gray',    'initial' => true],
        'em_servico' => ['order' => 1, 'color' => 'amber'],
        'concluida'  => ['order' => 2, 'color' => 'emerald', 'terminal' => true],
        'cancelada'  => ['order' => 3, 'color' => 'rose',    'terminal' => true],
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
        $this->seedLocacaoProcess($businessId, $roleMap);
        $this->seedManutencaoProcess($businessId, $roleMap);
    }

    // ------------------------------------------------------------------
    // Roles per-business (UltimatePOS Spatie — proibições §FSM Pipeline)
    // ------------------------------------------------------------------

    /**
     * @return array<string, string> map role canônica → nome resolvido (com sufixo #{biz} se UltimatePOS)
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

    // ------------------------------------------------------------------
    // Processo 1: cacamba_locacao
    // ------------------------------------------------------------------

    /** @param array<string, string> $roleMap */
    private function seedLocacaoProcess(int $businessId, array $roleMap): void
    {
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', 'cacamba_locacao')
            ->first();

        if (! $process) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id'              => $businessId,
                'key'                      => 'cacamba_locacao',
                'name'                     => 'Caçamba — Locação',
                'description'              => 'Pipeline FSM caso Martinho (key legacy preservada · vocabulário canon pós-ADR 0194 = sub-vertical 4 mecânica pesada caminhão basculante CNAE 4520; pré-correção dizia "locação de caçamba avulsa estacionária")',
                'default_for_contact_type' => 'any',
                'active'                   => true,
            ]);
        }

        $stages = $this->ensureStages($process, self::LOCACAO_STAGES, self::LOCACAO_STAGE_META);

        // Definição das transições — [stage_origem, key, label, target_stage, roles[], is_critical, side_effect_fqcn]
        $defs = [
            ['disponivel', 'iniciar_locacao', 'Iniciar locação (entregar caçamba)', 'locada',     ['mecanico', 'gerente'], true,  'App\\Domain\\Fsm\\SideEffects\\IniciarLocacaoCacamba'],
            ['locada',     'recolher',         'Recolher caçamba (devolução)',       'recolhida',  ['mecanico', 'gerente'], false, 'App\\Domain\\Fsm\\SideEffects\\RecolherCacamba'],
            // enviar_manutencao disponível de qualquer não-terminal não-manutencao
            ['disponivel', 'enviar_manutencao', 'Enviar pra manutenção',             'manutencao', ['gerente'],            true,  'App\\Domain\\Fsm\\SideEffects\\EnviarCacambaManutencao'],
            ['recolhida',  'enviar_manutencao', 'Enviar pra manutenção',             'manutencao', ['gerente'],            true,  'App\\Domain\\Fsm\\SideEffects\\EnviarCacambaManutencao'],
            // voltar_disponivel: de manutencao → disponivel; e de recolhida → disponivel
            ['manutencao', 'voltar_disponivel', 'Liberar pra locação',               'disponivel', ['mecanico', 'gerente'], false, 'App\\Domain\\Fsm\\SideEffects\\VoltarCacambaDisponivel'],
            ['recolhida',  'voltar_disponivel', 'Liberar pra locação',               'disponivel', ['mecanico', 'gerente'], false, 'App\\Domain\\Fsm\\SideEffects\\VoltarCacambaDisponivel'],
        ];

        $this->ensureActions($stages, $defs, $roleMap);
    }

    // ------------------------------------------------------------------
    // Processo 2: cacamba_manutencao
    // ------------------------------------------------------------------

    /** @param array<string, string> $roleMap */
    private function seedManutencaoProcess(int $businessId, array $roleMap): void
    {
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', 'cacamba_manutencao')
            ->first();

        if (! $process) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id'              => $businessId,
                'key'                      => 'cacamba_manutencao',
                'name'                     => 'Caçamba — Manutenção',
                'description'              => 'Pipeline de manutenção/reparo de caçamba (3 estados simples)',
                'default_for_contact_type' => 'any',
                'active'                   => true,
            ]);
        }

        $stages = $this->ensureStages($process, self::MANUTENCAO_STAGES, self::MANUTENCAO_STAGE_META);

        $defs = [
            ['aberta',     'iniciar_servico', 'Iniciar serviço',  'em_servico', ['mecanico'],            false, 'App\\Domain\\Fsm\\SideEffects\\IniciarServicoCacamba'],
            ['em_servico', 'concluir',        'Concluir serviço', 'concluida',  ['mecanico', 'gerente'], true,  'App\\Domain\\Fsm\\SideEffects\\ConcluirServicoCacamba'],
            // cancelar disponível em qualquer stage não-terminal
            ['aberta',     'cancelar',        'Cancelar serviço', 'cancelada',  ['gerente'],             true,  'App\\Domain\\Fsm\\SideEffects\\CancelarServicoCacamba'],
            ['em_servico', 'cancelar',        'Cancelar serviço', 'cancelada',  ['gerente'],             true,  'App\\Domain\\Fsm\\SideEffects\\CancelarServicoCacamba'],
        ];

        $this->ensureActions($stages, $defs, $roleMap);
    }

    // ------------------------------------------------------------------
    // Helpers compartilhados
    // ------------------------------------------------------------------

    /**
     * @param array<string, string> $stagesLabels
     * @param array<string, array{order:int, terminal?:bool, color:string, initial?:bool}> $stagesMeta
     * @return array<string, SaleProcessStage>
     */
    private function ensureStages(SaleProcess $process, array $stagesLabels, array $stagesMeta): array
    {
        $stages = [];
        foreach ($stagesLabels as $key => $name) {
            $meta = $stagesMeta[$key];
            $stages[$key] = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => $key],
                [
                    'name'        => $name,
                    'sort_order'  => $meta['order'],
                    'is_initial'  => $meta['initial'] ?? false,
                    'is_terminal' => $meta['terminal'] ?? false,
                    'color'       => $meta['color'],
                ],
            );
        }
        return $stages;
    }

    /**
     * @param array<string, SaleProcessStage> $stages
     * @param list<array{0:string,1:string,2:string,3:string|null,4:list<string>,5:bool,6:string|null}> $defs
     * @param array<string, string> $roleMap
     */
    private function ensureActions(array $stages, array $defs, array $roleMap): void
    {
        foreach ($defs as [$stageKey, $key, $label, $targetKey, $roles, $isCritical, $sideEffect]) {
            $stage = $stages[$stageKey];
            $action = SaleStageAction::firstOrCreate(
                ['stage_id' => $stage->id, 'key' => $key],
                [
                    'label'                 => $label,
                    'target_stage_id'       => $targetKey ? $stages[$targetKey]->id : null,
                    'side_effect_class'     => $sideEffect,
                    'requires_confirmation' => $isCritical,
                    'is_critical'           => $isCritical,
                ],
            );

            // Sync roles idempotente — usa nome resolvido com sufixo #{biz} (UltimatePOS)
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

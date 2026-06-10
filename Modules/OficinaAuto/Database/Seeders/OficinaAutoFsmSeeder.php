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
 *  1) cacamba_locacao   — processo LEGADO (keys preservadas Tier 0; labels já em
 *     vocabulário de REPARO — ADR 0265). OS novas NUNCA entram aqui (mapa
 *     ServiceOrderPipelineStarter não roteia pra este processo).
 *     Stages (4): disponivel (initial) → locada → recolhida (terminal)
 *                 lateral: manutencao
 *     Actions (4): iniciar_locacao | recolher | enviar_manutencao | voltar_disponivel
 *
 *  2) cacamba_manutencao — fluxo de manutenção caçamba (oficina simples)
 *     Stages (4): aberta (initial) → em_servico → concluida (terminal)
 *                 lateral: cancelada (terminal)
 *     Actions (3): iniciar_servico | concluir | cancelar
 *
 *  3) oficina_mecanica_os — fluxo REAL da oficina de mecânica pesada do Martinho
 *     (caminhão entra pra manutenção/troca de peça — NÃO é locação de caçamba).
 *     Confirmado por [W] 2026-06-02 (sessão port do Kanban do carro). Nome correto
 *     SEM "caçamba" (o legado cacamba_* foi equívoco corrigido pela ADR 0194).
 *     Stages (6 board + 3 terminais): recepcao (initial) → em_diagnostico →
 *                 aguardando_aprovacao → aguardando_pecas → em_execucao →
 *                 pronto_retirada → entregue (terminal)
 *                 laterais terminais: cancelado, garantia_acionada
 *     Actions: iniciar_diagnostico | enviar_orcamento | aprovar_pedir_pecas |
 *              aprovar_executar | recusar_orcamento | pecas_chegaram |
 *              concluir_servico | entregar | acionar_garantia | cancelar_os
 *     NÃO usa side-effects (sem módulo de estoque na OficinaAuto ainda) — transições
 *     puras de stage + audit em sale_stage_history. Quando catálogo de peça
 *     hidráulica (US-OFICINA-027) integrar estoque, anexar Reservar/ConsumirEstoque.
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

    /**
     * @var array<string, string> stage_key → label PT-BR.
     *
     * KEYS intocadas (compat backwards prod biz=164 + trava FSM); DISPLAY NAMES já em
     * vocabulário de REPARO (ADR 0265 erradica locação — auditoria [CC] 2026-06-09). O dado
     * em prod é renomeado pela migration 2026_06_09_000003_rename_cacamba_locacao_stage_labels.
     */
    private const LOCACAO_STAGES = [
        'disponivel' => 'Aguardando',
        'locada'     => 'Em execução',
        'recolhida'  => 'Pronto p/ retirar',
        'manutencao' => 'Em diagnóstico',
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

    // ----- Processo 3: oficina_mecanica_os (fluxo REAL do carro · [W] 2026-06-02) -----

    /** @var array<string, string> stage_key → label PT-BR (fluxo de reparo de caminhão) */
    private const MECANICA_STAGES = [
        'recepcao'             => 'Recepção',
        'em_diagnostico'       => 'Diagnóstico',
        'aguardando_aprovacao' => 'Aguardando aprovação',
        'aguardando_pecas'     => 'Aguardando peças',
        'em_execucao'          => 'Em execução',
        'pronto_retirada'      => 'Pronto p/ retirar',
        'entregue'             => 'Entregue',
        'cancelado'            => 'Cancelado',
        'garantia_acionada'    => 'Garantia acionada',
    ];

    /** @var array<string, array{order:int, terminal?:bool, color:string, initial?:bool}> */
    private const MECANICA_STAGE_META = [
        'recepcao'             => ['order' => 0, 'color' => 'gray',    'initial' => true],
        'em_diagnostico'       => ['order' => 1, 'color' => 'blue'],
        'aguardando_aprovacao' => ['order' => 2, 'color' => 'amber'],
        'aguardando_pecas'     => ['order' => 3, 'color' => 'violet'],
        'em_execucao'          => ['order' => 4, 'color' => 'indigo'],
        'pronto_retirada'      => ['order' => 5, 'color' => 'emerald'],
        'entregue'             => ['order' => 6, 'color' => 'green',   'terminal' => true],
        'cancelado'            => ['order' => 7, 'color' => 'rose',    'terminal' => true],
        'garantia_acionada'    => ['order' => 8, 'color' => 'orange',  'terminal' => true],
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
        $this->seedMecanicaOsProcess($businessId, $roleMap);
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
                'name'                     => 'Fluxo legado — equipamento',
                'description'              => 'Pipeline FSM caso Martinho (key legacy preservada · vocabulário canon pós-ADR 0194 = sub-vertical 4 mecânica pesada caminhão basculante CNAE 4520; nome user-facing em vocabulário de reparo — ADR 0265)',
                'default_for_contact_type' => 'any',
                'active'                   => true,
            ]);
        }

        $stages = $this->ensureStages($process, self::LOCACAO_STAGES, self::LOCACAO_STAGE_META);

        // Definição das transições — [stage_origem, key, label, target_stage, roles[], is_critical, side_effect_fqcn]
        // KEYS e side_effect_class INTOCADOS (Tier 0 — ADR 0143/0194, Martinho live);
        // LABELS em vocabulário de REPARO (ADR 0265 erradica locação — evidência [W]
        // 2026-06-10 OS-00004: checklist oferecia "Iniciar locação (entregar caçamba)").
        // Dado em prod renomeado pela migration 2026_06_10_000002 (firstOrCreate não
        // atualiza linha existente — seeder cobre só ambiente novo).
        $defs = [
            ['disponivel', 'iniciar_locacao', 'Iniciar execução',        'locada',     ['mecanico', 'gerente'], true,  'App\\Domain\\Fsm\\SideEffects\\IniciarLocacaoCacamba'],
            ['locada',     'recolher',         'Concluir serviço',        'recolhida',  ['mecanico', 'gerente'], false, 'App\\Domain\\Fsm\\SideEffects\\RecolherCacamba'],
            // enviar_manutencao disponível de qualquer não-terminal não-manutencao
            ['disponivel', 'enviar_manutencao', 'Enviar pra diagnóstico', 'manutencao', ['gerente'],            true,  'App\\Domain\\Fsm\\SideEffects\\EnviarCacambaManutencao'],
            ['recolhida',  'enviar_manutencao', 'Enviar pra diagnóstico', 'manutencao', ['gerente'],            true,  'App\\Domain\\Fsm\\SideEffects\\EnviarCacambaManutencao'],
            // voltar_disponivel: de manutencao → disponivel; e de recolhida → disponivel
            ['manutencao', 'voltar_disponivel', 'Voltar pra aguardando',  'disponivel', ['mecanico', 'gerente'], false, 'App\\Domain\\Fsm\\SideEffects\\VoltarCacambaDisponivel'],
            ['recolhida',  'voltar_disponivel', 'Voltar pra aguardando',  'disponivel', ['mecanico', 'gerente'], false, 'App\\Domain\\Fsm\\SideEffects\\VoltarCacambaDisponivel'],
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
    // Processo 3: oficina_mecanica_os (fluxo REAL do carro — [W] 2026-06-02)
    // ------------------------------------------------------------------

    /**
     * Fluxo de reparo de caminhão pesado (Martinho) — confirmado por [W]:
     *   Recepção → Diagnóstico → Aguardando aprovação → Aguardando peças →
     *   Em execução → Pronto p/ retirar → Entregue. Laterais: Cancelado, Garantia.
     *
     * Transições puras (side_effect null) — sem módulo de estoque na OficinaAuto.
     * RBAC: actions críticas (aprovação/conclusão/cancelamento) exigem role
     * (fail-secure US-SELL-031), por isso TODAS recebem mecanico e/ou gerente.
     *
     * @param array<string, string> $roleMap
     */
    private function seedMecanicaOsProcess(int $businessId, array $roleMap): void
    {
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', 'oficina_mecanica_os')
            ->first();

        if (! $process) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id'              => $businessId,
                'key'                      => 'oficina_mecanica_os',
                'name'                     => 'Oficina — OS de Mecânica',
                'description'              => 'Pipeline FSM real da oficina de mecânica pesada (caminhão entra pra manutenção/troca de peça). Recepção → Diagnóstico → Aprovação → Peças → Execução → Pronto p/ retirar → Entregue (ADR 0194 · confirmado [W] 2026-06-02).',
                'default_for_contact_type' => 'any',
                'active'                   => true,
            ]);
        }

        $stages = $this->ensureStages($process, self::MECANICA_STAGES, self::MECANICA_STAGE_META);

        // [stage_origem, key, label, target, roles[], is_critical, side_effect(null)]
        $defs = [
            // Linha principal do reparo
            ['recepcao',             'iniciar_diagnostico', 'Iniciar diagnóstico',                 'em_diagnostico',       ['mecanico', 'gerente'], false, null],
            ['em_diagnostico',       'enviar_orcamento',    'Enviar orçamento pra aprovação',      'aguardando_aprovacao', ['mecanico', 'gerente'], false, null],
            // Aprovação do cliente — crítica (decisão comercial)
            ['aguardando_aprovacao', 'aprovar_pedir_pecas', 'Cliente aprovou — pedir peças',       'aguardando_pecas',     ['gerente', 'mecanico'], true,  null],
            ['aguardando_aprovacao', 'aprovar_executar',    'Cliente aprovou — já executar',       'em_execucao',          ['gerente', 'mecanico'], true,  null],
            ['aguardando_aprovacao', 'recusar_orcamento',   'Cliente recusou orçamento',           'cancelado',            ['gerente'],             true,  null],
            // Peças → execução
            ['aguardando_pecas',     'pecas_chegaram',      'Peças chegaram — iniciar execução',   'em_execucao',          ['mecanico', 'gerente'], false, null],
            // Conclusão técnica — crítica
            ['em_execucao',          'concluir_servico',    'Concluir serviço',                    'pronto_retirada',      ['mecanico', 'gerente'], true,  null],
            // Entrega ao cliente
            ['pronto_retirada',      'entregar',            'Entregar ao cliente',                 'entregue',             ['mecanico', 'gerente'], false, null],
            // Garantia (lateral, pré-entrega defeito reaberto) — crítica
            ['pronto_retirada',      'acionar_garantia',    'Acionar garantia',                    'garantia_acionada',    ['gerente'],             true,  null],
            // Cancelamento — gerente, de qualquer stage de board não-terminal
            ['recepcao',             'cancelar_os',         'Cancelar OS',                         'cancelado',            ['gerente'],             true,  null],
            ['em_diagnostico',       'cancelar_os',         'Cancelar OS',                         'cancelado',            ['gerente'],             true,  null],
            ['aguardando_pecas',     'cancelar_os',         'Cancelar OS',                         'cancelado',            ['gerente'],             true,  null],
            ['em_execucao',          'cancelar_os',         'Cancelar OS',                         'cancelado',            ['gerente'],             true,  null],
            ['pronto_retirada',      'cancelar_os',         'Cancelar OS',                         'cancelado',            ['gerente'],             true,  null],
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

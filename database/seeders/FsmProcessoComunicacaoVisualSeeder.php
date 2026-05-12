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
 * US-COMVIS-NEW-001 — Processo FSM "OS Comunicação Visual" (ADR 0143 — LIVE prod).
 *
 * Espelha o pattern dos seeders FsmProcessoVendaComProducaoSeeder (Sells) e
 * FsmProcessoOsReparoPadraoSeeder (Repair) — pipeline OS de comunicação visual
 * com RBAC granular per-action, side-effects, e roles per-business.
 *
 * Stages canônicos (SPEC §11.1 — 13 ativos + 2 terminais + opcional `aguardando_maquina`):
 *
 *   quote_draft (initial) → quote_sent → quote_approved →
 *   arte_em_aprovacao → arte_aprovada →
 *   aguardando_maquina (OPCIONAL — habilitar via business_settings) →
 *   em_impressao → impressao_concluida →
 *   aguardando_acabamento → acabamento_concluido →
 *   aguardando_instalacao → em_instalacao → instalado_aguardando_aprovacao_final →
 *   entregue_completo (T)
 *   Laterais terminais: cancelado, garantia_acionada
 *
 * Roles per-business (Spatie Permission UltimatePOS — coluna roles.business_id NOT NULL):
 *   comvis.designer, comvis.operador, comvis.instalador,
 *   comvis.atendimento, comvis.gerente, comvis.financeiro,
 *   comvis.fiscal, comvis.estoque, comvis.logistica, comvis.system
 *   (10 roles → suffix #{biz} aplicado em UltimatePOS)
 *
 * Actions críticas 🔒 (SPEC §11.2 — is_critical=true + RBAC obrigatório):
 *   enviar_para_aprovacao_arte, aprovar_arte, iniciar_impressao,
 *   concluir_impressao, concluir_acabamento, concluir_instalacao,
 *   emitir_nfe_e_nfse, cancelar_os, aplicar_garantia
 *
 * Side-effects orquestrados via SaleStageAction.side_effect_class:
 *   - ReservarEstoque (quote_approved trigger reserva substrato)
 *   - ConsumirEstoque (impressao_concluida consome m² substrato reserved)
 *   - LiberarReserva (cancelar_os qualquer não-terminal)
 *   - CancelarVendaCascade (cancela NFe SEFAZ + Asaas + Whatsapp + email)
 *
 * Idempotente — rodar múltiplas vezes não cria duplicatas.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §11
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see database/seeders/FsmProcessoOsReparoPadraoSeeder.php (pattern referência)
 * @see database/seeders/FsmProcessoVendaComProducaoSeeder.php (pattern referência)
 */
class FsmProcessoComunicacaoVisualSeeder extends Seeder
{
    /** @var array<string, string> Mapping key → label PT-BR */
    private const STAGES = [
        'quote_draft'                            => 'Orçamento — Rascunho',
        'quote_sent'                             => 'Orçamento — Enviado',
        'quote_approved'                         => 'Aprovado pelo cliente',
        'arte_em_aprovacao'                      => 'Arte em aprovação',
        'arte_aprovada'                          => 'Arte aprovada',
        'aguardando_maquina'                     => 'Aguardando máquina (PCP)',
        'em_impressao'                           => 'Em impressão',
        'impressao_concluida'                    => 'Impressão concluída',
        'aguardando_acabamento'                  => 'Aguardando acabamento',
        'acabamento_concluido'                   => 'Acabamento concluído',
        'aguardando_instalacao'                  => 'Aguardando instalação',
        'em_instalacao'                          => 'Em instalação',
        'instalado_aguardando_aprovacao_final'   => 'Instalado — aguardando aprovação final',
        'entregue_completo'                      => 'Entregue ao cliente',
        'cancelado'                              => 'Cancelado',
        'garantia_acionada'                      => 'Garantia acionada',
    ];

    /** @var array<string, array{order:int, terminal?:bool, color:string, default_active?:bool}> */
    private const STAGE_META = [
        'quote_draft'                            => ['order' => 0,  'color' => 'gray'],
        'quote_sent'                             => ['order' => 1,  'color' => 'blue'],
        'quote_approved'                         => ['order' => 2,  'color' => 'cyan'],
        'arte_em_aprovacao'                      => ['order' => 3,  'color' => 'amber'],
        'arte_aprovada'                          => ['order' => 4,  'color' => 'violet'],
        // Stage opcional — habilitado per-business via UI admin (US-COMVIS-NEW-002)
        // Default: inserido mas pode ser desativado via flag is_active=false
        'aguardando_maquina'                     => ['order' => 5,  'color' => 'slate', 'default_active' => false],
        'em_impressao'                           => ['order' => 6,  'color' => 'orange'],
        'impressao_concluida'                    => ['order' => 7,  'color' => 'green'],
        'aguardando_acabamento'                  => ['order' => 8,  'color' => 'amber'],
        'acabamento_concluido'                   => ['order' => 9,  'color' => 'green'],
        'aguardando_instalacao'                  => ['order' => 10, 'color' => 'amber'],
        'em_instalacao'                          => ['order' => 11, 'color' => 'indigo'],
        'instalado_aguardando_aprovacao_final'   => ['order' => 12, 'color' => 'amber'],
        'entregue_completo'                      => ['order' => 13, 'terminal' => true, 'color' => 'green'],
        'cancelado'                              => ['order' => 14, 'terminal' => true, 'color' => 'red'],
        'garantia_acionada'                      => ['order' => 15, 'terminal' => true, 'color' => 'red'],
    ];

    /** @var list<string> Roles canônicas CV (sufixo #{biz} aplicado no ensureRoles) */
    private const ROLES = [
        'comvis.designer',
        'comvis.operador',
        'comvis.instalador',
        'comvis.atendimento',
        'comvis.gerente',
        'comvis.financeiro',
        'comvis.fiscal',
        'comvis.estoque',
        'comvis.logistica',
        'comvis.system',
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
            ->where('key', 'os_comunicacao_visual')
            ->first();

        if (! $process) {
            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id'              => $businessId,
                'key'                      => 'os_comunicacao_visual',
                'name'                     => 'OS Comunicação Visual',
                'description'              => 'Pipeline OS gráfica/com.visual Orçamento → Arte → Impressão → Acabamento → Instalação → Entrega com RBAC granular (CNAE 1813-0/01)',
                'default_for_contact_type' => 'any',
                'active'                   => true,
            ]);
        }

        $stages = $this->ensureStages($process);
        $this->ensureActions($stages, $roleMap);
    }

    /**
     * Cria roles globais OU per-business conforme schema da tabela `roles`.
     *
     * UltimatePOS estende Spatie Permission com coluna `roles.business_id` NOT NULL + FK.
     * Convenção (lição hotfix #624 — proibicoes.md §FSM Pipeline):
     *   - Quando coluna existe: nome único `role.key#{business_id}` + business_id explícito
     *   - Quando NÃO existe (Pest in-memory SQLite): role global com nome puro
     *
     * SaleStageActionRole.role_name guarda o nome resolvido pra
     * $user->hasAnyRole($roleNames) bater independente do schema.
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
                    'name'        => $name,
                    'sort_order'  => $meta['order'],
                    'is_initial'  => $key === 'quote_draft',
                    'is_terminal' => $meta['terminal'] ?? false,
                    'color'       => $meta['color'],
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
        $defs = [
            // ── Orçamento ─────────────────────────────────────────────────────
            ['quote_draft', 'enviar_orcamento', 'Enviar orçamento ao cliente', 'quote_sent', ['comvis.atendimento'], false, null],
            ['quote_sent', 'cliente_aprovou', 'Cliente aprovou orçamento', 'quote_approved', ['comvis.atendimento', 'comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\ReservarEstoque'],
            ['quote_sent', 'cliente_rejeitou', 'Cliente rejeitou orçamento', 'cancelado', ['comvis.atendimento'], false, null],

            // ── Arte ──────────────────────────────────────────────────────────
            ['quote_approved', 'enviar_para_aprovacao_arte', 'Enviar arte pra aprovação cliente', 'arte_em_aprovacao', ['comvis.designer', 'comvis.gerente'], true, null],
            // aprovar_arte 🔒 — pode ser disparada via system_user (link público token US-COMVIS-NEW-004) OU gerente manual
            ['arte_em_aprovacao', 'aprovar_arte', 'Arte aprovada pelo cliente', 'arte_aprovada', ['comvis.system', 'comvis.gerente'], true, null],
            ['arte_em_aprovacao', 'rejeitar_arte', 'Rejeitar arte (volta pra designer)', 'arte_em_aprovacao', ['comvis.system', 'comvis.gerente'], false, null],

            // ── Impressão (com fork condicional aguardando_maquina) ───────────
            // Default: arte_aprovada → em_impressao direto (Gold-tipo, sem PCP industrial)
            ['arte_aprovada', 'iniciar_impressao', 'Iniciar impressão', 'em_impressao', ['comvis.operador', 'comvis.gerente'], true, null],
            // Override: gráfica industrial habilita aguardando_maquina via business_settings (US-COMVIS-NEW-002)
            ['arte_aprovada', 'aguardar_maquina', 'Aguardar liberação de máquina (PCP industrial)', 'aguardando_maquina', ['comvis.operador', 'comvis.gerente'], false, null],
            ['aguardando_maquina', 'iniciar_impressao', 'Iniciar impressão (máquina liberada)', 'em_impressao', ['comvis.operador', 'comvis.gerente'], true, null],
            // concluir_impressao 🔒 — side-effect ConsumirEstoque substrato (m² lona reservation)
            ['em_impressao', 'concluir_impressao', 'Concluir impressão', 'impressao_concluida', ['comvis.operador', 'comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\ConsumirEstoque'],
            ['em_impressao', 'refazer_impressao', 'Refazer impressão (problema detectado)', 'em_impressao', ['comvis.operador', 'comvis.gerente'], true, null],

            // ── Acabamento ────────────────────────────────────────────────────
            ['impressao_concluida', 'iniciar_acabamento', 'Iniciar acabamento', 'aguardando_acabamento', ['comvis.operador'], false, null],
            ['aguardando_acabamento', 'concluir_acabamento', 'Concluir acabamento', 'acabamento_concluido', ['comvis.operador', 'comvis.gerente'], true, null],

            // ── Instalação (com fork: cliente busca pula direto pra entregue) ─
            // Caminho 1: cliente_busca → jump direto pra entregue_completo
            ['acabamento_concluido', 'entregar_balcao', 'Cliente busca no balcão', 'entregue_completo', ['comvis.atendimento'], false, null],
            // Caminho 2: instalação agendada
            ['acabamento_concluido', 'agendar_instalacao', 'Agendar instalação', 'aguardando_instalacao', ['comvis.atendimento', 'comvis.gerente'], false, null],
            ['aguardando_instalacao', 'iniciar_instalacao', 'Iniciar instalação (chegou no cliente)', 'em_instalacao', ['comvis.instalador'], false, null],
            ['aguardando_instalacao', 'reagendar_instalacao', 'Reagendar instalação', 'aguardando_instalacao', ['comvis.atendimento', 'comvis.gerente'], false, null],
            // concluir_instalacao 🔒 — gera assinatura cliente + GPS (LGPD consent obrigatório)
            ['em_instalacao', 'concluir_instalacao', 'Concluir instalação', 'instalado_aguardando_aprovacao_final', ['comvis.instalador', 'comvis.gerente'], true, null],
            ['instalado_aguardando_aprovacao_final', 'aprovacao_final_cliente', 'Cliente aprovou instalação final', 'entregue_completo', ['comvis.system', 'comvis.atendimento', 'comvis.gerente'], false, null],

            // ── Fiscal (dual-doc NFe55 + NFSe56 paralelo) US-COMVIS-NEW-003 ────
            // emitir_nfe_e_nfse 🔒 — pode ser disparada em entregue_completo pra emitir os 2 docs paralelos
            ['entregue_completo', 'emitir_nfe_e_nfse', 'Emitir NFe55 (mercadoria) + NFSe56 (instalação) paralelo', null, ['comvis.financeiro', 'comvis.fiscal', 'comvis.gerente'], true, null],

            // ── Garantia (pós entregue_completo) ──────────────────────────────
            ['entregue_completo', 'aplicar_garantia', 'Acionar garantia (abre OS filha)', 'garantia_acionada', ['comvis.gerente'], true, null],

            // ── Cancelamento — disponível em todo stage não-terminal ──────────
            // Side-effect CancelarVendaCascade orquestra cancel NFe SEFAZ + Asaas/Inter + WhatsApp/email
            ['quote_draft', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['quote_sent', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['quote_approved', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['arte_em_aprovacao', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['arte_aprovada', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['aguardando_maquina', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['em_impressao', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['impressao_concluida', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['aguardando_acabamento', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['acabamento_concluido', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['aguardando_instalacao', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['em_instalacao', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
            ['instalado_aguardando_aprovacao_final', 'cancelar_os', 'Cancelar OS', 'cancelado', ['comvis.gerente'], true, 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade'],
        ];

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

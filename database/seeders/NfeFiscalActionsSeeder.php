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
 * US-SELL-029 + US-SELL-030 — Actions FSM fiscais (NFe + Inutilização).
 *
 * Seeder DESACOPLADO de FsmProcessoVendaComProducaoSeeder pra evitar conflito
 * de área com Agent A (que mexe em sale_stage_actions migrations + seeder).
 *
 * Actions adicionadas:
 *   - emitir_nova_apos_cancelamento (US-SELL-029) — disponível em qualquer
 *     stage não-terminal após NFe cancelada via SEFAZ. CRIA NOVA TRANSACTION
 *     (link via metadata.original_transaction_id) — número fiscal preservado
 *     no registro original (CONFAZ SINIEF 07/2005 Art. 14).
 *
 *   - inutilizar_faixa (US-SELL-030) — action TRANSVERSAL (não pertence a stage
 *     específico), chamável via UI admin fiscal /nfe-brasil/inutilizacoes.
 *     Aciona NfeInutilizacaoService::inutilizar(). NÃO transita stage.
 *
 * Idempotente — rodar múltiplas vezes não duplica.
 *
 * Pré-req: FsmProcessoVendaComProducaoSeeder rodado primeiro (cria
 * SaleProcess + stages base). Agent A é dono do seeder principal — esse
 * apenas APENDA actions fiscais sem tocar lá.
 *
 * Pain point Wagner 2026-05-12:
 *   "cancelam nota perdem número pula sequencial"
 *
 * Refs:
 *   - SPEC.md US-SELL-029 + US-SELL-030
 *   - CONFAZ Ajuste SINIEF 07/2005 Art. 14
 *   - memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-01
 */
class NfeFiscalActionsSeeder extends Seeder
{
    /** @var list<string> Roles fiscais necessárias (idempotente) */
    private const ROLES = [
        'fiscal.emitir',
        'fiscal.inutilizar',
        'vendas.gerente', // pra emitir_nova_apos_cancelamento (decisão custosa)
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

        // Localiza process Venda Com Produção (criado por FsmProcessoVendaComProducaoSeeder).
        // Se ausente — Agent A ainda não rodou, skip silencioso (idempotente).
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', 'venda_com_producao')
            ->first();

        if (! $process) {
            $this->command?->warn(
                "NfeFiscalActionsSeeder: process 'venda_com_producao' ausente pra biz={$businessId}. ".
                'Rode FsmProcessoVendaComProducaoSeeder primeiro.'
            );
            return;
        }

        $stages = SaleProcessStage::withoutGlobalScope(ScopeByBusiness::class)
            ->where('process_id', $process->id)
            ->get()
            ->keyBy('key');

        $this->ensureFiscalActions($stages, $roleMap);
    }

    /**
     * Cria roles fiscais respeitando convenção UltimatePOS (sufixo #{biz}).
     * Imita ensureRoles do FsmProcessoVendaComProducaoSeeder.
     *
     * @return array<string, string> key canônica → nome resolvido
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

    /**
     * @param \Illuminate\Support\Collection<string, SaleProcessStage> $stages
     * @param array<string, string> $roleMap
     */
    private function ensureFiscalActions($stages, array $roleMap): void
    {
        // ── Action 1: emitir_nova_apos_cancelamento (US-SELL-029) ────────────
        // Disponível em stages onde faz sentido refazer venda (após cancelamento
        // SEFAZ aceito). NÃO transita stage — cria NOVA transaction com link
        // pro original via metadata. Side-effect handler ainda a implementar
        // (App\Domain\Fsm\SideEffects\EmitirNovaAposCancelamento — Wave 2).
        $stagesPraReemissao = ['invoiced', 'cancelled'];

        foreach ($stagesPraReemissao as $stageKey) {
            $stage = $stages->get($stageKey);
            if (! $stage) {
                continue; // stage não existe — skip silencioso
            }

            $action = SaleStageAction::firstOrCreate(
                ['stage_id' => $stage->id, 'key' => 'emitir_nova_apos_cancelamento'],
                [
                    'label' => 'Emitir nova NFe (após cancelamento SEFAZ)',
                    'target_stage_id' => null, // não transita — cria nova transaction
                    'side_effect_class' => 'App\\Domain\\Fsm\\SideEffects\\EmitirNovaAposCancelamento',
                    'requires_confirmation' => true,
                    'is_critical' => true, // exige role gerente — decisão custosa
                ],
            );

            $this->syncRoles($action, ['vendas.gerente', 'fiscal.emitir'], $roleMap);
        }

        // ── Action 2: inutilizar_faixa (US-SELL-030) ─────────────────────────
        // TRANSVERSAL — disponível em qualquer stage não-terminal pra fechar
        // gap de sequencial fiscal. Acionável via UI admin fiscal.
        // NÃO transita stage (target_stage_id=null). Side-effect chama
        // NfeInutilizacaoService::inutilizar() com payload da request.
        $stagesPraInutilizacao = ['invoiced', 'ready_for_invoice'];

        foreach ($stagesPraInutilizacao as $stageKey) {
            $stage = $stages->get($stageKey);
            if (! $stage) {
                continue;
            }

            $action = SaleStageAction::firstOrCreate(
                ['stage_id' => $stage->id, 'key' => 'inutilizar_faixa'],
                [
                    'label' => 'Inutilizar faixa de números fiscais (SEFAZ)',
                    'target_stage_id' => null, // não transita
                    'side_effect_class' => 'App\\Domain\\Fsm\\SideEffects\\InutilizarFaixaNfe',
                    'requires_confirmation' => true,
                    'is_critical' => true,
                ],
            );

            $this->syncRoles($action, ['fiscal.inutilizar'], $roleMap);
        }
    }

    /**
     * Sync roles na action (cria as faltantes, mantém existentes).
     *
     * @param array<int, string> $roleKeys
     * @param array<string, string> $roleMap
     */
    private function syncRoles(SaleStageAction $action, array $roleKeys, array $roleMap): void
    {
        $existingRoles = $action->roles->pluck('role_name')->all();

        foreach ($roleKeys as $roleKey) {
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

<?php

declare(strict_types=1);

namespace Modules\Repair\Http\Controllers;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-REP-FSM-004 — Wire-up FSM canônico (ADR 0129) pra OS Repair.
 *
 * Espelha `App\Http\Controllers\SaleFsmActionController` (Sells) — mesma
 * estrutura de 3 endpoints (actions/execute/startPipeline) com lógica
 * adaptada pra `Modules\Repair\Entities\JobSheet`.
 *
 * Rotas (registradas em Modules/Repair/Routes/web.php):
 *   GET  /api/repair/job-sheets/{id}/fsm-actions     → lista actions disponíveis
 *   POST /repair/job-sheets/{id}/fsm-action          → executa transição (RBAC + side-effect)
 *   POST /repair/job-sheets/{id}/fsm-start-pipeline  → inicia pipeline em OS legacy
 *
 * Multi-tenant Tier 0 (ADR 0093): scope por session('user.business_id').
 * RBAC: ExecuteStageActionService valida internamente (Spatie hasAnyRole).
 *
 * IMPORTANTE: NÃO substitui `JobSheetController@update` legacy — adiciona path
 * FSM paralelo. Coexistência permite rollback per-business (Fase G — canary).
 */
class RepairFsmActionController extends Controller
{
    /**
     * Lista actions disponíveis no stage atual da OS.
     */
    public function actions(int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $businessId = (int) session('user.business_id');
        $os = JobSheet::where('business_id', $businessId)->find($id);

        if (! $os) {
            return response()->json(['error' => 'OS não encontrada'], 404);
        }

        $currentStageId = $os->current_stage_id;

        // Sem stage = OS não está em pipeline FSM ainda
        if ($currentStageId === null) {
            return response()->json([
                'job_sheet_id' => $id,
                'current_stage' => null,
                'actions' => [],
                'in_pipeline' => false,
            ]);
        }

        $currentStage = SaleProcessStage::find($currentStageId);
        $actions = SaleStageAction::with(['targetStage', 'roles'])
            ->where('stage_id', $currentStageId)
            ->get();

        $user = auth()->user();
        $policy = app(StageActionPolicy::class);

        $payload = $actions->map(function (SaleStageAction $a) use ($policy, $user, $os) {
            return [
                'key' => $a->key,
                'label' => $a->label,
                'target_stage' => $a->targetStage ? [
                    'key' => $a->targetStage->key,
                    'name' => $a->targetStage->name,
                    'color' => $a->targetStage->color,
                ] : null,
                'is_critical' => (bool) ($a->is_critical ?? false),
                'requires_confirmation' => (bool) $a->requires_confirmation,
                'has_side_effect' => ! empty($a->side_effect_class),
                'can_execute' => $policy->canExecute($user, $os, $a->key),
            ];
        });

        return response()->json([
            'job_sheet_id' => $id,
            'current_stage' => [
                'key' => $currentStage?->key,
                'name' => $currentStage?->name,
                'color' => $currentStage?->color,
                'is_terminal' => (bool) $currentStage?->is_terminal,
            ],
            'actions' => $payload,
            'in_pipeline' => true,
        ]);
    }

    /**
     * Executa uma transição FSM na OS.
     */
    public function execute(Request $request, int $id, ExecuteStageActionService $service): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'action_key' => 'required|string|max:80',
            'payload' => 'sometimes|array',
        ]);

        $businessId = (int) session('user.business_id');
        $os = JobSheet::where('business_id', $businessId)->find($id);

        if (! $os) {
            return response()->json(['error' => 'OS não encontrada'], 404);
        }

        try {
            $history = $service->execute(
                $os,
                $validated['action_key'],
                auth()->user(),
                $validated['payload'] ?? [],
            );

            return response()->json([
                'ok' => true,
                'history_id' => $history->id,
                'new_stage_id' => $os->fresh()->current_stage_id,
                'to_stage' => $history->toStage ? [
                    'key' => $history->toStage->key,
                    'name' => $history->toStage->name,
                    'color' => $history->toStage->color,
                ] : null,
            ]);
        } catch (UnauthorizedActionException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (InvalidActionForCurrentStageException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('RepairFsmActionController: falha execute', [
                'business_id' => $businessId,
                'job_sheet_id' => $id,
                'action_key' => $validated['action_key'],
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Falha interna: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Inicia pipeline FSM numa OS legacy (current_stage_id IS NULL).
     *
     * Mapeia status legacy (`repair_statuses.name` + `is_completed_status`) pro
     * stage FSM inicial apropriado. Como `repair_statuses` é dinâmica per-business
     * (cliente cria nomenclatura à mão na UI Settings), o mapping é heurístico:
     *
     *   - status name 'received' / 'recebido' / etc → 'recebido_para_diagnostico'
     *   - status name 'analyzing' / 'diagnostico' → 'em_diagnostico'
     *   - status name 'in_repair' / 'execucao' → 'em_execucao'
     *   - status name 'ready' / 'pronto' → 'concluido_aguardando_retirada'
     *   - status name 'delivered' / 'entregue' OR is_completed_status=1 → 'entregue_completo'
     *   - status name 'cancelled' / 'cancelado' → 'cancelado'
     *   - default → 'recebido_para_diagnostico' (início do pipeline)
     *
     * Bulk-start (Fase F) terá heurística mais robusta com `--dry-run` + revisão CSV.
     * Aqui é só inicialização single-OS opt-in via UI.
     */
    public function startPipeline(Request $request, int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'process_key' => 'sometimes|string|max:80',
        ]);
        $processKey = $validated['process_key'] ?? 'os_reparo_padrao';

        $businessId = (int) session('user.business_id');
        $os = JobSheet::where('business_id', $businessId)->find($id);

        if (! $os) {
            return response()->json(['error' => 'OS não encontrada'], 404);
        }

        if ($os->current_stage_id !== null) {
            return response()->json([
                'error' => 'OS já está em pipeline FSM (stage_id=' . $os->current_stage_id . ')',
            ], 422);
        }

        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', $processKey)
            ->where('active', true)
            ->first();

        if (! $process) {
            return response()->json([
                'error' => "Processo '{$processKey}' não cadastrado pro business {$businessId}. " .
                    'Rode o seeder FsmProcessoOsReparoPadraoSeeder.',
            ], 422);
        }

        $stageKey = $this->resolveInitialStage($os);
        $stage = $process->stages()->where('key', $stageKey)->first();

        if (! $stage) {
            return response()->json([
                'error' => "Stage '{$stageKey}' não cadastrado no processo '{$processKey}'.",
            ], 422);
        }

        // Marca flag autorizativa + atualiza current_stage_id (trait GuardsFsmTransitions
        // consome flag; sem ela lança UnauthorizedActionException no save).
        FsmAuthorizationFlag::mark($os::class, $os->getKey());
        $os->current_stage_id = $stage->id;
        $os->save();

        // Audit log: registra entrada no pipeline
        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'transaction_id' => $os->id,
            'action_id' => null,
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'user_id' => auth()->id(),
            'payload_snapshot' => [
                'pipeline_started' => true,
                'process_key' => $processKey,
                'entity' => 'job_sheet',
                'mapped_from' => "status_id={$os->status_id}",
            ],
            'executed_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'process_key' => $processKey,
            'new_stage_id' => $stage->id,
            'stage' => [
                'key' => $stage->key,
                'name' => $stage->name,
                'color' => $stage->color,
            ],
        ]);
    }

    /**
     * Mapeia status legacy da OS pro stage FSM inicial apropriado.
     *
     * Heurística por (a) is_completed_status flag, (b) substring match em name
     * (case-insensitive, PT-BR + EN). Sem match → fallback inicial.
     */
    private function resolveInitialStage(JobSheet $os): string
    {
        if (! $os->status_id) {
            return 'recebido_para_diagnostico';
        }

        // Scope global aplica ao RepairStatus via HasBusinessScope; OS já está
        // scopada por business_id no caller, então status do mesmo biz vai bater.
        // withoutGlobalScope ScopeByBusiness pra evitar dependência de session
        // em fluxos de teste/CLI — filtramos explicitamente por business_id.
        $status = RepairStatus::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $os->business_id)
            ->find($os->status_id);

        if (! $status) {
            return 'recebido_para_diagnostico';
        }

        // is_completed_status=1 → OS finalizada legacy → entregue
        if (! empty($status->is_completed_status)) {
            return 'entregue_completo';
        }

        $name = mb_strtolower((string) $status->name);

        // Cancelado
        if (str_contains($name, 'cancel')) {
            return 'cancelado';
        }

        // Pronto / aguardando retirada
        if (str_contains($name, 'ready') || str_contains($name, 'pronto') || str_contains($name, 'retirada')) {
            return 'concluido_aguardando_retirada';
        }

        // Em execução / em reparo / consertando
        if (str_contains($name, 'in_repair') || str_contains($name, 'repair') ||
            str_contains($name, 'execu') || str_contains($name, 'consert')) {
            return 'em_execucao';
        }

        // Aguardando peças
        if (str_contains($name, 'parts') || str_contains($name, 'peca') || str_contains($name, 'peça')) {
            return 'aguardando_pecas';
        }

        // Em diagnóstico / análise
        if (str_contains($name, 'analy') || str_contains($name, 'diagn') ||
            str_contains($name, 'avali')) {
            return 'em_diagnostico';
        }

        // Aguardando aprovação cliente
        if (str_contains($name, 'aprov') || str_contains($name, 'approv') ||
            str_contains($name, 'orcament') || str_contains($name, 'orçament')) {
            return 'diagnosticado_aguardando_aprovacao';
        }

        // Recebido / aberta / default
        return 'recebido_para_diagnostico';
    }
}

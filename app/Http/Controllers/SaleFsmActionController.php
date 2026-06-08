<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Domain\Fsm\Services\InitialStageResolver;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wire-up UI FSM (US-SELL-035 ext) — endpoints pra UI listar/executar
 * transições FSM da venda no drawer SaleSheet.tsx.
 *
 * Rotas:
 *   GET  /api/sells/{id}/fsm-actions      → lista actions disponíveis stage atual
 *   POST /sells/{id}/fsm-action           → executa transição (RBAC + side-effect)
 *
 * Multi-tenant Tier 0 (ADR 0093): scope por session('user.business_id').
 * RBAC: ExecuteStageActionService valida internamente (Spatie hasAnyRole).
 */
class SaleFsmActionController extends Controller
{
    public function __construct(
        private readonly InitialStageResolver $resolver,
    ) {}

    /**
     * Lista actions disponíveis no stage atual da venda.
     */
    public function actions(int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $businessId = (int) session('user.business_id');
        $venda = Transaction::where('business_id', $businessId)->find($id);

        if (! $venda) {
            return response()->json(['error' => 'Venda não encontrada'], 404);
        }

        $currentStageId = $venda->current_stage_id;

        // Sem stage = venda não está em pipeline FSM ainda
        if ($currentStageId === null) {
            return response()->json([
                'transaction_id' => $id,
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

        $payload = $actions->map(function (SaleStageAction $a) use ($policy, $user, $venda) {
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
                'can_execute' => $policy->canExecute($user, $venda, $a->key),
            ];
        });

        return response()->json([
            'transaction_id' => $id,
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
     * Executa uma transição FSM.
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
        $venda = Transaction::where('business_id', $businessId)->find($id);

        if (! $venda) {
            return response()->json(['error' => 'Venda não encontrada'], 404);
        }

        try {
            $history = $service->execute(
                $venda,
                $validated['action_key'],
                auth()->user(),
                $validated['payload'] ?? [],
            );

            return response()->json([
                'ok' => true,
                'history_id' => $history->id,
                'new_stage_id' => $venda->fresh()->current_stage_id,
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
            \Log::error('SaleFsmActionController: falha execute', [
                'business_id' => $businessId,
                'transaction_id' => $id,
                'action_key' => $validated['action_key'],
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Falha interna: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Inicia pipeline FSM numa venda legada (current_stage_id IS NULL).
     *
     * Mapeia status atual da venda pro stage inicial apropriado:
     *   - status='draft' + sub_status='quotation' → 'quote_sent'
     *   - status='draft' (rascunho puro) → 'quote_draft'
     *   - status='final' + payment_status='paid' → 'paid'
     *   - status='final' + payment_status='due' → 'invoiced'
     *   - default → 'quote_draft' (início do pipeline)
     *
     * Cria entrada em sale_stage_history pra rastreabilidade ("pipeline iniciado").
     */
    public function startPipeline(Request $request, int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'process_key' => 'sometimes|string|max:80',
        ]);
        $processKey = $validated['process_key'] ?? 'venda_com_producao';

        $businessId = (int) session('user.business_id');
        $venda = Transaction::where('business_id', $businessId)->find($id);

        if (! $venda) {
            return response()->json(['error' => 'Venda não encontrada'], 404);
        }

        if ($venda->current_stage_id !== null) {
            return response()->json([
                'error' => 'Venda já está em pipeline FSM (stage_id=' . $venda->current_stage_id . ')',
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
                    'Rode o seeder FsmProcessoVendaComProducaoSeeder.',
            ], 422);
        }

        $stageKey = $this->resolver->resolve($venda);
        $stage = $process->stages()->where('key', $stageKey)->first();

        if (! $stage) {
            return response()->json([
                'error' => "Stage '{$stageKey}' não cadastrado no processo '{$processKey}'.",
            ], 422);
        }

        // Marca flag autorizativa + atualiza current_stage_id
        FsmAuthorizationFlag::mark($venda::class, $venda->getKey());
        $venda->current_stage_id = $stage->id;
        $venda->save();

        // Audit log: registra entrada no pipeline
        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'transaction_id' => $venda->id,
            'action_id' => null,
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'user_id' => auth()->id(),
            'payload_snapshot' => [
                'pipeline_started' => true,
                'process_key' => $processKey,
                'mapped_from' => "status={$venda->status} payment_status={$venda->payment_status} sub_status={$venda->sub_status}",
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

}

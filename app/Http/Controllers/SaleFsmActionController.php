<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
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
}

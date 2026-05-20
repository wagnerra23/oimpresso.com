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
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wire-up UI FSM ServiceOrder (Wave 7-A — espelha SaleFsmActionController).
 *
 * Endpoints pra UI listar/executar transições FSM duma Ordem de Serviço
 * (locação caçamba OU manutenção caçamba — ADR 0137 + ADR 0143).
 *
 * Rotas (Modules/OficinaAuto/Routes/web.php):
 *   GET  /oficina-auto/service-orders/{order}/fsm/actions
 *   POST /oficina-auto/service-orders/{order}/fsm/execute
 *   POST /oficina-auto/service-orders/{order}/fsm/start-pipeline
 *
 * Multi-tenant Tier 0 (ADR 0093): ServiceOrder global scope filtra business_id.
 * RBAC: ExecuteStageActionService valida internamente (Spatie hasAnyRole).
 *
 * Process key resolution: $serviceOrder->order_type
 *   - 'locacao'    → 'cacamba_locacao'    (4 stages, 4 actions)
 *   - 'manutencao' → 'cacamba_manutencao' (4 stages, 3 actions)
 *
 * Defensivo: column `current_stage_id` em service_orders pode ainda não existir
 * (migration FSM dedicated Wave 5/6 owns). Trata como null → in_pipeline=false.
 *
 * @see app/Http/Controllers/SaleFsmActionController.php (pattern canônico)
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (processos cadastrados)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class ServiceOrderFsmActionController extends Controller
{
    /**
     * Map order_type → process_key cadastrado em OficinaAutoFsmSeeder.
     *
     * @var array<string, string>
     */
    private const ORDER_TYPE_TO_PROCESS = [
        'locacao'    => 'cacamba_locacao',
        'manutencao' => 'cacamba_manutencao',
    ];

    /**
     * Lista actions disponíveis no stage atual da OS.
     */
    public function actions(ServiceOrder $order): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            Response::HTTP_FORBIDDEN
        );

        $processKey = $this->resolveProcessKey($order);
        $currentStageId = $order->current_stage_id ?? null;

        // Sem stage = OS não está em pipeline FSM ainda
        if ($currentStageId === null) {
            return response()->json([
                'service_order_id' => $order->id,
                'process_key'      => $processKey,
                'current_stage'    => null,
                'actions'          => [],
                'in_pipeline'      => false,
            ]);
        }

        $currentStage = SaleProcessStage::find($currentStageId);
        $actions = SaleStageAction::with(['targetStage', 'roles'])
            ->where('stage_id', $currentStageId)
            ->get();

        $user = auth()->user();
        $policy = app(StageActionPolicy::class);

        $payload = $actions->map(function (SaleStageAction $a) use ($policy, $user, $order) {
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
                'can_execute' => $policy->canExecute($user, $order, $a->key),
            ];
        });

        // Gap #2 estado-da-arte FSM screen (Wave 7-E) — pipeline horizontal "você está aqui".
        // Lista ordenada de todos stages do processo da OS pra renderizar mini-grafo no drawer.
        // Multi-tenant Tier 0 (ADR 0093) via currentStage.process.business_id.
        $stagesPipeline = collect();
        if ($currentStage && $currentStage->process_id) {
            $stagesPipeline = SaleProcessStage::query()
                ->where('process_id', $currentStage->process_id)
                ->orderBy('sort_order')
                ->get(['id', 'key', 'name', 'color', 'sort_order', 'is_initial', 'is_terminal'])
                ->map(fn ($s) => [
                    'key'         => $s->key,
                    'name'        => $s->name,
                    'color'       => $s->color,
                    'sort_order'  => (int) $s->sort_order,
                    'is_initial'  => (bool) $s->is_initial,
                    'is_terminal' => (bool) $s->is_terminal,
                    'is_current'  => $s->id === $currentStageId,
                ]);
        }

        return response()->json([
            'service_order_id' => $order->id,
            'process_key'      => $processKey,
            'current_stage' => [
                'key' => $currentStage?->key,
                'name' => $currentStage?->name,
                'color' => $currentStage?->color,
                'is_terminal' => (bool) $currentStage?->is_terminal,
            ],
            'stages_pipeline' => $stagesPipeline,
            'actions'         => $payload,
            'in_pipeline'     => true,
        ]);
    }

    /**
     * Executa uma transição FSM na OS.
     */
    public function execute(Request $request, ServiceOrder $order, ExecuteStageActionService $service): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.update'),
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'action_key' => 'required|string|max:80',
            'payload'    => 'sometimes|array',
        ]);

        try {
            $history = $service->execute(
                $order,
                $validated['action_key'],
                auth()->user(),
                $validated['payload'] ?? [],
            );

            return response()->json([
                'ok' => true,
                'history_id'    => $history->id,
                'new_stage_id'  => $order->fresh()->current_stage_id ?? null,
                'to_stage' => $history->toStage ? [
                    'key'   => $history->toStage->key,
                    'name'  => $history->toStage->name,
                    'color' => $history->toStage->color,
                ] : null,
            ]);
        } catch (UnauthorizedActionException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (InvalidActionForCurrentStageException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('ServiceOrderFsmActionController: falha execute', [
                'business_id'      => $order->business_id ?? null,
                'service_order_id' => $order->id,
                'action_key'       => $validated['action_key'],
                'error'            => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Falha interna: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Inicia pipeline FSM numa OS legada (current_stage_id IS NULL).
     *
     * Resolve o processo correto via order_type:
     *  - locacao    → cacamba_locacao    (initial stage: disponivel)
     *  - manutencao → cacamba_manutencao (initial stage: aberta)
     *
     * Cria entrada em sale_stage_history pra rastreabilidade ("pipeline iniciado").
     * Permite override de process_key via request body (edge cases superadmin).
     */
    public function startPipeline(Request $request, ServiceOrder $order): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.update'),
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'process_key' => 'sometimes|string|max:80',
        ]);

        $processKey = $validated['process_key'] ?? $this->resolveProcessKey($order);

        if ($processKey === null) {
            return response()->json([
                'error' => "OS sem order_type definido — não foi possível inferir processo FSM. " .
                    'Informe process_key explicitamente.',
            ], 422);
        }

        if (($order->current_stage_id ?? null) !== null) {
            return response()->json([
                'error' => 'OS já está em pipeline FSM (stage_id=' . $order->current_stage_id . ')',
            ], 422);
        }

        $businessId = (int) $order->business_id;

        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', $processKey)
            ->where('active', true)
            ->first();

        if (! $process) {
            return response()->json([
                'error' => "Processo '{$processKey}' não cadastrado pro business {$businessId}. " .
                    'Rode o seeder OficinaAutoFsmSeeder.',
            ], 422);
        }

        $stage = $process->stages()->where('is_initial', true)->first();

        if (! $stage) {
            return response()->json([
                'error' => "Processo '{$processKey}' não tem stage inicial cadastrado.",
            ], 422);
        }

        // Marca flag autorizativa + atualiza current_stage_id
        FsmAuthorizationFlag::mark($order::class, $order->getKey());
        $order->current_stage_id = $stage->id;
        $order->save();

        // Audit log: registra entrada no pipeline
        SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id'      => $businessId,
            'transaction_id'   => $order->id,  // subject_id polimórfico — usa ID da OS
            'action_id'        => null,
            'from_stage_id'    => null,
            'to_stage_id'      => $stage->id,
            'user_id'          => auth()->id(),
            'payload_snapshot' => [
                'pipeline_started' => true,
                'subject_type'     => ServiceOrder::class,
                'service_order_id' => $order->id,
                'process_key'      => $processKey,
                'order_type'       => $order->order_type ?? null,
            ],
            'executed_at' => now(),
        ]);

        return response()->json([
            'ok'             => true,
            'process_key'    => $processKey,
            'new_stage_id'   => $stage->id,
            'stage' => [
                'key'   => $stage->key,
                'name'  => $stage->name,
                'color' => $stage->color,
            ],
        ]);
    }

    /**
     * Timeline FSM auditável da OS (Wave 7-C — gap #1 estado-da-arte FSM screen).
     *
     * Espelha SaleHistoryController->index() mas discrimina entries de ServiceOrder
     * vs Sale via process_key cacamba_* (ServiceOrder reusa sale_stage_history
     * armazenando $order->id no campo transaction_id — subject_id polimórfico,
     * ver ExecuteStageActionService::executeInternal linha 122).
     *
     * Entries de startPipeline têm action_id NULL — discriminadas via
     * payload_snapshot.pipeline_started=true (ver método startPipeline linha 256).
     *
     * Permissão reusa `oficinaauto.service_order.view` — quem vê a OS vê histórico
     * FSM (não há PII adicional além do já visível no show).
     */
    public function history(ServiceOrder $order): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            Response::HTTP_FORBIDDEN
        );

        $businessId = (int) $order->business_id;
        $processKeys = array_values(self::ORDER_TYPE_TO_PROCESS);

        $items = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('transaction_id', $order->id)
            ->where(function ($q) use ($processKeys) {
                $q->whereHas('action.stage.process', function ($p) use ($processKeys) {
                    $p->whereIn('key', $processKeys);
                })->orWhere(function ($q2) {
                    $q2->whereNull('action_id')
                        ->whereJsonContains('payload_snapshot->pipeline_started', true);
                });
            })
            ->with([
                'action:id,key,label,target_stage_id,side_effect_class,event_class',
                'fromStage:id,key,name,color',
                'toStage:id,key,name,color',
            ])
            ->orderByDesc('executed_at')
            ->limit(200)
            ->get();

        $userIds = $items->pluck('user_id')->filter()->unique()->values();
        $userNames = \DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('username', 'id')
            ->toArray();

        $payload = $items->map(function (SaleStageHistory $h) use ($userNames): array {
            return [
                'id' => $h->id,
                'executed_at' => $h->executed_at?->toIso8601String(),
                'user' => [
                    'id' => $h->user_id,
                    'name' => $h->user_id ? ($userNames[$h->user_id] ?? null) : null,
                ],
                'action' => $h->action ? [
                    'key' => $h->action->key,
                    'label' => $h->action->label,
                    'has_side_effect' => ! empty($h->action->side_effect_class),
                    'has_event' => ! empty($h->action->event_class),
                ] : null,
                'from_stage' => $h->fromStage ? [
                    'key' => $h->fromStage->key,
                    'name' => $h->fromStage->name,
                    'color' => $h->fromStage->color,
                ] : null,
                'to_stage' => $h->toStage ? [
                    'key' => $h->toStage->key,
                    'name' => $h->toStage->name,
                    'color' => $h->toStage->color,
                ] : null,
                'payload' => $h->payload_snapshot,
            ];
        });

        return response()->json([
            'service_order_id' => $order->id,
            'count' => $items->count(),
            'items' => $payload,
        ]);
    }

    /**
     * Resolve process_key a partir do order_type da OS.
     */
    private function resolveProcessKey(ServiceOrder $order): ?string
    {
        $orderType = $order->order_type ?? null;
        return self::ORDER_TYPE_TO_PROCESS[$orderType] ?? null;
    }
}

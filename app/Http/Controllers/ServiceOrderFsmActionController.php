<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\ServiceOrderPipelineStarter;
use Modules\OficinaAuto\Services\StageGateEvaluator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wire-up UI FSM ServiceOrder (Wave 7-A — espelha SaleFsmActionController).
 *
 * Endpoints pra UI listar/executar transições FSM duma Ordem de Serviço
 * (reparo/mecânica — ADR 0137 + ADR 0143 + ADR 0265).
 *
 * Rotas (Modules/OficinaAuto/Routes/web.php):
 *   GET  /oficina-auto/service-orders/{order}/fsm/actions
 *   POST /oficina-auto/service-orders/{order}/fsm/execute
 *   POST /oficina-auto/service-orders/{order}/fsm/start-pipeline
 *
 * Multi-tenant Tier 0 (ADR 0093): ServiceOrder global scope filtra business_id.
 * RBAC: ExecuteStageActionService valida internamente (permission module-level
 * OU Spatie hasAnyRole — ADR 0265 fio usável, roles são camada adicional).
 *
 * Process key resolution: $serviceOrder->order_type (ver
 * ServiceOrderPipelineStarter::ORDER_TYPE_TO_PROCESS — mapa único compartilhado
 * com o auto-start do store()). 'locacao' ERRADICADO (ADR 0265): OS nova nunca
 * entra no pipeline de locação; órfãs do legado re-apontadas pela migration
 * 2026_06_10_000001.
 *
 * Defensivo: column `current_stage_id` em service_orders pode ainda não existir
 * (migration FSM dedicated Wave 5/6 owns). Trata como null → in_pipeline=false.
 *
 * @see app/Http/Controllers/SaleFsmActionController.php (pattern canônico)
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (processos cadastrados)
 * @see Modules/OficinaAuto/Services/ServiceOrderPipelineStarter.php (start compartilhado)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class ServiceOrderFsmActionController extends Controller
{

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
        $gateEvaluator = app(StageGateEvaluator::class);

        $payload = $actions->map(function (SaleStageAction $a) use ($policy, $user, $order, $processKey, $gateEvaluator) {
            // F3 OS-V2-5 — gate por action: a UI desabilita o botão de avançar + tooltip
            // do que falta. O servidor enforça a MESMA regra em execute() (gate é servidor).
            $gate = $gateEvaluator->evaluate($order, $processKey, $a->key);

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
                'gate' => $gate,
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
    public function execute(Request $request, ServiceOrder $order, ExecuteStageActionService $service, StageGateEvaluator $gateEvaluator): JsonResponse
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
            'override'   => 'sometimes|boolean',
        ]);

        $payload = $validated['payload'] ?? [];

        // F3 OS-V2-5 — gate de etapa ENFORÇADO no servidor (UI é espelho). Bloqueia 422
        // quando há requisito bloqueante pendente, salvo override explícito de
        // gerente/superadmin (registrado na trilha sale_stage_history).
        $gate = $gateEvaluator->evaluate($order, $this->resolveProcessKey($order), $validated['action_key']);
        if (! $gate['satisfied']) {
            $user = auth()->user();
            $canOverride = $user->can('superadmin')
                || $user->hasRole(['gerente', 'gerente#' . $order->business_id]);
            $override = (bool) ($validated['override'] ?? false);

            if (! $override || ! $canOverride) {
                return response()->json([
                    'error'        => 'Checklist de etapa incompleto — ' . $gate['blocking_unmet'] . ' requisito(s) pendente(s).',
                    'gate'         => $gate,
                    'can_override' => $canOverride,
                ], 422);
            }

            // Override autorizado — carimba na trilha (payload_snapshot) pra auditoria.
            $payload['gate_override'] = true;
            $payload['gate_unmet'] = collect($gate['requirements'])
                ->where('blocking', true)
                ->where('ok', false)
                ->pluck('key')
                ->values()
                ->all();
        }

        try {
            $history = $service->execute(
                $order,
                $validated['action_key'],
                auth()->user(),
                $payload,
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
     * OS NOVAS já nascem em pipeline (auto-start no ServiceOrderController::store —
     * ADR 0265); este endpoint sobrevive pro backlog legado e edge cases (override
     * de process_key superadmin). Lógica compartilhada em ServiceOrderPipelineStarter.
     */
    public function startPipeline(Request $request, ServiceOrder $order, ServiceOrderPipelineStarter $starter): JsonResponse
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

        try {
            $stage = $starter->start($order, $validated['process_key'] ?? null, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'             => true,
            'process_key'    => ($validated['process_key'] ?? null) ?? $starter->resolveProcessKey($order),
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
        // Inclui 'cacamba_locacao' SÓ pra leitura de histórico legado (OS antigas têm
        // transições reais nesse processo — timeline não pode sumir). Roteamento de OS
        // nova NUNCA cai nele (ORDER_TYPE_TO_PROCESS não tem 'locacao' — ADR 0265).
        $processKeys = array_merge(
            array_values(ServiceOrderPipelineStarter::ORDER_TYPE_TO_PROCESS),
            ['cacamba_locacao'],
        );

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
     * F3 OS-V2-5 — Gate (checklist de etapa) da PRÓXIMA transição da OS.
     *
     * Resolve a action de avanço natural do stage atual (menor target sort_order > atual,
     * incluindo o terminal positivo "entregue"; cancelamentos/garantia ficam de fora por
     * terem sort_order maior que o avanço positivo) e devolve os requisitos do gate dela.
     * O drawer (ServiceOrderStageGate) renderiza a checklist + CTA "Avançar" desta resposta.
     *
     * Multi-tenant Tier 0 (ADR 0093): ServiceOrder via global scope + permission view.
     */
    public function gate(ServiceOrder $order, StageGateEvaluator $gateEvaluator): JsonResponse
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

        if ($processKey === null || $currentStageId === null) {
            return response()->json([
                'service_order_id' => $order->id,
                'in_pipeline'      => false,
                'current_stage'    => null,
                'forward_action'   => null,
                'requirements'     => [],
                'blocking_unmet'   => 0,
                'total'            => 0,
                'done'             => 0,
                'satisfied'        => true,
                'can_override'     => false,
            ]);
        }

        $currentStage = SaleProcessStage::find($currentStageId);

        // Action de avanço natural = menor target.sort_order ENTRE os maiores que o atual.
        $forward = SaleStageAction::with('targetStage')
            ->where('stage_id', $currentStageId)
            ->get()
            ->filter(fn (SaleStageAction $a) => $a->targetStage
                && (int) $a->targetStage->sort_order > (int) ($currentStage?->sort_order ?? -1))
            ->sortBy(fn (SaleStageAction $a) => (int) $a->targetStage->sort_order)
            ->first();

        if ($forward === null) {
            return response()->json([
                'service_order_id' => $order->id,
                'in_pipeline'      => true,
                'current_stage'    => [
                    'key'         => $currentStage?->key,
                    'name'        => $currentStage?->name,
                    'color'       => $currentStage?->color,
                    'is_terminal' => (bool) $currentStage?->is_terminal,
                ],
                'forward_action'   => null,
                'requirements'     => [],
                'blocking_unmet'   => 0,
                'total'            => 0,
                'done'             => 0,
                'satisfied'        => true,
                'can_override'     => false,
            ]);
        }

        $gate = $gateEvaluator->evaluate($order, $processKey, $forward->key);

        $user = auth()->user();
        $canOverride = $user->can('superadmin')
            || $user->hasRole(['gerente', 'gerente#' . $order->business_id]);

        return response()->json(array_merge($gate, [
            'service_order_id' => $order->id,
            'in_pipeline'      => true,
            'current_stage'    => [
                'key'         => $currentStage?->key,
                'name'        => $currentStage?->name,
                'color'       => $currentStage?->color,
                'is_terminal' => (bool) $currentStage?->is_terminal,
            ],
            'forward_action'   => [
                'key'          => $forward->key,
                'label'        => $forward->label,
                'is_critical'  => (bool) ($forward->is_critical ?? false),
                'target_stage' => $forward->targetStage ? [
                    'key'   => $forward->targetStage->key,
                    'name'  => $forward->targetStage->name,
                    'color' => $forward->targetStage->color,
                ] : null,
            ],
            'can_override'     => $canOverride,
        ]));
    }

    /**
     * Resolve process_key a partir do order_type da OS (mapa único no starter).
     */
    private function resolveProcessKey(ServiceOrder $order): ?string
    {
        return app(ServiceOrderPipelineStarter::class)->resolveProcessKey($order);
    }
}

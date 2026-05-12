<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Models\SaleStageHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-SELL-035 — Timeline FSM de uma transaction.
 *
 * GET /api/sells/{id}/history → retorna histórico de transições da venda
 *   pra rendering em <SaleTimeline /> (Wave 3 frontend) ou consumo CLI.
 *
 * Multi-tenant Tier 0 (ADR 0093): HasBusinessScope em SaleStageHistory já
 * escopa por business_id da sessão. Cross-tenant attempt retorna 404.
 *
 * Permissão: `sale.history.view` (default ON pra roles vendas.*,
 * financeiro.*, gerencial). User sem permission → 403.
 */
class SaleHistoryController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (! auth()->user()->can('sale.history.view')) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão pra ver histórico de vendas.');
        }

        $businessId = (int) $request->session()->get('user.business_id');

        $items = SaleStageHistory::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $id)
            ->with([
                'action:id,key,label,target_stage_id,side_effect_class,event_class',
                'fromStage:id,key,name,color',
                'toStage:id,key,name,color',
            ])
            ->orderByDesc('executed_at')
            ->limit(200)
            ->get();

        // user_id → name resolve manual (sem relationship em SaleStageHistory pra
        // evitar dependência circular com User; query 1+1 controlada)
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
            'transaction_id' => $id,
            'count' => $items->count(),
            'items' => $payload,
        ]);
    }
}

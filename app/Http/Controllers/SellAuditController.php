<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Models\SaleStageHistory;
use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-SELL-COWORK-R3-CURADORIA Onda 3.5 — Audit Trail FSM real.
 *
 * GET /sells/{sale}/audit → retorna histórico de transições FSM
 *   (sale_stage_history) já formatado pra consumo direto pelo componente
 *   resources/js/Pages/Sells/_components/SaleAuditTrail.tsx.
 *
 * Diferença vs SaleHistoryController (/api/sells/{id}/history):
 *   - History: payload "cru" (from_stage como objeto {key,name,color}) pro
 *     componente <SaleTimeline /> que renderiza badges coloridos
 *   - Audit: payload "flat amigável" (from_stage como label string) pro
 *     componente compacto <SaleAuditTrail /> que renderiza linhas timeline
 *
 * Multi-tenant Tier 0 (ADR 0093): escopo explícito por session
 *   `user.business_id` na query da Transaction + filtro `business_id` na
 *   query do SaleStageHistory (defesa em profundidade contra cross-tenant).
 *
 * Sem novo middleware/permission — herda `['web','auth']` do grupo. Fallback
 * Onda 3 (derivação determinística no frontend) permanece via prop opcional
 * `realApiUrl` em SaleAuditTrail.tsx (sem realApiUrl → modo determinístico).
 *
 * Refs:
 *   - ADR 0143 FSM Pipeline LIVE prod biz=1
 *   - Onda 3 R3 Curadoria (PR #1041)
 */
class SellAuditController extends Controller
{
    public function show(Request $request, int $sale): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $businessId = (int) $request->session()->get('user.business_id');

        // Tier 0 multi-tenant: confirma que sale pertence ao business
        // ANTES de tocar sale_stage_history (defesa em profundidade).
        $venda = Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->find($sale);

        if (! $venda) {
            return response()->json(['error' => 'Venda não encontrada'], 404);
        }

        $items = SaleStageHistory::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $venda->id)
            ->with([
                'action:id,key,label',
                'fromStage:id,key,name',
                'toStage:id,key,name',
            ])
            ->orderBy('executed_at', 'asc')
            ->limit(200)
            ->get();

        // user_id → username resolve manual (sem relationship em SaleStageHistory
        // pra evitar dependência circular com User; query 1+1 controlada — mesmo
        // pattern já adotado em SaleHistoryController).
        $userIds = $items->pluck('user_id')->filter()->unique()->values();
        $userNames = \DB::table('users')
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'surname', 'username'])
            ->keyBy('id');

        $history = $items->map(function (SaleStageHistory $h) use ($userNames): array {
            $userName = 'sistema';
            if ($h->user_id && isset($userNames[$h->user_id])) {
                $u = $userNames[$h->user_id];
                $fullName = trim(($u->first_name ?? '') . ' ' . ($u->surname ?? ''));
                $userName = $fullName !== '' ? $fullName : ($u->username ?? 'sistema');
            }

            return [
                'id' => $h->id,
                'when' => $h->executed_at?->toIso8601String(),
                'from_stage' => $h->fromStage?->name,
                'to_stage' => $h->toStage?->name ?? '—',
                'action' => $h->action?->label ?? 'Pipeline iniciado',
                'action_key' => $h->action?->key,
                'user_name' => $userName,
            ];
        });

        return response()->json([
            'venda_id' => $venda->id,
            'invoice_no' => $venda->invoice_no,
            'count' => $history->count(),
            'history' => $history,
        ]);
    }
}

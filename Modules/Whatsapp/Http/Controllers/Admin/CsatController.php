<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\CsatResponse;
use Modules\Whatsapp\Entities\Conversation;

/**
 * CsatController — dashboard pesquisa pós-atendimento (PR-6 CYCLE-07).
 *
 * Rota: GET /atendimento/csat (middleware `whatsapp.access`).
 *
 * UI Cockpit V2 (ADR 0110) com 4 KPI cards + tabela últimas N responses.
 *
 * Filtro range padrão: 30 dias. Aceita 7/30/90 dias via `?range=`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — todos queries filtram
 * `business_id` via global scope (`HasBusinessScope` em CsatResponse).
 *
 * @see CsatDispatcher
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
class CsatController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        // Whitelist range — protege contra injection em raw SQL (ainda que
        // só usemos via subHours, defesa simbólica). Default 30d.
        $rangeDays = match ($request->input('range')) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            default => 30,
        };
        $since = now()->subDays($rangeDays);

        // Base query — todas pesquisas (pending + respondidas) dentro do range.
        $baseQuery = CsatResponse::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since);

        $totalAsked = (clone $baseQuery)->count();
        $totalResponded = (clone $baseQuery)->whereNotNull('score')->count();

        // Média score (só rows respondidas — null não conta).
        $avgScore = (clone $baseQuery)->whereNotNull('score')->avg('score');
        $avgScore = $avgScore !== null ? round((float) $avgScore, 2) : 0.0;

        // Taxa resposta (%).
        $responseRate = $totalAsked > 0
            ? round(($totalResponded / $totalAsked) * 100, 1)
            : 0.0;

        // Distribuição score 1-5.
        $distribution = [];
        for ($s = 1; $s <= 5; $s++) {
            $distribution[$s] = (clone $baseQuery)->where('score', $s)->count();
        }

        // Últimas 20 responses (só respondidas) com eager-load.
        $recent = (clone $baseQuery)
            ->whereNotNull('score')
            ->with([
                'conversation:id,business_id,channel_id,contact_name,customer_external_id',
                'conversation.channel:id,label,type',
                'resolvedBy:id,first_name,surname,last_name',
            ])
            ->orderByDesc('responded_at')
            ->limit(20)
            ->get()
            ->map(fn (CsatResponse $r) => [
                'id' => $r->id,
                'score' => $r->score,
                'comment' => $r->comment,
                'asked_at' => $r->asked_at?->toIso8601String(),
                'responded_at' => $r->responded_at?->toIso8601String(),
                'conversation' => $r->conversation ? [
                    'id' => $r->conversation->id,
                    'contact_name' => $r->conversation->contact_name ?? $r->conversation->customer_external_id,
                    'channel_label' => $r->conversation->channel?->label,
                    'channel_type' => $r->conversation->channel?->type,
                ] : null,
                'resolved_by' => $r->resolvedBy ? [
                    'id' => $r->resolvedBy->id,
                    'name' => trim(($r->resolvedBy->first_name ?? '') . ' ' . ($r->resolvedBy->surname ?? '')) ?: "User #{$r->resolvedBy->id}",
                ] : null,
            ]);

        return Inertia::render('Atendimento/Csat/Index', [
            'businessId' => $businessId,
            'range' => $rangeDays,
            'kpis' => [
                'avg_score' => $avgScore,
                'total_asked' => $totalAsked,
                'total_responded' => $totalResponded,
                'response_rate' => $responseRate,
            ],
            'distribution' => $distribution,
            'recent' => $recent,
        ]);
    }
}

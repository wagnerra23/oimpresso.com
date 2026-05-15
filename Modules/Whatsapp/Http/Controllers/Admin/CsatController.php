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

        // D-14 perf 2026-05-15 (skill `inertia-defer-default` Tier 0):
        // kpis (4 counts) + distribution (5 counts) + recent (query+eager+map)
        // viram Inertia::defer — pulam execução quando partial reload não pede.
        return Inertia::render('Atendimento/Csat/Index', [
            // ─── Eager (custo zero) ───
            'businessId' => $businessId,
            'range' => $rangeDays,

            // ─── Defer (queries pesadas) ───
            'kpis' => Inertia::defer(fn () => $this->buildKpisPayload($businessId, $rangeDays)),
            'distribution' => Inertia::defer(fn () => $this->buildDistributionPayload($businessId, $rangeDays)),
            'recent' => Inertia::defer(fn () => $this->buildRecentPayload($businessId, $rangeDays)),
        ]);
    }

    /**
     * D-14 perf — 4 KPIs CSAT (4 counts + 1 avg query).
     *
     * @return array{avg_score: float, total_asked: int, total_responded: int, response_rate: float}
     */
    protected function buildKpisPayload(int $businessId, int $rangeDays): array
    {
        $since = now()->subDays($rangeDays);
        $baseQuery = CsatResponse::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since);

        $totalAsked = (clone $baseQuery)->count();
        $totalResponded = (clone $baseQuery)->whereNotNull('score')->count();
        $avgScore = (clone $baseQuery)->whereNotNull('score')->avg('score');
        $avgScore = $avgScore !== null ? round((float) $avgScore, 2) : 0.0;
        $responseRate = $totalAsked > 0
            ? round(($totalResponded / $totalAsked) * 100, 1)
            : 0.0;

        return [
            'avg_score' => $avgScore,
            'total_asked' => $totalAsked,
            'total_responded' => $totalResponded,
            'response_rate' => $responseRate,
        ];
    }

    /**
     * D-14 perf — distribuição score 1-5 (5 counts em loop).
     *
     * @return array<int, int>
     */
    protected function buildDistributionPayload(int $businessId, int $rangeDays): array
    {
        $since = now()->subDays($rangeDays);
        $baseQuery = CsatResponse::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since);

        $distribution = [];
        for ($s = 1; $s <= 5; $s++) {
            $distribution[$s] = (clone $baseQuery)->where('score', $s)->count();
        }
        return $distribution;
    }

    /**
     * D-14 perf — últimas 20 responses (query + 3 eager-loads + map).
     */
    protected function buildRecentPayload(int $businessId, int $rangeDays): array
    {
        $since = now()->subDays($rangeDays);

        return CsatResponse::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since)
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
            ])
            ->all();
    }
}

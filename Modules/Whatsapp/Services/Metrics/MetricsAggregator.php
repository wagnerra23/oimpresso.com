<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Metrics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

/**
 * Observabilidade D9.a (ADR 0155): aggregation diária envolto em
 * `OtelHelper::span(` (Tracer whatsapp.metrics.aggregate_day) — mede
 * latência por business + dia processado.
 *
 * MetricsAggregator — agrega `messages` + `conversations` em snapshot
 * diário (US-WA-021/041, CYCLE-07 PR-3).
 *
 * Operação canônica:
 *   - Lê `messages` + `conversations` do dia alvo (filtro `created_at`).
 *   - Calcula 8 colunas (counts + médias + custo).
 *   - UPSERT em `whatsapp_conversation_metricas` (idempotente —
 *     re-rodar mesma data substitui linha sem duplicar).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - Service é CLI-callable (sem session), portanto recebe `$businessId`
 *     explícito em todos métodos.
 *   - `withoutGlobalScope(ScopeByBusiness::class)` justificado nos SELECTs
 *     que cruzam tabelas filhas (messages, conversations) — filtragem
 *     manual via `WHERE business_id = ?` garante isolamento.
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap P0 #4
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-021/041
 */
class MetricsAggregator
{
    /**
     * Agrega métricas de um business + dia (+ canal opcional) em UPSERT.
     *
     * Quando `$channelId` é null, agrega o business inteiro (soma todos
     * canais). Quando informado, agrega só aquele canal.
     *
     * Idempotente — chave única (business_id, metric_date, channel_id).
     */
    public function aggregateForDate(
        int $businessId,
        Carbon $date,
        ?int $channelId = null,
    ): void {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $stats = $this->computeStats($businessId, $start, $end, $channelId);

        // DB::table upsert pra evitar pegadinhas de cast Eloquent em SQLite tests
        // (campo `date` cast em Eloquent vs storage real `YYYY-MM-DD 00:00:00`).
        DB::table('whatsapp_conversation_metricas')->updateOrInsert(
            [
                'business_id' => $businessId,
                'metric_date' => $date->toDateString(),
                'channel_id' => $channelId,
            ],
            array_merge($stats, [
                'updated_at' => now(),
                'created_at' => now(),
            ]),
        );

        Log::info('[whatsapp.metrics.aggregated]', [
            'business_id' => $businessId,
            'metric_date' => $date->toDateString(),
            'channel_id' => $channelId,
            'stats' => $stats,
        ]);
    }

    /**
     * Agrega snapshot diário completo de um business: 1 row agregada
     * (channel_id=null) + N rows por canal ativo no dia.
     */
    public function aggregateBusinessForDate(int $businessId, Carbon $date): int
    {
        // Row agregada (todos canais).
        $this->aggregateForDate($businessId, $date, null);

        // Row por canal que teve atividade no dia. SUPERADMIN: scan
        // cross-business só dentro deste businessId — filtro explícito.
        $channelIds = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->pluck('id');

        foreach ($channelIds as $channelId) {
            $this->aggregateForDate($businessId, $date, (int) $channelId);
        }

        return $channelIds->count() + 1;
    }

    /**
     * Computa estatísticas SQL puras. Single query path por coluna —
     * evita N+1 + economiza memória vs cursor PHP.
     *
     * @return array<string,int|null>
     */
    private function computeStats(
        int $businessId,
        Carbon $start,
        Carbon $end,
        ?int $channelId,
    ): array {
        // Conversations opened — created_at na janela do dia
        $convOpenedQuery = DB::table('conversations')
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$start, $end]);

        // Conversations resolved — updated_at na janela do dia E status=resolved
        $convResolvedQuery = DB::table('conversations')
            ->where('business_id', $businessId)
            ->where('status', 'resolved')
            ->whereBetween('updated_at', [$start, $end]);

        // Messages — created_at na janela do dia
        $msgInboundQuery = DB::table('messages')
            ->where('business_id', $businessId)
            ->where('direction', 'inbound')
            ->whereBetween('created_at', [$start, $end]);

        $msgOutboundQuery = DB::table('messages')
            ->where('business_id', $businessId)
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end]);

        // Cost — soma de cost_centavos do dia
        $costQuery = DB::table('messages')
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$start, $end]);

        // Filtro per-canal (via conversation_id join) quando $channelId != null
        if ($channelId !== null) {
            $convOpenedQuery->where('channel_id', $channelId);
            $convResolvedQuery->where('channel_id', $channelId);

            // Messages → join via conversation_id pra filtrar por canal
            $convIdsSubquery = DB::table('conversations')
                ->where('business_id', $businessId)
                ->where('channel_id', $channelId)
                ->select('id');

            $msgInboundQuery->whereIn('conversation_id', $convIdsSubquery);
            $msgOutboundQuery->whereIn('conversation_id', $convIdsSubquery);
            $costQuery->whereIn('conversation_id', $convIdsSubquery);
        }

        return [
            'conversations_opened' => (int) $convOpenedQuery->count(),
            'conversations_resolved' => (int) $convResolvedQuery->count(),
            'messages_inbound' => (int) $msgInboundQuery->count(),
            'messages_outbound' => (int) $msgOutboundQuery->count(),
            'avg_first_response_seconds' => $this->computeAvgFirstResponse(
                $businessId,
                $start,
                $end,
                $channelId,
            ),
            'avg_resolution_seconds' => $this->computeAvgResolution(
                $businessId,
                $start,
                $end,
                $channelId,
            ),
            'total_cost_centavos' => (int) ($costQuery->sum('cost_centavos') ?? 0),
        ];
    }

    /**
     * Tempo médio até 1ª resposta humana — para cada conversa aberta
     * no dia, calcula segundos entre `created_at` (1ª msg inbound) e
     * o `created_at` da 1ª msg outbound humana (sender_kind != 'bot').
     *
     * Retorna null quando não há nenhuma conversa medível no dia.
     */
    private function computeAvgFirstResponse(
        int $businessId,
        Carbon $start,
        Carbon $end,
        ?int $channelId,
    ): ?int {
        $convQuery = DB::table('conversations as c')
            ->where('c.business_id', $businessId)
            ->whereBetween('c.created_at', [$start, $end]);

        if ($channelId !== null) {
            $convQuery->where('c.channel_id', $channelId);
        }

        $rows = $convQuery
            ->select('c.id', 'c.created_at')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $deltas = [];
        foreach ($rows as $row) {
            // 1ª resposta humana (outbound, sender_kind != 'bot')
            $firstResponseAt = DB::table('messages')
                ->where('business_id', $businessId)
                ->where('conversation_id', $row->id)
                ->where('direction', 'outbound')
                ->where(function ($q) {
                    $q->whereNull('sender_kind')
                      ->orWhere('sender_kind', '!=', 'bot');
                })
                ->orderBy('created_at', 'asc')
                ->value('created_at');

            if ($firstResponseAt === null) {
                continue;
            }

            $delta = Carbon::parse($firstResponseAt)->diffInSeconds(
                Carbon::parse($row->created_at),
                true,
            );
            $deltas[] = (int) $delta;
        }

        if ($deltas === []) {
            return null;
        }

        return (int) round(array_sum($deltas) / count($deltas));
    }

    /**
     * Tempo médio até `status=resolved` — segundos entre `created_at`
     * e `updated_at` das conversas resolvidas no dia.
     *
     * Retorna null quando não há nenhuma conversa resolvida no dia.
     */
    private function computeAvgResolution(
        int $businessId,
        Carbon $start,
        Carbon $end,
        ?int $channelId,
    ): ?int {
        $query = DB::table('conversations')
            ->where('business_id', $businessId)
            ->where('status', 'resolved')
            ->whereBetween('updated_at', [$start, $end]);

        if ($channelId !== null) {
            $query->where('channel_id', $channelId);
        }

        $rows = $query->select('created_at', 'updated_at')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $deltas = [];
        foreach ($rows as $row) {
            $delta = Carbon::parse($row->updated_at)->diffInSeconds(
                Carbon::parse($row->created_at),
                true,
            );
            $deltas[] = (int) $delta;
        }

        if ($deltas === []) {
            return null;
        }

        return (int) round(array_sum($deltas) / count($deltas));
    }
}

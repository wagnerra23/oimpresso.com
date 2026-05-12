<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ConversationMetric;

/**
 * MetricsController — dashboard `/atendimento/metricas` (US-WA-021/041,
 * CYCLE-07 PR-3).
 *
 * Lê snapshots agregados de `whatsapp_conversation_metricas` (gerados
 * daily 02:30 BRT pelo `whatsapp:metrics-aggregate`). NÃO faz scan em
 * runtime de `messages` — performance-critical (10k+ msgs/dia em prod
 * biz=1 já).
 *
 * Range picker — últimos 7/30/90 dias (default 30). Stats agregadas
 * (channel_id=null) alimentam os 4 KPI cards e chart de linha. Stats
 * per-canal alimentam a tabela breakdown.
 *
 * Permission: `whatsapp.access` (reusada — mesmo gate da Inbox).
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap P0 #4
 */
class MetricsController extends Controller
{
    private const ALLOWED_RANGES = [7, 30, 90];

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        // 1) Resolve range (default 30 dias, whitelist evita SQL injection)
        $range = (int) $request->input('range', 30);
        if (! in_array($range, self::ALLOWED_RANGES, true)) {
            $range = 30;
        }

        $endDate = CarbonImmutable::today();
        $startDate = $endDate->subDays($range - 1);

        // 2) Agregadas (channel_id=null) — drive KPI cards + linha do chart
        $aggregated = ConversationMetric::query()
            ->where('business_id', $businessId)
            ->whereNull('channel_id')
            ->whereBetween('metric_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('metric_date')
            ->get();

        // 3) Por canal — drive tabela breakdown (período inteiro somado)
        $perChannel = ConversationMetric::query()
            ->where('business_id', $businessId)
            ->whereNotNull('channel_id')
            ->whereBetween('metric_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 4) Totais do período (KPI cards)
        $totals = [
            'conversations_opened' => (int) $aggregated->sum('conversations_opened'),
            'conversations_resolved' => (int) $aggregated->sum('conversations_resolved'),
            'messages_inbound' => (int) $aggregated->sum('messages_inbound'),
            'messages_outbound' => (int) $aggregated->sum('messages_outbound'),
            'total_cost_centavos' => (int) $aggregated->sum('total_cost_centavos'),
            'avg_first_response_seconds' => $this->averageOf($aggregated, 'avg_first_response_seconds'),
            'avg_resolution_seconds' => $this->averageOf($aggregated, 'avg_resolution_seconds'),
        ];

        // 5) Chart series (1 ponto por dia — pode ter gaps quando dia sem
        // atividade; frontend renderiza zeros pros dias faltantes).
        $series = $aggregated->map(fn (ConversationMetric $m) => [
            'date' => $m->metric_date->toDateString(),
            'opened' => $m->conversations_opened,
            'resolved' => $m->conversations_resolved,
            'inbound' => $m->messages_inbound,
            'outbound' => $m->messages_outbound,
            'cost_centavos' => $m->total_cost_centavos,
        ])->values();

        // 6) Tabela per-canal (label do Channel + soma agregada do período)
        $channelLabels = Channel::query()
            ->where('business_id', $businessId)
            ->pluck('label', 'id');

        $breakdown = $perChannel
            ->groupBy('channel_id')
            ->map(function ($rows, $channelId) use ($channelLabels) {
                /** @var \Illuminate\Support\Collection<int,\Modules\Whatsapp\Entities\ConversationMetric> $rows */
                return [
                    'channel_id' => (int) $channelId,
                    'channel_label' => $channelLabels[$channelId] ?? "Canal #{$channelId}",
                    'conversations_opened' => (int) $rows->sum('conversations_opened'),
                    'conversations_resolved' => (int) $rows->sum('conversations_resolved'),
                    'messages_inbound' => (int) $rows->sum('messages_inbound'),
                    'messages_outbound' => (int) $rows->sum('messages_outbound'),
                    'total_cost_centavos' => (int) $rows->sum('total_cost_centavos'),
                    'avg_first_response_seconds' => $this->averageOf($rows, 'avg_first_response_seconds'),
                ];
            })
            ->values()
            ->sortByDesc('messages_inbound')
            ->values();

        return Inertia::render('Atendimento/Metricas/Index', [
            'range' => $range,
            'allowedRanges' => self::ALLOWED_RANGES,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'totals' => $totals,
            'series' => $series,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Média de coluna nullable (ignora rows com null — semântica "sem
     * conversa medível naquele dia").
     */
    private function averageOf(iterable $rows, string $col): ?int
    {
        $values = collect($rows)
            ->pluck($col)
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (int) $v);

        if ($values->isEmpty()) {
            return null;
        }

        return (int) round($values->avg());
    }
}

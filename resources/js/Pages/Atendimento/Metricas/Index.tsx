// @memcofre
//   tela: /atendimento/metricas
//   stories: US-WA-021/041 (CYCLE-07 PR-3 — dashboard métricas omnichannel)
//   adrs: 0135 (omnichannel arquitetura) · Constituição §4 loop fechado por métrica
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   gap-mercado: P0 #4 (COMPARATIVO-MERCADO-2026-05-12.md)
//   permissao: whatsapp.access (reusa gate Inbox)
//
// Lê snapshot diário pre-agregado em `whatsapp_conversation_metricas`
// (cron 02:30 BRT). NÃO faz scan runtime de messages — performance
// crítica em prod biz=1 (10k+ msgs/dia).

import { router } from '@inertiajs/react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

interface SeriesPoint {
  date: string;
  opened: number;
  resolved: number;
  inbound: number;
  outbound: number;
  cost_centavos: number;
}

interface BreakdownRow {
  channel_id: number;
  channel_label: string;
  conversations_opened: number;
  conversations_resolved: number;
  messages_inbound: number;
  messages_outbound: number;
  total_cost_centavos: number;
  avg_first_response_seconds: number | null;
}

interface Totals {
  conversations_opened: number;
  conversations_resolved: number;
  messages_inbound: number;
  messages_outbound: number;
  total_cost_centavos: number;
  avg_first_response_seconds: number | null;
  avg_resolution_seconds: number | null;
}

interface Props {
  range: number;
  allowedRanges: number[];
  startDate: string;
  endDate: string;
  totals: Totals;
  series: SeriesPoint[];
  breakdown: BreakdownRow[];
}

function formatReais(centavos: number): string {
  return (centavos / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

function formatDuration(seconds: number | null): string {
  if (seconds === null) return '—';
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}min`;
  const hours = Math.floor(minutes / 60);
  const remMin = minutes % 60;
  return remMin === 0 ? `${hours}h` : `${hours}h${remMin}min`;
}

function formatDate(iso: string): string {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y.slice(2)}`;
}

export default function MetricasIndex({
  range,
  allowedRanges,
  startDate,
  endDate,
  totals,
  series,
  breakdown,
}: Props) {
  function setRange(newRange: number) {
    router.get(
      '/atendimento/metricas',
      { range: newRange },
      { preserveScroll: true, preserveState: false },
    );
  }

  // Chart bounds — calculados em runtime do server data.
  const maxInbound = Math.max(...series.map((s) => s.inbound), 1);
  const maxOutbound = Math.max(...series.map((s) => s.outbound), 1);
  const chartMax = Math.max(maxInbound, maxOutbound, 1);

  const chartWidth = 800;
  const chartHeight = 240;
  const padX = 40;
  const padY = 20;
  const innerW = chartWidth - padX * 2;
  const innerH = chartHeight - padY * 2;
  const stepX = series.length > 1 ? innerW / (series.length - 1) : innerW;

  function pointsFor(key: 'inbound' | 'outbound'): string {
    return series
      .map((s, i) => {
        const x = padX + i * stepX;
        const y = padY + innerH - (s[key] / chartMax) * innerH;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
      })
      .join(' ');
  }

  return (
    <AppShellV2 title="Métricas de atendimento">
      <div className="space-y-6">
        <PageHeader
          icon="bar-chart-3"
          title="Métricas de atendimento"
          description={`Período: ${formatDate(startDate)} → ${formatDate(endDate)} (${range} dias)`}
          action={
            <div className="flex gap-2" role="group" aria-label="Filtro de período">
              {allowedRanges.map((r) => (
                <Button
                  key={r}
                  type="button"
                  variant={r === range ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setRange(r)}
                >
                  Últimos {r} dias
                </Button>
              ))}
            </div>
          }
        />

        {/* KPI Cards — 4 indicadores principais */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard
            label="Conversas abertas"
            value={totals.conversations_opened.toLocaleString('pt-BR')}
            icon="message-square"
            tone="info"
            description={`${totals.conversations_resolved.toLocaleString('pt-BR')} resolvidas no período`}
          />
          <KpiCard
            label="Mensagens"
            value={(totals.messages_inbound + totals.messages_outbound).toLocaleString('pt-BR')}
            icon="messages-square"
            tone="default"
            description={`${totals.messages_inbound.toLocaleString('pt-BR')} entrada · ${totals.messages_outbound.toLocaleString('pt-BR')} saída`}
          />
          <KpiCard
            label="Tempo 1ª resposta"
            value={formatDuration(totals.avg_first_response_seconds)}
            icon="clock"
            tone={
              totals.avg_first_response_seconds === null
                ? 'default'
                : totals.avg_first_response_seconds < 600
                  ? 'success'
                  : totals.avg_first_response_seconds < 3600
                    ? 'warning'
                    : 'danger'
            }
            description="Tempo médio até resposta humana"
          />
          <KpiCard
            label="Custo total"
            value={formatReais(totals.total_cost_centavos)}
            icon="dollar-sign"
            tone="default"
            description="Soma de cost_centavos no período"
          />
        </div>

        {/* Chart de linha — mensagens in/out por dia */}
        <Card className="p-6">
          <div className="mb-4 flex items-center justify-between gap-2">
            <div>
              <h2 className="text-base font-semibold tracking-tight">
                Volume de mensagens por dia
              </h2>
              <p className="text-sm text-muted-foreground">
                Linha azul = entrada · Linha verde = saída
              </p>
            </div>
            <div className="flex items-center gap-4 text-xs text-muted-foreground">
              <span className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 rounded-sm bg-blue-500" />
                Entrada
              </span>
              <span className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 rounded-sm bg-emerald-500" />
                Saída
              </span>
            </div>
          </div>

          {series.length === 0 ? (
            <EmptyState
              icon="bar-chart-3"
              title="Sem dados no período"
              description="Aguardando snapshot diário do cron whatsapp:metrics-aggregate (02:30 BRT)."
            />
          ) : (
            <div className="w-full overflow-x-auto">
              <svg
                viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                className="w-full h-auto min-w-[600px]"
                role="img"
                aria-label="Gráfico de mensagens por dia"
              >
                {/* Grid lines horizontais */}
                {[0, 0.25, 0.5, 0.75, 1].map((frac) => (
                  <line
                    key={frac}
                    x1={padX}
                    y1={padY + innerH * frac}
                    x2={chartWidth - padX}
                    y2={padY + innerH * frac}
                    stroke="currentColor"
                    strokeOpacity="0.1"
                    strokeWidth="1"
                  />
                ))}

                {/* Y-axis labels */}
                {[0, 0.5, 1].map((frac) => (
                  <text
                    key={frac}
                    x={padX - 6}
                    y={padY + innerH * (1 - frac) + 4}
                    textAnchor="end"
                    fontSize="10"
                    fill="currentColor"
                    opacity="0.5"
                  >
                    {Math.round(chartMax * frac)}
                  </text>
                ))}

                {/* Linhas inbound + outbound */}
                <polyline
                  fill="none"
                  stroke="#3b82f6"
                  strokeWidth="2"
                  points={pointsFor('inbound')}
                />
                <polyline
                  fill="none"
                  stroke="#10b981"
                  strokeWidth="2"
                  points={pointsFor('outbound')}
                />

                {/* X-axis labels — primeiro, meio e último */}
                {series.length > 0 && (
                  <>
                    <text
                      x={padX}
                      y={chartHeight - 4}
                      fontSize="10"
                      fill="currentColor"
                      opacity="0.6"
                    >
                      {formatDate(series[0].date)}
                    </text>
                    {series.length > 2 && (
                      <text
                        x={chartWidth / 2}
                        y={chartHeight - 4}
                        fontSize="10"
                        fill="currentColor"
                        textAnchor="middle"
                        opacity="0.6"
                      >
                        {formatDate(series[Math.floor(series.length / 2)].date)}
                      </text>
                    )}
                    <text
                      x={chartWidth - padX}
                      y={chartHeight - 4}
                      fontSize="10"
                      fill="currentColor"
                      textAnchor="end"
                      opacity="0.6"
                    >
                      {formatDate(series[series.length - 1].date)}
                    </text>
                  </>
                )}
              </svg>
            </div>
          )}
        </Card>

        {/* Breakdown por canal */}
        <Card className="p-6">
          <div className="mb-4">
            <h2 className="text-base font-semibold tracking-tight">
              Breakdown por canal
            </h2>
            <p className="text-sm text-muted-foreground">
              Soma do período por canal · ordem decrescente de mensagens entrada
            </p>
          </div>

          {breakdown.length === 0 ? (
            <EmptyState
              icon="message-circle-off"
              title="Sem dados de canais no período"
              description="Métricas per-canal aparecem aqui após o cron 02:30 BRT processar."
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm" data-slot="metrics-breakdown">
                <thead>
                  <tr className="border-b border-border text-left text-[11px] font-semibold text-muted-foreground uppercase tracking-widest">
                    <th className="py-2 pr-4">Canal</th>
                    <th className="py-2 pr-4 text-right">Abertas</th>
                    <th className="py-2 pr-4 text-right">Resolvidas</th>
                    <th className="py-2 pr-4 text-right">Entrada</th>
                    <th className="py-2 pr-4 text-right">Saída</th>
                    <th className="py-2 pr-4 text-right">1ª resposta</th>
                    <th className="py-2 text-right">Custo</th>
                  </tr>
                </thead>
                <tbody>
                  {breakdown.map((row) => (
                    <tr
                      key={row.channel_id}
                      className="border-b border-border/50 hover:bg-muted/40"
                    >
                      <td className="py-2 pr-4 font-medium">{row.channel_label}</td>
                      <td className="py-2 pr-4 text-right tabular-nums">
                        {row.conversations_opened.toLocaleString('pt-BR')}
                      </td>
                      <td className="py-2 pr-4 text-right tabular-nums">
                        {row.conversations_resolved.toLocaleString('pt-BR')}
                      </td>
                      <td className="py-2 pr-4 text-right tabular-nums">
                        {row.messages_inbound.toLocaleString('pt-BR')}
                      </td>
                      <td className="py-2 pr-4 text-right tabular-nums">
                        {row.messages_outbound.toLocaleString('pt-BR')}
                      </td>
                      <td className="py-2 pr-4 text-right tabular-nums text-muted-foreground">
                        {formatDuration(row.avg_first_response_seconds)}
                      </td>
                      <td className="py-2 text-right tabular-nums">
                        {formatReais(row.total_cost_centavos)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppShellV2>
  );
}

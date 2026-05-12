// @memcofre
//   tela: /atendimento/csat
//   stories: US-WA-CSAT (PR-6 CYCLE-07) — pesquisa pós-resolução 1-5
//   adrs: 0135 (omnichannel), 0142 (notas internas pattern reusado)
//   spec: memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
//   permissao: whatsapp.access
//
// Concorrentes referência: Chatwoot CSAT, Take Blip pesquisa pós-atendimento,
// Octadesk NPS. Layout Cockpit V2 (KPI grid 4 cards + tabela últimas 20 + filtro range).

import { useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { Star } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';

interface RecentResponse {
  id: number;
  score: number;
  comment: string | null;
  asked_at: string | null;
  responded_at: string | null;
  conversation: {
    id: number;
    contact_name: string;
    channel_label: string | null;
    channel_type: string | null;
  } | null;
  resolved_by: {
    id: number;
    name: string;
  } | null;
}

interface Props {
  businessId: number;
  range: number;
  kpis: {
    avg_score: number;
    total_asked: number;
    total_responded: number;
    response_rate: number;
  };
  distribution: Record<string, number>;
  recent: RecentResponse[];
}

function StarRow({ score }: { score: number }) {
  return (
    <span className="inline-flex items-center gap-0.5" aria-label={`${score} de 5 estrelas`}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          size={14}
          className={i < score ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'}
        />
      ))}
    </span>
  );
}

function formatDateTime(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return iso;
  }
}

function toneForScore(score: number): 'success' | 'info' | 'warning' | 'danger' {
  if (score >= 4.5) return 'success';
  if (score >= 3.5) return 'info';
  if (score >= 2.5) return 'warning';
  return 'danger';
}

export default function CsatIndex({ range, kpis, distribution, recent }: Props) {
  const [rangeFilter, setRangeFilter] = useState<string>(String(range));

  function onRangeChange(value: string) {
    setRangeFilter(value);
    router.get(route('atendimento.csat.index'), { range: value }, {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const maxBar = Math.max(1, ...Object.values(distribution));

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="star"
        title="CSAT — Satisfação pós-atendimento"
        description="Pesquisa 1-5 enviada automaticamente quando atendente marca conversa como resolvida. Resposta parseada do próximo inbound do cliente."
        actions={
          <Select value={rangeFilter} onValueChange={onRangeChange}>
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder="Range" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="7">Últimos 7 dias</SelectItem>
              <SelectItem value="30">Últimos 30 dias</SelectItem>
              <SelectItem value="90">Últimos 90 dias</SelectItem>
            </SelectContent>
          </Select>
        }
      />

      <KpiGrid cols={4}>
        <KpiCard
          icon="star"
          tone={kpis.total_responded > 0 ? toneForScore(kpis.avg_score) : 'default'}
          label="Score médio"
          value={kpis.total_responded > 0 ? kpis.avg_score.toFixed(2) : '—'}
          description={`Escala 1-5 (${kpis.total_responded} respostas)`}
        />
        <KpiCard
          icon="send"
          tone="default"
          label="Pesquisas enviadas"
          value={kpis.total_asked.toLocaleString('pt-BR')}
          description="No range selecionado"
        />
        <KpiCard
          icon="message-circle"
          tone={kpis.total_responded > 0 ? 'info' : 'default'}
          label="Respondidas"
          value={kpis.total_responded.toLocaleString('pt-BR')}
          description="Score 1-5 detectado"
        />
        <KpiCard
          icon="percent"
          tone={kpis.response_rate >= 30 ? 'success' : kpis.response_rate >= 15 ? 'warning' : 'danger'}
          label="Taxa de resposta"
          value={`${kpis.response_rate.toFixed(1)}%`}
          description="Respondidas / Enviadas"
        />
      </KpiGrid>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Distribuição de notas</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {[5, 4, 3, 2, 1].map(score => {
              const count = distribution[String(score)] ?? 0;
              const widthPct = (count / maxBar) * 100;
              return (
                <div key={score} className="flex items-center gap-3 text-sm">
                  <div className="w-16">
                    <StarRow score={score} />
                  </div>
                  <div className="flex-1 bg-muted rounded-full h-3 overflow-hidden">
                    <div
                      className={`h-full rounded-full transition-all ${
                        score >= 4 ? 'bg-emerald-500'
                          : score === 3 ? 'bg-amber-500'
                            : 'bg-destructive'
                      }`}
                      style={{ width: `${widthPct}%` }}
                    />
                  </div>
                  <div className="w-12 text-right tabular-nums text-muted-foreground">
                    {count}
                  </div>
                </div>
              );
            })}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Últimas respostas</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {recent.length === 0 ? (
            <EmptyState
              icon="star"
              title="Sem respostas ainda"
              description="Quando clientes responderem 1-5 às pesquisas CSAT enviadas após você marcar a conversa como resolvida, elas aparecem aqui."
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3 text-left">Nota</th>
                    <th className="px-4 py-3 text-left">Cliente</th>
                    <th className="px-4 py-3 text-left">Canal</th>
                    <th className="px-4 py-3 text-left">Atendente</th>
                    <th className="px-4 py-3 text-left">Comentário</th>
                    <th className="px-4 py-3 text-left">Respondido em</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {recent.map(r => (
                    <tr key={r.id} className="hover:bg-muted/30">
                      <td className="px-4 py-3">
                        <StarRow score={r.score} />
                      </td>
                      <td className="px-4 py-3">
                        {r.conversation ? (
                          <a
                            href={`/atendimento/inbox?thread=${r.conversation.id}`}
                            className="text-foreground hover:underline"
                          >
                            {r.conversation.contact_name}
                          </a>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {r.conversation?.channel_label ?? '—'}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {r.resolved_by?.name ?? '—'}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground max-w-xs truncate" title={r.comment ?? ''}>
                        {r.comment ?? <span className="italic text-xs">sem comentário</span>}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground tabular-nums text-xs">
                        {formatDateTime(r.responded_at)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

CsatIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

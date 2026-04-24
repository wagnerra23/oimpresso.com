// @docvault
//   tela: /ponto
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-006
//   rules: R-PONT-001, R-PONT-002
//   adrs: ui/0002
//   tests: Modules/PontoWr2/Tests/Feature/DashboardIndexTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { ArrowRight, AlertTriangle, CheckCheck } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { cn, formatMinutes } from '@/Lib/utils';

import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import StatusBadge from '@/Components/shared/StatusBadge';
import PresenceStrip from '@/Components/shared/ponto/PresenceStrip';
import ActivityFeed from '@/Components/shared/ponto/ActivityFeed';
import AlertInbox from '@/Components/shared/ponto/AlertInbox';

interface Kpis {
  colaboradores_ativos: number;
  presentes_agora: number;
  atrasos_hoje: number;
  faltas_hoje: number;
  he_mes_minutos: number;
  aprovacoes_pendentes: number;
}

interface Aprovacao {
  id: number;
  tipo: string;
  prioridade: string;
  data_inicio: string | null;
  data_fim: string | null;
  justificativa: string;
  estado: string;
  created_at: string | null;
  colaborador: { id: number | null; nome: string; matricula: string | null };
}

interface Marcacao {
  id: number;
  tipo: string;
  momento: string | null;
  momento_completo?: string | null;
  origem: string;
  tempo?: string | null;
  colaborador: { id: number | null; nome: string; matricula: string | null };
  rep: { identificador: string | null; tipo: string | null };
}

interface SeriePonto {
  data: string;
  label: string;
  trabalhado: number;
  he: number;
}

interface Presenca {
  id: number;
  nome: string;
  matricula: string | null;
  iniciais: string;
  status: 'presente' | 'saiu' | 'atrasado' | 'ausente';
  entrada: string | null;
  saida: string | null;
  ultima: string | null;
  marcacoes: number;
}

interface Alerta {
  tipo: string;
  titulo: string;
  subtitulo: string;
  acao_label: string;
  acao_href: string;
  severidade: 'info' | 'warning' | 'danger';
}

interface Props {
  kpis: Kpis;
  aprovacoes: Aprovacao[];
  atividade_recente: Marcacao[];
  serie_7dias: SeriePonto[];
  presenca_agora: Presenca[];
  alertas: Alerta[];
  server_time: string;
}

export default function DashboardIndex({
  kpis,
  aprovacoes,
  atividade_recente,
  serie_7dias,
  presenca_agora,
  alertas,
  server_time,
}: Props) {
  // Polling ao vivo — recarrega presença + atividade + alertas a cada 30s
  // sem perder scroll position nem recriar a sidebar.
  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({
        only: ['kpis', 'presenca_agora', 'atividade_recente', 'alertas', 'server_time'],
      });
    }, 30000);
    return () => clearInterval(interval);
  }, []);

  return (
    <>
      <Head title="Dashboard · Ponto WR2" />
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <PageHeader
          icon="layout-dashboard"
          title="Dashboard"
          description={`Visão geral do ponto eletrônico — hoje, ${new Date().toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
          })}`}
          action={
            <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs text-muted-foreground">
              <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden />
              <span>atualizado agora · {server_time}</span>
            </span>
          }
        />

        {/* KPIs */}
        <KpiGrid cols={6}>
          <KpiCard
            label="Colaboradores"
            value={kpis.colaboradores_ativos}
            icon="users"
            tone="info"
            size="compact"
            onClick={() => router.visit('/ponto/colaboradores')}
          />
          <KpiCard
            label="Presentes agora"
            value={kpis.presentes_agora}
            icon="user-check"
            tone="success"
            size="compact"
          />
          <KpiCard
            label="Atrasos hoje"
            value={kpis.atrasos_hoje}
            icon="clock-alert"
            tone={kpis.atrasos_hoje > 0 ? 'warning' : 'default'}
            size="compact"
          />
          <KpiCard
            label="Faltas hoje"
            value={kpis.faltas_hoje}
            icon="user-x"
            tone={kpis.faltas_hoje > 0 ? 'danger' : 'default'}
            size="compact"
          />
          <KpiCard
            label="HE do mês"
            value={formatMinutes(kpis.he_mes_minutos)}
            icon="trending-up"
            tone="info"
            size="compact"
          />
          <KpiCard
            label="Aprovações"
            value={kpis.aprovacoes_pendentes}
            icon="check-check"
            tone={kpis.aprovacoes_pendentes > 0 ? 'danger' : 'default'}
            size="compact"
            onClick={() => router.visit('/ponto/aprovacoes')}
          />
        </KpiGrid>

        {/* Presença ao vivo */}
        <PresenceStrip colaboradores={presenca_agora} />

        {/* Grid 2 colunas — esquerda: gráfico + atividade | direita: alertas + aprovações */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          {/* Esquerda (2 cols): gráfico + atividade */}
          <div className="lg:col-span-2 space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Últimos 7 dias</CardTitle>
                <CardDescription className="text-xs">
                  Minutos trabalhados + horas extras por dia
                </CardDescription>
              </CardHeader>
              <CardContent>
                <BarChart7Days serie={serie_7dias} />
              </CardContent>
            </Card>

            <ActivityFeed marcacoes={atividade_recente} title="Atividade de hoje" />
          </div>

          {/* Direita (1 col): alertas + aprovações */}
          <div className="space-y-4">
            <AlertInbox alertas={alertas} />

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <div>
                  <CardTitle className="text-base flex items-center gap-1.5">
                    <CheckCheck size={16} className="text-primary" /> Aprovações
                  </CardTitle>
                  <CardDescription className="text-xs">Intercorrências pendentes</CardDescription>
                </div>
                <Button variant="ghost" size="sm" asChild>
                  <Link href="/ponto/aprovacoes" className="text-xs">
                    Ver todas <ArrowRight size={12} className="ml-1" />
                  </Link>
                </Button>
              </CardHeader>
              <CardContent className="space-y-2">
                {aprovacoes.length === 0 ? (
                  <p className="text-xs text-muted-foreground text-center py-6">
                    Nenhuma pendência
                  </p>
                ) : (
                  aprovacoes.map((a) => <ApprovalRow key={a.id} item={a} />)
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </>
  );
}

DashboardIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Dashboard' }]}>
    {page}
  </AppShell>
);

// ============================================================================
// Bar chart 7 dias (canvas simples — ADR PontoWr2 UI-0001, sem lib externa)
// ============================================================================

function BarChart7Days({ serie }: { serie: SeriePonto[] }) {
  const max = Math.max(...serie.map((d) => d.trabalhado + d.he), 1);
  return (
    <div className="flex items-end justify-between gap-1.5 h-40">
      {serie.map((d) => {
        const totalPct = ((d.trabalhado + d.he) / max) * 100;
        const hePct = d.he > 0 ? (d.he / (d.trabalhado + d.he)) * totalPct : 0;
        const regPct = totalPct - hePct;
        return (
          <div key={d.data} className="flex flex-col items-center flex-1 group">
            <div className="w-full flex flex-col items-center mb-1">
              <span className="text-[9px] text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity">
                {formatMinutes(d.trabalhado + d.he)}
              </span>
            </div>
            <div className="w-full max-w-[40px] flex flex-col justify-end h-28 relative">
              {d.he > 0 && (
                <div
                  className="bg-amber-500 dark:bg-amber-600 rounded-t"
                  style={{ height: `${hePct}%` }}
                  title={`HE: ${formatMinutes(d.he)}`}
                />
              )}
              <div
                className={cn('bg-primary/80', d.he === 0 && 'rounded-t')}
                style={{ height: `${regPct}%` }}
                title={`Trabalhado: ${formatMinutes(d.trabalhado)}`}
              />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1.5">{d.label}</span>
          </div>
        );
      })}
    </div>
  );
}

// ============================================================================
// ApprovalRow
// ============================================================================

function ApprovalRow({ item }: { item: Aprovacao }) {
  return (
    <Link
      href={`/ponto/intercorrencias/${item.id}`}
      className="flex items-start gap-2 p-2 -mx-2 rounded hover:bg-accent transition-colors"
    >
      <AlertTriangle size={14} className="text-amber-500 mt-0.5 shrink-0" />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className="text-sm font-medium truncate">{item.colaborador.nome}</span>
          <StatusBadge kind="prioridade" value={item.prioridade} />
        </div>
        <p className="text-xs text-muted-foreground truncate">
          {item.tipo.replace(/_/g, ' ').toLowerCase()} · {item.data_inicio}
        </p>
        <p className="text-[10px] text-muted-foreground">{item.created_at}</p>
      </div>
    </Link>
  );
}

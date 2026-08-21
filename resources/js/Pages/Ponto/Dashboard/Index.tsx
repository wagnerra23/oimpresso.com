// @docvault
//   tela: /ponto
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-006
//   rules: R-PONT-001, R-PONT-002
//   adrs: ui/0002
//   tests: Modules/PontoWr2/Tests/Feature/DashboardIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { ArrowRight, AlertTriangle, CheckCheck } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { cn, formatMinutes } from '@/Lib/utils';

import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import { PageHeaderPrimary } from '@/Components/PageHeader';
import { Grid } from '@/Components/layout';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import StatusBadge from '@/Components/shared/StatusBadge';
import PresenceStrip from '../_components/PresenceStrip';
import ActivityFeed from '../_components/ActivityFeed';
import AlertInbox from '../_components/AlertInbox';

interface Kpis {
  colaboradores_ativos: number;
  presentes_agora: number;
  atrasos_hoje: number;
  faltas_hoje: number;
  he_mes_minutos: number;
  aprovacoes_pendentes: number;
  /** Dias em DIVERGENCIA na competência — alimenta `painel-nota-fechamento`. */
  divergencias_mes: number;
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
  /**
   * Todas as props abaixo (exceto server_time) vêm via `Inertia::defer` do
   * DashboardController — `undefined` no first render até o auto-fetch async
   * resolver (RUNBOOK-inertia-defer-pattern.md). Cada seção embrulha em
   * `<Deferred data="..." fallback={skeleton}>`.
   */
  kpis?: Kpis;
  aprovacoes?: Aprovacao[];
  atividade_recente?: Marcacao[];
  serie_7dias?: SeriePonto[];
  presenca_agora?: Presenca[];
  alertas?: Alerta[];
  server_time: string;
}

/**
 * Nota "o que trava o fechamento" — seção `painel-nota-fechamento` do contrato.
 *
 * Âncora de design: `prototipo-ui/cowork/ponto-page.jsx` §`Nota contrato="painel-nota-fechamento"`
 * (âncora de SÍMBOLO, nunca linha — re-localize com
 * `grep -n "painel-nota-fechamento" prototipo-ui/cowork/ponto-page.jsx`).
 *
 * Existe porque dia em DIVERGENCIA não é detalhe de relatório: ele impede a
 * apuração de consolidar E faz o AFD gerado sair com a jornada errada. Quem abre
 * o painel precisa ver isso antes de tentar fechar o mês, não depois.
 *
 * Os 3 estados são os do contrato (`com-pendencia` · `sem-pendencia` ·
 * `so-divergencia`) e a redação é a do protótipo, não reescrita aqui.
 */
function NotaFechamento({ pendentes, divergencias }: { pendentes: number; divergencias: number }) {
  const travado = pendentes > 0 || divergencias > 0;
  const dias = divergencias === 1 ? 'dia está' : 'dias estão';
  const mes = new Date().toLocaleDateString('pt-BR', { month: 'long' });

  return (
    <div
      data-contract="painel-nota-fechamento"
      className={cn(
        'rounded-lg border px-4 py-3 text-sm',
        travado
          ? 'border-warning/40 bg-warning-soft text-foreground'
          : 'border-success/40 bg-success-soft text-foreground',
      )}
    >
      <p className="flex items-center gap-1.5 font-medium">
        {travado ? (
          <AlertTriangle size={15} className="text-warning" aria-hidden />
        ) : (
          <CheckCheck size={15} className="text-success" aria-hidden />
        )}
        O que trava o fechamento de {mes}
      </p>
      <p className="mt-1 text-muted-foreground">
        {pendentes === 0 && divergencias === 0 ? (
          <>Nenhuma intercorrência aguardando decisão e nenhum dia em divergência — a competência pode consolidar.</>
        ) : pendentes === 0 ? (
          <>
            Nenhuma intercorrência aguardando decisão, mas {divergencias} {dias} em{' '}
            <b>DIVERGENCIA</b> na apuração — o espelho não consolida assim, e o AFD gerado sai com a
            jornada errada.
          </>
        ) : (
          <>
            {pendentes === 1 ? 'Uma intercorrência espera' : `${pendentes} intercorrências esperam`}{' '}
            decisão e {divergencias} {dias} em <b>DIVERGENCIA</b> na apuração. Enquanto isso, o
            espelho do mês não consolida — e o AFD gerado sai com a jornada errada.
          </>
        )}
      </p>
    </div>
  );
}

function NotaSkeleton() {
  return <div className="h-16 animate-pulse rounded-lg border border-border bg-background" />;
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
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>
              Dashboard <span className="text-stone-400 font-normal">· Ponto eletrônico</span>
            </h1>
            <p>
              {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
              {' · atualizado '}
              <span className="inline-flex items-center gap-1 text-success">
                <span className="h-1.5 w-1.5 rounded-full bg-success animate-pulse" aria-hidden />
                {server_time}
              </span>
            </p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="dashboard" hidePrimary />
            <PageHeaderPrimary label="Bater ponto" onClick={() => router.visit('/ponto')} />
          </div>
        </header>

        {/* Nota "o que trava o fechamento" — 1ª seção do contrato `ponto-painel`,
            e por isso vem ANTES dos KPIs (o gate cobra a ordem das âncoras).
            Lê da mesma prop deferida dos KPIs: são a mesma consulta. */}
        <Deferred data="kpis" fallback={<NotaSkeleton />}>
          <NotaFechamento
            pendentes={kpis?.aprovacoes_pendentes ?? 0}
            divergencias={kpis?.divergencias_mes ?? 0}
          />
        </Deferred>

        {/* KPIs — prop deferida: guarda `?.`/`?? 0` cobre o first render */}
        <Deferred data="kpis" fallback={<KpiSkeleton />}>
          <KpiGrid cols={6} data-contract="painel-kpis">
            <KpiCard
              label="Colaboradores ativos"
              value={kpis?.colaboradores_ativos ?? 0}
              icon="users"
              tone="info"
              size="compact"
              onClick={() => router.visit('/ponto/colaboradores')}
            />
            <KpiCard
              label="Presentes agora"
              value={kpis?.presentes_agora ?? 0}
              icon="user-check"
              tone="success"
              size="compact"
            />
            <KpiCard
              label="Atrasos hoje"
              value={kpis?.atrasos_hoje ?? 0}
              icon="clock-alert"
              tone={(kpis?.atrasos_hoje ?? 0) > 0 ? 'warning' : 'default'}
              size="compact"
            />
            <KpiCard
              label="Faltas hoje"
              value={kpis?.faltas_hoje ?? 0}
              icon="user-x"
              tone={(kpis?.faltas_hoje ?? 0) > 0 ? 'danger' : 'default'}
              size="compact"
            />
            <KpiCard
              label="HE do mês"
              value={formatMinutes(kpis?.he_mes_minutos ?? 0)}
              icon="trending-up"
              tone="info"
              size="compact"
            />
            <KpiCard
              label="Aprovações pendentes"
              value={kpis?.aprovacoes_pendentes ?? 0}
              icon="check-check"
              tone={(kpis?.aprovacoes_pendentes ?? 0) > 0 ? 'danger' : 'default'}
              size="compact"
              onClick={() => router.visit('/ponto/aprovacoes')}
            />
          </KpiGrid>
        </Deferred>

        {/* Presença ao vivo */}
        <Deferred data="presenca_agora" fallback={<StripSkeleton />}>
          <PresenceStrip colaboradores={presenca_agora ?? []} />
        </Deferred>

        {/* Grid 2 colunas — esquerda: gráfico + atividade | direita: alertas + aprovações */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          {/* Esquerda (2 cols): gráfico + fila de aprovações + atividade */}
          <div className="lg:col-span-2 space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Últimos 7 dias</CardTitle>
                <CardDescription className="text-xs">
                  Minutos trabalhados + horas extras por dia
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Deferred data="serie_7dias" fallback={<ChartSkeleton />}>
                  <BarChart7Days serie={serie_7dias ?? []} />
                </Deferred>
              </CardContent>
            </Card>

            {/* Fila de aprovações vem ANTES da atividade, e na coluna larga — é a ordem
                do contrato `ponto-painel` e do protótipo (`ponto-page.jsx` §pt-cols-2:
                fila, depois atividade). A ordem das âncoras é ordem de LEITURA (DOM),
                então não dá pra acertar com CSS `order` sem descolar leitura de visual. */}
            <Card data-contract="painel-fila-aprovacoes">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <div>
                  <CardTitle className="text-base flex items-center gap-1.5">
                    <CheckCheck size={16} className="text-primary" /> Fila de aprovações
                  </CardTitle>
                  <CardDescription className="text-xs">Intercorrências pendentes</CardDescription>
                </div>
                <Button variant="ghost" size="sm" asChild>
                  <Link href="/ponto/aprovacoes" className="text-xs">
                    Ver fila completa <ArrowRight size={12} className="ml-1" />
                  </Link>
                </Button>
              </CardHeader>
              <CardContent className="space-y-2">
                <Deferred data="aprovacoes" fallback={<RowsSkeleton />}>
                  {(aprovacoes ?? []).length === 0 ? (
                    <p className="text-xs text-muted-foreground text-center py-6">
                      Nenhuma intercorrência aguardando decisão.
                    </p>
                  ) : (
                    (aprovacoes ?? []).map((a) => <ApprovalRow key={a.id} item={a} />)
                  )}
                </Deferred>
              </CardContent>
            </Card>

            <div data-contract="painel-atividade">
              <Deferred data="atividade_recente" fallback={<CardListSkeleton />}>
                {/* "marcações de hoje" (copy do contrato) vive no subtítulo do
                    ActivityFeed; o rótulo da seção é o título. */}
                <ActivityFeed marcacoes={atividade_recente ?? []} title="Atividade recente" />
              </Deferred>
            </div>
          </div>

          {/* Direita (1 col): alertas */}
          <div className="space-y-4">
            <Deferred data="alertas" fallback={<CardListSkeleton />}>
              <AlertInbox alertas={alertas ?? []} />
            </Deferred>
          </div>
        </div>
      </div>
    </>
  );
}

DashboardIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Dashboard · Ponto WR2" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Dashboard' }]}>
    {page}
  </AppShellV2>
);

// ============================================================================
// Skeletons dos blocos deferidos (fallback do <Deferred> — first render).
// Idioma espelha Cliente/Index.tsx (KpiSkeleton/TableSkeleton, canon defer).
// ============================================================================

function KpiSkeleton() {
  return (
    <Grid min="sm" gap={3}>
      {[0, 1, 2, 3, 4, 5].map((i) => (
        <div key={i} className="rounded-lg border border-border bg-background h-20 animate-pulse" />
      ))}
    </Grid>
  );
}

function StripSkeleton() {
  return <div className="rounded-lg border border-border bg-background h-16 animate-pulse" />;
}

function ChartSkeleton() {
  return <div className="h-40 rounded bg-muted/50 animate-pulse" />;
}

function CardListSkeleton() {
  return <div className="rounded-lg border border-border bg-background h-48 animate-pulse" />;
}

function RowsSkeleton() {
  return (
    <div className="space-y-2 py-2">
      {[0, 1, 2].map((i) => (
        <div key={i} className="h-10 rounded bg-muted/50 animate-pulse" />
      ))}
    </div>
  );
}

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
      <AlertTriangle size={14} className="text-warning mt-0.5 shrink-0" />
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

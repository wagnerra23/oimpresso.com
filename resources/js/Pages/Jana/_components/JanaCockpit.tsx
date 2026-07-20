// JanaCockpit — Cockpit "Analista IA" canon da Jana V2 no padrão PT-04 (/ia/dashboard).
//
// Substitui o bundle CSS paralelo `.sells-cowork .vd-insights-*` (JanaCockpitV2) pelo
// vocabulário canônico de dashboard (PT-04-Dashboard): shared KpiGrid/KpiCard + Card +
// tokens semânticos Tailwind (dark herda nativo). Zero ilha CSS — a violação R7 do
// ui:lint some com este componente (ver US-COPI-146 · PT-04 L80 · ADR UI-0013).
//
// A LÓGICA é idêntica ao JanaCockpitV2 (brief, acoes, janaKpis) — só o render mudou.
// O JanaCockpitV2 (com .vd-insights-*) continua servindo a tab Insights de /sells, onde
// o bundle .sells-cowork é legítimo (tela-dona). Bifurcação decidida por [W] 2026-07-20.
//
// Golden de referência: resources/js/Pages/governance/Dashboard.tsx
// Âncora de design: prototipo-ui/cowork/chat-jana.jsx (.jc-* · "dark herda via token")

import { useMemo, type ReactNode } from 'react';
import {
  AlertCircle,
  AlertTriangle,
  BarChart3,
  Calendar,
  ClipboardList,
  CreditCard,
  Download,
  Lightbulb,
  MessageSquare,
  Search,
  Settings,
  Sparkles,
  Target,
  TrendingDown,
  TrendingUp,
  Volume2,
} from 'lucide-react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

export interface JanaCockpitProps {
  sellKpis: {
    total: number;
    paid: number;
    due: number;
    partial: number;
    overdue: number;
  };
  coworkAggregates?: {
    sparkline?: number[];
    deltaRevenueVsYesterday?: number | null;
    deltaTicketVsLastWeek?: number | null;
    topSeller?: { name: string; total: number } | null;
    pixHojeTotal?: number;
    faturadoHojeTotal?: number;
  };
  insightsAggregates: {
    overdueCount: number;
    overdueValue: number;
    ageingBuckets: { '0-30d': number; '30-90d': number; '90-365d': number; '>365d': number };
    methodsAgg: Array<{ method: string; total: number }>;
    topClientes: Array<{ name: string; total: number }>;
    topDevedor: { name: string; total: number } | null;
    ticketMedio: number;
    totalAReceber: number;
  };
  userName?: string;
  businessName?: string;
  businessId?: number;
}

const fmtBRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const fmtShort = (n: number) =>
  n >= 1000 ? 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k' : fmtBRL(n);

const greeting = (): string => {
  const h = new Date().getHours();
  if (h < 12) return 'Bom dia';
  if (h < 18) return 'Boa tarde';
  return 'Boa noite';
};

const formatTimeShort = (): string =>
  new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

const plural = (n: number, one: string, many: string) => (n === 1 ? one : many);

// Mapeia o tom do CTA da ação para a variante do Button canônico.
type CtaTone = 'danger' | 'violet' | 'orange' | 'dark' | 'primary';
const ctaVariant = (t: CtaTone): 'default' | 'destructive' | 'secondary' =>
  t === 'danger' ? 'destructive' : t === 'orange' || t === 'dark' ? 'secondary' : 'default';

// Seção seccionadora (H2) — mesmo estilo do golden governance/Dashboard.
function SectionTitle({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <h2 className="mt-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-widest text-muted-foreground">
      <span className="inline-flex text-muted-foreground">{icon}</span>
      {children}
    </h2>
  );
}

// Card de análise (título + ícone + pill opcional + valor grande + corpo).
function AnalysisCard({
  icon,
  title,
  subtitle,
  pill,
  big,
  children,
}: {
  icon: ReactNode;
  title: string;
  subtitle: string;
  pill?: { label: string; tone: 'crit' | 'ok' | 'warn' };
  big: ReactNode;
  children: ReactNode;
}) {
  const pillTone =
    pill?.tone === 'crit'
      ? 'bg-destructive-soft text-destructive-fg'
      : pill?.tone === 'warn'
        ? 'bg-warning-soft text-warning-fg'
        : 'bg-success-soft text-success-fg';

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-4">
        <header className="flex items-center gap-2.5">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
            {icon}
          </span>
          <div className="flex flex-1 flex-col">
            <b className="text-sm font-semibold text-foreground">{title}</b>
            <small className="text-[11px] text-muted-foreground">{subtitle}</small>
          </div>
          {pill && (
            <span className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide ${pillTone}`}>
              {pill.label}
            </span>
          )}
        </header>
        <div className="text-2xl font-semibold tabular-nums text-foreground">{big}</div>
        {children}
      </CardContent>
    </Card>
  );
}

export default function JanaCockpit({
  sellKpis,
  coworkAggregates,
  insightsAggregates,
  userName,
  businessName,
  businessId,
}: JanaCockpitProps): ReactNode {
  // ── Brief calculations (idêntico ao V2) ──────────────────────────────────
  const faturadoHoje = coworkAggregates?.faturadoHojeTotal ?? 0;
  const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;
  const deltaRev = coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = coworkAggregates?.deltaTicketVsLastWeek ?? null;
  const totalVendas = sellKpis?.total ?? 0;
  const totalPendentes = sellKpis?.due ?? 0;

  const overdueCount = insightsAggregates.overdueCount;
  const overdueValue = insightsAggregates.overdueValue;
  const totalAReceber = insightsAggregates.totalAReceber;
  const ageingBuckets = insightsAggregates.ageingBuckets;
  const ageingTotal = Object.values(ageingBuckets).reduce((a, b) => a + b, 0);
  const methodsAggList = insightsAggregates.methodsAgg;
  const methodsTotal = methodsAggList.reduce((a, m) => a + m.total, 0);
  const topClientesList = insightsAggregates.topClientes;
  const topClientesTotal = topClientesList.reduce((a, c) => a + c.total, 0);
  const ticketMedio = insightsAggregates.ticketMedio;
  const topDevedor = insightsAggregates.topDevedor;

  const sparkline = coworkAggregates?.sparkline ?? [];
  const sparkMax = Math.max(...sparkline, 1);
  const sparkSum = sparkline.reduce((a, b) => a + b, 0);

  const firstName = userName?.split(' ')[0] || 'você';
  const firstNameUpper = firstName.toUpperCase();

  // ── Ações sugeridas (idêntico ao V2) ─────────────────────────────────────
  type AcaoTone = 'rose' | 'violet' | 'peach' | 'grey';
  interface Acao {
    id: string;
    icon: ReactNode;
    title: string;
    sub: string;
    tone: AcaoTone;
    cta: { label: string; tone: CtaTone };
  }

  const acoes = useMemo((): Acao[] => {
    const list: Acao[] = [];
    if (overdueCount > 0) {
      list.push({
        id: 'regua-whatsapp',
        icon: <MessageSquare size={16} />,
        title: `Régua WhatsApp · ${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`,
        sub: `Potencial recuperação: ${fmtShort(overdueValue)}${topDevedor ? ` · top devedor: ${topDevedor.name}` : ''}`,
        tone: 'rose',
        cta: { label: 'Disparar', tone: 'danger' },
      });
    }
    if (overdueCount > 0 && overdueValue > 1000 && topDevedor) {
      list.push({
        id: 'negociar-top',
        icon: <Sparkles size={16} />,
        title: `Negociar com ${topDevedor.name}`,
        sub: `Valor ${fmtShort(topDevedor.total)} · contato direto vale mais que régua automática`,
        tone: 'violet',
        cta: { label: 'Preparar', tone: 'violet' },
      });
    }
    if (deltaTicket !== null && deltaTicket <= -5) {
      list.push({
        id: 'investigar-ticket',
        icon: <TrendingDown size={16} />,
        title: 'Investigar queda ticket médio',
        sub: `${deltaTicket}% vs semana passada · pode ser mix de produto mudando`,
        tone: 'peach',
        cta: { label: 'Investigar', tone: 'orange' },
      });
    }
    if (faturadoHoje > 0 && pixHoje > 0 && pixHoje / faturadoHoje > 0.5) {
      const pct = Math.round((pixHoje / faturadoHoje) * 100);
      list.push({
        id: 'pix-adocao',
        icon: <TrendingUp size={16} />,
        title: `PIX adoção em ${pct}% — manter`,
        sub: `${fmtShort(pixHoje)} de ${fmtShort(faturadoHoje)} hoje · custo zero vs maquininha`,
        tone: 'grey',
        cta: { label: 'Detalhe', tone: 'dark' },
      });
    }
    if (overdueCount === 0 && totalPendentes > 10) {
      list.push({
        id: 'preventivo-pendentes',
        icon: <Calendar size={16} />,
        title: `${totalPendentes} pendentes sem estourar ainda`,
        sub: 'Janela ideal pra lembrete amigável antes da régua agressiva',
        tone: 'grey',
        cta: { label: 'Lembrar', tone: 'primary' },
      });
    }
    return list;
  }, [overdueCount, overdueValue, deltaTicket, faturadoHoje, pixHoje, totalPendentes, topDevedor]);

  return (
    <div className="space-y-4">
      {/* Header do cockpit ─────────────────────────────────────────────────── */}
      <header className="flex flex-wrap items-center gap-3">
        <div className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-primary to-primary/70 text-primary-foreground">
          <Sparkles size={22} />
        </div>
        <div className="min-w-0 flex-1">
          <h2 className="text-lg font-semibold text-foreground">
            Jana <span className="mx-0.5 text-muted-foreground">·</span> Analista IA
          </h2>
          <p className="mt-0.5 font-mono text-[11px] tracking-wide text-muted-foreground">
            <span className="font-semibold text-muted-foreground">
              {(businessName || 'OIMPRESSO').toUpperCase()}
            </span>
            {businessId != null && (
              <>
                <span className="mx-1.5 opacity-40">·</span>biz={businessId}
              </>
            )}
            <span className="mx-1.5 opacity-40">·</span>v2026.05
          </p>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <span className="mr-1 inline-flex items-center gap-1.5 text-[11px] text-muted-foreground">
            <span className="h-1.5 w-1.5 rounded-full bg-success" />
            Atualizado {formatTimeShort()}
          </span>
          <Button variant="outline" size="sm" title="Configurar Brain B Jana (em breve)">
            <Settings size={13} /> Configurar
          </Button>
          <Button size="sm" title="Exportar relatório (em breve)">
            <Download size={13} /> Exportar
          </Button>
        </div>
      </header>

      {/* Brief diário ──────────────────────────────────────────────────────── */}
      <Card className="border-primary/20 bg-primary/5">
        <CardContent className="flex flex-col gap-3.5 p-5">
          <header className="flex flex-wrap items-center gap-3">
            <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
              <Calendar size={14} />
              <b className="font-semibold text-foreground">Brief diário</b>
              <span className="opacity-50">·</span>
              {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
            </span>
            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide text-primary">
              IA
            </span>
            <button
              type="button"
              className="ml-auto inline-flex items-center gap-1 rounded px-1.5 py-1 text-xs text-muted-foreground hover:text-foreground"
              title="Ouvir áudio do brief (em breve — TTS V2)"
            >
              <Volume2 size={11} /> Ouvir áudio
            </button>
          </header>

          <div className="flex flex-col gap-2">
            <p className="text-sm leading-relaxed text-foreground">
              <strong className="font-semibold">
                {greeting()}
                {userName ? `, ${firstName}` : ''}.
              </strong>{' '}
              <strong className="font-semibold">{totalVendas}</strong> vendas no período
              {totalPendentes > 0 && (
                <>
                  {' · '}
                  <strong className="font-semibold">{totalPendentes}</strong> pendentes
                </>
              )}
              . Hoje somou <strong className="font-semibold">{fmtShort(faturadoHoje)}</strong>
              {deltaRev !== null && (
                <>
                  {' '}
                  <span
                    className={`inline-flex items-center gap-0.5 font-medium ${deltaRev >= 0 ? 'text-success' : 'text-destructive'}`}
                  >
                    {deltaRev >= 0 ? <TrendingUp size={11} /> : <TrendingDown size={11} />}
                    {deltaRev >= 0 ? '+' : ''}
                    {deltaRev}% vs ontem
                  </span>
                </>
              )}
              {pixHoje > 0 && faturadoHoje > 0 && (
                <>
                  {' · PIX '}
                  <strong className="font-semibold">{fmtShort(pixHoje)}</strong>{' '}
                  <small className="text-muted-foreground">
                    ({Math.round((pixHoje / faturadoHoje) * 100)}% imediato)
                  </small>
                </>
              )}
              .
            </p>

            {overdueCount > 0 && (
              <p className="flex items-start gap-2 rounded-md border-l-[3px] border-destructive bg-destructive-soft px-3 py-2.5 text-sm text-foreground">
                <span className="mt-0.5 inline-flex shrink-0 text-destructive">
                  <AlertCircle size={13} />
                </span>
                <span>
                  <strong className="font-semibold text-destructive">{fmtShort(overdueValue)}</strong> em{' '}
                  <strong className="font-semibold">
                    {overdueCount} {plural(overdueCount, 'venda vencida', 'vendas vencidas')}
                  </strong>
                  . Top devedor:{' '}
                  {topDevedor ? (
                    <>
                      <strong className="font-semibold">{topDevedor.name}</strong> ({fmtShort(topDevedor.total)})
                    </>
                  ) : (
                    '—'
                  )}
                  .
                </span>
              </p>
            )}

            {deltaTicket !== null && Math.abs(deltaTicket) >= 5 && (
              <p className="flex items-start gap-2 rounded-md border-l-[3px] border-warning bg-warning-soft px-3 py-2.5 text-sm text-foreground">
                <span className="mt-0.5 inline-flex shrink-0 text-warning">
                  <AlertCircle size={13} />
                </span>
                <span>
                  Ticket médio{' '}
                  <strong className={`font-semibold ${deltaTicket >= 0 ? 'text-success' : 'text-destructive'}`}>
                    {deltaTicket >= 0 ? '+' : ''}
                    {deltaTicket}%
                  </strong>{' '}
                  vs semana passada — investigar mix de produto.
                </span>
              </p>
            )}

            <div className="mt-1 flex flex-wrap gap-1.5">
              {overdueCount > 0 && (
                <button
                  type="button"
                  className="inline-flex items-center gap-1.5 rounded-full border border-primary bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                >
                  <MessageSquare size={11} /> Disparar régua WhatsApp pros {overdueCount} atrasados
                </button>
              )}
              <button
                type="button"
                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-3 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
              >
                <ClipboardList size={11} /> Ver top devedores
              </button>
              <button
                type="button"
                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-3 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
              >
                <Search size={11} /> Investigar queda ticket médio
              </button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* KPIs (4 cards) ────────────────────────────────────────────────────── */}
      <KpiGrid cols={4}>
        <KpiCard
          label="Faturamento mês"
          value={fmtShort(sparkSum || faturadoHoje)}
          icon="wallet"
          tone="default"
          delta={deltaRev !== null ? { value: deltaRev, label: 'vs ontem' } : undefined}
        />
        <KpiCard
          label="Inadimplência total"
          value={fmtShort(overdueValue)}
          icon="alert-triangle"
          tone={overdueValue > 0 ? 'danger' : 'success'}
          description={
            overdueCount > 0
              ? `${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`
              : 'tudo em dia'
          }
        />
        <KpiCard
          label="Ticket médio"
          value={fmtShort(ticketMedio)}
          icon="trending-up"
          tone="default"
          delta={deltaTicket !== null ? { value: deltaTicket, label: '7d' } : undefined}
        />
        <KpiCard
          label="PIX hoje"
          value={fmtShort(pixHoje)}
          icon="zap"
          tone="info"
          description={
            faturadoHoje > 0
              ? `${Math.round((pixHoje / faturadoHoje) * 100)}% do faturado`
              : '— sem faturamento hoje'
          }
        />
      </KpiGrid>

      {/* Análises principais ───────────────────────────────────────────────── */}
      <SectionTitle icon={<BarChart3 size={14} />}>Análises principais</SectionTitle>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Inadimplência buckets */}
        <AnalysisCard
          icon={<AlertTriangle size={16} />}
          title="Inadimplência"
          subtitle={`${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`}
          pill={{ label: overdueCount > 0 ? 'Crítico' : 'OK', tone: overdueCount > 0 ? 'crit' : 'ok' }}
          big={<span className="text-destructive">{fmtShort(ageingTotal)}</span>}
        >
          <div className="flex flex-col gap-2">
            {Object.entries(ageingBuckets).map(([label, v]) => (
              <div key={label} className="flex flex-col gap-1">
                <div className="flex items-baseline justify-between text-xs">
                  <span className="text-muted-foreground">{label}</span>
                  <b className="font-semibold tabular-nums text-foreground">{fmtShort(v)}</b>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                  <div
                    className="h-full rounded-full bg-gradient-to-r from-warning to-destructive"
                    style={{ width: ageingTotal > 0 ? `${(v / ageingTotal) * 100}%` : '0%' }}
                  />
                </div>
              </div>
            ))}
          </div>
        </AnalysisCard>

        {/* Faturamento sparkline */}
        <AnalysisCard
          icon={<TrendingUp size={16} />}
          title="Faturamento"
          subtitle="30 dias"
          pill={
            deltaRev !== null
              ? { label: `${deltaRev >= 0 ? '+' : ''}${deltaRev}% vs ontem`, tone: deltaRev >= 0 ? 'ok' : 'warn' }
              : undefined
          }
          big={<span className="text-success">{fmtShort(sparkSum)}</span>}
        >
          {sparkline.length === 0 ? (
            <div className="py-2 text-xs text-muted-foreground">Carregando sparkline…</div>
          ) : (
            <div className="text-primary">
              <svg viewBox={`0 0 ${sparkline.length * 4} 40`} preserveAspectRatio="none" className="h-10 w-full">
                <polyline
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  points={sparkline.map((v, i) => `${i * 4},${40 - (v / sparkMax) * 38 - 1}`).join(' ')}
                />
              </svg>
              <div className="flex justify-between text-[10px] text-muted-foreground">
                <span>D-{sparkline.length}</span>
                <span>hoje</span>
              </div>
            </div>
          )}
        </AnalysisCard>

        {/* Top clientes */}
        <AnalysisCard
          icon={<Target size={16} />}
          title="Top 5 clientes"
          subtitle="concentração"
          big={<span>{topClientesList.length}</span>}
        >
          <div className="flex flex-col gap-2">
            {topClientesList.length === 0 ? (
              <div className="py-2 text-xs text-muted-foreground">Sem dados de clientes</div>
            ) : (
              topClientesList.map((c) => (
                <div key={c.name} className="flex flex-col gap-1">
                  <div className="flex items-baseline justify-between text-xs">
                    <span className="max-w-[70%] truncate text-muted-foreground" title={c.name}>
                      {c.name}
                    </span>
                    <b className="font-semibold tabular-nums text-foreground">{fmtShort(c.total)}</b>
                  </div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-primary"
                      style={{ width: topClientesTotal > 0 ? `${(c.total / topClientesTotal) * 100}%` : '0%' }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </AnalysisCard>

        {/* Métodos de pagamento */}
        <AnalysisCard
          icon={<CreditCard size={16} />}
          title="Métodos de pagamento"
          subtitle={`top ${methodsAggList.length}`}
          big={<span>{fmtShort(methodsTotal)}</span>}
        >
          <div className="flex flex-col gap-2">
            {methodsAggList.length === 0 ? (
              <div className="py-2 text-xs text-muted-foreground">Sem pagamentos registrados</div>
            ) : (
              methodsAggList.map((m) => (
                <div key={m.method} className="flex flex-col gap-1">
                  <div className="flex items-baseline justify-between text-xs">
                    <span className="text-muted-foreground">{m.method}</span>
                    <b className="font-semibold tabular-nums text-foreground">
                      {methodsTotal > 0 ? Math.round((m.total / methodsTotal) * 100) : 0}%
                    </b>
                  </div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-success"
                      style={{ width: methodsTotal > 0 ? `${(m.total / methodsTotal) * 100}%` : '0%' }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </AnalysisCard>
      </div>

      {/* Ações sugeridas ───────────────────────────────────────────────────── */}
      {acoes.length > 0 && (
        <>
          <SectionTitle icon={<Lightbulb size={14} />}>Ações que {firstNameUpper} sugere</SectionTitle>

          <Card>
            <CardContent className="flex flex-col divide-y divide-border p-0">
              {acoes.map((a) => (
                <div key={a.id} className="grid grid-cols-[auto_1fr_auto] items-center gap-3.5 p-3.5">
                  <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-border bg-card text-muted-foreground">
                    {a.icon}
                  </span>
                  <div className="min-w-0">
                    <b className="block text-sm font-semibold text-foreground">{a.title}</b>
                    <small className="block text-[11.5px] text-muted-foreground">{a.sub}</small>
                  </div>
                  <Button variant={ctaVariant(a.cta.tone)} size="sm" title={`${a.cta.label} (HITL — em breve V2)`}>
                    {a.cta.label}
                  </Button>
                </div>
              ))}
            </CardContent>
          </Card>
        </>
      )}

      <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
        <Lightbulb size={12} className="mt-0.5 shrink-0" />
        Insights baseados em vendas filtradas atual + agregados 30d. Próximas ondas: ações HITL real
        (régua WhatsApp · investigar anomalias) + agentes Brain B Jana real.
      </p>

      {/* Anti-flicker placeholder de totalAReceber pra reuso futuro do hook. */}
      <span hidden data-total-a-receber={totalAReceber} />
    </div>
  );
}

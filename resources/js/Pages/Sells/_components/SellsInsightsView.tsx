// SellsInsightsView — Cockpit "Analista IA" da tab Sells (V2 · Onda gaps r5).
// Refs:
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.jsx (canon visual)
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.css (tokens .jc-*)
//   - memory/requisitos/Sells/Sells-r5-cowork-vs-prod-2026-05-26.md (5 gaps catalogados)
//
// V2 fecha 5 gaps vs V1 (PR #1684):
//  GAP 1 — KPIs row dedicado Jana (4 cards independentes do dashboard)
//  GAP 2 — Lista "AÇÕES QUE [USER] SUGERE" estruturadas (AcaoRow)
//  GAP 3 — JanaHeader avatar grande + tenant breadcrumb + updatedAt + Configurar/Exportar
//  GAP 4 — Brief header refinements (📅 + IA pill + "Ouvir áudio" btn placeholder)
//  GAP 5 — H2 separadores hierárquicos ("ANÁLISES PRINCIPAIS" + "AÇÕES QUE JANA SUGERE")
//
// Mode "Analista IA" da rota Sells — mostra brief diário + KPIs Jana + 4 análises + ações
// sugeridas, usando dados agregados que já existem no payload (sellKpis + coworkAggregates
// + rows atuais). MVP enxuto. Próxima onda plugará agentes Brain B Jana real (ADR 0035).

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
  Wallet,
  Zap,
} from 'lucide-react';

export interface InsightsViewProps {
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
  /** Vendas atuais (já filtradas no Index pelo saved view + status pill). */
  rows: Array<{
    final_total: number;
    payment_status: string;
    sla_kind: string;
    days_to_due: number | null;
    customer_name: string | null;
    payment_method_label: string | null;
  }>;
  userName?: string;
  /** Tenant context pro header breadcrumb. */
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

export default function SellsInsightsView({
  sellKpis,
  coworkAggregates,
  rows,
  userName,
  businessName,
  businessId,
}: InsightsViewProps): ReactNode {
  // ── Brief calculations ───────────────────────────────────────────────────
  const faturadoHoje = coworkAggregates?.faturadoHojeTotal ?? 0;
  const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;
  const deltaRev = coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = coworkAggregates?.deltaTicketVsLastWeek ?? null;
  const totalVendas = sellKpis?.total ?? rows.length;
  const totalPendentes = sellKpis?.due ?? rows.filter((r) => r.payment_status !== 'paid').length;
  const overdueCount = rows.filter((r) => r.sla_kind === 'overdue').length;
  const overdueValue = rows
    .filter((r) => r.sla_kind === 'overdue')
    .reduce((acc, r) => acc + (Number(r.final_total) || 0), 0);
  const totalAReceber = rows
    .filter((r) => r.payment_status !== 'paid')
    .reduce((acc, r) => acc + (Number(r.final_total) || 0), 0);

  // ── Inadimplência buckets ────────────────────────────────────────────────
  const ageingBuckets = (() => {
    const buckets = { '0-30d': 0, '30-90d': 0, '90-365d': 0, '>365d': 0 };
    rows
      .filter((r) => r.sla_kind === 'overdue' && r.days_to_due !== null)
      .forEach((r) => {
        const days = Math.abs(r.days_to_due as number);
        const v = Number(r.final_total) || 0;
        if (days <= 30) buckets['0-30d'] += v;
        else if (days <= 90) buckets['30-90d'] += v;
        else if (days <= 365) buckets['90-365d'] += v;
        else buckets['>365d'] += v;
      });
    return buckets;
  })();
  const ageingTotal = Object.values(ageingBuckets).reduce((a, b) => a + b, 0);

  // ── Métodos pagamento ────────────────────────────────────────────────────
  const methodsAgg = (() => {
    const m: Record<string, number> = {};
    rows.forEach((r) => {
      const k = r.payment_method_label || 'Outros';
      m[k] = (m[k] ?? 0) + (Number(r.final_total) || 0);
    });
    return Object.entries(m)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5);
  })();
  const methodsTotal = methodsAgg.reduce((a, [, v]) => a + v, 0);

  // ── Top clientes ─────────────────────────────────────────────────────────
  const topClientes = (() => {
    const m: Record<string, number> = {};
    rows.forEach((r) => {
      const k = r.customer_name || 'Cliente padrão';
      m[k] = (m[k] ?? 0) + (Number(r.final_total) || 0);
    });
    return Object.entries(m)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5);
  })();
  const topClientesTotal = topClientes.reduce((a, [, v]) => a + v, 0);

  // ── Sparkline 30d ────────────────────────────────────────────────────────
  const sparkline = coworkAggregates?.sparkline ?? [];
  const sparkMax = Math.max(...sparkline, 1);
  const sparkSum = sparkline.reduce((a, b) => a + b, 0);

  // ── Ticket médio ─────────────────────────────────────────────────────────
  const ticketMedio = rows.length > 0
    ? rows.reduce((acc, r) => acc + (Number(r.final_total) || 0), 0) / rows.length
    : 0;

  const firstName = userName?.split(' ')[0] || 'você';
  const firstNameUpper = firstName.toUpperCase();

  // ── GAP 2 — Ações sugeridas (estruturadas) ───────────────────────────────
  // Gera ações de acordo com sinais detectados no payload. Cada ação tem
  // ícone + título + descrição + tone (rose/violet/peach/grey) + CTA.
  type AcaoTone = 'rose' | 'violet' | 'peach' | 'grey';
  type CtaTone = 'danger' | 'violet' | 'orange' | 'dark' | 'primary';
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
    // Sinal 1 — vendas vencidas → régua WhatsApp HITL
    if (overdueCount > 0) {
      const topDevedor = rows
        .filter((r) => r.sla_kind === 'overdue')
        .sort((a, b) => (Number(b.final_total) || 0) - (Number(a.final_total) || 0))[0];
      list.push({
        id: 'regua-whatsapp',
        icon: <MessageSquare size={16} />,
        title: `Régua WhatsApp · ${overdueCount} venda${overdueCount === 1 ? '' : 's'} vencida${overdueCount === 1 ? '' : 's'}`,
        sub: `Potencial recuperação: ${fmtShort(overdueValue)} · ${topDevedor ? `top devedor: ${topDevedor.customer_name || 'Cliente padrão'}` : ''}`.replace(/ · $/, ''),
        tone: 'rose',
        cta: { label: 'Disparar', tone: 'danger' },
      });
    }
    // Sinal 2 — top devedor > R$1k (mesmo se não overdue ainda)
    if (overdueCount > 0 && overdueValue > 1000) {
      const topDevedor = rows
        .filter((r) => r.sla_kind === 'overdue')
        .sort((a, b) => (Number(b.final_total) || 0) - (Number(a.final_total) || 0))[0];
      if (topDevedor?.customer_name) {
        list.push({
          id: 'negociar-top',
          icon: <Sparkles size={16} />,
          title: `Negociar com ${topDevedor.customer_name}`,
          sub: `Valor ${fmtShort(Number(topDevedor.final_total) || 0)} · contato direto vale mais que régua automática`,
          tone: 'violet',
          cta: { label: 'Preparar', tone: 'violet' },
        });
      }
    }
    // Sinal 3 — queda ticket médio
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
    // Sinal 4 — PIX adoção alta (positivo — manter)
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
    // Sinal 5 — pendentes alto sem overdue (alerta antes do estouro)
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
  }, [overdueCount, overdueValue, deltaTicket, faturadoHoje, pixHoje, totalPendentes, rows]);

  // ── GAP 1 — KPIs row Jana (4 cards próprios) ─────────────────────────────
  interface JanaKpi {
    label: string;
    icon: ReactNode;
    value: string;
    delta?: string;
    deltaCls?: 'down' | 'up' | 'info' | '';
    sub?: string;
    emphasize?: boolean;
  }

  const janaKpis: JanaKpi[] = [
    {
      label: 'Faturamento mês',
      icon: <Wallet size={14} />,
      value: fmtShort(sparkSum || faturadoHoje),
      delta: deltaRev !== null ? `${deltaRev >= 0 ? '↑ +' : '↓ '}${deltaRev}% vs ontem` : undefined,
      deltaCls: deltaRev !== null ? (deltaRev >= 0 ? 'up' : 'down') : '',
    },
    {
      label: 'Inadimplência total',
      icon: <AlertTriangle size={14} />,
      value: fmtShort(overdueValue),
      sub: overdueCount > 0
        ? `${overdueCount} venda${overdueCount === 1 ? '' : 's'} vencida${overdueCount === 1 ? '' : 's'}`
        : 'tudo em dia',
      emphasize: overdueValue > 0,
      deltaCls: overdueValue > 0 ? 'down' : '',
    },
    {
      label: 'Ticket médio',
      icon: <TrendingUp size={14} />,
      value: fmtShort(ticketMedio),
      delta: deltaTicket !== null
        ? `${deltaTicket >= 0 ? '↑ +' : '↓ '}${deltaTicket}% 7d`
        : undefined,
      deltaCls: deltaTicket !== null ? (deltaTicket >= 0 ? 'up' : 'down') : '',
    },
    {
      label: 'PIX hoje',
      icon: <Zap size={14} />,
      value: fmtShort(pixHoje),
      sub: faturadoHoje > 0
        ? `${Math.round((pixHoje / faturadoHoje) * 100)}% do faturado`
        : '— sem faturamento hoje',
      deltaCls: 'info',
    },
  ];

  return (
    <div className="vd-insights">
      {/* GAP 3 — JanaHeader dedicado ──────────────────────────────────────── */}
      <header className="vd-insights-jh">
        <div className="vd-insights-jh-l">
          <div className="vd-insights-jh-av">
            <Sparkles size={22} />
          </div>
          <div className="vd-insights-jh-id">
            <h2>
              Jana <span className="dot">·</span> Analista IA
            </h2>
            <p>
              <span className="vd-insights-jh-tenant">
                {(businessName || 'OIMPRESSO').toUpperCase()}
              </span>
              {businessId != null && (
                <>
                  <span className="vd-insights-jh-sep">·</span>biz={businessId}
                </>
              )}
              <span className="vd-insights-jh-sep">·</span>v2026.05
            </p>
          </div>
        </div>
        <div className="vd-insights-jh-r">
          <span className="vd-insights-jh-updated">
            <span className="d" />
            Atualizado {formatTimeShort()}
          </span>
          <button
            type="button"
            className="vd-insights-jh-btn ghost"
            title="Configurar Brain B Jana (em breve)"
          >
            <Settings size={12} /> Configurar
          </button>
          <button
            type="button"
            className="vd-insights-jh-btn dark"
            title="Exportar relatório (em breve)"
          >
            <Download size={12} /> Exportar
          </button>
        </div>
      </header>

      {/* Brief diário · Jana (GAP 4 — header refinements) ──────────────────── */}
      <section className="vd-insights-brief">
        <header className="vd-insights-brief-h">
          <span className="vd-insights-brief-h-l">
            <Calendar size={14} />
            <b>Brief diário</b>
            <span className="sep">·</span>
            {new Date().toLocaleDateString('pt-BR', {
              day: '2-digit',
              month: 'long',
              year: 'numeric',
            })}
          </span>
          <span className="vd-insights-pill ia">IA</span>
          <button
            type="button"
            className="vd-insights-audio"
            title="Ouvir áudio do brief (em breve — TTS V2)"
          >
            <Volume2 size={11} /> Ouvir áudio
          </button>
        </header>

        <div className="vd-insights-brief-body">
          <p className="vd-insights-brief-greet">
            <strong>
              {greeting()}{userName ? `, ${firstName}` : ''}.
            </strong>{' '}
            <strong>{totalVendas}</strong> vendas no período
            {totalPendentes > 0 && (
              <>
                {' · '}
                <strong>{totalPendentes}</strong> pendentes
              </>
            )}
            . Hoje somou <strong>{fmtShort(faturadoHoje)}</strong>
            {deltaRev !== null && (
              <>
                {' '}
                <span className={deltaRev >= 0 ? 'vd-pos' : 'vd-neg'}>
                  {deltaRev >= 0 ? <TrendingUp size={11} /> : <TrendingDown size={11} />}
                  {deltaRev >= 0 ? '+' : ''}
                  {deltaRev}% vs ontem
                </span>
              </>
            )}
            {pixHoje > 0 && faturadoHoje > 0 && (
              <>
                {' · PIX'} <strong>{fmtShort(pixHoje)}</strong>{' '}
                <small>
                  ({Math.round((pixHoje / faturadoHoje) * 100)}% imediato)
                </small>
              </>
            )}
            .
          </p>

          {overdueCount > 0 && (
            <p className="vd-insights-anomaly">
              <span className="vd-insights-anomaly-ic">
                <AlertCircle size={13} />
              </span>
              <strong className="vd-neg">{fmtShort(overdueValue)}</strong> em{' '}
              <strong>{overdueCount} vendas vencidas</strong>. Top devedor:{' '}
              {(() => {
                const top = rows
                  .filter((r) => r.sla_kind === 'overdue')
                  .sort((a, b) => (b.final_total || 0) - (a.final_total || 0))[0];
                return top ? (
                  <>
                    <strong>{top.customer_name || 'Cliente padrão'}</strong> ({fmtShort(top.final_total)})
                  </>
                ) : (
                  '—'
                );
              })()}
              .
            </p>
          )}

          {deltaTicket !== null && Math.abs(deltaTicket) >= 5 && (
            <p className="vd-insights-anomaly">
              <span className="vd-insights-anomaly-ic">
                <AlertCircle size={13} />
              </span>
              Ticket médio{' '}
              <strong className={deltaTicket >= 0 ? 'vd-pos' : 'vd-neg'}>
                {deltaTicket >= 0 ? '+' : ''}
                {deltaTicket}%
              </strong>{' '}
              vs semana passada — investigar mix de produto.
            </p>
          )}

          <div className="vd-insights-chips">
            {overdueCount > 0 && (
              <button type="button" className="vd-insights-chip primary">
                <MessageSquare size={11} /> Disparar régua WhatsApp pros {overdueCount} atrasados
              </button>
            )}
            <button type="button" className="vd-insights-chip">
              <ClipboardList size={11} /> Ver top devedores
            </button>
            <button type="button" className="vd-insights-chip">
              <Search size={11} /> Investigar queda ticket médio
            </button>
          </div>
        </div>
      </section>

      {/* GAP 1 — KPIs row dedicado Jana (4 cards próprios) ──────────────────── */}
      <div className="vd-insights-kpis">
        {janaKpis.map((k) => (
          <div
            key={k.label}
            className={`vd-insights-kpi${k.emphasize ? ' emph' : ''}`}
          >
            <div className="vd-insights-kpi-h">
              <span>{k.label.toUpperCase()}</span>
              <span className="vd-insights-kpi-ic">{k.icon}</span>
            </div>
            <b className={`vd-insights-kpi-v ${k.deltaCls === 'down' ? 'red' : ''}`}>
              {k.value}
            </b>
            {k.delta && (
              <small className={`vd-insights-kpi-d ${k.deltaCls || ''}`}>{k.delta}</small>
            )}
            {k.sub && <small className="vd-insights-kpi-d">{k.sub}</small>}
          </div>
        ))}
      </div>

      {/* GAP 5 — H2 separador "ANÁLISES PRINCIPAIS" ─────────────────────────── */}
      <h2 className="vd-insights-h2">
        <span className="vd-insights-h2-ic"><BarChart3 size={14} /></span> ANÁLISES PRINCIPAIS
      </h2>

      {/* 4 análises grid 2x2 (já existia) ──────────────────────────────────── */}
      <div className="vd-insights-grid">
        {/* Análise 1: Inadimplência buckets */}
        <section className="vd-insights-card">
          <header className="vd-insights-card-h">
            <span className="vd-insights-card-ic"><AlertTriangle size={16} /></span>
            <div>
              <b>Inadimplência</b>
              <small>{overdueCount} vendas vencidas</small>
            </div>
            <span className={`vd-insights-pill ${overdueCount > 0 ? 'crit' : 'ok'}`}>
              {overdueCount > 0 ? 'CRÍTICO' : 'OK'}
            </span>
          </header>
          <div className="vd-insights-card-big">
            <span className="vd-neg">{fmtShort(ageingTotal)}</span>
          </div>
          <div className="vd-insights-buckets">
            {Object.entries(ageingBuckets).map(([label, v]) => (
              <div key={label} className="vd-insights-bucket">
                <div className="vd-insights-bucket-lbl">
                  <span>{label}</span>
                  <b>{fmtShort(v)}</b>
                </div>
                <div className="vd-insights-bucket-bar">
                  <div
                    className="vd-insights-bucket-fill"
                    style={{
                      width: ageingTotal > 0 ? `${(v / ageingTotal) * 100}%` : '0%',
                    }}
                  />
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Análise 2: Faturamento sparkline 30d */}
        <section className="vd-insights-card">
          <header className="vd-insights-card-h">
            <span className="vd-insights-card-ic"><TrendingUp size={16} /></span>
            <div>
              <b>Faturamento</b>
              <small>30 dias</small>
            </div>
            {deltaRev !== null && (
              <span className={`vd-insights-pill ${deltaRev >= 0 ? 'ok' : 'warn'}`}>
                {deltaRev >= 0 ? '+' : ''}
                {deltaRev}% vs ontem
              </span>
            )}
          </header>
          <div className="vd-insights-card-big">
            <span className="vd-pos">{fmtShort(sparkSum)}</span>
          </div>
          <div className="vd-insights-spark">
            {sparkline.length === 0 ? (
              <div className="vd-insights-empty">Carregando sparkline…</div>
            ) : (
              <>
                <svg viewBox={`0 0 ${sparkline.length * 4} 40`} preserveAspectRatio="none">
                  <polyline
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    points={sparkline
                      .map((v, i) => `${i * 4},${40 - (v / sparkMax) * 38 - 1}`)
                      .join(' ')}
                  />
                </svg>
                <div className="vd-insights-spark-range">
                  <span>D-{sparkline.length}</span>
                  <span>hoje</span>
                </div>
              </>
            )}
          </div>
        </section>

        {/* Análise 3: Top clientes Pareto */}
        <section className="vd-insights-card">
          <header className="vd-insights-card-h">
            <span className="vd-insights-card-ic"><Target size={16} /></span>
            <div>
              <b>Top 5 clientes</b>
              <small>concentração</small>
            </div>
          </header>
          <div className="vd-insights-card-big">
            <span>{topClientes.length}</span>
          </div>
          <div className="vd-insights-bars">
            {topClientes.length === 0 ? (
              <div className="vd-insights-empty">Sem dados de clientes</div>
            ) : (
              topClientes.map(([name, value]) => (
                <div key={name} className="vd-insights-bar-row">
                  <div className="vd-insights-bar-lbl">
                    <span title={name}>{name.slice(0, 28)}</span>
                    <b>{fmtShort(value)}</b>
                  </div>
                  <div className="vd-insights-bar">
                    <div
                      className="vd-insights-bar-fill"
                      style={{
                        width: topClientesTotal > 0 ? `${(value / topClientesTotal) * 100}%` : '0%',
                      }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </section>

        {/* Análise 4: Métodos de pagamento */}
        <section className="vd-insights-card">
          <header className="vd-insights-card-h">
            <span className="vd-insights-card-ic"><CreditCard size={16} /></span>
            <div>
              <b>Métodos de pagamento</b>
              <small>top {methodsAgg.length}</small>
            </div>
          </header>
          <div className="vd-insights-card-big">
            <span>{fmtShort(methodsTotal)}</span>
          </div>
          <div className="vd-insights-bars">
            {methodsAgg.length === 0 ? (
              <div className="vd-insights-empty">Sem pagamentos registrados</div>
            ) : (
              methodsAgg.map(([method, value]) => (
                <div key={method} className="vd-insights-bar-row">
                  <div className="vd-insights-bar-lbl">
                    <span>{method}</span>
                    <b>
                      {methodsTotal > 0 ? Math.round((value / methodsTotal) * 100) : 0}%
                    </b>
                  </div>
                  <div className="vd-insights-bar">
                    <div
                      className="vd-insights-bar-fill green"
                      style={{
                        width: methodsTotal > 0 ? `${(value / methodsTotal) * 100}%` : '0%',
                      }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </section>
      </div>

      {/* GAP 5 — H2 separador "AÇÕES QUE [USER] SUGERE" ────────────────────── */}
      {acoes.length > 0 && (
        <>
          <h2 className="vd-insights-h2">
            <span className="vd-insights-h2-ic"><Lightbulb size={14} /></span> AÇÕES QUE {firstNameUpper} SUGERE
          </h2>

          {/* GAP 2 — Lista de ações estruturadas (AcaoRow) ─────────────────── */}
          <div className="vd-insights-acoes">
            {acoes.map((a) => (
              <div key={a.id} className={`vd-insights-acao tone-${a.tone}`}>
                <span className="vd-insights-acao-ic">{a.icon}</span>
                <div className="vd-insights-acao-text">
                  <b>{a.title}</b>
                  <small>{a.sub}</small>
                </div>
                <button
                  type="button"
                  className={`vd-insights-cta ${a.cta.tone}`}
                  title={`${a.cta.label} (HITL — em breve V2)`}
                >
                  {a.cta.label}
                </button>
              </div>
            ))}
          </div>
        </>
      )}

      <p className="vd-insights-foot">
        <Lightbulb size={12} className="inline-block mr-1 align-[-2px]" />
        Insights baseados em vendas filtradas atual + agregados 30d.
        Próximas ondas: ações HITL real (régua WhatsApp · investigar anomalias) + agentes Brain B Jana real.
      </p>

      {/* Anti-flicker placeholder de totalAReceber pra reuso futuro do hook. */}
      <span hidden data-total-a-receber={totalAReceber} />
    </div>
  );
}

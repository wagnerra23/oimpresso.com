// JanaCockpitV2 — Cockpit "Analista IA" canon da Jana V2, hospedado em /ia/dashboard.
// Refs:
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.jsx (canon visual)
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.css (tokens .jc-*)
//   - memory/requisitos/Sells/Sells-r5-cowork-vs-prod-2026-05-26.md (5 gaps catalogados)
//
// História:
//   1. PR #1684 — V1 como tab em /sells
//   2. PR #1686 — V2 fecha 5 gaps r5 (header + KPIs + H2 + Ações + brief)
//   3. Onda atual — movido pra /ia/dashboard (Jana é marca IA; Sells volta single-view)
//     Antes: resources/js/Pages/Sells/_components/SellsInsightsView.tsx
//     Agora: resources/js/Pages/Jana/components/JanaCockpitV2.tsx
//
// Mudança técnica da Onda atual: contrato de props refatorado de `rows`-driven
// (computava ageingBuckets/methodsAgg/topClientes/topDevedor no frontend) pra
// `insightsAggregates` pré-computed server-side via App\Services\Sells\
// SellsCockpitAggregator. Componente standalone — não depende de filtros Sells.
//
// V2 features:
//  GAP 1 — KPIs row dedicado Jana (4 cards próprios)
//  GAP 2 — Lista "AÇÕES QUE [USER] SUGERE" estruturadas (AcaoRow)
//  GAP 3 — JanaHeader avatar grande + tenant breadcrumb + updatedAt + Configurar/Exportar
//  GAP 4 — Brief header refinements (📅 + IA pill + "Ouvir áudio" btn placeholder)
//  GAP 5 — H2 separadores hierárquicos ("ANÁLISES PRINCIPAIS" + "AÇÕES QUE JANA SUGERE")

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

export interface JanaCockpitV2Props {
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
  /**
   * Pré-agregações server-side (SellsCockpitAggregator.buildInsightsAggregates).
   * Substituem o `rows`-driven que existia em SellsInsightsView V1/V2.
   */
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

export default function JanaCockpitV2({
  sellKpis,
  coworkAggregates,
  insightsAggregates,
  userName,
  businessName,
  businessId,
}: JanaCockpitV2Props): ReactNode {
  // ── Brief calculations ───────────────────────────────────────────────────
  const faturadoHoje = coworkAggregates?.faturadoHojeTotal ?? 0;
  const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;
  const deltaRev = coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = coworkAggregates?.deltaTicketVsLastWeek ?? null;
  const totalVendas = sellKpis?.total ?? 0;
  const totalPendentes = sellKpis?.due ?? 0;

  // Pré-agregados server-side (SellsCockpitAggregator.buildInsightsAggregates).
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

  // ── Sparkline 30d ────────────────────────────────────────────────────────
  const sparkline = coworkAggregates?.sparkline ?? [];
  const sparkMax = Math.max(...sparkline, 1);
  const sparkSum = sparkline.reduce((a, b) => a + b, 0);

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
      list.push({
        id: 'regua-whatsapp',
        icon: <MessageSquare size={16} />,
        title: `Régua WhatsApp · ${overdueCount} venda${overdueCount === 1 ? '' : 's'} vencida${overdueCount === 1 ? '' : 's'}`,
        sub: `Potencial recuperação: ${fmtShort(overdueValue)} · ${topDevedor ? `top devedor: ${topDevedor.name}` : ''}`.replace(/ · $/, ''),
        tone: 'rose',
        cta: { label: 'Disparar', tone: 'danger' },
      });
    }
    // Sinal 2 — top devedor > R$ [redacted Tier 0]k (mesmo se não overdue ainda)
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
  }, [overdueCount, overdueValue, deltaTicket, faturadoHoje, pixHoje, totalPendentes, topDevedor]);

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
              {topDevedor ? (
                <>
                  <strong>{topDevedor.name}</strong> ({fmtShort(topDevedor.total)})
                </>
              ) : (
                '—'
              )}
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
            <span>{topClientesList.length}</span>
          </div>
          <div className="vd-insights-bars">
            {topClientesList.length === 0 ? (
              <div className="vd-insights-empty">Sem dados de clientes</div>
            ) : (
              topClientesList.map((c) => (
                <div key={c.name} className="vd-insights-bar-row">
                  <div className="vd-insights-bar-lbl">
                    <span title={c.name}>{c.name.slice(0, 28)}</span>
                    <b>{fmtShort(c.total)}</b>
                  </div>
                  <div className="vd-insights-bar">
                    <div
                      className="vd-insights-bar-fill"
                      style={{
                        width: topClientesTotal > 0 ? `${(c.total / topClientesTotal) * 100}%` : '0%',
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
              <small>top {methodsAggList.length}</small>
            </div>
          </header>
          <div className="vd-insights-card-big">
            <span>{fmtShort(methodsTotal)}</span>
          </div>
          <div className="vd-insights-bars">
            {methodsAggList.length === 0 ? (
              <div className="vd-insights-empty">Sem pagamentos registrados</div>
            ) : (
              methodsAggList.map((m) => (
                <div key={m.method} className="vd-insights-bar-row">
                  <div className="vd-insights-bar-lbl">
                    <span>{m.method}</span>
                    <b>
                      {methodsTotal > 0 ? Math.round((m.total / methodsTotal) * 100) : 0}%
                    </b>
                  </div>
                  <div className="vd-insights-bar">
                    <div
                      className="vd-insights-bar-fill green"
                      style={{
                        width: methodsTotal > 0 ? `${(m.total / methodsTotal) * 100}%` : '0%',
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

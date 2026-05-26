// SellsInsightsView — view "Insights Jana" da tab bar Sells (PR #1682 · P5 parking lot).
// Refs:
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.jsx (canon visual)
//   - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #13
//
// Mode "Analista IA" da rota Sells — mostra brief diário + 4 análises usando dados
// agregados que já existem no payload (sellKpis + coworkAggregates + rows atuais).
// MVP enxuto. Próxima onda plugará agentes Brain B Jana real (ADR 0035).

import type { ReactNode } from 'react';
import { AlertCircle, TrendingUp, TrendingDown, MessageSquare, Sparkles } from 'lucide-react';

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

export default function SellsInsightsView({
  sellKpis,
  coworkAggregates,
  rows,
  userName,
}: InsightsViewProps): ReactNode {
  // Brief calculations
  const faturadoHoje = coworkAggregates?.faturadoHojeTotal ?? 0;
  const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;
  const deltaRev = coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = coworkAggregates?.deltaTicketVsLastWeek ?? null;
  // sellKpis.total = nº total vendas; sellKpis.due = pendentes (não pagas).
  // Usados no brief topo pra contextualizar volume.
  const totalVendas = sellKpis?.total ?? rows.length;
  const totalPendentes = sellKpis?.due ?? rows.filter((r) => r.payment_status !== 'paid').length;
  const overdueCount = rows.filter((r) => r.sla_kind === 'overdue').length;
  const overdueValue = rows
    .filter((r) => r.sla_kind === 'overdue')
    .reduce((acc, r) => acc + (Number(r.final_total) || 0), 0);

  // Inadimplência buckets (paridade prototipo Cowork chat-jana.jsx)
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

  // Métodos pagamento
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

  // Top clientes
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

  // Sparkline 30d
  const sparkline = coworkAggregates?.sparkline ?? [];
  const sparkMax = Math.max(...sparkline, 1);

  return (
    <div className="vd-insights">
      {/* Brief diário · Jana */}
      <section className="vd-insights-brief">
        <header className="vd-insights-brief-h">
          <div className="vd-insights-brief-av">
            <Sparkles size={18} />
          </div>
          <div className="vd-insights-brief-meta">
            <b>Jana · Analista IA</b>
            <small>
              {greeting()}{userName ? `, ${userName.split(' ')[0]}` : ''} ·{' '}
              {new Date().toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
              })}
            </small>
          </div>
        </header>

        <div className="vd-insights-brief-body">
          <p>
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
              📋 Ver top devedores
            </button>
            <button type="button" className="vd-insights-chip">
              🔍 Investigar queda ticket médio
            </button>
          </div>
        </div>
      </section>

      {/* 4 análises grid 2x2 */}
      <div className="vd-insights-grid">
        {/* Análise 1: Inadimplência buckets */}
        <section className="vd-insights-card">
          <header className="vd-insights-card-h">
            <span className="vd-insights-card-ic">🚨</span>
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
            <span className="vd-insights-card-ic">📈</span>
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
            <span className="vd-pos">
              {fmtShort(sparkline.reduce((a, b) => a + b, 0))}
            </span>
          </div>
          <div className="vd-insights-spark">
            {sparkline.length === 0 ? (
              <div className="vd-insights-empty">Carregando sparkline…</div>
            ) : (
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
            )}
          </div>
        </section>

        {/* Análise 3: Top clientes Pareto */}
        <section className="vd-insights-card">
          <header className="vd-insights-card-h">
            <span className="vd-insights-card-ic">🎯</span>
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
            <span className="vd-insights-card-ic">💳</span>
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

      <p className="vd-insights-foot">
        💡 Insights baseados em vendas filtradas atual + agregados 30d.
        Próximas ondas: ações HITL (régua WhatsApp · investigar anomalias) + agentes Brain B Jana real.
      </p>
    </div>
  );
}

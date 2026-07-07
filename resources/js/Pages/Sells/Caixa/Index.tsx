// Sells/Caixa/Index — cópia visual KB-9.75 da Cowork VendasCaixaPage.
// Onda 6 (ADR 0192 A1 KB-9.75) — Caixa do dia Inertia em /vendas/caixa COEXISTE
// com /cash-register/* Blade legacy (decisão Wagner 2026-05-25 ~15h, pattern
// Cliente Wave A-G drawer 760 · rollback trivial).
// Refs:
//  - prototipo-ui/vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)
//  - memory/requisitos/Sells/Caixa-r1-visual-comparison.md (15 dimensões)
//  - resources/js/Pages/Sells/Caixa/Index.charter.md (rascunho)
//  - ADR 0192 · 0104 MWART · 0107 visual gate · 0114 Cowork loop · 0143 FSM

import AppShellV2 from '@/Layouts/AppShellV2';
import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { usePage, router } from '@inertiajs/react';
import {
  Printer, CheckCircle2,
  Banknote, CreditCard, FileText, Landmark, Wallet, ReceiptText,
} from 'lucide-react';

// ──────────────────────────────────────────────────────────────
// TIPOS — paridade backend SellController@inertiaCaixa
// ──────────────────────────────────────────────────────────────
interface PorFormaPagamento {
  key: string;
  label: string;
  icon: string;
  clearing: string;
  count: number;
  total: number;
}

interface OsRef {
  id: number;
  invoice_no: string;
  os_ref: string;
}

interface PorOrigem {
  source: 'balcao' | 'oficina' | 'online' | string;
  label: string;
  count: number;
  total: number;
  refs: OsRef[];
}

interface CaixaPageProps {
  porFormaPagamento: PorFormaPagamento[];
  porOrigem: PorOrigem[];
  totalDia: number;
  countDia: number;
  caixaAberto: boolean;
  cashRegisterId: number | null;
  dateSelected: string; // 'Y-m-d'
  permissions: {
    view: boolean;
    close: boolean;
  };
}

// ──────────────────────────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────────────────────────
const fmtBRL = (n: number): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0);

const fmtDateBr = (ymd: string): string => {
  const parts = ymd.split('-');
  return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : ymd;
};

// ──────────────────────────────────────────────────────────────
// COMPONENTE PRINCIPAL
// ──────────────────────────────────────────────────────────────
export default function SellsCaixaIndex() {
  const { props } = usePage<{ props: CaixaPageProps }>() as unknown as { props: CaixaPageProps };
  const {
    porFormaPagamento,
    porOrigem,
    totalDia,
    countDia,
    caixaAberto,
    cashRegisterId,
    dateSelected,
    permissions,
  } = props;

  const [date, setDate] = useState<string>(dateSelected);

  // Onda 6 (ADR 0192) — dispatch CustomEvent cross-módulo pra abrir Sells/Index drawer.
  // Listener registrado em Sells/Index Onda 4 (commit e40289010 linha 928).
  // NÃO usa router.visit — preserva contexto Caixa e abre venda em nova aba/janela
  // ou (caso ideal) integra com Sells/Index quando o user navega de volta.
  const openVenda = useCallback((vendaId: number) => {
    // Navega pra /sells com query ?open=ID — Sells/Index detecta na monta
    // e abre o drawer SaleSheet automaticamente.
    router.visit(`/sells?open=${vendaId}`, { preserveScroll: false });
  }, []);

  const onChangeDate = useCallback((newDate: string) => {
    setDate(newDate);
    // D-14: partial reload — só re-busca o que muda com a data. caixaAberto/
    // cashRegisterId/permissions são por user/business (closures no controller).
    router.get(
      '/vendas/caixa',
      { date: newDate },
      {
        preserveState: false,
        preserveScroll: true,
        only: ['porFormaPagamento', 'porOrigem', 'totalDia', 'countDia', 'dateSelected'],
      }
    );
  }, []);

  // KPIs derivados — esperado/conferido/diferença ficam pra Onda 6+1 (read-write
  // integrar movimentos). Por ora mostra placeholders dependentes do legacy.
  const cashSales = useMemo(
    () => porFormaPagamento.find(p => p.key === 'cash')?.total || 0,
    [porFormaPagamento]
  );

  const onFecharCaixa = useCallback(() => {
    if (caixaAberto && cashRegisterId) {
      // Navega pra modal legacy de fechamento — Onda 6+1 substitui por drawer Inertia.
      window.location.href = `/cash-register/close-register/${cashRegisterId}`;
    } else {
      // Caso não haja caixa aberto, navega pra abrir caixa legacy.
      window.location.href = '/cash-register/create';
    }
  }, [caixaAberto, cashRegisterId]);

  const onImprimirZ = useCallback(() => {
    // Placeholder Onda 6+2 — por ora reaproveita endpoint register-details legacy.
    window.open('/cash-register/register-details', '_blank');
  }, []);

  return (
    <div className="sells-cowork">
      <div className="os-page vc-page vd-subpage">
        {/* HEADER */}
        <header className="os-head">
          <div className="os-head-l">
            <h1>Caixa do dia</h1>
            <p>Conferência por forma de pagamento, sangrias e fechamento</p>
          </div>
          <div className="os-head-r">
            <input
              type="date"
              className="vc-date"
              value={date}
              onChange={e => onChangeDate(e.target.value)}
              aria-label="Selecionar data do caixa"
            />
            <button
              type="button"
              className="os-btn ghost"
              onClick={onImprimirZ}
              aria-label="Imprimir Z do caixa"
            >
              <Printer size={11} />
              Imprimir Z
            </button>
            {permissions.close && (
              <button
                type="button"
                className="os-btn primary"
                onClick={onFecharCaixa}
                aria-label={caixaAberto ? 'Fechar caixa' : 'Abrir caixa'}
              >
                <CheckCircle2 size={11} />
                {caixaAberto ? 'Fechar caixa' : 'Abrir caixa'}
              </button>
            )}
          </div>
        </header>

        {/* KPIs hero */}
        <div className="os-kpis">
          <div className="os-kpi">
            <span className="os-kpi-label">Faturado no dia</span>
            <span className="os-kpi-value">{fmtBRL(totalDia)}</span>
            <span className="os-kpi-sub">
              {countDia} venda{countDia !== 1 ? 's' : ''}
            </span>
          </div>
          <div className="os-kpi">
            <span className="os-kpi-label">Vendas em dinheiro</span>
            <span className="os-kpi-value">{fmtBRL(cashSales)}</span>
            <span className="os-kpi-sub">cash · imediato</span>
          </div>
          <div className="os-kpi">
            <span className="os-kpi-label">Caixa</span>
            <span
              className="os-kpi-value"
              style={{
                color: caixaAberto ? 'oklch(0.50 0.14 145)' : 'oklch(0.55 0.02 250)',
              }}
            >
              {caixaAberto ? 'aberto' : 'fechado'}
            </span>
            <span className="os-kpi-sub">{caixaAberto ? `#${cashRegisterId}` : 'sem registro'}</span>
          </div>
          <div className="os-kpi">
            <span className="os-kpi-label">Origens hoje</span>
            <span className="os-kpi-value">{porOrigem.length}</span>
            <span className="os-kpi-sub">balcão · oficina · online</span>
          </div>
        </div>

        {/* Grid 4 cards */}
        <div className="vc-grid">
          {/* Section 1 — Por forma de pagamento */}
          <section className="vc-card">
            <header className="vc-card-h">
              <h3>Por forma de pagamento</h3>
              <span className="vc-muted">{fmtDateBr(date)}</span>
            </header>
            {porFormaPagamento.length === 0 ? (
              <p className="vc-empty">Sem movimentação no dia.</p>
            ) : (
              <table className="vc-pay-table">
                <thead>
                  <tr>
                    <th>Forma</th>
                    <th>Compensação</th>
                    <th>Vendas</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  {porFormaPagamento.map(x => (
                    <tr key={x.key}>
                      <td>
                        <span className="vc-pay-icon">{paymentIcon(x.icon)}</span> {x.label}
                      </td>
                      <td className="vc-muted">{x.clearing}</td>
                      <td className="vc-num">{x.count}</td>
                      <td className="vc-num strong">{fmtBRL(x.total)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <td colSpan={3}>Total bruto</td>
                    <td className="vc-num strong">{fmtBRL(totalDia)}</td>
                  </tr>
                </tfoot>
              </table>
            )}
          </section>

          {/* Section 2 — Por origem (A1 KB-9.75 · ADR 0192) */}
          <section className="vc-card vc-card-source">
            <header className="vc-card-h">
              <h3>Por origem</h3>
              <span className="vc-muted">balcão · oficina · online</span>
            </header>
            {porOrigem.length === 0 && (
              <p className="vc-empty">Sem movimentação no dia.</p>
            )}
            {porOrigem.map(g => {
              const pct = totalDia > 0 ? Math.round((g.total / totalDia) * 100) : 0;
              return (
                <div key={g.source} className={`vc-src-row vc-src-${g.source}`}>
                  <div className="vc-src-h">
                    <span className="vc-src-dot" aria-hidden="true" />
                    <b>{g.label}</b>
                    <span className="vc-src-ct">
                      {g.count} venda{g.count !== 1 ? 's' : ''}
                    </span>
                    <span className="vc-src-tot">{fmtBRL(g.total)}</span>
                  </div>
                  <div className="vc-src-bar" aria-hidden="true">
                    <div style={{ width: pct + '%' }} />
                  </div>
                  <div className="vc-src-meta">
                    <small>{pct}% do faturamento do dia</small>
                    {g.source === 'oficina' && g.refs.length > 0 && (
                      <small className="vc-src-refs">
                        {g.refs.slice(0, 3).map((v, i) => (
                          <span key={v.id}>
                            {i > 0 && ' · '}
                            <a
                              href={`/sells?open=${v.id}`}
                              onClick={e => {
                                e.preventDefault();
                                openVenda(v.id);
                              }}
                            >
                              ↗ #{v.os_ref}
                            </a>
                          </span>
                        ))}
                        {g.refs.length > 3 && ` · +${g.refs.length - 3}`}
                      </small>
                    )}
                  </div>
                </div>
              );
            })}
          </section>

          {/* Section 3 — Movimentos do caixa (placeholder Onda 6+1) */}
          <section className="vc-card">
            <header className="vc-card-h">
              <h3>Movimentos do caixa</h3>
              <span className="vc-muted">read-only · Onda 6+1 wire-up</span>
            </header>
            <p className="vc-empty">
              Sangrias e suprimentos continuam via fluxo legacy{' '}
              <a href="/cash-register">/cash-register</a> nesta wave.
              <br />
              <small>Onda 6+1 substitui por drawer Inertia read-write.</small>
            </p>
          </section>

          {/* Section 4 — Conferência física (placeholder Onda 6+1) */}
          <section className="vc-card">
            <header className="vc-card-h">
              <h3>Conferência física</h3>
              <span className="vc-muted">read-only · Onda 6+1 wire-up</span>
            </header>
            <p className="vc-empty">
              Fechamento de caixa real (denominations + closing note) preservado em{' '}
              {caixaAberto && cashRegisterId ? (
                <a href={`/cash-register/close-register/${cashRegisterId}`}>
                  /cash-register/close-register/{cashRegisterId}
                </a>
              ) : (
                <a href="/cash-register">/cash-register</a>
              )}
              .
              <br />
              <small>
                Onda 6+1 substitui modal legacy por drawer Inertia + denominations form
                replicado do Cowork canon.
              </small>
            </p>
          </section>
        </div>
      </div>
    </div>
  );
}

// Mapeia keyword → ícone lucide (AP6 · paridade Cowork ícones leves).
// O wrapper <span className="vc-pay-icon"> já provê margin-right:6px (sells-cowork.css).
function paymentIcon(name: string): ReactNode {
  const cls = 'h-3.5 w-3.5 inline-block align-text-bottom';
  switch (name) {
    case 'cash':
      return <Banknote className={cls} />;
    case 'card':
      return <CreditCard className={cls} />;
    case 'cheque':
      return <FileText className={cls} />;
    case 'transfer':
      return <Landmark className={cls} />;
    case 'advance':
      return <Wallet className={cls} />;
    default:
      return <ReceiptText className={cls} />;
  }
}

SellsCaixaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

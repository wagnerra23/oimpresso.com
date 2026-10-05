// Sells/Caixa/Index — cópia visual KB-9.75 da Cowork VendasCaixaPage.
// Onda 6 (ADR 0192 A1 KB-9.75) — Caixa do dia Inertia em /vendas/caixa COEXISTE
// com /cash-register/* Blade legacy (decisão Wagner 2026-05-25 ~15h, pattern
// Cliente Wave A-G drawer 760 · rollback trivial).
// Refs:
//  - prototipo-ui/cowork/Wagner/vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)
//  - memory/requisitos/Sells/Caixa-r1-visual-comparison.md (15 dimensões)
//  - resources/js/Pages/Sells/Caixa/Index.charter.md (rascunho)
//  - ADR 0192 · 0104 MWART · 0107 visual gate · 0114 Cowork loop · 0143 FSM

import AppShellV2 from '@/Layouts/AppShellV2';
import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { usePage, router, Deferred } from '@inertiajs/react';
import { parseDecimalPtBR, formatDecimalPtBR } from '@/Lib/numberPtBR';
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

// Thread 07 venda-menu — turno aberto (SellController::buildCaixaTurnoPayload).
// Totais = CashRegisterUtil::getRegisterDetails (o mesmo do Blade payment_details).
interface TurnoForma {
  key: string;
  label: string;
  vendas: number;
  despesas: number;
  devolucoes: number;
}

interface TurnoMovimento {
  id: number;
  hora: string;
  tipo: string;
  tipoLabel: string;
  formaLabel: string;
  sentido: 'credit' | 'debit' | string;
  valor: number;
  vendaId: number | null;
  invoiceNo: string | null;
}

interface Turno {
  id: number;
  abertoEm: string;
  local: string | null;
  trocoInicial: number;
  totalVendas: number;
  totalDespesas: number;
  totalDevolucoes: number;
  esperadoDinheiro: number;
  cartoes: number;
  cheques: number;
  userId: number;
  porForma: TurnoForma[];
  movimentos: TurnoMovimento[];
}

interface CaixaPageProps {
  porFormaPagamento: PorFormaPagamento[];
  porOrigem: PorOrigem[];
  totalDia: number;
  countDia: number;
  caixaAberto: boolean;
  cashRegisterId: number | null;
  dateSelected: string; // 'Y-m-d'
  turno?: Turno | null; // deferred
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
    turno,
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

  // Esperado/contado/diferença vivem na seção Conferência física (thread 07, PR 2).
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

          {/* Section 3 — Movimentos do turno (thread 07 · leitura) */}
          <section className="vc-card">
            <header className="vc-card-h">
              <h3>Movimentos do caixa</h3>
              <span className="vc-muted">turno aberto · somente leitura</span>
            </header>
            <Deferred data="turno" fallback={<p className="vc-empty">Carregando turno…</p>}>
              <TurnoMovimentos turno={turno ?? null} />
            </Deferred>
          </section>

          {/* Section 4 — Conferência física (thread 07 · PR 2) */}
          <section className="vc-card">
            <header className="vc-card-h">
              <h3>Conferência física</h3>
              <span className="vc-muted">contagem e fechamento do turno</span>
            </header>
            <Deferred data="turno" fallback={<p className="vc-empty">Carregando turno…</p>}>
              <ConferenciaFisica turno={turno ?? null} podeFechar={permissions.close} />
            </Deferred>
          </section>
        </div>
      </div>
    </div>
  );
}

// Diferença exibida: zero = bateu; positivo = sobra; negativo = falta (playbook Caixa R3).
function textoDiferenca(centavos: number | null): string {
  if (centavos === null) return '—';
  if (centavos === 0) return 'bateu certinho';
  return `${centavos > 0 ? 'sobra' : 'falta'} ${fmtBRL(Math.abs(centavos) / 100)}`;
}

// Conferência física: o operador informa o contado; a tela mostra a diferença e fecha o
// turno pelo POST /cash-register/close-register que o modal legado já usa.
// REGRA MESTRE valor: o esperado vem pronto do controller (expressão do modal Blade).
// A única conta aqui é a diferença exibida (não gravada), feita em centavos inteiros.
function ConferenciaFisica({ turno, podeFechar }: { turno: Turno | null; podeFechar: boolean }) {
  const [contado, setContado] = useState('');
  const [cartoes, setCartoes] = useState(String(turno?.cartoes ?? 0));
  const [cheques, setCheques] = useState(String(turno?.cheques ?? 0));
  const [nota, setNota] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  if (!turno) {
    return <p className="vc-empty">Sem turno aberto para conferir.</p>;
  }

  const contadoNum = parseDecimalPtBR(contado);
  const temContado = contado.trim() !== '' && Number.isFinite(contadoNum);
  const difCentavos = temContado
    ? Math.round(contadoNum * 100) - Math.round(turno.esperadoDinheiro * 100)
    : null;
  const precisaNota = difCentavos !== null && difCentavos !== 0;

  const fechar = () => {
    if (!temContado) {
      setErro('Informe o valor contado em dinheiro.');
      return;
    }
    if (precisaNota && nota.trim() === '') {
      setErro('Há diferença: escreva a observação de fechamento.');
      return;
    }
    setErro(null);
    setEnviando(true);
    router.post(
      '/cash-register/close-register',
      {
        user_id: turno.userId,
        // pt-BR com 2 casas ("1.234,56"): a forma que o num_uf do backend lê sem ambiguidade.
        closing_amount: formatDecimalPtBR(Math.round(contadoNum * 100) / 100),
        total_card_slips: Number.parseInt(cartoes, 10) || 0,
        total_cheques: Number.parseInt(cheques, 10) || 0,
        closing_note: nota,
      },
      {
        preserveScroll: true,
        onSuccess: page => {
          if ((page.props as unknown as CaixaPageProps).caixaAberto) {
            setErro('O caixa não fechou. Tente pelo /cash-register.');
          }
        },
        onFinish: () => setEnviando(false),
      }
    );
  };

  return (
    <>
      <table className="vc-pay-table" aria-label="Conferência do dinheiro">
        <tbody>
          <tr>
            <td>Esperado em dinheiro</td>
            <td className="vc-num strong">{fmtBRL(turno.esperadoDinheiro)}</td>
          </tr>
          <tr>
            <td>
              <label htmlFor="vc-contado">Contado em dinheiro</label>
            </td>
            <td className="vc-num">
              <input
                id="vc-contado"
                className="vc-date"
                inputMode="decimal"
                placeholder="0,00"
                value={contado}
                onChange={e => setContado(e.target.value)}
              />
            </td>
          </tr>
          <tr>
            <td>Diferença</td>
            <td className="vc-num strong" aria-live="polite">
              {textoDiferenca(difCentavos)}
            </td>
          </tr>
          <tr>
            <td>
              <label htmlFor="vc-cartoes">Comprovantes de cartão</label>
            </td>
            <td className="vc-num">
              <input id="vc-cartoes" className="vc-date" type="number" min={0} value={cartoes} onChange={e => setCartoes(e.target.value)} />
            </td>
          </tr>
          <tr>
            <td>
              <label htmlFor="vc-cheques">Cheques</label>
            </td>
            <td className="vc-num">
              <input id="vc-cheques" className="vc-date" type="number" min={0} value={cheques} onChange={e => setCheques(e.target.value)} />
            </td>
          </tr>
        </tbody>
      </table>
      <label htmlFor="vc-nota" className="vc-muted">
        Observação de fechamento{precisaNota ? ' (obrigatória: há diferença)' : ''}
      </label>
      <textarea id="vc-nota" className="vc-date" rows={2} value={nota} onChange={e => setNota(e.target.value)} />
      {erro && <p className="vc-empty" role="alert">{erro}</p>}
      {podeFechar ? (
        <button type="button" className="os-btn primary" onClick={fechar} disabled={enviando}>
          <CheckCircle2 size={11} />
          {enviando ? 'Fechando…' : 'Fechar caixa com esta contagem'}
        </button>
      ) : (
        <p className="vc-empty">Fechar o caixa exige a permissão de fechar caixa.</p>
      )}
    </>
  );
}

// Movimentos + totais do turno aberto. Nenhuma conta no front: todo número vem
// pronto do controller (REGRA MESTRE valor) — aqui só formata.
function TurnoMovimentos({ turno }: { turno: Turno | null }) {
  if (!turno) {
    return (
      <p className="vc-empty">
        Nenhum caixa aberto para você. Abra o turno em <a href="/cash-register/create">/cash-register/create</a>.
      </p>
    );
  }
  return (
    <>
      <p className="vc-muted">
        Turno #{turno.id} · aberto em {turno.abertoEm}
        {turno.local ? ` · ${turno.local}` : ''} · troco inicial {fmtBRL(turno.trocoInicial)}
      </p>
      <table className="vc-pay-table" aria-label="Totais do turno por forma de pagamento">
        <thead>
          <tr>
            <th>Forma</th>
            <th>Vendas</th>
            <th>Despesas</th>
            <th>Devoluções</th>
          </tr>
        </thead>
        <tbody>
          {turno.porForma.map(f => (
            <tr key={f.key}>
              <td>{f.label}</td>
              <td className="vc-num">{fmtBRL(f.vendas)}</td>
              <td className="vc-num">{fmtBRL(f.despesas)}</td>
              <td className="vc-num">{fmtBRL(f.devolucoes)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <td>Total do turno</td>
            <td className="vc-num strong">{fmtBRL(turno.totalVendas)}</td>
            <td className="vc-num strong">{fmtBRL(turno.totalDespesas)}</td>
            <td className="vc-num strong">{fmtBRL(turno.totalDevolucoes)}</td>
          </tr>
        </tfoot>
      </table>
      {turno.movimentos.length === 0 ? (
        <p className="vc-empty">Sem movimentos no turno.</p>
      ) : (
        <table className="vc-pay-table" aria-label="Movimentos do turno">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Tipo</th>
              <th>Forma</th>
              <th>Venda</th>
              <th>Valor</th>
            </tr>
          </thead>
          <tbody>
            {turno.movimentos.map(m => (
              <tr key={m.id}>
                <td className="vc-muted">{m.hora}</td>
                <td>{m.tipoLabel}</td>
                <td>{m.formaLabel}</td>
                <td>{m.vendaId ? <a href={`/sells?open=${m.vendaId}`}>{m.invoiceNo}</a> : '—'}</td>
                <td className="vc-num">
                  {m.sentido === 'debit' ? '− ' : ''}
                  {fmtBRL(m.valor)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
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

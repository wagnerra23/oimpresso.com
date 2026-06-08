// FinPresentationMode — Cowork KB-9.75 Financeiro Onda 7b
// (fullscreen pra reunião com sócio).
//
// Refs:
//  - prototipo-ui/financeiro-output.jsx FinPresentationMode
//  - Sells SalePresentationMode (pattern parecido)
//
// Render minimalista pra apresentar dados Fin em reunião:
//   - Background dark gradient + KPIs gigantes
//   - Tab navigation entre views (Resumo / Por contraparte / Por categoria)
//   - Atalho Esc fecha
//   - Sem dependência externa (zero backend)

import { useEffect, useMemo, useState } from 'react';

interface KpiSnapshot {
  saldo_previsto: number;
  recebido: { valor: number; qtd: number };
  a_receber: { valor: number; qtd: number };
  pago: { valor: number; qtd: number };
  a_pagar: { valor: number; qtd: number };
}

interface LancamentoLite {
  id: number;
  contraparte: string;
  categoria?: string;
  valor: number;
  kind?: 'receivable' | 'payable';
  status?: string;
}

type ViewMode = 'overview' | 'parties' | 'categories';

interface FinPresentationModeProps {
  open: boolean;
  onClose: () => void;
  kpis: KpiSnapshot;
  lancamentos: LancamentoLite[];
  periodLabel: string;
  businessName?: string;
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function brlBig(n: number): string {
  if (Math.abs(n) >= 1_000_000) return 'R$ ' + (n / 1_000_000).toFixed(2).replace('.', ',') + 'M';
  if (Math.abs(n) >= 1_000) return 'R$ ' + (n / 1_000).toFixed(1).replace('.', ',') + 'k';
  return brl(n);
}

export function FinPresentationMode({ open, onClose, kpis, lancamentos, periodLabel, businessName }: FinPresentationModeProps) {
  const [view, setView] = useState<ViewMode>('overview');

  // Atalho Esc fecha
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      else if (e.key === '1') setView('overview');
      else if (e.key === '2') setView('parties');
      else if (e.key === '3') setView('categories');
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  const parties = useMemo(() => {
    if (view !== 'parties') return [];
    const m: Record<string, { recebido: number; pago: number; count: number }> = {};
    for (const l of lancamentos) {
      const key = l.contraparte || 'Sem contraparte';
      if (!m[key]) m[key] = { recebido: 0, pago: 0, count: 0 };
      m[key].count++;
      if (l.kind === 'receivable') m[key].recebido += l.valor || 0;
      else m[key].pago += l.valor || 0;
    }
    return Object.entries(m)
      .map(([nome, v]) => ({ nome, ...v, total: v.recebido + v.pago }))
      .sort((a, b) => b.total - a.total)
      .slice(0, 10);
  }, [view, lancamentos]);

  const cats = useMemo(() => {
    if (view !== 'categories') return [];
    const m: Record<string, { total: number; count: number }> = {};
    for (const l of lancamentos) {
      const key = l.categoria || 'Sem categoria';
      if (!m[key]) m[key] = { total: 0, count: 0 };
      m[key].count++;
      m[key].total += l.valor || 0;
    }
    return Object.entries(m)
      .map(([nome, v]) => ({ nome, ...v }))
      .sort((a, b) => b.total - a.total)
      .slice(0, 10);
  }, [view, lancamentos]);

  if (!open) return null;

  const net = kpis.recebido.valor - kpis.pago.valor;

  return (
    <div className="fin-present" role="dialog" aria-label="Modo apresentação">
      <header className="fin-present-h">
        <div>
          <h1>Financeiro · {periodLabel}</h1>
          {businessName && <p>{businessName}</p>}
        </div>
        <nav className="fin-present-nav">
          <button
            type="button"
            className={view === 'overview' ? 'on' : ''}
            onClick={() => setView('overview')}
          >
            <kbd>1</kbd> Resumo
          </button>
          <button
            type="button"
            className={view === 'parties' ? 'on' : ''}
            onClick={() => setView('parties')}
          >
            <kbd>2</kbd> Contrapartes
          </button>
          <button
            type="button"
            className={view === 'categories' ? 'on' : ''}
            onClick={() => setView('categories')}
          >
            <kbd>3</kbd> Categorias
          </button>
        </nav>
        <button type="button" className="fin-present-close" onClick={onClose} title="Fechar (Esc)">×</button>
      </header>

      <main className="fin-present-body">
        {view === 'overview' && (
          <div className="fin-present-overview">
            <div className="fin-present-hero">
              <small>Saldo previsto</small>
              <h2 className={kpis.saldo_previsto >= 0 ? 'pos' : 'neg'}>{brl(kpis.saldo_previsto)}</h2>
              <p>
                Realizado <b className={net >= 0 ? 'pos' : 'neg'}>{brl(net)}</b>
                {' · '}
                Pendente <b>{brl(kpis.a_receber.valor - kpis.a_pagar.valor)}</b>
              </p>
            </div>
            <div className="fin-present-grid">
              <div className="fin-present-card in">
                <small>↑ Recebido</small>
                <b>{brlBig(kpis.recebido.valor)}</b>
                <i>{kpis.recebido.qtd} baixas</i>
              </div>
              <div className="fin-present-card open">
                <small>⏰ A Receber</small>
                <b>{brlBig(kpis.a_receber.valor)}</b>
                <i>{kpis.a_receber.qtd} títulos</i>
              </div>
              <div className="fin-present-card out">
                <small>↓ Pago</small>
                <b>{brlBig(kpis.pago.valor)}</b>
                <i>{kpis.pago.qtd} baixas</i>
              </div>
              <div className="fin-present-card late">
                <small>⚠ A Pagar</small>
                <b>{brlBig(kpis.a_pagar.valor)}</b>
                <i>{kpis.a_pagar.qtd} títulos</i>
              </div>
            </div>
          </div>
        )}

        {view === 'parties' && (
          <div className="fin-present-table">
            <h2>Top 10 contrapartes</h2>
            <table>
              <thead>
                <tr><th>Contraparte</th><th>Recebido</th><th>Pago</th><th>Saldo</th><th>Trans</th></tr>
              </thead>
              <tbody>
                {parties.length === 0 && <tr><td colSpan={5}>Sem dados no período.</td></tr>}
                {parties.map((p) => (
                  <tr key={p.nome}>
                    <td><b>{p.nome}</b></td>
                    <td className="pos">{brl(p.recebido)}</td>
                    <td className="neg">{brl(p.pago)}</td>
                    <td><b>{brl(p.recebido - p.pago)}</b></td>
                    <td>{p.count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {view === 'categories' && (
          <div className="fin-present-table">
            <h2>Top 10 categorias</h2>
            <table>
              <thead>
                <tr><th>Categoria</th><th>Total</th><th>Transações</th></tr>
              </thead>
              <tbody>
                {cats.length === 0 && <tr><td colSpan={3}>Sem dados no período.</td></tr>}
                {cats.map((c) => (
                  <tr key={c.nome}>
                    <td><b>{c.nome}</b></td>
                    <td><b>{brl(c.total)}</b></td>
                    <td>{c.count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </main>

      <footer className="fin-present-f">
        <small>
          <kbd>Esc</kbd> sair · <kbd>1</kbd> resumo · <kbd>2</kbd> contrapartes · <kbd>3</kbd> categorias
        </small>
      </footer>
    </div>
  );
}

export default FinPresentationMode;

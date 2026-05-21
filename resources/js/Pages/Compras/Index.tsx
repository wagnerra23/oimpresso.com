// @memcofre tela=/compras module=Compras
// Wagner 2026-05-21 — Wave 1+2+3+4 F1 pin literal do protótipo compras-page.jsx.
// Cockpit Compras (US-COM-001). Drawer 5 tabs / Importar XML / Nova compra ficam pra Waves 6+.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

import '../../../css/cowork-compras-bundle.css';
import Drawer, { type CompraDetalhe } from './components/Drawer';

type Stage = 'rascunho' | 'pedido' | 'transito' | 'recebido' | 'conferido' | 'pago' | 'cancelada';

interface Kpis {
  aberto: number;
  transito: number;
  mes: number;
  fornec: number;
}

interface Row {
  id: number;
  ref_no: string | null;
  document: string | null;
  transaction_date: string;
  name: string | null; // contact name
  supplier_business_name: string | null;
  status: Stage;
  payment_status: string;
  final_total: number;
  location_name: string;
  amount_paid: number | null;
}

interface RowsPayload {
  data: Row[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface Props {
  filters: {
    q: string;
    stage: string;
  };
  selected_id: number | null;
  kpis: Kpis | null;
  rows: RowsPayload | null;
  compra_detalhe: CompraDetalhe | null;
}

const STAGES: { id: Stage; l: string; ic: string }[] = [
  { id: 'rascunho', l: 'Rascunho', ic: '○' },
  { id: 'pedido', l: 'Pedido', ic: '✎' },
  { id: 'transito', l: 'Em trânsito', ic: '⇨' },
  { id: 'recebido', l: 'Recebido', ic: '⊞' },
  { id: 'conferido', l: 'Conferido', ic: '✓' },
  { id: 'pago', l: 'Pago', ic: '$' },
];

function fmtMoney(v: number | null | undefined): string {
  return 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d: string): string {
  const date = new Date(d);
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function dueAmount(row: Row): number {
  return Math.max(0, Number(row.final_total ?? 0) - Number(row.amount_paid ?? 0));
}

function ComprasIndex({ filters, selected_id, kpis, rows, compra_detalhe }: Props) {
  const [localFilter, setLocalFilter] = useState<string>(filters.stage || 'all');

  const openDrawer = (row: Row) => {
    router.get(
      '/compras',
      { q: filters.q, stage: filters.stage, compra_id: row.id },
      {
        only: ['compra_detalhe', 'selected_id'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
      }
    );
  };

  const closeDrawer = () => {
    router.get(
      '/compras',
      { q: filters.q, stage: filters.stage },
      {
        only: ['compra_detalhe', 'selected_id'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
      }
    );
  };

  const drawerOpen = selected_id != null;

  const filteredRows = useMemo<Row[]>(() => {
    if (!rows) return [];
    const data = rows.data;
    if (localFilter === 'all') return data;
    if (localFilter === 'abertas') return data.filter((p) => dueAmount(p) > 0);
    if (localFilter === 'rascunhos') return data.filter((p) => p.status === 'rascunho');
    if (localFilter === 'transito') return data.filter((p) => p.status === 'transito' || p.status === 'pedido');
    return data;
  }, [rows, localFilter]);

  const totalRows = rows?.meta.total ?? 0;

  return (
    <div className="compras-root" data-screen-label="01 Compras">
      <div className="cmp-main">
        {/* HEAD */}
        <header className="hd">
          <div className="crumbs">
            ERP · Operação · <b style={{ color: 'var(--cmp-ink-2)' }}>Compras</b>
          </div>
          <h1>Compras</h1>
          <span className="count">{filteredRows.length} de {totalRows}</span>
          <div className="sp"></div>
          <div className="search">
            <span>⌕</span>
            <input
              placeholder="Buscar NF-e, fornecedor, ref, chave..."
              defaultValue={filters.q}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const value = (e.target as HTMLInputElement).value;
                  router.visit('/compras', {
                    data: { q: value, stage: localFilter },
                    preserveScroll: true,
                    preserveState: true,
                  });
                }
              }}
            />
            <kbd
              style={{
                fontSize: 9,
                fontFamily: 'var(--cmp-mono)',
                color: 'var(--cmp-ink-3)',
                background: 'var(--cmp-line-2)',
                padding: '1px 5px',
                borderRadius: 3,
              }}
            >
              /
            </kbd>
          </div>
          <button className="btn" disabled title="Disponível na Wave 6 (importar XML DF-e)">
            ↓ Importar XML
          </button>
          <button className="btn primary" disabled title="Disponível na Wave 8 (form de criação)">
            + Nova compra
          </button>
        </header>

        {/* TABS */}
        <nav className="tbs">
          <a
            className={localFilter === 'all' ? 'active' : ''}
            onClick={() => setLocalFilter('all')}
          >
            Todas <span className="ct">{totalRows}</span>
          </a>
          <a
            className={localFilter === 'abertas' ? 'active' : ''}
            onClick={() => setLocalFilter('abertas')}
          >
            A pagar
          </a>
          <a
            className={localFilter === 'rascunhos' ? 'active' : ''}
            onClick={() => setLocalFilter('rascunhos')}
          >
            Rascunhos
          </a>
          <a
            className={localFilter === 'transito' ? 'active' : ''}
            onClick={() => setLocalFilter('transito')}
          >
            Em trânsito
          </a>
          <div className="sp"></div>
          <div className="filters">
            <span style={{ fontSize: 10.5, textTransform: 'uppercase', letterSpacing: '.04em', color: 'var(--cmp-ink-3)' }}>
              filtros
            </span>
            <span className="filter-pill" title="Filtros server-side chegam na Wave 7">Em breve</span>
          </div>
        </nav>

        {/* BODY */}
        <div className={`bd ${drawerOpen ? 'with-drawer' : ''}`}>
          {/* LISTA */}
          <main className="list">
            <Deferred data="kpis" fallback={<KpisSkeleton />}>
              {kpis ? <KpisGrid k={kpis} /> : <KpisSkeleton />}
            </Deferred>

            <Deferred data="rows" fallback={<TableSkeleton />}>
              {rows ? (
                <TableCompras rows={filteredRows} selectedId={selected_id} onSelect={openDrawer} />
              ) : (
                <TableSkeleton />
              )}
            </Deferred>
          </main>

          {/* DRAWER 5 tabs sobre grid */}
          {drawerOpen && (
            <Deferred data="compra_detalhe" fallback={<DrawerSkeleton onClose={closeDrawer} />}>
              {compra_detalhe ? (
                <Drawer compra={compra_detalhe} onClose={closeDrawer} />
              ) : (
                <DrawerSkeleton onClose={closeDrawer} />
              )}
            </Deferred>
          )}
        </div>

        {/* FOOTER */}
        <footer className="ft">
          <b>{filteredRows.length}</b> compras exibidas
          {rows && (
            <>
              {' '}· total <b>{fmtMoney(filteredRows.reduce((s, p) => s + Number(p.final_total ?? 0), 0))}</b>
              {' '}· a pagar <b style={{ color: 'var(--cmp-warn)' }}>
                {fmtMoney(filteredRows.reduce((s, p) => s + dueAmount(p), 0))}
              </b>
            </>
          )}
          <div className="sp"></div>
          <span>
            Atalhos: <kbd>/</kbd> buscar · <kbd>Esc</kbd> fechar drawer
          </span>
        </footer>
      </div>
    </div>
  );
}

function KpisGrid({ k }: { k: Kpis }) {
  return (
    <div className="kpis">
      <div className="kpi warn">
        <small>A pagar</small>
        <b>{k.aberto}</b>
        <div className="ln">compras em aberto (due + partial)</div>
      </div>
      <div className="kpi">
        <small>Em trânsito</small>
        <b>{k.transito}</b>
        <div className="ln">aguardando recebimento</div>
      </div>
      <div className="kpi">
        <small>Volume do mês</small>
        <b>{fmtMoney(k.mes)}</b>
        <div className="ln">soma final_total mês corrente</div>
      </div>
      <div className="kpi ok">
        <small>Fornecedores ativos</small>
        <b>{k.fornec}</b>
        <div className="ln">distinct contact_id</div>
      </div>
    </div>
  );
}

function KpisSkeleton() {
  return (
    <div className="kpis">
      {[1, 2, 3, 4].map((i) => (
        <div key={i} className="kpi" style={{ opacity: 0.5 }}>
          <small>…</small>
          <b>—</b>
          <div className="ln">carregando</div>
        </div>
      ))}
    </div>
  );
}

function TableCompras({
  rows,
  selectedId,
  onSelect,
}: {
  rows: Row[];
  selectedId: number | null;
  onSelect: (r: Row) => void;
}) {
  if (rows.length === 0) {
    return (
      <div className="tbl" style={{ padding: 24, textAlign: 'center', color: 'var(--cmp-ink-3)' }}>
        Nenhuma compra encontrada com o filtro atual.
      </div>
    );
  }

  return (
    <div className="tbl">
      <table className="purchases">
        <thead>
          <tr>
            <th style={{ width: '100px' }}>Compra</th>
            <th>Fornecedor</th>
            <th style={{ width: '95px' }}>Data</th>
            <th style={{ width: '100px' }}>Estágio</th>
            <th style={{ width: '100px', textAlign: 'right' }}>Total</th>
            <th style={{ width: '100px', textAlign: 'right' }}>A pagar</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((p) => {
            const due = dueAmount(p);
            const stageInfo = STAGES.find((x) => x.id === p.status);
            return (
              <tr
                key={p.id}
                className={selectedId === p.id ? 'sel' : ''}
                onClick={() => onSelect(p)}
              >
                <td className="mono">
                  <b>#{p.id}</b>
                  {p.ref_no && <small>{p.ref_no}</small>}
                </td>
                <td>
                  <b>{p.supplier_business_name || p.name || '—'}</b>
                  <small>{p.location_name}</small>
                </td>
                <td className="mono">{fmtDate(p.transaction_date)}</td>
                <td>
                  {stageInfo ? (
                    <span className={`pill ${p.status}`}>{stageInfo.l}</span>
                  ) : (
                    <span className="pill">{p.status}</span>
                  )}
                </td>
                <td className="num">
                  <b>{fmtMoney(p.final_total)}</b>
                </td>
                <td
                  className="num"
                  style={{
                    color: due > 0 ? 'var(--cmp-warn)' : 'var(--cmp-ok)',
                    fontWeight: 600,
                  }}
                >
                  {due > 0 ? fmtMoney(due) : '✓'}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function TableSkeleton() {
  return (
    <div className="tbl" style={{ padding: 24, textAlign: 'center', color: 'var(--cmp-ink-3)' }}>
      Carregando compras…
    </div>
  );
}

function DrawerSkeleton({ onClose }: { onClose: () => void }) {
  return (
    <aside className="drawer">
      <div className="drw-head">
        <div>
          <h2 style={{ color: 'var(--cmp-ink-3)' }}>Carregando…</h2>
          <span className="mono">aguarde</span>
        </div>
        <button className="x" onClick={onClose} aria-label="Fechar">
          ✕
        </button>
      </div>
      <div className="drw-body">
        <div className="sec">
          <div
            className="card"
            style={{ textAlign: 'center', color: 'var(--cmp-ink-3)', padding: 24 }}
          >
            Buscando detalhe da compra…
          </div>
        </div>
      </div>
    </aside>
  );
}

ComprasIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Compras" breadcrumbItems={[{ label: 'Compras' }]}>
    {page}
  </AppShellV2>
);

export default ComprasIndex;

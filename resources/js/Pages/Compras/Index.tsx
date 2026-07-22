// @memcofre tela=/compras module=Compras
// Wagner 2026-05-21 — Wave 1+2+3+4 F1 pin literal do protótipo compras-page.jsx.
// Cockpit Compras (US-COM-001). Drawer 5 tabs / Importar XML / Nova compra ficam pra Waves 6+.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

import '../../../css/cowork-compras-bundle.css';
import AcoesDropdown from './components/AcoesDropdown';
import Drawer, { type CompraDetalhe } from './components/Drawer';
import VisibilidadeColunas, { type ColumnDef, useColumnVisibility } from './components/VisibilidadeColunas';

type Stage = 'rascunho' | 'pedido' | 'transito' | 'recebido' | 'conferido' | 'pago' | 'cancelada';

// Status CORE do UltimatePOS (transactions.status) — o que o backend REALMENTE manda (inglês).
// Fonte canônica: memory/dominio/compras.md → ordered=pedido · pending=aguardando · received=recebida.
type CoreStatus = 'draft' | 'ordered' | 'pending' | 'received' | 'cancelled';

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
  status: CoreStatus | string; // core cru do backend — normalizado p/ exibição via stageOf()
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

interface Summary {
  total: number;
  pago: number;
  a_pagar: number;
  reembolso: number;
}

/**
 * Permissions C1 — ADR compras-purchase-convergencia-c1 (2026-05-25).
 *
 * Backend `ComprasController::index()` resolve via `purchase.create/update/delete`
 * (não `compras.*` — alias V2 se Wagner mantiver módulo separado). User com
 * `compras.view` mas SEM `purchase.create` vê cockpit sem botão "+ Nova compra".
 */
interface PermissionsC1 {
  create: boolean;
  update: boolean;
  delete: boolean;
}

interface Props {
  filters: {
    q: string;
    stage: string;
    sort: string;
    dir: 'asc' | 'desc';
    per_page: number;
  };
  selected_id: number | null;
  permissions: PermissionsC1;
  kpis: Kpis | null;
  rows: RowsPayload | null;
  summary: Summary | null;
  compra_detalhe: CompraDetalhe | null;
}

const COLUMNS: ColumnDef[] = [
  { id: 'acao', label: 'Ação', required: true },
  { id: 'compra', label: 'Compra (ref)', required: true },
  { id: 'fornecedor', label: 'Fornecedor' },
  { id: 'data', label: 'Data' },
  { id: 'estagio', label: 'Estágio' },
  { id: 'total', label: 'Total' },
  { id: 'a_pagar', label: 'A pagar' },
];

const DEFAULT_COL_VISIBILITY: Record<string, boolean> = {
  acao: true,
  compra: true,
  fornecedor: true,
  data: true,
  estagio: true,
  total: true,
  a_pagar: true,
};

const STAGES: { id: Stage; l: string; ic: string }[] = [
  { id: 'rascunho', l: 'Rascunho', ic: '○' },
  { id: 'pedido', l: 'Pedido', ic: '✎' },
  { id: 'transito', l: 'Aguardando', ic: '⇨' },
  { id: 'recebido', l: 'Recebida', ic: '⊞' },
  { id: 'conferido', l: 'Conferido', ic: '✓' },
  { id: 'pago', l: 'Pago', ic: '$' },
];

// Normaliza o status CORE (transactions.status, inglês) → id PT do FSM acima.
// Sem isso o pill caía no fallback e mostrava "RECEIVED" cru, e os filtros de aba
// (que comparavam em PT) nunca batiam com o dado (inglês). memory/dominio/compras.md.
const CORE_TO_STAGE: Record<string, Stage> = {
  draft: 'rascunho',
  ordered: 'pedido',
  pending: 'transito',
  received: 'recebido',
  cancelled: 'cancelada',
  // aliases defensivos: se o backend já mandar o id PT, passa reto
  rascunho: 'rascunho', pedido: 'pedido', transito: 'transito',
  recebido: 'recebido', conferido: 'conferido', pago: 'pago', cancelada: 'cancelada',
};
function stageOf(coreStatus: string): Stage {
  return CORE_TO_STAGE[coreStatus] ?? 'rascunho';
}

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

function ComprasIndex({ filters, selected_id, permissions, kpis, rows, summary, compra_detalhe }: Props) {
  const [localFilter, setLocalFilter] = useState<string>(filters.stage || 'all');
  const [drawerInitialTab, setDrawerInitialTab] = useState<'resumo' | 'pagamentos'>('resumo');
  const [colVisibility, setColVisibility] = useColumnVisibility('compras-cols-v1', DEFAULT_COL_VISIBILITY);

  const navigateWithFilters = (overrides: Record<string, string | number | undefined>) => {
    const next = {
      q: filters.q,
      stage: filters.stage,
      sort: filters.sort,
      dir: filters.dir,
      per_page: filters.per_page,
      ...overrides,
    };
    // Limpa keys undefined pra não poluir URL
    const cleaned: Record<string, string | number> = {};
    for (const [k, v] of Object.entries(next)) {
      if (v !== undefined && v !== '' && v !== null) cleaned[k] = v;
    }
    // D-14: partial reload — só re-busca o que muda com filtro/sort/página.
    // kpis são agregados por business (Inertia::defer, sem filtro) — não re-buscam.
    router.get('/compras', cleaned, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['rows', 'summary', 'filters'],
    });
  };

  const handleSort = (col: string) => {
    const isCurrent = filters.sort === col;
    const nextDir = isCurrent && filters.dir === 'asc' ? 'desc' : 'asc';
    navigateWithFilters({ sort: col, dir: nextDir });
  };

  const handlePerPage = (value: number) => {
    navigateWithFilters({ per_page: value });
  };

  const openExportBlade = (_format: 'csv' | 'excel' | 'pdf' | 'print') => {
    // Bridge mode — Blade legacy DataTables tem exports nativos via buttons plugin.
    // PR seguinte substitui por endpoints Compras nativos respeitando filtros.
    // _format ignorado: Blade legacy mostra todos os 4 botões na mesma tela.
    window.open('/purchases', '_blank', 'noopener,noreferrer');
  };

  const openDrawer = (compraId: number, tab: 'resumo' | 'pagamentos' = 'resumo') => {
    setDrawerInitialTab(tab);
    router.get(
      '/compras',
      { q: filters.q, stage: filters.stage, compra_id: compraId },
      {
        only: ['compra_detalhe', 'selected_id'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
      }
    );
  };

  const openDrawerFromRow = (row: Row) => openDrawer(row.id, 'resumo');

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
    if (localFilter === 'rascunhos') return data.filter((p) => stageOf(p.status) === 'rascunho');
    if (localFilter === 'transito') return data.filter((p) => stageOf(p.status) === 'transito' || stageOf(p.status) === 'pedido');
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
                  // D-14: partial reload — busca só re-busca o que muda com filtro.
                  router.visit('/compras', {
                    data: { q: value, stage: localFilter },
                    preserveScroll: true,
                    preserveState: true,
                    only: ['rows', 'summary', 'filters'],
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
          {permissions.create && (
            <button
              className="btn primary"
              type="button"
              title="Nova compra (delega /purchases/create · ADR compras-purchase-convergencia-c1)"
              onClick={() => router.visit('/purchases/create')}
            >
              + Nova compra
            </button>
          )}
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
          <div className="list">
            <Deferred data="kpis" fallback={<KpisSkeleton />}>
              {kpis ? <KpisGrid k={kpis} /> : <KpisSkeleton />}
            </Deferred>

            <Toolbar
              perPage={filters.per_page}
              total={rows?.meta.total ?? 0}
              colVisibility={colVisibility}
              setColVisibility={setColVisibility}
              onPerPageChange={handlePerPage}
              onExport={openExportBlade}
            />

            <Deferred data="rows" fallback={<TableSkeleton />}>
              {rows ? (
                <TableCompras
                  rows={filteredRows}
                  selectedId={selected_id}
                  onSelect={openDrawerFromRow}
                  onOpenDrawer={openDrawer}
                  sortCol={filters.sort}
                  sortDir={filters.dir}
                  onSort={handleSort}
                  colVisibility={colVisibility}
                />
              ) : (
                <TableSkeleton />
              )}
            </Deferred>

            <Deferred data="summary" fallback={null}>
              {summary && rows ? (
                <SummaryFooter summary={summary} meta={rows.meta} onPageChange={(p) => navigateWithFilters({ page: p })} />
              ) : null}
            </Deferred>
          </div>

          {/* DRAWER 5 tabs sobre grid */}
          {drawerOpen && (
            <Deferred data="compra_detalhe" fallback={<DrawerSkeleton onClose={closeDrawer} />}>
              {compra_detalhe ? (
                <Drawer compra={compra_detalhe} onClose={closeDrawer} initialTab={drawerInitialTab} />
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
        <div className="ln">compras com saldo em aberto</div>
      </div>
      <div className="kpi">
        <small>Em trânsito</small>
        <b>{k.transito}</b>
        <div className="ln">aguardando recebimento</div>
      </div>
      <div className="kpi">
        <small>Volume do mês</small>
        <b>{fmtMoney(k.mes)}</b>
        <div className="ln">total comprado no mês</div>
      </div>
      <div className="kpi ok">
        <small>Fornecedores ativos</small>
        <b>{k.fornec}</b>
        <div className="ln">fornecedores com compra no período</div>
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

function SortHeader({
  col,
  label,
  sortCol,
  sortDir,
  onSort,
  align = 'left',
  style,
}: {
  col: string;
  label: string;
  sortCol: string;
  sortDir: 'asc' | 'desc';
  onSort: (col: string) => void;
  align?: 'left' | 'right';
  style?: React.CSSProperties;
}) {
  const active = sortCol === col;
  const arrow = active ? (sortDir === 'asc' ? '↑' : '↓') : '⇅';
  return (
    <th
      style={{ ...style, cursor: 'pointer', textAlign: align, userSelect: 'none' }}
      onClick={() => onSort(col)}
      title={`Ordenar por ${label}`}
    >
      {label}{' '}
      <span style={{ opacity: active ? 1 : 0.4, fontSize: 11, marginLeft: 2 }}>{arrow}</span>
    </th>
  );
}

function TableCompras({
  rows,
  selectedId,
  onSelect,
  onOpenDrawer,
  sortCol,
  sortDir,
  onSort,
  colVisibility,
}: {
  rows: Row[];
  selectedId: number | null;
  onSelect: (r: Row) => void;
  onOpenDrawer: (compraId: number, tab?: 'resumo' | 'pagamentos') => void;
  sortCol: string;
  sortDir: 'asc' | 'desc';
  onSort: (col: string) => void;
  colVisibility: Record<string, boolean>;
}) {
  if (rows.length === 0) {
    return (
      <div className="tbl" style={{ padding: 24, textAlign: 'center', color: 'var(--cmp-ink-3)' }}>
        Nenhuma compra encontrada com o filtro atual.
      </div>
    );
  }

  const v = colVisibility;

  return (
    <div className="tbl">
      <table className="purchases">
        <thead>
          <tr>
            {v.acao && <th style={{ width: '90px' }}>Ação</th>}
            {v.compra && (
              <SortHeader
                col="ref_no"
                label="Compra"
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={onSort}
                style={{ width: '100px' }}
              />
            )}
            {v.fornecedor && (
              <SortHeader
                col="contact_name"
                label="Fornecedor"
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={onSort}
              />
            )}
            {v.data && (
              <SortHeader
                col="transaction_date"
                label="Data"
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={onSort}
                style={{ width: '95px' }}
              />
            )}
            {v.estagio && (
              <SortHeader
                col="status"
                label="Estágio"
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={onSort}
                style={{ width: '100px' }}
              />
            )}
            {v.total && (
              <SortHeader
                col="final_total"
                label="Total"
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={onSort}
                align="right"
                style={{ width: '100px', textAlign: 'right' }}
              />
            )}
            {v.a_pagar && (
              <SortHeader
                col="payment_status"
                label="A pagar"
                sortCol={sortCol}
                sortDir={sortDir}
                onSort={onSort}
                align="right"
                style={{ width: '100px', textAlign: 'right' }}
              />
            )}
          </tr>
        </thead>
        <tbody>
          {rows.map((p) => {
            const due = dueAmount(p);
            const stageId = stageOf(p.status);
            const stageInfo = STAGES.find((x) => x.id === stageId);
            return (
              <tr
                key={p.id}
                className={selectedId === p.id ? 'sel' : ''}
                onClick={(e) => {
                  // Click no body da linha abre drawer — botões internos chamam stopPropagation
                  if ((e.target as HTMLElement).closest('button')) return;
                  onSelect(p);
                }}
              >
                {v.acao && (
                  <td onClick={(e) => e.stopPropagation()} style={{ cursor: 'default' }}>
                    <AcoesDropdown
                      compraId={p.id}
                      status={stageId}
                      paymentStatus={p.payment_status}
                      onOpenDrawer={onOpenDrawer}
                    />
                  </td>
                )}
                {v.compra && (
                  <td className="mono">
                    <b>#{p.id}</b>
                    {p.ref_no && <small>{p.ref_no}</small>}
                  </td>
                )}
                {v.fornecedor && (
                  <td>
                    <b>{p.supplier_business_name || p.name || '—'}</b>
                    <small>{p.location_name}</small>
                  </td>
                )}
                {v.data && <td className="mono">{fmtDate(p.transaction_date)}</td>}
                {v.estagio && (
                  <td>
                    {stageInfo ? (
                      <span className={`pill ${stageId}`}>{stageInfo.l}</span>
                    ) : (
                      <span className={`pill ${stageId}`}>{stageId}</span>
                    )}
                  </td>
                )}
                {v.total && (
                  <td className="num">
                    <b>{fmtMoney(p.final_total)}</b>
                  </td>
                )}
                {v.a_pagar && (
                  <td
                    className="num"
                    style={{
                      color: due > 0 ? 'var(--cmp-warn)' : 'var(--cmp-ok)',
                      fontWeight: 600,
                    }}
                  >
                    {due > 0 ? fmtMoney(due) : '✓'}
                  </td>
                )}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function Toolbar({
  perPage,
  total,
  colVisibility,
  setColVisibility,
  onPerPageChange,
  onExport,
}: {
  perPage: number;
  total: number;
  colVisibility: Record<string, boolean>;
  setColVisibility: (next: Record<string, boolean>) => void;
  onPerPageChange: (n: number) => void;
  onExport: (format: 'csv' | 'excel' | 'pdf' | 'print') => void;
}) {
  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        marginBottom: 10,
        padding: '8px 12px',
        background: '#fff',
        border: '1px solid var(--cmp-line)',
        borderRadius: 5,
        flexWrap: 'wrap',
      }}
    >
      <label style={{ fontSize: 11.5, color: 'var(--cmp-ink-2)', display: 'flex', alignItems: 'center', gap: 6 }}>
        Mostrar
        <select
          value={perPage}
          onChange={(e) => onPerPageChange(Number(e.target.value))}
          style={{
            padding: '3px 6px',
            border: '1px solid var(--cmp-line)',
            borderRadius: 4,
            background: '#fbf9f3',
            fontSize: 11.5,
          }}
        >
          {[10, 25, 50, 100].map((n) => (
            <option key={n} value={n}>
              {n}
            </option>
          ))}
        </select>
        entradas
      </label>

      <span style={{ color: 'var(--cmp-ink-3)', fontSize: 11.5, fontFamily: 'var(--cmp-mono)' }}>
        · {total} total
      </span>

      <div style={{ flex: 1 }}></div>

      <button className="btn sm" onClick={() => onExport('csv')} title="Exportar CSV (Blade legacy nova aba)">
        ↓ CSV
      </button>
      <button className="btn sm" onClick={() => onExport('excel')} title="Exportar Excel (Blade legacy nova aba)">
        ↓ Excel
      </button>
      <button className="btn sm" onClick={() => onExport('print')} title="Imprimir (Blade legacy nova aba)">
        ⎙ Imprimir
      </button>
      <VisibilidadeColunas columns={COLUMNS} value={colVisibility} onChange={setColVisibility} />
      <button className="btn sm" onClick={() => onExport('pdf')} title="Exportar PDF (Blade legacy nova aba)">
        ↓ PDF
      </button>
    </div>
  );
}

function SummaryFooter({
  summary,
  meta,
  onPageChange,
}: {
  summary: Summary;
  meta: RowsPayload['meta'];
  onPageChange: (page: number) => void;
}) {
  const start = (meta.current_page - 1) * meta.per_page + 1;
  const end = Math.min(meta.current_page * meta.per_page, meta.total);

  return (
    <div
      style={{
        marginTop: 10,
        padding: '10px 12px',
        background: '#fff',
        border: '1px solid var(--cmp-line)',
        borderRadius: 5,
        display: 'flex',
        alignItems: 'center',
        gap: 16,
        flexWrap: 'wrap',
      }}
    >
      <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap' }}>
        <SummaryCell label="Total" value={fmtMoney(summary.total)} />
        <SummaryCell label="Pago" value={fmtMoney(summary.pago)} accent="ok" />
        <SummaryCell label="A pagar" value={fmtMoney(summary.a_pagar)} accent="warn" />
        <SummaryCell label="Reembolsado" value={fmtMoney(summary.reembolso)} accent="err" />
      </div>

      <div style={{ flex: 1 }}></div>

      <div style={{ fontSize: 11.5, color: 'var(--cmp-ink-3)' }}>
        Mostrando <b>{start}</b>–<b>{end}</b> de <b>{meta.total}</b>
      </div>

      <div style={{ display: 'flex', gap: 4 }}>
        <button
          className="btn sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          ← Anterior
        </button>
        <span
          style={{
            padding: '3px 10px',
            background: 'var(--cmp-accent-soft)',
            border: '1px solid #c9d6e8',
            borderRadius: 4,
            fontSize: 11.5,
            color: 'var(--cmp-accent)',
            fontWeight: 600,
            fontFamily: 'var(--cmp-mono)',
          }}
        >
          {meta.current_page} / {meta.last_page}
        </span>
        <button
          className="btn sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Próximo →
        </button>
      </div>
    </div>
  );
}

function SummaryCell({
  label,
  value,
  accent,
}: {
  label: string;
  value: string;
  accent?: 'ok' | 'warn' | 'err';
}) {
  const color =
    accent === 'ok'
      ? 'var(--cmp-ok)'
      : accent === 'warn'
        ? 'var(--cmp-warn)'
        : accent === 'err'
          ? 'var(--cmp-err)'
          : 'var(--cmp-ink)';
  return (
    <div>
      <div
        style={{
          fontSize: 9.5,
          textTransform: 'uppercase',
          letterSpacing: '.05em',
          color: 'var(--cmp-ink-3)',
          fontWeight: 600,
          marginBottom: 2,
        }}
      >
        {label}
      </div>
      <div
        style={{
          fontSize: 14,
          fontWeight: 700,
          fontFamily: 'var(--cmp-mono)',
          fontVariantNumeric: 'tabular-nums',
          color,
        }}
      >
        {value}
      </div>
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

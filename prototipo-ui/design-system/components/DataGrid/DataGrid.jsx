/**
 * DataGrid — a ÚNICA grade do DS (ver NOTAS_INTERNAS.md · fusão 2026-08).
 * Absorveu DataTable (seleção/ordenação controladas) e DataTablePro (header
 * fixo + resize de colunas). A paginação embutida reusa o componente Pagination.
 *
 * columns: [{ key, label, align?:'right', mono?, width?, sortable?, resizable?, sortValue?(row) }]
 * rows:    [{ id, state?:'urgent'|'archived'|'selected', cells:{ [key]: node | {primary,sub} } }]
 * pagination (default true) · resizable · density 'compact'|'comfortable'
 * Seleção e ordenação: internas por padrão; passe selectedIds/onToggleRow ou
 * sortKey/onSort para controlar por fora (API antiga do DataTable).
 */
function GridCell({ value, mono }) {
  if (value && typeof value === 'object' && !React.isValidElement(value) && ('primary' in value || 'sub' in value)) {
    return (
      <React.Fragment>
        <b style={{ display: 'block', fontWeight: 600, fontSize: 12.5, letterSpacing: '-0.006em', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{value.primary}</b>
        {value.sub && <small style={{ display: 'block', fontSize: 11, color: 'var(--text-mute)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{value.sub}</small>}
      </React.Fragment>
    );
  }
  if (mono) return <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12, letterSpacing: '-0.01em' }}>{value}</span>;
  return value;
}

function gridPages(page, count) {
  if (count <= 7) return Array.from({ length: count }, (_, i) => i + 1);
  const out = [1];
  const lo = Math.max(2, page - 1), hi = Math.min(count - 1, page + 1);
  if (lo > 2) out.push('…');
  for (let i = lo; i <= hi; i++) out.push(i);
  if (hi < count - 1) out.push('…');
  out.push(count);
  return out;
}

export function DataGrid({
  columns = [], rows = [], pagination = true,
  pageSize: pageSizeProp = 10, pageSizeOptions = [10, 25, 50, 100], onPageSizeChange,
  page: pageProp, defaultPage = 1, onPageChange, density = 'compact', selectable = false, zebra = true,
  resizable = false, onRowClick, onSelectionChange, defaultSort, maxHeight = 420, height,
  emptyLabel = 'Nenhum registro encontrado', totalLabel = 'registros',
  selectedIds, onToggleRow, onToggleAll, sortKey: sortKeyProp, sortDir: sortDirProp, onSort,
}) {
  const [pageState, setPageState] = React.useState(defaultPage);
  const [sizeState, setSizeState] = React.useState(pageSizeProp);
  const [sortKeyState, setSortKeyState] = React.useState(defaultSort ? defaultSort.key : null);
  const [sortDirState, setSortDirState] = React.useState(defaultSort && defaultSort.dir === 'desc' ? 'desc' : 'asc');
  const [selState, setSelState] = React.useState(() => new Set());
  const [w, setW] = React.useState({});
  const drag = React.useRef(null);
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};

  const controlledSel = Array.isArray(selectedIds);
  const controlledSort = onSort != null;
  const sel = controlledSel ? new Set(selectedIds.map(String)) : selState;
  const sortKey = controlledSort ? sortKeyProp : sortKeyState;
  const sortDir = controlledSort ? (sortDirProp || 'asc') : sortDirState;

  const pageSize = onPageSizeChange ? pageSizeProp : sizeState;
  const page = pageProp != null ? pageProp : pageState;
  const setPage = (p) => { if (pageProp == null) setPageState(p); if (onPageChange) onPageChange(p); };
  const setSize = (n) => { if (onPageSizeChange) onPageSizeChange(n); else setSizeState(n); setPage(1); };

  const sortVal = (c, row) => {
    if (c.sortValue) return c.sortValue(row);
    const v = row.cells ? row.cells[c.key] : row[c.key];
    if (v && typeof v === 'object' && 'primary' in v) return String(v.primary).toLowerCase();
    if (typeof v === 'number') return v;
    return String(v == null ? '' : v).toLowerCase();
  };
  let sorted = rows;
  if (sortKey && !controlledSort) {
    const c = columns.find((x) => x.key === sortKey);
    if (c) { const dir = sortDir === 'desc' ? -1 : 1; sorted = rows.slice().sort((a, b) => { const x = sortVal(c, a), y = sortVal(c, b); return (x < y ? -1 : x > y ? 1 : 0) * dir; }); }
  }
  const total = sorted.length;
  const pageCount = pagination ? Math.max(1, Math.ceil(total / pageSize)) : 1;
  const current = Math.min(page, pageCount);
  const view = pagination ? sorted.slice((current - 1) * pageSize, current * pageSize) : sorted;

  const doSort = (c) => {
    if (!c.sortable) return;
    if (controlledSort) { onSort(c.key); return; }
    if (sortKey === c.key) setSortDirState((d) => (d === 'asc' ? 'desc' : 'asc'));
    else { setSortKeyState(c.key); setSortDirState('asc'); }
    setPage(1);
  };

  const allChecked = selectable && view.length > 0 && view.every((r) => sel.has(String(r.id)));
  const someChecked = selectable && view.some((r) => sel.has(String(r.id)));
  const headChk = (el) => { if (el) el.indeterminate = someChecked && !allChecked; };
  const emit = (next) => { setSelState(next); if (onSelectionChange) onSelectionChange([...next]); };
  const toggleAll = (on) => {
    if (controlledSel || onToggleAll) { if (onToggleAll) onToggleAll(on); return; }
    const n = new Set(sel); view.forEach((r) => (on ? n.add(String(r.id)) : n.delete(String(r.id)))); emit(n);
  };
  const toggleRow = (row) => {
    if (controlledSel || onToggleRow) { if (onToggleRow) onToggleRow(row.id, row); return; }
    const n = new Set(sel); const k = String(row.id); if (n.has(k)) n.delete(k); else n.add(k); emit(n);
  };

  const startResize = (key, e) => {
    e.preventDefault(); e.stopPropagation();
    const th = e.currentTarget.parentNode;
    drag.current = { key, startX: e.clientX, startW: th.getBoundingClientRect().width };
    const move = (ev) => { const d = ev.clientX - drag.current.startX; setW((m) => ({ ...m, [drag.current.key]: Math.max(48, Math.round(drag.current.startW + d)) })); };
    const up = () => { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); drag.current = null; };
    document.addEventListener('mousemove', move); document.addEventListener('mouseup', up);
  };

  const relaxed = density === 'relaxed' || density === 'comfortable';
  const pad = relaxed ? '9px 10px' : '5px 10px';
  const th = { position: 'sticky', top: 0, zIndex: 1, background: 'var(--bg-2)', padding: '7px 10px', font: '600 10px/1.2 var(--font-sans)', textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--text-mute)', whiteSpace: 'nowrap', borderBottom: '1px solid var(--border)', userSelect: 'none' };
  const chk = { accentColor: 'var(--accent)', width: 13, height: 13, cursor: 'pointer' };
  const colW = (c) => (w[c.key] != null ? w[c.key] + 'px' : c.width);

  const numBtn = (p, on) => (
    <button key={p} type="button" onClick={() => setPage(p)} aria-current={on ? 'page' : undefined}
      style={{ minWidth: 24, height: 24, padding: '0 6px', border: '1px solid ' + (on ? 'transparent' : 'var(--border)'), borderRadius: 5, background: on ? 'var(--accent)' : 'transparent', color: on ? 'var(--accent-fg)' : 'var(--text-dim)', font: (on ? '600' : '500') + ' 11.5px/1 var(--font-sans)', fontVariantNumeric: 'tabular-nums', cursor: 'pointer' }}>{p}</button>
  );
  const edge = (dir, disabled) => (
    <button type="button" disabled={disabled} onClick={() => setPage(current + (dir === 'left' ? -1 : 1))} aria-label={dir === 'left' ? 'Página anterior' : 'Próxima página'}
      style={{ width: 24, height: 24, display: 'inline-grid', placeItems: 'center', border: '1px solid var(--border)', borderRadius: 5, background: 'transparent', color: disabled ? 'var(--text-mute)' : 'var(--text)', cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.45 : 1 }}>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><polyline points={dir === 'left' ? '15 18 9 12 15 6' : '9 18 15 12 9 6'} /></svg>
    </button>
  );

  const footer = !pagination ? null : (
    <div style={{ flex: 'none', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 12, flexWrap: 'wrap', padding: '7px 10px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)' }}>
      {NS.Pagination
        ? <NS.Pagination compact page={current} pageCount={pageCount} onChange={setPage}
            total={total} pageSize={pageSize} totalLabel={totalLabel}
            onPageSize={pageSizeOptions && pageSizeOptions.length > 1 ? setSize : undefined} pageSizeOptions={pageSizeOptions} />
        : (
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', justifyContent: 'space-between' }}>
            <span style={{ font: '400 11.5px/1 var(--font-sans)', color: 'var(--text-mute)', fontVariantNumeric: 'tabular-nums' }}>
              <b style={{ color: 'var(--text-dim)', fontWeight: 600 }}>{total === 0 ? 0 : (current - 1) * pageSize + 1}–{Math.min(current * pageSize, total)}</b> de {total} {totalLabel}
            </span>
            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
              {edge('left', current <= 1)}
              {gridPages(current, pageCount).map((p, i) => (p === '…'
                ? <span key={'e' + i} style={{ padding: '0 2px', color: 'var(--text-mute)', font: '400 11.5px/1 var(--font-sans)' }}>…</span>
                : numBtn(p, p === current)))}
              {edge('right', current >= pageCount)}
            </div>
          </div>
        )}
    </div>
  );

  return (
    <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0, background: 'var(--surface)' }}>
      <div style={{ maxHeight: height != null ? height : maxHeight, overflow: 'auto', scrollbarGutter: 'stable' }}>
        <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0, fontSize: 12.5, color: 'var(--text)', fontFamily: 'var(--font-sans)', tableLayout: resizable ? 'fixed' : 'auto' }}>
          <thead>
            <tr>
              {selectable && (
                <th style={{ ...th, width: 34, textAlign: 'center' }}>
                  <input type="checkbox" ref={headChk} checked={!!allChecked} onChange={(e) => toggleAll(e.target.checked)} aria-label="Selecionar página" style={chk} />
                </th>
              )}
              {columns.map((c, ci) => (
                <th key={c.key} style={{ ...th, position: 'sticky', textAlign: c.align === 'right' ? 'right' : 'left', width: colW(c), cursor: c.sortable ? 'pointer' : 'default' }}
                  aria-sort={sortKey === c.key ? (sortDir === 'desc' ? 'descending' : 'ascending') : undefined}>
                  <span onClick={() => doSort(c)} style={{ display: 'inline-flex', alignItems: 'center', gap: 3, color: sortKey === c.key ? 'var(--text)' : 'inherit' }}>
                    {c.label}
                    {c.sortable && <span aria-hidden style={{ opacity: sortKey === c.key ? 1 : 0.4 }}>{sortKey === c.key ? (sortDir === 'desc' ? '↓' : '↑') : '↕'}</span>}
                  </span>
                  {resizable && c.resizable !== false && ci < columns.length - 1 && (
                    <span onMouseDown={(e) => startResize(c.key, e)} style={{ position: 'absolute', top: 0, right: 0, width: 7, height: '100%', cursor: 'col-resize', zIndex: 2, transform: 'translateX(3px)' }} />
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {view.length === 0 && (
              <tr><td colSpan={columns.length + (selectable ? 1 : 0)} style={{ padding: '34px 12px', textAlign: 'center', color: 'var(--text-mute)', font: '400 12.5px/1.4 var(--font-sans)' }}>{emptyLabel}</td></tr>
            )}
            {view.map((row, i) => {
              const isSel = sel.has(String(row.id)) || row.state === 'selected';
              const base = isSel ? 'var(--accent-soft)' : zebra && i % 2 ? 'color-mix(in oklch, var(--bg-2) 55%, transparent)' : 'transparent';
              return (
                <tr key={row.id}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  tabIndex={onRowClick ? 0 : undefined}
                  role={onRowClick ? 'button' : undefined}
                  onKeyDown={onRowClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onRowClick(row); } } : undefined}
                  onMouseEnter={(e) => { if (!isSel) for (const td of e.currentTarget.children) td.style.background = 'var(--bg-2)'; }}
                  onMouseLeave={(e) => { if (!isSel) for (const td of e.currentTarget.children) td.style.background = base; }}
                  style={{ cursor: onRowClick ? 'pointer' : 'default', boxShadow: row.state === 'urgent' ? 'inset 2px 0 0 var(--neg)' : 'none' }}>
                  {selectable && (
                    <td onClick={(e) => e.stopPropagation()} style={{ padding: pad, textAlign: 'center', background: base, borderBottom: '1px solid var(--border-2)', width: 34 }}>
                      <input type="checkbox" checked={sel.has(String(row.id))} onChange={() => toggleRow(row)} aria-label={'Selecionar ' + row.id} style={chk} />
                    </td>
                  )}
                  {columns.map((c) => (
                    <td key={c.key} style={{ padding: pad, background: base, borderBottom: '1px solid var(--border-2)', textAlign: c.align === 'right' ? 'right' : 'left', fontVariantNumeric: c.align === 'right' ? 'tabular-nums' : 'normal', opacity: row.state === 'archived' ? 0.55 : 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 320 }}>
                      <GridCell value={row.cells ? row.cells[c.key] : row[c.key]} mono={c.mono} />
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {footer}
    </div>
  );
}

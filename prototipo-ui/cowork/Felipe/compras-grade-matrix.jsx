// compras-grade-matrix.jsx — Grade Matrix (tam × cor) para entrada de compra vestuário.
// Portado de prototipo-ui/prototipos/compras-grade-matrix/page.jsx @main (lido neste turno).
// Persona: Larissa @ ROTA LIVRE (1280px, balcão, densidade + atalhos).
// Backend vivo: Purchase/_components/GradeMatrixInput.tsx + app/Services/Purchase/GradeLayoutBuilder.php.
// Mudanças vs original: paleta bespoke → tokens .cockpit; prompt() → preenchimento inline;
// payload de debug → tabela de linhas adicionadas. Ergonomia (atalhos, Σ, foco) preservada.
(() => {
const { useState, useMemo, useRef, useEffect, useCallback } = React;
const fmt = n => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const MODELS = [
  { id: "MOD-101", sku: "CAM-001", name: "Camiseta básica algodão", template: "Tam roupa adulto", category: "Camisetas",
    sizes: [{ id: "v-p", l: "P" }, { id: "v-m", l: "M" }, { id: "v-g", l: "G" }, { id: "v-gg", l: "GG" }],
    colors: [{ id: "c-pre", l: "Preto", hex: "#1a1917" }, { id: "c-bra", l: "Branco", hex: "#fafaf7" }, { id: "c-azu", l: "Azul mar.", hex: "#1f3a5f" }],
    unitCost: 22.50 },
  { id: "MOD-102", sku: "CAL-007", name: "Calça jeans skinny", template: "Tam numerado 36-46", category: "Calças",
    sizes: [{ id: "v-36", l: "36" }, { id: "v-38", l: "38" }, { id: "v-40", l: "40" }, { id: "v-42", l: "42" }, { id: "v-44", l: "44" }],
    colors: [{ id: "c-jea", l: "Jeans", hex: "#3b5a7a" }, { id: "c-pre", l: "Preto", hex: "#1a1917" }],
    unitCost: 58.00 },
  { id: "MOD-103", sku: "VES-022", name: "Vestido midi viscose", template: "Tam roupa adulto", category: "Vestidos",
    sizes: [{ id: "v-p", l: "P" }, { id: "v-m", l: "M" }, { id: "v-g", l: "G" }, { id: "v-gg", l: "GG" }],
    colors: [{ id: "c-fl1", l: "Floral azul", hex: "#5a7ab0" }, { id: "c-fl2", l: "Floral rosa", hex: "#c97da8" }, { id: "c-pre", l: "Preto", hex: "#1a1917" }, { id: "c-ver", l: "Verde", hex: "#3e6b50" }],
    unitCost: 41.00 },
];
const SINGLE_MODEL = { id: "MOD-999", sku: "ETQ-001", name: "Etiqueta adesiva preço", category: "Insumos", unitCost: 0.08 };

function ComprasGradeMatrixPage() {
  const [selected, setSelected] = useState(MODELS[0]);
  const [qty, setQty] = useState({});
  const [unitCost, setUnitCost] = useState(MODELS[0].unitCost);
  const [savedLines, setSavedLines] = useState([]);
  const [focusCell, setFocusCell] = useState({ row: 0, col: 0 });
  const [fillCol, setFillCol] = useState(null);
  const gridRef = useRef(null);

  useEffect(() => { setQty({}); setUnitCost(selected.unitCost || 0); setFocusCell({ row: 0, col: 0 }); setFillCol(null); }, [selected]);

  const cellKey = (s, c) => s + "|" + c;
  const setCell = (s, c, v) => setQty(p => ({ ...p, [cellKey(s, c)]: v === "" ? 0 : Math.max(0, parseInt(v, 10) || 0) }));

  const totals = useMemo(() => {
    if (!selected.sizes) return { byRow: {}, byCol: {}, grand: 0 };
    const byRow = {}, byCol = {}; let grand = 0;
    selected.sizes.forEach(sz => {
      byRow[sz.id] = 0;
      selected.colors.forEach(co => { const v = qty[cellKey(sz.id, co.id)] || 0; byRow[sz.id] += v; byCol[co.id] = (byCol[co.id] || 0) + v; grand += v; });
    });
    return { byRow, byCol, grand };
  }, [qty, selected]);

  const totalValue = totals.grand * unitCost;

  const onCellKey = useCallback((e, ri, ci) => {
    const rows = selected.sizes ? selected.sizes.length : 0, cols = selected.colors ? selected.colors.length : 0;
    if (!rows) return;
    let nr = ri, nc = ci;
    if (e.key === "Tab" && !e.shiftKey) { e.preventDefault(); nc = ci + 1; if (nc >= cols) { nc = 0; nr = ri + 1; if (nr >= rows) nr = 0; } }
    else if (e.key === "Tab") { e.preventDefault(); nc = ci - 1; if (nc < 0) { nc = cols - 1; nr = ri - 1; if (nr < 0) nr = rows - 1; } }
    else if (e.key === "Enter" || e.key === "ArrowDown") { e.preventDefault(); nr = (ri + 1) % rows; }
    else if (e.key === "ArrowUp") { e.preventDefault(); nr = (ri - 1 + rows) % rows; }
    else if (e.key === "ArrowRight") { e.preventDefault(); nc = (ci + 1) % cols; }
    else if (e.key === "ArrowLeft") { e.preventDefault(); nc = (ci - 1 + cols) % cols; }
    else if (e.key === "Escape") { e.preventDefault(); setQty({}); return; }
    else return;
    setFocusCell({ row: nr, col: nc });
  }, [selected]);

  useEffect(() => {
    if (!gridRef.current) return;
    const el = gridRef.current.querySelector('input[data-row="' + focusCell.row + '"][data-col="' + focusCell.col + '"]');
    if (el) el.focus();
  }, [focusCell]);

  const applyFill = (colorId, raw) => {
    const n = parseInt(raw, 10);
    setFillCol(null);
    if (!Number.isFinite(n) || n < 0) return;
    setQty(p => { const next = { ...p }; selected.sizes.forEach(sz => { next[cellKey(sz.id, colorId)] = n; }); return next; });
  };

  const addToPurchase = () => {
    if (!totals.grand) return;
    const lines = [];
    selected.sizes.forEach(sz => selected.colors.forEach(co => {
      const q = qty[cellKey(sz.id, co.id)] || 0;
      if (q > 0) lines.push({ sku: selected.sku, model: selected.name, size: sz.l, color: co.l, qty: q, unit: unitCost, total: q * unitCost });
    }));
    setSavedLines(p => [...p, ...lines]);
    setQty({});
    setFocusCell({ row: 0, col: 0 });
  };

  const isVariable = !!(selected.sizes && selected.colors);
  const skus = isVariable ? selected.sizes.length * selected.colors.length : 0;

  return (
    <div className="gmi-root" data-screen-label="Compras · Grade Matrix">
      <div className="gmi-hd">
        <div>
          <div className="gmi-crumbs">Compras · Nova compra</div>
          <h1>Adicionar item à compra</h1>
        </div>
        <div className="gmi-stats">
          <div className="gmi-stat"><small>SKUs na grade</small><b>{skus || "—"}</b></div>
          <div className="gmi-stat"><small>Σ unidades</small><b>{totals.grand}</b></div>
          <div className="gmi-stat"><small>Valor</small><b>{fmt(totalValue)}</b></div>
        </div>
      </div>
      {window.PageHeaderNav ? <window.PageHeaderNav route="cmp-grade"/> : null}
      <div className="gmi-card">
        <div className="gmi-row">
          <div className="gmi-fld gmi-fld-model">
            <label htmlFor="gmi-model">Modelo</label>
            <select id="gmi-model" value={selected.id} onChange={e => setSelected(e.target.value === SINGLE_MODEL.id ? SINGLE_MODEL : (MODELS.find(m => m.id === e.target.value) || MODELS[0]))}>
              {MODELS.map(m => <option key={m.id} value={m.id}>{m.sku} — {m.name} ({m.category})</option>)}
              <option value={SINGLE_MODEL.id}>{SINGLE_MODEL.sku} — {SINGLE_MODEL.name} (item simples)</option>
            </select>
            <small>{isVariable ? selected.sizes.length + " tam × " + selected.colors.length + " cor = " + skus + " SKUs" : "item simples · sem variação"}</small>
          </div>
          <div className="gmi-fld gmi-fld-cost">
            <label htmlFor="gmi-cost">Custo unitário (R$)</label>
            <input id="gmi-cost" type="number" step="0.01" min="0" value={unitCost} onChange={e => setUnitCost(parseFloat(e.target.value) || 0)}/>
            <small>aplicado a todas as células</small>
          </div>
          <div className="gmi-sp"></div>
          <button className="gmi-btn" onClick={() => setQty({})} disabled={!totals.grand}>Limpar grade</button>
          <button className="gmi-btn-primary" onClick={addToPurchase} disabled={!totals.grand}>
            Adicionar à compra{totals.grand > 0 ? <span className="gmi-badge">{totals.grand} un · {fmt(totalValue)}</span> : null}
          </button>
        </div>
      </div>

      <div className="gmi-card">
        {isVariable ? (
          <div className="gmi-grid-wrap" ref={gridRef}>
            <table className="gmi-grid">
              <thead>
                <tr>
                  <th className="gmi-corner"><small>{selected.template}</small><span>tam ↓ · cor →</span></th>
                  {selected.colors.map(co => (
                    <th key={co.id} className="gmi-colhead" onDoubleClick={() => setFillCol(co.id)} title="duplo-clique preenche a coluna">
                      <span className="gmi-swatch" style={{ background: co.hex }}></span>
                      <span>{co.l}</span>
                      {fillCol === co.id
                        ? <input autoFocus type="number" min="0" defaultValue="0" style={{ display: "block", width: "100%", marginTop: 4, textAlign: "center" }} onBlur={e => applyFill(co.id, e.target.value)} onKeyDown={e => { if (e.key === "Enter") applyFill(co.id, e.target.value); if (e.key === "Escape") setFillCol(null); }}/>
                        : <small>Σ {totals.byCol[co.id] || 0}</small>}
                    </th>
                  ))}
                  <th className="gmi-rowtotal-head">Σ linha</th>
                </tr>
              </thead>
              <tbody>
                {selected.sizes.map((sz, ri) => (
                  <tr key={sz.id}>
                    <th className="gmi-rowhead">{sz.l}</th>
                    {selected.colors.map((co, ci) => {
                      const v = qty[cellKey(sz.id, co.id)] || 0;
                      return (
                        <td key={co.id} className={"gmi-cell" + (v > 0 ? " has-val" : "")}>
                          <input type="number" min="0" max="9999" data-row={ri} data-col={ci} value={v || ""} placeholder="·"
                            aria-label={sz.l + " " + co.l}
                            onChange={e => setCell(sz.id, co.id, e.target.value)}
                            onKeyDown={e => onCellKey(e, ri, ci)}
                            onFocus={() => setFocusCell({ row: ri, col: ci })}/>
                        </td>
                      );
                    })}
                    <td className="gmi-rowtotal">{totals.byRow[sz.id] || 0}</td>
                  </tr>
                ))}
                <tr className="gmi-totals-row">
                  <th className="gmi-rowhead">Σ col</th>
                  {selected.colors.map(co => <td key={co.id} className="gmi-coltotal">{totals.byCol[co.id] || 0}</td>)}
                  <td className="gmi-grand"><b>{totals.grand}</b><small>{fmt(totalValue)}</small></td>
                </tr>
              </tbody>
            </table>
          </div>
        ) : (
          <div className="gmi-single">
            <label htmlFor="gmi-single-qty">Quantidade</label>
            <input id="gmi-single-qty" type="number" min="0" max="99999" placeholder="0" value={qty.single || ""} onChange={e => setQty({ single: parseInt(e.target.value, 10) || 0 })}/>
            <small>Item simples — sem grade. Total: <b>{(qty.single || 0)} un · {fmt((qty.single || 0) * unitCost)}</b></small>
          </div>
        )}
        <div className="gmi-ft">
          <span className="gmi-kb"><kbd>Tab</kbd> próx. cor</span>
          <span className="gmi-kb"><kbd>Shift+Tab</kbd> cor anterior</span>
          <span className="gmi-kb"><kbd>Enter</kbd> próx. tam</span>
          <span className="gmi-kb"><kbd>↑ ↓ ← →</kbd> navegar</span>
          <span className="gmi-kb"><kbd>Esc</kbd> limpar grade</span>
          <span className="gmi-kb"><kbd>2× clique na cor</kbd> preencher coluna</span>
          <div className="gmi-sp"></div>
          {savedLines.length > 0 ? <span className="gmi-batch">{savedLines.length} linhas · {fmt(savedLines.reduce((s, l) => s + l.total, 0))}</span> : null}
        </div>
      </div>

      {savedLines.length > 0 ? (
        <div className="gmi-card">
          <details className="gmi-lines" open>
            <summary>Linhas adicionadas à compra ({savedLines.length})</summary>
            <table>
              <thead><tr><th>SKU</th><th>Modelo</th><th>Tam</th><th>Cor</th><th style={{ textAlign: "right" }}>Qtd</th><th style={{ textAlign: "right" }}>Unit.</th><th style={{ textAlign: "right" }}>Total</th></tr></thead>
              <tbody>
                {savedLines.slice(-14).map((l, i) => (
                  <tr key={i}><td>{l.sku}</td><td>{l.model}</td><td>{l.size}</td><td>{l.color}</td><td className="num">{l.qty}</td><td className="num">{fmt(l.unit)}</td><td className="num">{fmt(l.total)}</td></tr>
                ))}
              </tbody>
            </table>
          </details>
        </div>
      ) : null}
    </div>
  );
}

if (typeof window !== "undefined") window.ComprasGradeMatrixPage = ComprasGradeMatrixPage;
})();

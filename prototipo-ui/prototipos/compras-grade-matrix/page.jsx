// page.jsx — F1 protótipo GradeMatrixInput pra entrada de compra vestuário PME
// Persona: Larissa @ ROTA LIVRE (biz=4 · 1280px · não-técnica · vestuário)
// Referência ergonômica: Cin7 Size/Color Grid + Lightspeed Matrix Inventory
// Backend pronto: app/Variation.php + purchase_lines.variation_id (PurchaseController:645)
// CSS escopado em .gmi-root. IIFE: expõe window.GradeMatrixPrototype.
(() => {
const { useState, useMemo, useRef, useEffect, useCallback } = React;

const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ─── MOCK vestuário (Larissa real, NÃO gráfica) ───
// 3 modelos camiseta/calça/vestido, grade PMGG × 3 cores
const MODELS = [
  {
    id: "MOD-101", sku: "CAM-001", name: "Camiseta básica algodão",
    template: "Tam roupa adulto", category: "Camisetas",
    sizes: [{ id: "v-p",  l: "P"  }, { id: "v-m",  l: "M"  }, { id: "v-g",  l: "G"  }, { id: "v-gg", l: "GG" }],
    colors: [{ id: "c-pre", l: "Preto",  hex: "#1a1917" }, { id: "c-bra", l: "Branco", hex: "#fafaf7" }, { id: "c-azu", l: "Azul mar.",hex: "#1f3a5f" }],
    unitCost: 22.50, sellPrice: 49.90,
  },
  {
    id: "MOD-102", sku: "CAL-007", name: "Calça jeans skinny",
    template: "Tam numerado 36-46", category: "Calças",
    sizes: [{ id: "v-36", l: "36" }, { id: "v-38", l: "38" }, { id: "v-40", l: "40" }, { id: "v-42", l: "42" }, { id: "v-44", l: "44" }],
    colors: [{ id: "c-jea", l: "Jeans",  hex: "#3b5a7a" }, { id: "c-pre", l: "Preto",  hex: "#1a1917" }],
    unitCost: 58.00, sellPrice: 129.90,
  },
  {
    id: "MOD-103", sku: "VES-022", name: "Vestido midi viscose",
    template: "Tam roupa adulto", category: "Vestidos",
    sizes: [{ id: "v-p",  l: "P"  }, { id: "v-m",  l: "M"  }, { id: "v-g",  l: "G"  }, { id: "v-gg", l: "GG" }],
    colors: [{ id: "c-fl1", l: "Floral azul", hex: "#5a7ab0" }, { id: "c-fl2", l: "Floral rosa", hex: "#c97da8" }, { id: "c-pre", l: "Preto", hex: "#1a1917" }, { id: "c-ver", l: "Verde", hex: "#3e6b50" }],
    unitCost: 41.00, sellPrice: 89.90,
  },
];

// Modelo single (sem variação) — empty state da grade
const SINGLE_MODEL = { id: "MOD-999", sku: "ETQ-001", name: "Etiqueta adesiva preço", category: "Insumos", unitCost: 0.08, sellPrice: 0 };

// ─── COMPONENTE ───
function GradeMatrixPrototype() {
  const [selected, setSelected] = useState(MODELS[0]);
  const [qty, setQty] = useState({}); // { "v-p|c-pre": 5, ... }
  const [unitCost, setUnitCost] = useState(MODELS[0].unitCost);
  const [savedLines, setSavedLines] = useState([]); // batch acumulado pré-submit
  const [focusCell, setFocusCell] = useState({ row: 0, col: 0 });
  const gridRef = useRef(null);

  // ao trocar modelo, reset grade
  useEffect(() => {
    setQty({});
    setUnitCost(selected.unitCost || 0);
    setFocusCell({ row: 0, col: 0 });
  }, [selected]);

  const cellKey = (sizeId, colorId) => `${sizeId}|${colorId}`;

  const setCell = (sizeId, colorId, v) => {
    const k = cellKey(sizeId, colorId);
    const n = v === "" ? 0 : Math.max(0, parseInt(v, 10) || 0);
    setQty(prev => ({ ...prev, [k]: n }));
  };

  // Totais on-the-fly (useMemo)
  const totals = useMemo(() => {
    if (!selected.sizes) return { byRow: {}, byCol: {}, grand: 0 };
    const byRow = {}, byCol = {};
    let grand = 0;
    selected.sizes.forEach(sz => {
      byRow[sz.id] = 0;
      selected.colors.forEach(co => {
        const v = qty[cellKey(sz.id, co.id)] || 0;
        byRow[sz.id] += v;
        byCol[co.id] = (byCol[co.id] || 0) + v;
        grand += v;
      });
    });
    return { byRow, byCol, grand };
  }, [qty, selected]);

  const totalValue = totals.grand * unitCost;

  // ─── Atalhos teclado canônicos Cin7/Lightspeed ───
  const onCellKey = useCallback((e, rowIdx, colIdx) => {
    const rows = selected.sizes?.length || 0;
    const cols = selected.colors?.length || 0;
    if (!rows) return;
    let nr = rowIdx, nc = colIdx;
    if (e.key === "Tab" && !e.shiftKey) { e.preventDefault(); nc = colIdx + 1; if (nc >= cols) { nc = 0; nr = rowIdx + 1; if (nr >= rows) nr = 0; } }
    else if (e.key === "Tab" && e.shiftKey) { e.preventDefault(); nc = colIdx - 1; if (nc < 0) { nc = cols - 1; nr = rowIdx - 1; if (nr < 0) nr = rows - 1; } }
    else if (e.key === "Enter") { e.preventDefault(); nr = (rowIdx + 1) % rows; }
    else if (e.key === "ArrowDown") { e.preventDefault(); nr = (rowIdx + 1) % rows; }
    else if (e.key === "ArrowUp") { e.preventDefault(); nr = (rowIdx - 1 + rows) % rows; }
    else if (e.key === "ArrowRight") { e.preventDefault(); nc = (colIdx + 1) % cols; }
    else if (e.key === "ArrowLeft") { e.preventDefault(); nc = (colIdx - 1 + cols) % cols; }
    else if (e.key === "Escape") { e.preventDefault(); setQty({}); return; }
    else return;
    setFocusCell({ row: nr, col: nc });
  }, [selected]);

  // foco programático
  useEffect(() => {
    if (!gridRef.current) return;
    const sel = `input[data-row="${focusCell.row}"][data-col="${focusCell.col}"]`;
    const el = gridRef.current.querySelector(sel);
    if (el) el.focus();
  }, [focusCell]);

  const fillColumn = (colorId) => {
    const v = prompt(`Preencher coluna "${selected.colors.find(c => c.id === colorId)?.l}" com qty:`, "0");
    const n = parseInt(v, 10);
    if (!Number.isFinite(n) || n < 0) return;
    setQty(prev => {
      const next = { ...prev };
      selected.sizes.forEach(sz => { next[cellKey(sz.id, colorId)] = n; });
      return next;
    });
  };

  const addToPurchase = () => {
    if (totals.grand === 0) return;
    const lines = [];
    selected.sizes.forEach(sz => {
      selected.colors.forEach(co => {
        const k = cellKey(sz.id, co.id);
        const q = qty[k] || 0;
        if (q > 0) lines.push({
          model_id: selected.id, variation_size: sz.l, variation_color: co.l,
          variation_id_mock: k, qty: q, unit_cost: unitCost, total: q * unitCost,
        });
      });
    });
    setSavedLines(prev => [...prev, ...lines]);
    setQty({});
    setFocusCell({ row: 0, col: 0 });
  };

  const isVariable = !!(selected.sizes && selected.colors);

  return (
    <div className="gmi-root" data-screen-label="F1 GradeMatrixInput vestuário">
      <header className="gmi-hd">
        <div className="crumbs">ERP · Operação · Compras · <b>+ Nova compra</b> · <b style={{ color: "var(--gmi-ink)" }}>Adicionar item</b></div>
        <h1>Adicionar item à compra</h1>
        <div className="sp" />
        <span className="hint">protótipo F1 · Cowork → Claude Code · ref. Cin7/Lightspeed</span>
      </header>

      {/* Linha 1: seleção modelo + custo + ações */}
      <div className="gmi-row gmi-row-1">
        <div className="fld fld-model">
          <label>Modelo</label>
          <select
            value={selected.id}
            onChange={(e) => {
              if (e.target.value === SINGLE_MODEL.id) setSelected(SINGLE_MODEL);
              else setSelected(MODELS.find(m => m.id === e.target.value) || MODELS[0]);
            }}
          >
            {MODELS.map(m => (
              <option key={m.id} value={m.id}>{m.sku} — {m.name} ({m.category})</option>
            ))}
            <option value={SINGLE_MODEL.id}>— {SINGLE_MODEL.sku} {SINGLE_MODEL.name} (item simples)</option>
          </select>
          <small>{isVariable ? `${selected.sizes.length} tam × ${selected.colors.length} cor = ${selected.sizes.length * selected.colors.length} SKUs` : "item simples · sem variação"}</small>
        </div>
        <div className="fld fld-cost">
          <label>Custo unitário (R$)</label>
          <input type="number" step="0.01" min="0" value={unitCost} onChange={(e) => setUnitCost(parseFloat(e.target.value) || 0)} />
          <small>aplicado a todas as células · override por filho em modo avançado</small>
        </div>
        <div className="sp" />
        <button className="btn-ghost" onClick={() => setQty({})} disabled={!totals.grand}>Limpar grade</button>
        <button className="btn-primary" onClick={addToPurchase} disabled={!totals.grand}>
          Adicionar {totals.grand > 0 && <span className="badge">{totals.grand} un · {fmt(totalValue)}</span>}
        </button>
      </div>

      {/* GRADE */}
      {isVariable ? (
        <div className="gmi-grid-wrap" ref={gridRef}>
          <table className="gmi-grid">
            <thead>
              <tr>
                <th className="corner">
                  <small>{selected.template}</small>
                  <span>tam ↓ · cor →</span>
                </th>
                {selected.colors.map((co, ci) => (
                  <th key={co.id} className="col-head" onDoubleClick={() => fillColumn(co.id)} title="duplo-clique pra preencher coluna">
                    <span className="swatch" style={{ background: co.hex }} />
                    <span className="col-name">{co.l}</span>
                    <small>↓ Σ {totals.byCol[co.id] || 0}</small>
                  </th>
                ))}
                <th className="row-total-head">Σ linha</th>
              </tr>
            </thead>
            <tbody>
              {selected.sizes.map((sz, ri) => (
                <tr key={sz.id}>
                  <th className="row-head">{sz.l}</th>
                  {selected.colors.map((co, ci) => {
                    const k = cellKey(sz.id, co.id);
                    const v = qty[k] || 0;
                    return (
                      <td key={co.id} className={`cell ${v > 0 ? "has-val" : ""}`}>
                        <input
                          type="number"
                          min="0" max="9999"
                          data-row={ri} data-col={ci}
                          value={v || ""}
                          onChange={(e) => setCell(sz.id, co.id, e.target.value)}
                          onKeyDown={(e) => onCellKey(e, ri, ci)}
                          onFocus={() => setFocusCell({ row: ri, col: ci })}
                          placeholder="·"
                        />
                      </td>
                    );
                  })}
                  <td className="row-total">{totals.byRow[sz.id] || 0}</td>
                </tr>
              ))}
              <tr className="col-totals-row">
                <th className="row-head">Σ col</th>
                {selected.colors.map(co => (
                  <td key={co.id} className="col-total">{totals.byCol[co.id] || 0}</td>
                ))}
                <td className="grand-total">
                  <b>{totals.grand}</b>
                  <small>{fmt(totalValue)}</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      ) : (
        // EMPTY STATE — modelo single
        <div className="gmi-single">
          <label>Quantidade</label>
          <input
            type="number" min="0" max="99999" placeholder="qty"
            onChange={(e) => setQty({ "single": parseInt(e.target.value, 10) || 0 })}
            value={qty.single || ""}
          />
          <small>Item simples — sem grade. Total: <b>{(qty.single || 0)} un · {fmt((qty.single || 0) * unitCost)}</b></small>
        </div>
      )}

      {/* SHORTCUTS BAR */}
      <footer className="gmi-ft">
        <span className="kb"><kbd>Tab</kbd> próx. cor</span>
        <span className="kb"><kbd>Shift+Tab</kbd> cor anterior</span>
        <span className="kb"><kbd>Enter</kbd> próx. tam</span>
        <span className="kb"><kbd>↑ ↓ ← →</kbd> navegar</span>
        <span className="kb"><kbd>Esc</kbd> limpar grade</span>
        <span className="kb"><kbd>2× clique col</kbd> preencher coluna</span>
        <div className="sp" />
        {savedLines.length > 0 && (
          <span className="batch-hint">
            <b>{savedLines.length}</b> linhas adicionadas à compra · {fmt(savedLines.reduce((s, l) => s + l.total, 0))}
          </span>
        )}
      </footer>

      {/* Preview do que vai pro backend (debug F1) */}
      {savedLines.length > 0 && (
        <details className="gmi-debug">
          <summary>↓ payload pra POST /compras/store ({savedLines.length} linhas)</summary>
          <pre>{JSON.stringify(savedLines.slice(-12), null, 2)}</pre>
        </details>
      )}
    </div>
  );
}

if (typeof window !== "undefined") window.GradeMatrixPrototype = GradeMatrixPrototype;
})();

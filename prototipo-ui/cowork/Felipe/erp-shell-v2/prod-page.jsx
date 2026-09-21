// prod-page.jsx — Catálogo de Produtos (visual Picker Mecânica)
// Substitui a versão CV-focada (backup em prod-page-v1-cv.jsx).
// Layout: header → chips de categoria → [sidebar marca | grid/lista] → drawer detalhe.
// SEM filtro/aplicação por veículo (intencional, por solicitação do produto).
const { useState: useStateP, useMemo: useMemoP } = React;

// ─── Paleta industrial por categoria ───
const PMCat = {
  "Mecânica":            { color: "#456a8c", short: "MEC" },
  "Impressos":           { color: "#1f3a5f", short: "IMP" },
  "Comunicação Visual":  { color: "#8a3a2a", short: "CV"  },
  "Embalagens":          { color: "#6a5a2a", short: "EMB" },
  "Adesivos":            { color: "#9a5a2a", short: "ADE" },
  "Vestuário":           { color: "#5a4a6e", short: "VES" },
  "Acabamento":          { color: "#2a5a3a", short: "ACB" },
  "Brindes":             { color: "#6a3a5e", short: "BRI" },
  "Serviços":            { color: "#2a5a8a", short: "SRV" },
  "Composições":         { color: "#2a6d3a", short: "KIT" },
};
const catColor = (c) => (PMCat[c]?.color) || "#5b574f";
const catShort = (c) => (PMCat[c]?.short) || c.slice(0,3).toUpperCase();

// Mock de prateleira determinístico (a partir do id) — sem mudar PROD_DATA
function prodLoc(p) {
  const letters = "ABCDEFGH";
  const n = parseInt((p.id.match(/\d+/) || ["0"])[0], 10);
  const letter = letters[(n - 1) % letters.length];
  const shelf  = String(((n - 1) % 9) + 1).padStart(2, "0");
  return `${letter}${Math.floor(n/10)+1}-${shelf}`;
}

// ── Tabela de preços padrão (4 níveis com multiplicadores) ──
// Cada produto pode sobrescrever via `priceTable` no PROD_LIST.
const DEFAULT_PRICE_TIERS = [
  { key:"varejo",      label:"Varejo",      mult:1.00, desc:"Balcão · cliente avulso" },
  { key:"atacado",     label:"Atacado",     mult:0.90, desc:"≥10 un · revenda" },
  { key:"convenio",    label:"Convênio",    mult:0.85, desc:"Seguradora · frota" },
  { key:"funcionario", label:"Funcionário", mult:0.75, desc:"Política interna" },
];

const parseBRPrice = (s) => {
  if (typeof s === "number") return s;
  if (!s) return 0;
  return parseFloat(String(s).replace(/[^\d,]/g,"").replace(",",".")) || 0;
};
const fmtBR = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" });

// Faixa de preços pelas variantes (min..max). Se só 1 valor, retorna single.
function priceRange(p) {
  const prices = (p.variants || [])
    .map(v => parseBRPrice(v.price))
    .filter(x => x > 0);
  if (prices.length === 0) {
    const base = parseBRPrice(p.price);
    return { min: base, max: base, hasRange: false, count: 1 };
  }
  const min = Math.min(...prices);
  const max = Math.max(...prices);
  return { min, max, hasRange: max > min, count: prices.length };
}
// Formata faixa: "R$ 220,00 – 380,00"  (R$ só no começo, sem repetir)
function fmtPriceRange(min, max) {
  const fmt = (n) => n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (min === max) return "R$ " + fmt(min);
  return `R$ ${fmt(min)} – ${fmt(max)}`;
}

function buildPriceTable(p) {
  if (Array.isArray(p.priceTable) && p.priceTable.length > 0) {
    return p.priceTable;
  }
  const base = parseBRPrice(p.price);
  return DEFAULT_PRICE_TIERS.map(t => ({
    key:   t.key,
    label: t.label,
    desc:  t.desc,
    price: base * t.mult,
    discount: t.mult === 1 ? null : Math.round((1 - t.mult) * 100),
  }));
}

function ProdListPage({ typeFilter = "all", onTypeFilter }) {
  const all = PROD_DATA.PROD_LIST;

  // ── Persistência em localStorage ──
  const loadLS = (k, def) => { try { const v = localStorage.getItem(k); return v == null ? def : JSON.parse(v); } catch (e) { return def; } };
  const saveLS = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };

  const [query, setQuery]           = useStateP("");
  const [openId, setOpenId]         = useStateP(null);
  const [view, _setView]            = useStateP(() => loadLS("oimpresso.prod.view", "list"));
  const [includeInactive, _setIncIn] = useStateP(() => loadLS("oimpresso.prod.inactive", false));
  const [sort, _setSort]            = useStateP(() => loadLS("oimpresso.prod.sort", { key: "name", dir: "asc" }));
  const [stockFilter, _setStockFilter] = useStateP(() => loadLS("oimpresso.prod.stock", "all")); // all | ok | warn | out

  const setView          = (v) => { _setView(v);          saveLS("oimpresso.prod.view", v); };
  const setIncIn         = (v) => { _setIncIn(v);         saveLS("oimpresso.prod.inactive", v); };
  const setSort          = (v) => { _setSort(v);          saveLS("oimpresso.prod.sort", v); };
  const setStockFilter   = (v) => { _setStockFilter(v);   saveLS("oimpresso.prod.stock", v); };

  // Inferência de tipo: produto | servico | composicao
  // Prioriza p.type explícito; senão cai em heurística por categoria/unidade.
  const prodType = (p) => {
    if (p.type === "servico" || p.type === "composicao" || p.type === "produto") return p.type;
    const c = (p.category || "").toLowerCase();
    if (c === "serviços" || p.unit === "h") return "servico";
    if (c === "composições") return "composicao";
    return "produto";
  };

  // Filtra
  const filtered = useMemoP(() => {
    let out = all;
    if (!includeInactive) out = out.filter(p => p.active);
    if (typeFilter !== "all") out = out.filter(p => prodType(p) === typeFilter);
    if (stockFilter !== "all") {
      out = out.filter(p => {
        const total = PROD_DATA.prodStock(p);
        if (stockFilter === "out")  return total === 0;
        if (stockFilter === "ok")   return total > 0 && stkStatus(p) === "ok";
        if (stockFilter === "warn") return total > 0 && stkStatus(p) === "warn";
        return true;
      });
    }
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.id.toLowerCase().includes(q) ||
        (p.brand || "").toLowerCase().includes(q) ||
        p.category.toLowerCase().includes(q) ||
        ((p.oem || []).join(" ").toLowerCase().includes(q))
      );
    }
    return out;
  }, [all, typeFilter, stockFilter, query, includeInactive]);

  // Disponíveis × Esgotados (esgotados vão no fim, acinzentados)
  const parseBRn = (s) => typeof s === "number" ? s : parseFloat(String(s||"").replace(/[^\d,]/g,"").replace(",",".")) || 0;
  const bestSup = (p) => (p.suppliers || []).slice().sort((a,b) => a.cost - b.cost)[0];
  const sortKey = (p) => {
    switch (sort.key) {
      case "name":     return p.name.toLowerCase();
      case "stock":    return PROD_DATA.prodStock(p);
      case "cost":     return bestSup(p)?.cost || 0;
      case "price":    return parseBRn(p.price);
      case "margin":   return bestSup(p)?.margin || 0;
      case "variants": return (p.variants || []).length;
      case "lead":     return p.lead || "";
      case "pop":      return p.popularity || 0;
      default:         return 0;
    }
  };
  const sortApply = (arr) => {
    const out = arr.slice().sort((a, b) => {
      const va = sortKey(a), vb = sortKey(b);
      if (va < vb) return sort.dir === "asc" ? -1 : 1;
      if (va > vb) return sort.dir === "asc" ? 1 : -1;
      return 0;
    });
    return out;
  };
  const onSort = (key) => {
    setSort(s => s.key === key
      ? { key, dir: s.dir === "asc" ? "desc" : "asc" }
      : { key, dir: key === "name" || key === "lead" ? "asc" : "desc" });
  };

  const avail = sortApply(filtered.filter(p => PROD_DATA.prodStock(p) > 0));
  const outs  = sortApply(filtered.filter(p => PROD_DATA.prodStock(p) === 0));

  // Stock status agnóstico (sem depender do helper antigo)
  function stkStatus(p) {
    const total = PROD_DATA.prodStock(p);
    if (total === 0) return "out";
    // Min ~ 10% do estoque máximo entre variantes ou 5 unidades
    const variants = p.variants || [];
    const max = variants.reduce((a, v) => Math.max(a, v.stock || 0), 0);
    const threshold = Math.max(5, Math.round(max * 0.10));
    return total < threshold ? "warn" : "ok";
  }
  function stkLabel(p) {
    const total = PROD_DATA.prodStock(p);
    if (total === 0) return "Esgotado";
    const unit = p.unit === "milheiro" ? "un" : p.unit;
    return `${total.toLocaleString("pt-BR")} ${unit}`;
  }

  const openProd = openId ? all.find(p => p.id === openId) : null;

  // ── Linha (Densa, padrão) ──
  const Row = (p, isOut) => {
    const ss = isOut ? "out" : stkStatus(p);
    const stkCls = ss === "ok" ? "ok" : ss === "warn" ? "warn" : "out";
    const best = bestSup(p);
    const varCount = (p.variants || []).length;
    return (
      <div key={p.id}
           className={"pm-row" + (isOut ? " out" : "") + (openId===p.id ? " sel" : "")}
           style={{ "--row-c": catColor(p.category) }}
           onClick={() => setOpenId(p.id)}>
        <div className="pm-thumb" style={{background: catColor(p.category)}}>
          {catShort(p.category)}
        </div>
        <div className="pm-info">
          <div className="pm-info-top">
            <span className="pm-dot" style={{background: catColor(p.category)}}/>
            <span>{p.category}</span>
            <span style={{opacity:.4}}>·</span>
            <span className="pm-brand">{p.brand || "—"}</span>
          </div>
          <b>{p.name}</b>
          <small>
            <span>{p.id}</span>
            <span className="pm-loc">{prodLoc(p)}</span>
            {p.oem && p.oem[0] && <code className="pm-oem">{p.oem[0]}{p.oem.length > 1 && <i> +{p.oem.length-1}</i>}</code>}
          </small>
        </div>
        <div className={"pm-stk-pill " + stkCls}>
          <span className="d"/>
          {isOut ? "Esgotado" : stkLabel(p)}
        </div>
        <div className="pm-cost">
          {best ? <>
            <b>{best.cost.toLocaleString("pt-BR", {style:"currency", currency:"BRL"})}</b>
            <small>{best.name.length > 14 ? best.name.slice(0,12)+"…" : best.name}</small>
          </> : <span className="pm-dash">—</span>}
        </div>
        <div className="pm-price">
          {(() => {
            const r = priceRange(p);
            return r.hasRange ? (
              <>
                <b className="pm-price-rng">{fmtPriceRange(r.min, r.max)}</b>
                <small>{r.count} var. · / {p.unit === "milheiro" ? "milh." : p.unit}</small>
              </>
            ) : (
              <>
                <b>{p.price}</b>
                <small>/ {p.unit === "milheiro" ? "milh." : p.unit}</small>
              </>
            );
          })()}
        </div>
        <div className="pm-margin">
          {best
            ? <span className={"pm-margin-pill " + (best.margin > 50 ? "high" : best.margin > 25 ? "mid" : "low")}>+{best.margin}%</span>
            : <span className="pm-dash">—</span>}
        </div>
        <div className="pm-var">
          {varCount > 0 ? <b>{varCount}</b> : <span className="pm-dash">—</span>}
        </div>
        <div className="pm-pop">
          <b>{p.popularity || 0}<i>%</i></b>
          <div className="pm-pop-bar"><div style={{ width:(p.popularity||0)+"%" }}/></div>
        </div>
        <div className="pm-lead">{p.lead}</div>
      </div>
    );
  };

  // ── Card (Balcão) ──
  const Card = (p, isOut) => {
    const ss = isOut ? "out" : stkStatus(p);
    const stkCls = ss === "ok" ? "ok" : ss === "warn" ? "warn" : "danger";
    return (
      <div key={p.id}
           className={"pm-card" + (isOut ? " out" : "") + (openId===p.id ? " sel" : "")}
           onClick={() => setOpenId(p.id)}>
        <div className="pm-img" style={{background: catColor(p.category)}}>
          <span className="pm-img-cat">{p.category}</span>
          <span className="pm-img-overlay">{catShort(p.category)}</span>
          <span className="pm-img-id">{p.id}</span>
          <span className={"pm-img-stk " + stkCls}>
            <span className="d"/>{isOut ? "Esgotado" : stkLabel(p)}
          </span>
        </div>
        <div className="pm-card-body">
          <span className="pm-card-brand">{p.brand || "—"}</span>
          <b className="pm-card-name">{p.name}</b>
          <div className="pm-card-meta">
            <span className="pm-loc">{prodLoc(p)}</span>
            {(p.variants || []).length > 0 && <span>{(p.variants||[]).length} var.</span>}
          </div>
        </div>
        <div className="pm-card-foot">
          <div className="pm-card-price">
            {(() => {
              const r = priceRange(p);
              return r.hasRange
                ? <><b className="pm-price-rng">{fmtPriceRange(r.min, r.max)}</b><small>{r.count} var. · / {p.unit}</small></>
                : <><b>{p.price}</b><small>/ {p.unit}</small></>;
            })()}
          </div>
          <div className="pm-card-unit"><b>{p.unit === "milheiro" ? "milh." : p.unit}</b><span>unidade</span></div>
        </div>
      </div>
    );
  };

  const renderEither = (p, isOut) => view === "grid" ? Card(p, isOut) : Row(p, isOut);

  return (
    <>
      {/* Header do shell (mantido pra coerência com OS/Clientes) */}
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Produtos</h1>
          <p>{filtered.length} produtos · {avail.length} disponíveis · {outs.length} esgotados</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost"><I.search size={13}/> Importar</button>
          <button className="os-btn primary"><I.plus size={13}/> Novo produto</button>
        </div>
      </div>

      {/* Busca + toggle de modos */}
      <div className="pm-search">
        <div className="pm-s">
          <I.search size={14} className="pm-s-ic"/>
          <input
            placeholder="Buscar por nome, código, marca, categoria ou código OEM..."
            value={query}
            onChange={e => setQuery(e.target.value)}/>
          <span className="pm-s-scope">5 campos</span>
        </div>
        <label className="pm-toggle-inactive">
          <input type="checkbox" checked={includeInactive} onChange={e => setIncIn(e.target.checked)}/>
          <span>Inativos</span>
        </label>
        <div className="pm-view-toggle">
          <button className={view==='list'?'active':''} onClick={() => setView('list')} title="Lista densa">≡ Densa</button>
          <button className={view==='grid'?'active':''} onClick={() => setView('grid')} title="Cards para balcão">▦ Balcão</button>
        </div>
      </div>

      {/* Filtro rápido de estoque */}
      {(() => {
        const baseAll = all.filter(p => (includeInactive || p.active) && (typeFilter==="all" || prodType(p)===typeFilter));
        const cOk   = baseAll.filter(p => PROD_DATA.prodStock(p) > 0 && stkStatus(p) === "ok").length;
        const cWarn = baseAll.filter(p => PROD_DATA.prodStock(p) > 0 && stkStatus(p) === "warn").length;
        const cOut  = baseAll.filter(p => PROD_DATA.prodStock(p) === 0).length;
        const cAll  = baseAll.length;
        return (
          <div className="pm-stockbar">
            <span className="pm-stockbar-lbl">Estoque</span>
            <button className={"pm-stockbar-chip" + (stockFilter==="all"  ? " act" : "")} onClick={() => setStockFilter("all")}>
              Todos <span className="pm-stockbar-n">{cAll}</span>
            </button>
            <button className={"pm-stockbar-chip ok" + (stockFilter==="ok" ? " act" : "")} onClick={() => setStockFilter("ok")}>
              <span className="d"/> Em estoque <span className="pm-stockbar-n">{cOk}</span>
            </button>
            <button className={"pm-stockbar-chip warn" + (stockFilter==="warn" ? " act" : "")} onClick={() => setStockFilter("warn")}>
              <span className="d"/> Estoque baixo <span className="pm-stockbar-n">{cWarn}</span>
            </button>
            <button className={"pm-stockbar-chip out" + (stockFilter==="out" ? " act" : "")} onClick={() => setStockFilter("out")}>
              <span className="d"/> Esgotado <span className="pm-stockbar-n">{cOut}</span>
            </button>
            <span style={{flex:1}}/>
            {(stockFilter !== "all" || query.trim() || typeFilter !== "all") && (
              <button className="pm-clear-filters" onClick={() => { setStockFilter("all"); setQuery(""); onTypeFilter?.("all"); }}>
                ⤬ Limpar filtros
              </button>
            )}
          </div>
        );
      })()}

      {/* (Os chips de tipo foram movidos pro topnav contextual no Header.) */}

      {/* Corpo: lista única (sem sidebar de marcas) */}
      <div className="pm-wrap">
        <div className="pm-body">
          {filtered.length === 0 && (
            <div className="pm-empty">
              <div className="pm-empty-ico">⌕</div>
              <b>Nenhum produto encontrado</b>
              <span>Ajuste a busca, troque o tipo ou desligue o filtro de estoque.</span>
              <button className="pm-empty-btn" onClick={() => { setStockFilter("all"); setQuery(""); onTypeFilter?.("all"); }}>
                Limpar todos os filtros
              </button>
            </div>
          )}

          {avail.length > 0 && (
            <>
              {view === "list" ? (
                <div className="pm-table">
                  <div className="pm-thead">
                    <div className="pm-th-thumb"/>
                    <button className={"pm-th sortable" + (sort.key==="name" ? " act" : "")} onClick={() => onSort("name")}>
                      Produto <span className="pm-th-ind">{sort.key==="name" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span>
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="stock" ? " act" : "")} onClick={() => onSort("stock")}>
                      <span className="pm-th-ind">{sort.key==="stock" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Estoque
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="cost" ? " act" : "")} onClick={() => onSort("cost")}>
                      <span className="pm-th-ind">{sort.key==="cost" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Custo
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="price" ? " act" : "")} onClick={() => onSort("price")}>
                      <span className="pm-th-ind">{sort.key==="price" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Preço venda
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="margin" ? " act" : "")} onClick={() => onSort("margin")}>
                      <span className="pm-th-ind">{sort.key==="margin" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Margem
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="variants" ? " act" : "")} onClick={() => onSort("variants")}>
                      <span className="pm-th-ind">{sort.key==="variants" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Var.
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="pop" ? " act" : "")} onClick={() => onSort("pop")}>
                      <span className="pm-th-ind">{sort.key==="pop" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Pop.
                    </button>
                    <button className={"pm-th sortable r" + (sort.key==="lead" ? " act" : "")} onClick={() => onSort("lead")}>
                      <span className="pm-th-ind">{sort.key==="lead" ? (sort.dir==="asc" ? "↑" : "↓") : "⇵"}</span> Prazo
                    </button>
                  </div>
                  <div className="pm-tbody">
                    {avail.map(p => renderEither(p, false))}
                  </div>
                </div>
              ) : (
                <div className="pm-grid">
                  {avail.map(p => renderEither(p, false))}
                </div>
              )}
            </>
          )}

          {outs.length > 0 && (
            <>
              <div className="pm-sec">
                <span>Esgotados</span>
                <span className="pm-sec-n">{outs.length}</span>
                <span className="pm-sec-ln"/>
              </div>
              {view === "list" ? (
                <div className="pm-table">
                  <div className="pm-tbody">
                    {outs.map(p => renderEither(p, true))}
                  </div>
                </div>
              ) : (
                <div className="pm-grid">
                  {outs.map(p => renderEither(p, true))}
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* ── Totalizador no rodapé da página ── */}
      {(() => {
        const parseBR = (s) => {
          if (typeof s === "number") return s;
          if (!s) return 0;
          return parseFloat(String(s).replace(/[^\d,]/g,"").replace(",",".")) || 0;
        };
        const totItems   = filtered.length;
        const totEstoque = filtered.reduce((a,p) => a + PROD_DATA.prodStock(p), 0);
        const totVenda   = filtered.reduce((a,p) => a + (p.variants || []).reduce((s,v) => s + (v.stock||0) * parseBR(v.price), 0), 0);
        const totCusto   = filtered.reduce((a,p) => {
          const best = (p.suppliers || []).slice().sort((x,y) => x.cost - y.cost)[0];
          if (!best) return a;
          return a + PROD_DATA.prodStock(p) * best.cost;
        }, 0);
        const margins   = filtered.flatMap(p => (p.suppliers||[]).slice().sort((x,y) => x.cost-y.cost)[0] ? [((p.suppliers||[]).slice().sort((x,y) => x.cost-y.cost)[0]).margin] : []);
        const margemAvg = margins.length ? Math.round(margins.reduce((a,b) => a+b, 0) / margins.length) : null;
        const popAvg    = filtered.length ? Math.round(filtered.reduce((a,p) => a+(p.popularity||0), 0) / filtered.length) : 0;
        const fmtBRL    = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL", maximumFractionDigits:0 });

        return (
          <div className="pm-totals">
            <div className="pm-tot">
              <small>Itens listados</small>
              <b>{totItems}</b>
              {outs.length > 0 && <span className="pm-tot-sub">{outs.length} esgotado{outs.length>1?"s":""}</span>}
            </div>
            <div className="pm-tot">
              <small>Estoque total</small>
              <b>{totEstoque.toLocaleString("pt-BR")}</b>
              <span className="pm-tot-sub">unidades somadas</span>
            </div>
            <div className="pm-tot pm-tot-money">
              <small>Valor em estoque (venda)</small>
              <b>{fmtBRL(totVenda)}</b>
              <span className="pm-tot-sub">preço × estoque</span>
            </div>
            <div className="pm-tot pm-tot-money">
              <small>Custo em estoque</small>
              <b>{fmtBRL(totCusto)}</b>
              <span className="pm-tot-sub">melhor fornecedor</span>
            </div>
            <div className="pm-tot">
              <small>Margem média</small>
              <b className={margemAvg!=null ? (margemAvg > 50 ? "ok" : margemAvg > 25 ? "warn" : "low") : ""}>
                {margemAvg != null ? `+${margemAvg}%` : "—"}
              </b>
              <span className="pm-tot-sub">{margins.length} c/ cotação</span>
            </div>
            <div className="pm-tot">
              <small>Popularidade</small>
              <b>{popAvg}%</b>
              <div className="pm-tot-bar"><div style={{ width: popAvg + "%" }}/></div>
            </div>
          </div>
        );
      })()}

      {/* Drawer de detalhe (sem seção de veículos) */}
      {openProd && (() => {
        const t = prodType(openProd);
        const typeLbl = t === "servico" ? "Serviço" : t === "composicao" ? "Composição" : "Produto";
        const typeColor = t === "servico" ? "#2a5a8a" : t === "composicao" ? "#2a6d3a" : "#1f3a5f";
        const best = (openProd.suppliers || []).slice().sort((a,b) => a.cost - b.cost)[0];
        return (
        <>
          <div className="os-drawer-back" onClick={() => setOpenId(null)}/>
          <aside className="os-drawer pm-drawer">
            <div className="pm-drawer-strip" style={{background: catColor(openProd.category)}}/>
            <div className="os-drawer-h">
              <div>
                <div className="pm-drawer-tags">
                  <span className="pm-drawer-type" style={{background: typeColor}}>{typeLbl}</span>
                  <span className="pm-drawer-id">{openProd.id}</span>
                  {!openProd.active && <span className="pm-drawer-inact">Inativo</span>}
                </div>
                <h2>{openProd.name}</h2>
                <div className="os-drawer-meta">
                  <span className="pm-dot" style={{background: catColor(openProd.category), marginRight:6}}/>
                  {openProd.category}
                  {openProd.brand && <> · <b style={{color:"var(--text)"}}>{openProd.brand}</b></>}
                </div>
              </div>
              <button className="os-icon-btn" onClick={() => setOpenId(null)}><I.close size={16}/></button>
            </div>
            <div className="os-drawer-body">
              <div className="cli-kpis">
                <div className="cli-kpi"><b className="mono">{openProd.price}</b><small>Preço/{openProd.unit}</small></div>
                <div className="cli-kpi">
                  <b className="mono">{best ? best.cost.toLocaleString("pt-BR",{style:"currency",currency:"BRL"}) : "—"}</b>
                  <small>Custo melhor cotação</small>
                </div>
                <div className="cli-kpi">
                  <b className={"mono " + (best && best.margin > 50 ? "ok" : best && best.margin > 25 ? "warn" : best ? "low" : "")}>
                    {best ? `+${best.margin}%` : "—"}
                  </b>
                  <small>Margem</small>
                </div>
                <div className="cli-kpi">
                  <b className="mono">{openProd.popularity || 0}%</b>
                  <small>Popularidade</small>
                </div>
                <div className="cli-kpi">
                  <b className={"mono " + (stkStatus(openProd))}>
                    {PROD_DATA.prodStock(openProd).toLocaleString("pt-BR")}
                  </b>
                  <small>Estoque ({openProd.unit === "milheiro" ? "un" : openProd.unit})</small>
                </div>
                <div className="cli-kpi"><b>{openProd.lead}</b><small>Prazo</small></div>
                <div className="cli-kpi"><b>{prodLoc(openProd)}</b><small>Prateleira</small></div>
              </div>

              <div className="cli-section">
                {openProd.oem && openProd.oem.length > 0 && (
                  <>
                    <h4 className="prod-bom-h">Códigos OEM · originais</h4>
                    <div className="prod-oem-list">
                      {openProd.oem.map((c, i) => <code key={i} className="prod-oem">{c}</code>)}
                    </div>
                  </>
                )}
                {openProd.superseded && openProd.superseded.length > 0 && (
                  <>
                    <h4 className="prod-bom-h">Códigos equivalentes</h4>
                    <div className="prod-oem-list">
                      {openProd.superseded.map((c, i) => <code key={i} className="prod-oem alt">{c}</code>)}
                    </div>
                  </>
                )}

                {openProd.specs && Object.keys(openProd.specs).length > 0 && (
                  <>
                    <h4 className="prod-bom-h">Ficha técnica</h4>
                    <table className="prod-var-table">
                      <tbody>
                        {Object.entries(openProd.specs).map(([k, v]) => (
                          <tr key={k}>
                            <td style={{textTransform:"capitalize", color:"var(--text-dim)", width:"40%"}}>{k}</td>
                            <td className="mono">{v}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </>
                )}

                {/* ── Tabela de preços ── */}
                {(() => {
                  const tiers = buildPriceTable(openProd);
                  const bestCost = (openProd.suppliers || []).slice().sort((a,b) => a.cost - b.cost)[0]?.cost;
                  return (
                    <>
                      <h4 className="prod-bom-h">Tabela de preços</h4>
                      <div className="pm-price-table">
                        {tiers.map(t => {
                          const margin = bestCost ? Math.round(((t.price - bestCost) / t.price) * 100) : null;
                          const marginCls = margin == null ? "" : margin > 50 ? "high" : margin > 25 ? "mid" : "low";
                          return (
                            <div key={t.key} className={"pm-pt-row" + (t.key === "varejo" ? " primary" : "")}>
                              <div className="pm-pt-l">
                                <b>{t.label}</b>
                                <small>{t.desc}</small>
                              </div>
                              <div className="pm-pt-mid">
                                {t.discount != null && <span className="pm-pt-disc">−{t.discount}%</span>}
                              </div>
                              <div className="pm-pt-price">
                                <b>{fmtBR(t.price)}</b>
                                <small>/ {openProd.unit === "milheiro" ? "milh." : openProd.unit}</small>
                              </div>
                              {margin != null && (
                                <span className={"pm-margin-pill " + marginCls}>+{margin}%</span>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </>
                  );
                })()}

                <h4 className="prod-bom-h">Variantes · grade de SKUs</h4>
                <table className="prod-var-table">
                  <thead><tr><th>SKU</th><th>Especificação</th><th>Estoque</th><th>Preço</th></tr></thead>
                  <tbody>
                    {(openProd.variants || []).map(v => (
                      <tr key={v.sku}>
                        <td className="mono">{v.sku}</td>
                        <td>{v.spec}</td>
                        <td className={"mono" + (v.stock === 0 ? " zero" : v.stock < 100 ? " low" : "")}>
                          {v.stock > 0 ? v.stock.toLocaleString("pt-BR") : <span style={{color:"var(--text-mute)"}}>sob demanda</span>}
                        </td>
                        <td className="mono">{v.price}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {openProd.suppliers && openProd.suppliers.length > 0 && (
                  <>
                    <h4 className="prod-bom-h">Fornecedores · cotação</h4>
                    <table className="prod-supp-table">
                      <thead><tr><th>Fornecedor</th><th>Custo</th><th>Prazo</th><th>Margem</th></tr></thead>
                      <tbody>
                        {openProd.suppliers
                          .slice().sort((a,b) => a.cost - b.cost)
                          .map((s, i) => (
                            <tr key={i} className={i === 0 ? "best" : ""}>
                              <td><b>{s.name}</b>{i === 0 && <span className="prod-best-badge">melhor</span>}</td>
                              <td className="mono">{s.cost.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}</td>
                              <td>{s.lead}</td>
                              <td className={"mono " + (s.margin > 50 ? "high" : s.margin > 25 ? "mid" : "low")}>+{s.margin}%</td>
                            </tr>
                          ))}
                      </tbody>
                    </table>
                  </>
                )}

                <h4 className="prod-bom-h">Composição (BOM)</h4>
                {(openProd.bom || []).map((b,i) => (
                  <div key={i} className="prod-bom-row">
                    <I.check size={12}/> {b}
                  </div>
                ))}
              </div>
            </div>
            <div className="os-drawer-actions">
              <button className="os-btn primary"><I.pencil size={13}/> Editar</button>
              <button className="os-btn ghost">Duplicar</button>
              <span className="os-bulk-spacer"/>
              <button className="os-btn ghost danger">{openProd.active?"Desativar":"Reativar"}</button>
            </div>
          </aside>
        </>
        );
      })()}
    </>
  );
}

window.ProdListPage = ProdListPage;

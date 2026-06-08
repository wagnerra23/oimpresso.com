// prod-page.jsx — Catálogo de Produtos (Fase 3)
const { useState: useStateP, useMemo: useMemoP } = React;

function ProdListPage() {
  const all = PROD_DATA.PROD_LIST;
  const [catFilter, setCatFilter]   = useStateP("all");
  const [showInactive, setShowInactive] = useStateP(false);
  const [query, setQuery]           = useStateP("");
  const [openId, setOpenId]         = useStateP(null);

  const filtered = useMemoP(() => {
    let out = all;
    if (!showInactive) out = out.filter(p => p.active);
    if (catFilter !== "all") out = out.filter(p => p.category === catFilter);
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(p => p.name.toLowerCase().includes(q) || p.id.toLowerCase().includes(q));
    }
    return out;
  }, [all, catFilter, showInactive, query]);

  const stats = useMemoP(() => ({
    total: all.length,
    ativos: all.filter(p => p.active).length,
    cats: PROD_DATA.PROD_CATEGORIES.length,
    topPop: all.filter(p => p.popularity >= 70).length,
  }), [all]);

  const openProd = openId ? all.find(p => p.id === openId) : null;

  return (
    <>
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Produtos</h1>
          <p>Catálogo de impressão e comunicação visual</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost"><I.search size={13}/> Importar</button>
          <button className="os-btn primary"><I.plus size={13}/> Novo produto</button>
        </div>
      </div>

      <div className="os-stats">
        <div className="os-stat"><small>Total</small><b>{stats.total}</b></div>
        <div className="os-stat"><small>Ativos</small><b className="ok">{stats.ativos}</b></div>
        <div className="os-stat"><small>Categorias</small><b>{stats.cats}</b></div>
        <div className="os-stat"><small>Populares</small><b>{stats.topPop}</b></div>
      </div>

      <div className="os-toolbar">
        <div className="os-tabs">
          <button className={`os-tab ${catFilter==="all"?"active":""}`} onClick={() => setCatFilter("all")}>Todos <span className="os-tab-count">{all.filter(p => p.active).length}</span></button>
          {PROD_DATA.PROD_CATEGORIES.map(cat => (
            <button key={cat} className={`os-tab ${catFilter===cat?"active":""}`} onClick={() => setCatFilter(cat)}>
              {cat} <span className="os-tab-count">{all.filter(p => p.category === cat && p.active).length}</span>
            </button>
          ))}
        </div>
        <div className="os-toolbar-r">
          <label className="prod-toggle">
            <input type="checkbox" checked={showInactive} onChange={e => setShowInactive(e.target.checked)}/>
            <span>Mostrar inativos</span>
          </label>
          <div className="os-search">
            <I.search size={13}/>
            <input type="text" placeholder="Nome, código…" value={query} onChange={e => setQuery(e.target.value)}/>
          </div>
        </div>
      </div>

      <div className="prod-grid">
        {filtered.map(p => (
          <div key={p.id} className={`prod-card ${!p.active ? "inactive" : ""} ${openId===p.id ? "selected" : ""}`} onClick={() => setOpenId(p.id)}>
            <div className="prod-card-h">
              <span className="prod-cat">{p.category}</span>
              {!p.active && <span className="prod-inactive-badge">inativo</span>}
            </div>
            <h3 className="prod-name">{p.name}</h3>
            <div className="prod-id mono">{p.id}</div>
            <div className="prod-foot">
              <div className="prod-price">
                <b className="mono">{p.price}</b>
                <small>/{p.unit}</small>
              </div>
              <div className="prod-meta">
                <span><I.clock size={11}/> {p.lead}</span>
              </div>
            </div>
            <div className="prod-pop"><div className="prod-pop-fill" style={{ width: p.popularity + "%" }}/></div>
          </div>
        ))}
        {filtered.length === 0 && (
          <div className="os-empty" style={{ gridColumn:"1/-1" }}>
            <div className="os-empty-ico"><I.product size={20}/></div>
            <b>Nenhum produto</b>
            <small>Ajuste filtros ou cadastre um novo</small>
          </div>
        )}
      </div>

      {openProd && (
        <>
          <div className="os-drawer-back" onClick={() => setOpenId(null)}/>
          <aside className="os-drawer">
            <div className="os-drawer-h">
              <div>
                <div className="os-drawer-id mono">{openProd.id}</div>
                <h2>{openProd.name}</h2>
                <div className="os-drawer-meta">{openProd.category}</div>
              </div>
              <button className="os-icon-btn" onClick={() => setOpenId(null)}><I.close size={16}/></button>
            </div>
            <div className="os-drawer-body">
              <div className="cli-kpis">
                <div className="cli-kpi"><b className="mono">{openProd.price}</b><small>Preço/{openProd.unit}</small></div>
                <div className="cli-kpi"><b>{openProd.lead}</b><small>Prazo</small></div>
                <div className="cli-kpi"><b>{openProd.popularity}%</b><small>Popularidade</small></div>
                <div className="cli-kpi"><b className={openProd.active?"ok":"muted"}>{openProd.active?"ativo":"inativo"}</b><small>Status</small></div>
              </div>
              <div className="cli-section">
                <div className="cli-row"><span>Categoria</span><b>{openProd.category}</b></div>
                <div className="cli-row"><span>Unidade</span><b>{openProd.unit}</b></div>
                <div className="cli-row"><span>Estoque</span><b>{openProd.stock}</b></div>
                <h4 className="prod-bom-h">Composição (BOM)</h4>
                {openProd.bom.map((b,i) => (
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
      )}
    </>
  );
}

window.ProdListPage = ProdListPage;

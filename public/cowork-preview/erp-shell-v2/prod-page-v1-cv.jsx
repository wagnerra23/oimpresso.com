// prod-page.jsx — Catálogo de Produtos (Fase 3)
const { useState: useStateP, useMemo: useMemoP } = React;

function ProdListPage() {
  const all = PROD_DATA.PROD_LIST;
  const [catFilter, setCatFilter]   = useStateP("all");
  const [showInactive, setShowInactive] = useStateP(false);
  const [query, setQuery]           = useStateP("");
  const [openId, setOpenId]         = useStateP(null);
  const [view, setView]             = useStateP("grade"); // grade | cards (grade é o padrão)

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
          <div className="prod-view-toggle">
            <button className={view === "grade" ? "active" : ""} onClick={() => setView("grade")}>
              <I.grid size={11}/> Grade
            </button>
            <button className={view === "cards" ? "active" : ""} onClick={() => setView("cards")}>
              <I.product size={11}/> Cards
            </button>
          </div>
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

      {view === "grade" ? (
        <div className="prod-grade-wrap">
          <div className="prod-grade-info">
            <b>Catálogo · visão densa</b>
            <span>tudo o que você precisa em uma tabela · cole órdem em segundos</span>
          </div>
          <table className="prod-grade prod-grade-info-table">
            <thead>
              <tr>
                <th className="prod-grade-prod">Produto</th>
                <th>Marca</th>
                <th>Código OEM</th>
                <th className="r">Estoque</th>
                <th className="r">Preço venda</th>
                <th className="r">Custo mínimo</th>
                <th className="r">Margem</th>
                <th>Prazo</th>
                <th className="r">Vendas/mês</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(p => {
                const stock = PROD_DATA.prodStock(p);
                const st = PROD_DATA.prodStockStatus(p);
                const suppliers = (p.suppliers || []).slice().sort((a,b) => a.cost - b.cost);
                const best = suppliers[0];
                const variants = p.variants || [];
                const minPrice = variants.length > 0
                  ? Math.min(...variants.map(v => parseFloat((v.price || "0").replace(/[^\d,]/g, "").replace(",", ".")) || 0))
                  : 0;
                const popMonth = Math.round(p.popularity * 0.6); // estimativa de vendas/mês
                return (
                  <tr key={p.id} onClick={() => setOpenId(p.id)} style={{ cursor: "pointer" }} className={!p.active ? "prod-grade-inactive" : ""}>
                    <td className="prod-grade-prod">
                      <div className="prod-grade-thumb" style={{ background: `linear-gradient(135deg, oklch(0.75 0.13 ${p.img.hue}), oklch(0.50 0.16 ${p.img.hue}))` }}>{p.img.label}</div>
                      <div className="prod-grade-prod-meta">
                        <b>{p.name}</b>
                        <small>{p.id} · {p.category} · {variants.length} var.</small>
                        <span className={`prod-stock-badge ${st.c}`}>{stock > 0 ? `${stock.toLocaleString("pt-BR")} ${p.unit === "milheiro" ? "un" : p.unit}` : st.l}</span>
                      </div>
                    </td>
                    <td>{p.brand ? <b>{p.brand}</b> : <span style={{color: "var(--text-mute)"}}>—</span>}</td>
                    <td>
                      {p.oem && p.oem.length > 0 ? (
                        <>
                          <code className="prod-oem-inline">{p.oem[0]}</code>
                          {p.oem.length > 1 && <small style={{color:"var(--text-mute)", marginLeft:4, fontSize:10}}>+{p.oem.length - 1}</small>}
                        </>
                      ) : <span style={{color: "var(--text-mute)"}}>—</span>}
                    </td>
                    <td className="r mono">{stock > 0 ? stock.toLocaleString("pt-BR") : <span style={{color:"var(--text-mute)"}}>0</span>}</td>
                    <td className="r mono"><b>{p.price}</b>{minPrice > 0 && variants.length > 1 && <small style={{display:"block", fontSize:10, color:"var(--text-mute)"}}>a partir de R$ {minPrice.toFixed(2)}</small>}</td>
                    <td className="r mono">{best ? best.cost.toLocaleString("pt-BR", {style:"currency", currency:"BRL"}) : <span style={{color:"var(--text-mute)"}}>—</span>}{best && <small style={{display:"block", fontSize:10, color:"var(--text-mute)"}}>{best.name}</small>}</td>
                    <td className="r">{best ? <span className={`prod-margin-pill ${best.margin > 50 ? "high" : best.margin > 25 ? "mid" : "low"}`}>+{best.margin}%</span> : <span style={{color:"var(--text-mute)"}}>—</span>}</td>
                    <td><span className="prod-lead-pill">{p.lead}</span></td>
                    <td className="r mono">
                      <b>{popMonth}</b>
                      <div className="prod-pop-mini"><div style={{width: p.popularity + "%"}}/></div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : (
      <div className="prod-grid">
        {filtered.map(p => {
          const total = PROD_DATA.prodStock(p);
          const st = PROD_DATA.prodStockStatus(p);
          const variants = (p.variants || []);
          return (
          <div key={p.id} className={`prod-card ${!p.active ? "inactive" : ""} ${openId===p.id ? "selected" : ""}`} onClick={() => setOpenId(p.id)}>
            {/* Thumb: gradient + label do tipo de produto */}
            <div className="prod-thumb" style={{ background: `linear-gradient(135deg, oklch(0.78 0.13 ${p.img.hue}), oklch(0.50 0.16 ${p.img.hue}))` }}>
              <span className="prod-thumb-label">{p.img.label}</span>
              <span className="prod-cat-pill">{p.category}</span>
              {!p.active && <span className="prod-inactive-badge">inativo</span>}
            </div>
            <div className="prod-card-body">
              <h3 className="prod-name">{p.name}</h3>
              <div className="prod-id mono">{p.id} · {variants.length} variante{variants.length === 1 ? "" : "s"}</div>
              <div className="prod-foot">
                <div className="prod-price">
                  <small>a partir de</small>
                  <b className="mono">{p.price}</b>
                  <span className="prod-unit">/{p.unit}</span>
                </div>
                <span className={`prod-stock-badge ${st.c}`}>
                  {total > 0 ? <><b className="mono">{total.toLocaleString("pt-BR")}</b> <span>{p.unit === "milheiro" ? "un" : p.unit}</span></> : st.l}
                </span>
              </div>
              {/* Mini-grid de variantes — chips com spec + estoque */}
              <div className="prod-variants">
                {variants.slice(0, 3).map(v => (
                  <div key={v.sku} className={"prod-var" + (v.stock === 0 ? " empty" : "")}>
                    <span className="prod-var-spec">{v.spec}</span>
                    <span className={"prod-var-stock mono" + (v.stock === 0 ? " zero" : v.stock < 100 ? " low" : "")}>
                      {v.stock > 0 ? v.stock.toLocaleString("pt-BR") : "—"}
                    </span>
                  </div>
                ))}
                {variants.length > 3 && <div className="prod-var-more">+{variants.length - 3} variantes</div>}
              </div>
              <div className="prod-pop"><div className="prod-pop-fill" style={{ width: p.popularity + "%" }}/></div>
            </div>
          </div>
        );})}
        {filtered.length === 0 && (
          <div className="os-empty" style={{ gridColumn:"1/-1" }}>
            <div className="os-empty-ico"><I.product size={20}/></div>
            <b>Nenhum produto</b>
            <small>Ajuste filtros ou cadastre um novo</small>
          </div>
        )}
      </div>

      )}

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
                <div className="cli-row"><span>Estoque total</span><b className="mono">{PROD_DATA.prodStock(openProd).toLocaleString("pt-BR")} {openProd.unit === "milheiro" ? "un" : openProd.unit}</b></div>
                {openProd.brand && <div className="cli-row"><span>Marca</span><b>{openProd.brand}</b></div>}
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
                {openProd.specs && (
                  <>
                    <h4 className="prod-bom-h">Ficha técnica</h4>
                    <table className="prod-spec-table">
                      <tbody>
                        {Object.entries(openProd.specs).map(([k, v]) => (
                          <tr key={k}><td>{k.charAt(0).toUpperCase() + k.slice(1)}</td><td className="mono">{v}</td></tr>
                        ))}
                      </tbody>
                    </table>
                  </>
                )}
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
                <h4 className="prod-bom-h">Variantes · grade de SKUs</h4>
                <table className="prod-var-table">
                  <thead><tr><th>SKU</th><th>Especificação</th><th>Estoque</th><th>Preço</th></tr></thead>
                  <tbody>
                    {(openProd.variants || []).map(v => (
                      <tr key={v.sku}>
                        <td className="mono">{v.sku}</td>
                        <td>{v.spec}</td>
                        <td className={"mono" + (v.stock === 0 ? " zero" : v.stock < 100 ? " low" : "")}>{v.stock > 0 ? v.stock.toLocaleString("pt-BR") : <span style={{color:"var(--text-mute)"}}>sob demanda</span>}</td>
                        <td className="mono">{v.price}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {openProd.compat && (
                  <>
                    <h4 className="prod-bom-h">Aplicações · compatibilidade</h4>
                    <div className="prod-compat-chips">
                      {PROD_DATA.PROD_VEHICLES.filter(v => openProd.compat[v.key]).map(v => (
                        <span key={v.key} className="prod-compat-chip">
                          <b>{v.label}</b> <small>{v.brand}</small>
                        </span>
                      ))}
                    </div>
                  </>
                )}
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

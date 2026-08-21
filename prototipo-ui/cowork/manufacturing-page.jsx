// manufacturing-page.jsx — módulo Manufacturing dentro do shell Cockpit V2.
// Espelho de Modules/Manufacturing: Receitas (ficha técnica/BOM), Ordens de produção,
// Relatório e Configurações. Dados em manufacturing-data.jsx (window.MFG); CRUD da receita
// em manufacturing-recipe.jsx; produção/relatório/config em manufacturing-producao.jsx.
// CSS em manufacturing-page.css (escopo .mfg-root). Expõe window.ManufacturingPage.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const I = window.I;

const ABAS = [
  { id: "receitas", l: "Receitas" },
  { id: "insumos", l: "Insumos" },
  { id: "producao", l: "Ordens de produção" },
  { id: "relatorio", l: "Relatório" },
  { id: "config", l: "Configurações" },
];

function ManufacturingPage({ initialView }) {
  const MFG = window.MFG;
  const { fmt, num, custos } = MFG;
  const [aba, setAba] = useState(initialView || "receitas");
  const [recipes, setRecipes] = useState(MFG.RECIPES);
  const [producoes, setProducoes] = useState(MFG.PRODUCOES);
  const [settings, setSettings] = useState(MFG.SETTINGS);
  const [perms, setPerms] = useState({ ver: true, criar: true, editar: true, prod: true });
  const [tela, setTela] = useState(null); // {tipo:'receita-edit'|'op-form', id}
  const [novaOpen, setNovaOpen] = useState(false);
  const [opAberta, setOpAberta] = useState(null);

  // ── Receitas: filtros/lista ──
  const [q, setQ] = useState("");
  const [cat, setCat] = useState("Todas");
  const [kpi, setKpi] = useState(null);
  const [sel, setSel] = useState([]);
  const [openId, setOpenId] = useState(null);
  const [ord, setOrd] = useState({ k: "name", dir: "asc" });
  const [pag, setPag] = useState(1);
  const [confirma, setConfirma] = useState(null); // receita a excluir
  const [imprimir, setImprimir] = useState(null);
  const [toast, setToast] = useState(null);
  const buscaRef = useRef(null);
  const aviso = (t) => { setToast(t); setTimeout(() => setToast(null), 2600); };

  const linhas = useMemo(() => recipes.map((r) => ({ r, c: custos(r) })), [recipes, custos]);
  useEffect(() => {
    const onKey = (e) => {
      const emCampo = /^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName || ""));
      if (e.key === "/" && !emCampo && aba === "receitas") { e.preventDefault(); buscaRef.current && buscaRef.current.focus(); }
      if (e.key === "Escape") { setOpenId(null); setOpAberta(null); setNovaOpen(false); setConfirma(null); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [aba]);
  const CATS = useMemo(() => ["Todas", ...Array.from(new Set(recipes.map((r) => r.cat)))], [recipes]);
  const CHAVES = { name: (l) => l.r.name.toLowerCase(), cat: (l) => l.r.cat + l.r.sub, qtd: (l) => l.c.qtdLiq, total: (l) => l.c.total, unit: (l) => l.c.unit, venda: (l) => l.r.venda, margem: (l) => l.c.margem };
  const filtradas = useMemo(() => {
    const base = linhas.filter(({ r, c }) => {
      if (cat !== "Todas" && r.cat !== cat) return false;
      if (kpi === "margem" && c.margem >= 45) return false;
      if (kpi === "custo" && r.waste < 8) return false;
      const t = q.trim().toLowerCase();
      return !t || (r.name + " " + r.sku + " " + r.cat + " " + r.sub).toLowerCase().includes(t);
    });
    const f = CHAVES[ord.k] || CHAVES.name;
    return base.sort((a, b) => { const va = f(a), vb = f(b); const s = va > vb ? 1 : va < vb ? -1 : 0; return ord.dir === "asc" ? s : -s; });
  }, [q, cat, kpi, linhas, ord]);
  const POR_PAG = 10;
  const nPags = Math.max(1, Math.ceil(filtradas.length / POR_PAG));
  const pagina = Math.min(pag, nPags);
  const visiveis = filtradas.slice((pagina - 1) * POR_PAG, pagina * POR_PAG);
  const ordenar = (k) => { setOrd((o) => ({ k, dir: o.k === k && o.dir === "asc" ? "desc" : "asc" })); setPag(1); };
  const Th = ({ k, children, r: right }) => (
    <button className={"mfg-th sort" + (right ? " r" : "") + (ord.k === k ? " act" : "")} onClick={() => ordenar(k)}>
      {right && <span className="ind">{ord.k === k ? (ord.dir === "asc" ? "↑" : "↓") : "⇵"}</span>}{children}
      {!right && <span className="ind">{ord.k === k ? (ord.dir === "asc" ? "↑" : "↓") : "⇵"}</span>}
    </button>
  );

  const magra = linhas.filter(({ c }) => c.margem < 45).length;
  const perda = linhas.filter(({ r }) => r.waste >= 8).length;
  const custoMed = linhas.length ? linhas.reduce((s, l) => s + l.c.unit, 0) / linhas.length : 0;
  const toggle = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
  const allSel = filtradas.length > 0 && filtradas.every(({ r }) => sel.includes(r.id));
  const aberta = linhas.find(({ r }) => r.id === openId);

  // ── Ações ──
  const criarReceita = ({ nome, cat: c2, sub, un, qtd, clone }) => {
    const src = clone ? recipes.find((r) => r.id === clone) : null;
    const id = Math.max(0, ...recipes.map((r) => r.id)) + 1;
    const nova = {
      id, name: nome, sku: "MFG-" + String(id).padStart(4, "0"), cat: c2, sub, qtd, un,
      waste: src ? src.waste : 0, extra: src ? src.extra : 0, custoTipo: src ? src.custoTipo : "fixo",
      venda: src ? src.venda : 0, atualizado: "agora", produto: "—",
      grupos: src ? JSON.parse(JSON.stringify(src.grupos)) : [],
    };
    setRecipes((rs) => [...rs, nova]);
    setNovaOpen(false);
    setTela({ tipo: "receita-edit", id });
  };
  const salvarReceita = (r) => {
    setRecipes((rs) => rs.map((x) => x.id === r.id ? { ...r, atualizado: "agora" } : x));
    setTela(null);
    aviso("Receita salva · custo recalculado");
  };
  const excluirReceita = (id) => {
    setRecipes((rs) => rs.filter((r) => r.id !== id));
    setTela(null); setOpenId(null); setConfirma(null);
    aviso("Receita excluída");
  };
  const salvarOP = (op0) => {
    // ao finalizar, congela o custo do dia (histórico) — leitura ao vivo só vale pro rascunho
    const c = MFG.consumoOP({ ...op0, custoSnap: null }, recipes);
    const op = op0.final ? { ...op0, custoSnap: op0.custoSnap != null ? op0.custoSnap : Number(c.vivo.toFixed(2)) } : { ...op0, custoSnap: null };
    setProducoes((ps) => op.id ? ps.map((x) => x.id === op.id ? op : x) : [...ps, { ...op, id: Math.max(0, ...ps.map((p) => p.id)) + 1 }]);
    setTela(null);
    aviso(op.final ? "Produção finalizada · estoque movimentado" : "Rascunho salvo");
  };
  const atualizarPrecos = () => {
    setRecipes((rs) => rs.map((r) => sel.includes(r.id) ? { ...r, venda: Number((custos(r).unit * 2).toFixed(2)), atualizado: "agora" } : r));
    aviso(sel.length + " preço(s) de venda atualizados a partir do custo");
    setSel([]);
  };

  // ── Telas full (editor / form) tomam o corpo do módulo ──
  if (tela && tela.tipo === "receita-edit") {
    const r = recipes.find((x) => x.id === tela.id);
    if (r) return (
      <div className="mfg-root">
        <window.MfgIngredientesEditor recipe={r} settings={settings} perms={perms}
          onSave={salvarReceita} onCancel={() => setTela(null)} onDelete={() => setConfirma(r)} />
      </div>
    );
  }
  if (tela && tela.tipo === "op-form") {
    return (
      <div className="mfg-root">
        <window.MfgProducaoForm recipes={recipes} producoes={producoes} settings={settings} perms={perms}
          editing={tela.id ? producoes.find((p) => p.id === tela.id) : null}
          onSave={salvarOP} onCancel={() => setTela(null)} />
      </div>
    );
  }

  const rascunhos = producoes.filter((p) => !p.final).length;

  return (
    <div className="mfg-root" data-screen-label={"Manufacturing · " + (ABAS.find((a) => a.id === aba) || {}).l}>
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Manufacturing</h1>
          <p>{recipes.length} receitas · {producoes.length} ordens de produção · custo recalculado pelo preço atual dos ingredientes</p>
        </div>
        <div className="os-page-h-r">
          {aba === "receitas" && perms.criar && <button className="os-btn primary" onClick={() => setNovaOpen(true)}><I.plus size={13} /> Nova receita</button>}
          {aba === "producao" && perms.criar && <button className="os-btn primary" onClick={() => setTela({ tipo: "op-form", id: null })}><I.plus size={13} /> Nova produção</button>}
        </div>
      </div>

      <nav className="mfg-tabs" aria-label="Manufacturing">
        {ABAS.filter((a) => a.id !== "producao" || perms.prod).map((a) => (
          <button key={a.id} className={"mfg-tab" + (aba === a.id ? " act" : "")} onClick={() => setAba(a.id)}>
            {a.l}
            {a.id === "receitas" && <span className="mfg-tab-n">{recipes.length}</span>}
            {a.id === "producao" && <span className="mfg-tab-n">{producoes.length}{rascunhos ? " · " + rascunhos + " rasc." : ""}</span>}
          </button>
        ))}
      </nav>

      {aba === "receitas" && (
        <>
          <div className="mfg-kpis">
            <div className="mfg-kpi">
              <span className="mfg-kpi-l">Custo médio / unidade</span>
              <span className="mfg-kpi-v">{fmt(custoMed)}</span>
              <span className="mfg-kpi-s">média das {recipes.length} receitas</span>
            </div>
            <button className={"mfg-kpi" + (kpi === "margem" ? " act" : "")} onClick={() => setKpi(kpi === "margem" ? null : "margem")}>
              <span className="mfg-kpi-l">Margem abaixo de 45%</span>
              <span className="mfg-kpi-v warn">{magra}</span>
              <span className="mfg-kpi-s">preço de venda desatualizado</span>
            </button>
            <button className={"mfg-kpi" + (kpi === "custo" ? " act" : "")} onClick={() => setKpi(kpi === "custo" ? null : "custo")}>
              <span className="mfg-kpi-l">Desperdício ≥ 8%</span>
              <span className="mfg-kpi-v warn">{perda}</span>
              <span className="mfg-kpi-s">revisar plotagem / encaixe</span>
            </button>
            <div className="mfg-kpi">
              <span className="mfg-kpi-l">Produção do mês</span>
              <span className="mfg-kpi-v">{producoes.filter((p) => p.final).length}</span>
              <span className="mfg-kpi-s">{rascunhos} rascunho{rascunhos === 1 ? "" : "s"} em aberto</span>
            </div>
          </div>

          <div className="mfg-bar">
            <div className="mfg-s">
              <I.search size={14} className="ic" />
              <input ref={buscaRef} placeholder="Buscar receita por nome, SKU, categoria…  (tecla /)" value={q} onChange={(e) => { setQ(e.target.value); setPag(1); }} />
            </div>
            <div className="mfg-chips">
              {CATS.map((c) => <button key={c} className={"mfg-chip" + (cat === c ? " act" : "")} onClick={() => setCat(c)}>{c}</button>)}
            </div>
          </div>

          <div className="mfg-tablewrap">
            <div className="mfg-table">
              <div className="mfg-tr mfg-thead">
                <input type="checkbox" checked={allSel} onChange={() => setSel(allSel ? [] : filtradas.map(({ r }) => r.id))} aria-label="Selecionar todas" />
                <Th k="name">Receita</Th>
                <Th k="cat">Categoria</Th>
                <Th k="qtd" r>Quantidade</Th>
                <Th k="total" r>Custo total</Th>
                <Th k="unit" r>Custo unitário</Th>
                <Th k="venda" r>Venda</Th>
                <Th k="margem" r>Margem</Th>
              </div>
              {visiveis.map(({ r, c }) => (
                <div key={r.id} className={"mfg-tr mfg-row" + (sel.includes(r.id) ? " sel" : "")} onClick={() => setOpenId(r.id)}>
                  <input type="checkbox" checked={sel.includes(r.id)} onClick={(e) => e.stopPropagation()} onChange={() => toggle(r.id)} aria-label={"Selecionar " + r.name} />
                  <span className="mfg-name"><b>{r.name}</b><span className="mfg-sku">{r.sku} · {r.grupos.reduce((s, g) => s + g.itens.length, 0)} ingredientes</span></span>
                  <span className="mfg-cat">{r.cat} <i>/ {r.sub}</i></span>
                  <span className="mfg-num r">{r.subUn ? num(c.qtdLiq * r.subFator, 2) : num(c.qtdLiq, 2)}<span className="mfg-u">{r.subUn || r.un}</span></span>
                  <span className="mfg-num r">{fmt(c.total)}</span>
                  <span className="mfg-num r">{fmt(c.unit)}</span>
                  <span className="mfg-num dim r">{fmt(r.venda)}</span>
                  <span className="r"><span className={"mfg-pill " + (c.margem >= 55 ? "ok" : c.margem >= 45 ? "warn" : "bad")}>{num(c.margem, 0)}%</span></span>
                </div>
              ))}
              {filtradas.length === 0 && (
                <div className="mfg-empty">
                  <b>Nenhuma receita encontrada</b>
                  <span>Ajuste a busca, troque a categoria ou limpe o filtro de KPI.</span>
                </div>
              )}
            </div>
            {filtradas.length > POR_PAG && (
              <div className="mfg-pag">
                <span>{(pagina - 1) * POR_PAG + 1}–{Math.min(pagina * POR_PAG, filtradas.length)} de {filtradas.length}</span>
                <span className="sp" />
                <button disabled={pagina === 1} onClick={() => setPag(pagina - 1)}>‹</button>
                {Array.from({ length: nPags }, (_, i) => i + 1).map((n) => (
                  <button key={n} className={n === pagina ? "act" : ""} onClick={() => setPag(n)}>{n}</button>
                ))}
                <button disabled={pagina === nPags} onClick={() => setPag(pagina + 1)}>›</button>
              </div>
            )}
          </div>

          {sel.length > 0 && (
            <div className="mfg-bulk">
              <b>{sel.length} receita{sel.length > 1 ? "s" : ""} selecionada{sel.length > 1 ? "s" : ""}</b>
              <span className="sp" />
              <button className="os-btn ghost" onClick={() => setSel([])}>Limpar</button>
              <button className="os-btn ghost" onClick={() => setImprimir({ itens: linhas.filter(({ r }) => sel.includes(r.id)), semCusto: false })}><I.print size={13} /> Imprimir fichas</button>
              {settings.permitirPreco && perms.editar && <button className="os-btn primary" onClick={atualizarPrecos}>Atualizar preço de venda do produto</button>}
            </div>
          )}
        </>
      )}

      {aba === "insumos" && <window.MfgInsumosView recipes={recipes} onAbrirReceita={(id) => { setAba("receitas"); setOpenId(id); }} />}

      {aba === "producao" && perms.prod && (
        <window.MfgProducaoView producoes={producoes} recipes={recipes} perms={perms}
          onNew={() => setTela({ tipo: "op-form", id: null })} onOpen={(id) => setOpAberta(id)} />
      )}

      {aba === "relatorio" && <window.MfgRelatorio producoes={producoes} recipes={recipes} />}

      {aba === "config" && <window.MfgConfig settings={settings} setSettings={(s) => { setSettings(s); aviso("Configurações atualizadas"); }} perms={perms} setPerms={setPerms} />}

      {aberta && <RecipeDrawer r={aberta.r} c={aberta.c} perms={perms} settings={settings}
        onClose={() => setOpenId(null)}
        onEdit={() => { setOpenId(null); setTela({ tipo: "receita-edit", id: aberta.r.id }); }}
        onImprimir={(semCusto) => setImprimir({ itens: [aberta], semCusto })}
        onProduzir={() => { setOpenId(null); setTela({ tipo: "op-form", id: null }); }} />}

      {confirma && (
        <>
          <div className="mfg-scrim" onClick={() => setConfirma(null)} />
          <div className="mfg-modal sm" role="dialog" aria-label="Excluir receita">
            <div className="mfg-modal-h"><b>Excluir receita</b><button className="mfg-x" onClick={() => setConfirma(null)} aria-label="Fechar">✕</button></div>
            <div className="mfg-modal-b">
              <p>Excluir <b>{confirma.name}</b> apaga a ficha técnica e os {confirma.grupos.reduce((s, g) => s + g.itens.length, 0)} ingredientes. Ordens de produção já lançadas continuam com o custo registrado.</p>
              <p>Não dá pra desfazer.</p>
            </div>
            <div className="mfg-modal-f">
              <button className="os-btn ghost" onClick={() => setConfirma(null)}>Cancelar</button>
              <button className="os-btn ghost danger" onClick={() => excluirReceita(confirma.id)}>Excluir receita</button>
            </div>
          </div>
        </>
      )}

      {imprimir && <window.MfgFichaPrint itens={imprimir.itens} semCusto={imprimir.semCusto} onDone={() => setImprimir(null)} />}

      {opAberta && <window.MfgProducaoDrawer op={producoes.find((p) => p.id === opAberta)} recipes={recipes}
        onClose={() => setOpAberta(null)} onEdit={() => { const id = opAberta; setOpAberta(null); setTela({ tipo: "op-form", id }); }} />}

      {novaOpen && <window.MfgNovaReceita recipes={recipes} onClose={() => setNovaOpen(false)} onCreate={criarReceita} />}

      {toast && <div className="mfg-toast">{toast}</div>}
    </div>
  );
}

function RecipeDrawer({ r, c, perms, settings, onClose, onEdit, onProduzir, onImprimir }) {
  const { bySku, multDe, fmt, num } = window.MFG;
  return (
    <>
      <div className="mfg-scrim" onClick={onClose} />
      <aside className="mfg-drw" role="dialog" aria-label={"Receita " + r.name}>
        <div className="mfg-drw-h">
          <div>
            <h2>{r.name}</h2>
            <p>{r.sku} · {r.cat} / {r.sub} · rende {num(c.qtdLiq, 2)} {r.un} · atualizado {r.atualizado}</p>
          </div>
          <button className="mfg-x" onClick={onClose} aria-label="Fechar">✕</button>
        </div>
        <div className="mfg-drw-b">
          {r.grupos.map((g, gi) => {
            const sub = g.itens.reduce((a, i) => { const p = bySku(i.sku); return a + i.q * (p ? p.c : 0) * multDe(i); }, 0);
            return (
              <div className="mfg-grp" key={g.g + gi}>
                <div className="mfg-grp-h"><b>{g.g}</b><span className="v">{fmt(sub)}</span></div>
                {g.itens.map((i, ii) => {
                  const p = bySku(i.sku) || { n: i.sku, u: "", c: 0 };
                  return (
                    <div className="mfg-ing" key={i.sku + ii}>
                      <span className="n">{p.n}<small>{i.sku}{multDe(i) > 1 ? " · " + num(i.q * multDe(i), 2) + " " + p.u : ""}</small></span>
                      <span className="m">{num(i.q, i.q < 1 ? 3 : 2)} {i.subUn || p.u}</span>
                      <span className="m">{fmt(p.c)}</span>
                      <span className="m tot">{fmt(i.q * p.c * multDe(i))}</span>
                    </div>
                  );
                })}
                {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
              </div>
            );
          })}

          <div className="mfg-sec"><span>Custo</span><span className="ln" /></div>
          <dl className="mfg-tot">
            <dt>Ingredientes</dt><dd>{fmt(c.ing)}</dd>
            <dt>Custo extra ({r.custoTipo === "percentual" ? r.extra + "% sobre ingredientes" : r.custoTipo === "unidade" ? window.MFG.fmt(r.extra) + " por " + r.un + " produzido" : "valor fixo"})</dt><dd>{fmt(c.extra)}</dd>
            <dt>Desperdício</dt><dd>{num(r.waste, 0)}% · rende {num(c.qtdLiq, 2)} de {num(r.qtd, 2)} {r.un}</dd>
            {r.subUn && <><dt>Sub-unidade de saída</dt><dd>{num(c.qtdLiq * r.subFator, 2)} {r.subUn}</dd></>}
            <hr />
            <dt style={{ fontWeight: 600, color: "var(--text)" }}>Custo por {r.un}</dt>
            <dd style={{ fontSize: 15, fontWeight: 600, color: "var(--accent)" }}>{fmt(c.unit)}</dd>
            <dt>Preço de venda atual</dt><dd>{fmt(r.venda)}</dd>
            <dt>Margem</dt><dd>{num(c.margem, 1)}%</dd>
          </dl>
          <p className="mfg-note">O custo é recalculado a cada leitura a partir do preço atual dos ingredientes — a receita não guarda valor congelado. Uma compra de insumo salva em <button className="mfg-link" onClick={() => window.__go && window.__go("compras")}>Compras</button> muda este número.</p>
        </div>
        <div className="mfg-drw-f">
          <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          <button className="os-btn ghost" onClick={() => onImprimir(false)}><I.print size={13} /> Ficha com custo</button>
          <button className="os-btn ghost" onClick={() => onImprimir(true)}>Via de produção</button>
          {perms.prod && <button className="os-btn ghost" onClick={onProduzir}>Produzir</button>}
          {perms.editar && <button className="os-btn ghost" onClick={onEdit}><I.pencil size={13} /> Editar ingredientes</button>}
        </div>
      </aside>
    </>
  );
}

window.ManufacturingPage = ManufacturingPage;
})();

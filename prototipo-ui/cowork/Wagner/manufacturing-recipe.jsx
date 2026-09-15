// manufacturing-recipe.jsx — Onda 1: CRUD da receita.
// Espelha recipe/create.blade.php (modal: variação + clonar receita) e
// recipe/add_ingredients.blade.php (editor de ingredientes, grupos, desperdício,
// custo extra fixo/percentual, quantidade produzida, preço final).
// Expõe window.MfgNovaReceita e window.MfgIngredientesEditor.
(() => {
const { useState, useMemo, useRef, useEffect } = React;
const I = window.I;

const G = () => window.MFG;

function Campo({ label, hint, children, w }) {
  return (
    <label className="mfg-fld" style={w ? { width: w } : null}>
      <span>{label}</span>
      {children}
      {hint && <small>{hint}</small>}
    </label>
  );
}

// ── Modal "Nova receita" (recipe/create) ──
function MfgNovaReceita({ recipes, onClose, onCreate }) {
  const { INSUMOS, num } = G();
  const [nome, setNome] = useState("");
  const [cat, setCat] = useState("Comunicação visual");
  const [sub, setSub] = useState("");
  const [un, setUn] = useState("m²");
  const [qtd, setQtd] = useState(1);
  const [clone, setClone] = useState("");
  const existe = recipes.some((r) => r.name.trim().toLowerCase() === nome.trim().toLowerCase());
  const podeSalvar = nome.trim().length > 2 && !existe && Number(qtd) > 0;

  return (
    <>
      <div className="mfg-scrim" onClick={onClose} />
      <div className="mfg-modal" role="dialog" aria-label="Nova receita">
        <div className="mfg-modal-h"><b>Nova receita</b><button className="mfg-x" onClick={onClose} aria-label="Fechar">✕</button></div>
        <div className="mfg-modal-b">
          <Campo label="Produto / variação" hint="a receita pertence a uma variação do catálogo">
            <input className="mfg-inp" value={nome} onChange={(e) => setNome(e.target.value)} placeholder="ex. Banner lona 440g — acabado" />
          </Campo>
          {existe && <p className="mfg-err">Já existe receita para essa variação — edite a receita atual em vez de criar outra.</p>}
          <div className="mfg-row2">
            <Campo label="Categoria">
              <select className="mfg-inp" value={cat} onChange={(e) => setCat(e.target.value)}>
                <option>Comunicação visual</option><option>Têxtil</option><option>Brindes</option>
              </select>
            </Campo>
            <Campo label="Subcategoria">
              <input className="mfg-inp" value={sub} onChange={(e) => setSub(e.target.value)} placeholder="ex. Banner" />
            </Campo>
          </div>
          <div className="mfg-row2">
            <Campo label="Quantidade produzida">
              <input className="mfg-inp" type="number" min="0" step="0.01" value={qtd} onChange={(e) => setQtd(e.target.value)} />
            </Campo>
            <Campo label="Unidade">
              <select className="mfg-inp" value={un} onChange={(e) => setUn(e.target.value)}>
                <option>m²</option><option>un</option><option>m</option><option>kg</option><option>L</option>
              </select>
            </Campo>
          </div>
          <Campo label="Clonar ingredientes de" hint="opcional — copia grupos, quantidades e desperdício da receita escolhida">
            <select className="mfg-inp" value={clone} onChange={(e) => setClone(e.target.value)}>
              <option value="">Começar vazia</option>
              {recipes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
          </Campo>
          <p className="mfg-note">{INSUMOS.length} insumos disponíveis no catálogo · custo lido do preço de compra atual.</p>
        </div>
        <div className="mfg-modal-f">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!podeSalvar}
            onClick={() => onCreate({ nome: nome.trim(), cat, sub: sub.trim() || "—", un, qtd: Number(qtd), clone: clone ? Number(clone) : null })}>
            Criar e adicionar ingredientes
          </button>
        </div>
      </div>
    </>
  );
}

// ── Busca de insumo (get-ingredient-row) ──
function BuscaInsumo({ onPick, onCancel }) {
  const { INSUMOS, fmt } = G();
  const [q, setQ] = useState("");
  const ref = useRef(null);
  useEffect(() => { ref.current && ref.current.focus(); }, []);
  const res = INSUMOS.filter((i) => (i.n + " " + i.sku).toLowerCase().includes(q.trim().toLowerCase())).slice(0, 7);
  return (
    <div className="mfg-pick">
      <div className="mfg-s sm">
        <I.search size={13} className="ic" />
        <input ref={ref} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar insumo por nome ou SKU…"
          onKeyDown={(e) => { if (e.key === "Escape") onCancel(); if (e.key === "Enter" && res[0]) onPick(res[0]); }} />
        <button className="mfg-pick-x" onClick={onCancel}>esc</button>
      </div>
      <div className="mfg-pick-list">
        {res.map((i) => (
          <button key={i.sku} className="mfg-pick-i" onClick={() => onPick(i)}>
            <span className="n">{i.n}<small>{i.sku} · estoque {i.est} {i.u}</small></span>
            <span className="c">{fmt(i.c)}<small>/ {i.u}</small></span>
          </button>
        ))}
        {res.length === 0 && <p className="mfg-pick-empty">Nenhum insumo com esse termo.</p>}
      </div>
    </div>
  );
}

// ── Editor de ingredientes (add_ingredients) ──
function MfgIngredientesEditor({ recipe, settings, perms, onSave, onCancel, onDelete }) {
  const { GRUPOS, bySku, subUnsDe, multDe, custos, fmt, num } = G();
  const [r, setR] = useState(() => JSON.parse(JSON.stringify(recipe)));
  const [addIn, setAddIn] = useState(null); // índice do grupo recebendo ingrediente
  const [novoGrupo, setNovoGrupo] = useState(false);
  const podeEditar = perms.editar;
  const travado = settings.travarQtd;
  const c = useMemo(() => custos(r), [r]);
  const set = (patch) => setR((x) => ({ ...x, ...patch }));

  const setItem = (gi, ii, q) => setR((x) => {
    const g = x.grupos.map((gr, i) => i !== gi ? gr : { ...gr, itens: gr.itens.map((it, j) => j !== ii ? it : { ...it, q: Number(q) }) });
    return { ...x, grupos: g };
  });
  const setSubUn = (gi, ii, u) => setR((x) => ({ ...x, grupos: x.grupos.map((gr, i) => i !== gi ? gr : { ...gr, itens: gr.itens.map((it, j) => {
    if (j !== ii) return it;
    const s = subUnsDe(it.sku).find((z) => z.u === u);
    return { ...it, subUn: s ? s.u : null, mult: s ? s.m : 1 };
  }) }) }));
  const delItem = (gi, ii) => setR((x) => ({ ...x, grupos: x.grupos.map((gr, i) => i !== gi ? gr : { ...gr, itens: gr.itens.filter((_, j) => j !== ii) }) }));
  const addItem = (gi, ins) => { setR((x) => ({ ...x, grupos: x.grupos.map((gr, i) => i !== gi ? gr : { ...gr, itens: [...gr.itens, { sku: ins.sku, q: 1 }] }) })); setAddIn(null); };
  const delGrupo = (gi) => setR((x) => ({ ...x, grupos: x.grupos.filter((_, i) => i !== gi) }));
  const addGrupo = (nome) => { setR((x) => ({ ...x, grupos: [...x.grupos, { g: nome, itens: [] }] })); setNovoGrupo(false); };
  const nIng = r.grupos.reduce((s, g) => s + g.itens.length, 0);

  return (
    <div className="mfg-ed">
      <div className="mfg-crumb">
        <button onClick={onCancel}>Receitas</button><span>/</span><b>{r.name || "Nova receita"}</b>
        <span className="sp" />
        <span className="mfg-crumb-meta">{r.sku} · {nIng} ingredientes em {r.grupos.length} grupos</span>
      </div>

      <div className="mfg-ed-cols">
        <div className="mfg-ed-main">
          {r.grupos.map((g, gi) => {
            const sub = g.itens.reduce((a, i) => { const p = bySku(i.sku); return a + i.q * (p ? p.c : 0) * multDe(i); }, 0);
            return (
              <div className="mfg-grp" key={g.g + gi}>
                <div className="mfg-grp-h">
                  <b>{g.g}</b>
                  <span className="mfg-tab-n">{g.itens.length}</span>
                  <span className="v">{fmt(sub)}</span>
                  {podeEditar && <button className="mfg-mini danger" onClick={() => delGrupo(gi)} title="Remover grupo">✕</button>}
                </div>
                <div className="mfg-ing mfg-ing6 mfg-ing-h">
                  <span className="n">Ingrediente</span><span className="m">Quantidade</span><span className="m">Unidade</span><span className="m">Custo unit.</span><span className="m">Subtotal</span><span />
                </div>
                {g.itens.map((it, ii) => {
                  const p = bySku(it.sku) || { n: it.sku, u: "", c: 0 };
                  return (
                    <div className="mfg-ing mfg-ing6" key={it.sku + ii}>
                      <span className="n">{p.n}<small>{it.sku}{multDe(it) > 1 ? " · equivale a " + num(it.q * multDe(it), 2) + " " + p.u : ""}</small></span>
                      <span className="m">
                        {podeEditar && !travado
                          ? <input className="mfg-inp num" type="number" min="0" step="0.001" value={it.q} onChange={(e) => setItem(gi, ii, e.target.value)} />
                          : <span>{num(it.q, it.q < 1 ? 3 : 2)}</span>}
                      </span>
                      <span className="m">
                        {subUnsDe(it.sku).length > 0 && podeEditar
                          ? <select className="mfg-inp sel" value={it.subUn || ""} onChange={(ev) => setSubUn(gi, ii, ev.target.value)}>
                              <option value="">{p.u}</option>
                              {subUnsDe(it.sku).map((s) => <option key={s.u} value={s.u}>{s.u}</option>)}
                            </select>
                          : <em className="mfg-u">{it.subUn || p.u}</em>}
                      </span>
                      <span className="m">{fmt(p.c)}<em className="mfg-u">/ {p.u}</em></span>
                      <span className="m tot">{fmt(it.q * p.c * multDe(it))}</span>
                      <span>{podeEditar && <button className="mfg-mini danger" onClick={() => delItem(gi, ii)} title="Remover">✕</button>}</span>
                    </div>
                  );
                })}
                {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
                {podeEditar && (addIn === gi
                  ? <BuscaInsumo onPick={(ins) => addItem(gi, ins)} onCancel={() => setAddIn(null)} />
                  : <button className="mfg-add" onClick={() => setAddIn(gi)}><I.plus size={12} /> Ingrediente em {g.g}</button>)}
              </div>
            );
          })}

          {podeEditar && (novoGrupo
            ? <div className="mfg-pick">
                <div className="mfg-pick-list row">
                  {GRUPOS.filter((n) => !r.grupos.some((g) => g.g === n)).map((n) => (
                    <button key={n} className="mfg-chip" onClick={() => addGrupo(n)}>{n}</button>
                  ))}
                </div>
                <button className="mfg-add" onClick={() => setNovoGrupo(false)}>Cancelar</button>
              </div>
            : <button className="mfg-add mfg-block" onClick={() => setNovoGrupo(true)}><I.plus size={12} /> Novo grupo de ingredientes</button>)}
        </div>

        <aside className="mfg-ed-side">
          <div className="mfg-sec"><span>Receita</span><span className="ln" /></div>
          <Campo label="Nome"><input className="mfg-inp" value={r.name} disabled={!podeEditar} onChange={(e) => set({ name: e.target.value })} /></Campo>
          <div className="mfg-row2">
            <Campo label="Qtd. produzida"><input className="mfg-inp" type="number" min="0" step="0.01" value={r.qtd} disabled={!podeEditar} onChange={(e) => set({ qtd: Number(e.target.value) })} /></Campo>
            <Campo label="Unidade">
              <select className="mfg-inp" value={r.un} disabled={!podeEditar} onChange={(e) => set({ un: e.target.value })}>
                <option>m²</option><option>un</option><option>m</option><option>kg</option><option>L</option>
              </select>
            </Campo>
          </div>
          <div className="mfg-row2">
            <Campo label="Sub-unidade de saída" hint="opcional — como a quantidade aparece na lista">
              <input className="mfg-inp" value={r.subUn || ""} disabled={!podeEditar} placeholder="ex. m linear" onChange={(e) => set({ subUn: e.target.value || null })} />
            </Campo>
            <Campo label="Fator" hint={r.subUn ? "1 " + r.un + " = " + num(r.subFator || 1, 2) + " " + r.subUn : "—"}>
              <input className="mfg-inp" type="number" min="0" step="0.01" value={r.subFator || 1} disabled={!podeEditar || !r.subUn} onChange={(e) => set({ subFator: Number(e.target.value) })} />
            </Campo>
          </div>
          <Campo label="Desperdício (%)" hint={"rende " + num(c.qtdLiq, 2) + " " + r.un + " de " + num(r.qtd, 2)}>
            <input className="mfg-inp" type="number" min="0" max="100" step="0.5" value={r.waste} disabled={!podeEditar} onChange={(e) => set({ waste: Number(e.target.value) })} />
          </Campo>
          <div className="mfg-row2">
            <Campo label="Custo extra">
              <select className="mfg-inp" value={r.custoTipo} disabled={!podeEditar} onChange={(e) => set({ custoTipo: e.target.value })}>
                <option value="fixo">Valor fixo</option><option value="percentual">% dos ingredientes</option><option value="unidade">Por unidade produzida</option>
              </select>
            </Campo>
            <Campo label={r.custoTipo === "percentual" ? "Percentual" : r.custoTipo === "unidade" ? "R$ / unidade" : "Valor (R$)"}>
              <input className="mfg-inp" type="number" min="0" step="0.01" value={r.extra} disabled={!podeEditar} onChange={(e) => set({ extra: Number(e.target.value) })} />
            </Campo>
          </div>
          <Campo label="Preço de venda (R$)" hint={"margem " + num(c.margem, 1) + "%"}>
            <input className="mfg-inp" type="number" min="0" step="0.01" value={r.venda} disabled={!podeEditar} onChange={(e) => set({ venda: Number(e.target.value) })} />
          </Campo>

          <div className="mfg-sec"><span>Custo ao vivo</span><span className="ln" /></div>
          <dl className="mfg-tot">
            <dt>Ingredientes ({nIng})</dt><dd>{fmt(c.ing)}</dd>
            <dt>Custo extra</dt><dd>{fmt(c.extra)}</dd>
            <hr />
            <dt style={{ fontWeight: 600, color: "var(--text)" }}>Custo por {r.un}</dt>
            <dd style={{ fontSize: 15, fontWeight: 600, color: "var(--accent)" }}>{fmt(c.unit)}</dd>
          </dl>
          {travado && <p className="mfg-note">Edição de quantidade de ingrediente está bloqueada em Configurações.</p>}
          {!podeEditar && <p className="mfg-note">Sua permissão é apenas de leitura (mfg.receita: ver).</p>}
        </aside>
      </div>

      <div className="mfg-ed-f">
        {perms.editar && onDelete && <button className="os-btn ghost danger" onClick={onDelete}>Excluir receita</button>}
        <span className="sp" />
        <button className="os-btn ghost" onClick={onCancel}>Cancelar</button>
        <button className="os-btn primary" disabled={!podeEditar || nIng === 0} onClick={() => onSave(r)}>Salvar receita</button>
      </div>
    </div>
  );
}

window.MfgNovaReceita = MfgNovaReceita;
window.MfgIngredientesEditor = MfgIngredientesEditor;
window.MfgCampo = Campo;
})();

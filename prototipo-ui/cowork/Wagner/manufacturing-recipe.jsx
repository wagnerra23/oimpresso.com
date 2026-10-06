// manufacturing-recipe.jsx — Onda 1: CRUD da receita.
// Espelha recipe/create.blade.php (modal: variação + clonar receita) e
// recipe/add_ingredients.blade.php (editor de ingredientes, grupos, desperdício,
// custo extra fixo/percentual, quantidade produzida, preço final).
// Expõe window.MfgNovaReceita e window.MfgIngredientesEditor.
//
// ADERÊNCIA AO DS (onda A; onda B 2026-09-23): Modal, Button, Input, Select, Textarea, Alert e
// SearchInput (busca de insumo, com inputRef/autoFocus/onKeyDown) do bundle compilado.
// Continuam locais e declarados no handoff (ids da auditoria-aderencia-fabricacao-v2.md,
// remedidos na fonte viva em 29/09/2026):
//   B-05 campos numéricos com passo/teto — Input.d.ts L14-33 não tem min/max/step;
//   B-07 trilha do editor — Breadcrumb.d.ts L1-5 só aceita href; a volta aqui é por estado;
//   .mfg-mini (✕ remover) — Button.jsx L6 não repassa aria-label;
//   chips de "novo grupo" e a grade de ingredientes (C-04) — sem peça equivalente.
(() => {
const { useState, useMemo, useRef, useEffect } = React;
const I = window.I;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

const G = () => window.MFG;

// [TELA] `erro` espelha o fieldShell do DS (Input.jsx): rótulo e borda em
// --color-destructive-fg, "⚠ mensagem" no lugar da dica, aria-invalid no controle.
function Campo({ label, hint, erro, children, w }) {
  const INV = "var(--color-destructive-fg)";
  const controle = erro && React.isValidElement(children)
    ? React.cloneElement(children, { "aria-invalid": true, style: { ...(children.props.style || {}), borderColor: INV } })
    : children;
  return (
    <label className="mfg-fld" style={w ? { width: w } : null}>
      <span style={erro ? { color: INV } : null}>{label}</span>
      {controle}
      {erro
        ? <small style={{ color: INV, fontWeight: 500 }}>⚠ {erro}</small>
        : hint && <small>{hint}</small>}
    </label>
  );
}

// ── Modal "Nova receita" (recipe/create) ──
function MfgNovaReceita({ recipes, onClose, onCreate }) {
  const { Modal, Button, Input, Select, Alert } = ds();
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
    <Modal open onClose={onClose} title="Nova receita" width={520}
      footer={<>
        <Button onClick={onClose}>Cancelar</Button>
        <Button variant="primary" disabled={!podeSalvar}
          onClick={() => onCreate({ nome: nome.trim(), cat, sub: sub.trim() || "—", un, qtd: Number(qtd), clone: clone ? Number(clone) : null })}>
          Criar e adicionar ingredientes
        </Button>
      </>}>
      <div className="mfg-form">
        <Input label="Produto / variação" help="a receita pertence a uma variação do catálogo"
          value={nome} onChange={(e) => setNome(e.target.value)} placeholder="ex. Banner lona 440g — acabado" />
        {existe && <Alert tone="danger">Já existe receita para essa variação — edite a receita atual em vez de criar outra.</Alert>}
        <div className="mfg-row2">
          <Select label="Categoria" value={cat} onChange={(e) => setCat(e.target.value)}>
            <option>Comunicação visual</option><option>Têxtil</option><option>Brindes</option>
          </Select>
          <Input label="Subcategoria" value={sub} onChange={(e) => setSub(e.target.value)} placeholder="ex. Banner" />
        </div>
        <div className="mfg-row2">
          {/* [B-04] Input do DS não aceita min/step — campo local até o DS responder */}
          <Campo label="Quantidade produzida">
            <input className="mfg-inp" type="number" min="0" step="0.01" value={qtd} onChange={(e) => setQtd(e.target.value)} />
          </Campo>
          <Select label="Unidade" value={un} onChange={(e) => setUn(e.target.value)}>
            <option>m²</option><option>un</option><option>m</option><option>kg</option><option>L</option>
          </Select>
        </div>
        <Select label="Clonar ingredientes de" help="opcional — copia grupos, quantidades e desperdício da receita escolhida"
          value={clone} onChange={(e) => setClone(e.target.value)}>
          <option value="">Começar vazia</option>
          {recipes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </Select>
        <p className="mfg-note">{INSUMOS.length} insumos disponíveis no catálogo · custo lido do preço de compra atual.</p>
      </div>
    </Modal>
  );
}

// ── Busca de insumo (get-ingredient-row) ──
// Onda B (2026-09-23): SearchInput do DS — ele expõe inputRef, autoFocus e onKeyDown
// (Input.jsx L65, fonte viva). focusKey desligado: o "/" global é da lista de receitas.
function BuscaInsumo({ onPick, onCancel }) {
  const { INSUMOS, fmt } = G();
  const [q, setQ] = useState("");
  const { SearchInput, Button } = ds();
  const res = INSUMOS.filter((i) => (i.n + " " + i.sku).toLowerCase().includes(q.trim().toLowerCase())).slice(0, 7);
  return (
    <div className="mfg-pick">
      <div className="mfg-pick-s">
        <SearchInput autoFocus focusKey={null} kbd="esc" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar insumo por nome ou SKU…"
          onKeyDown={(e) => { if (e.key === "Escape") onCancel(); if (e.key === "Enter" && res[0]) onPick(res[0]); }} />
        <Button size="sm" onClick={onCancel}>Cancelar</Button>
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
  const { Button, Input, Select, Alert } = ds();
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
  // [TELA] QA 2026-09-29: o editor salvava receita sem nome, com rendimento 0, desperdício de
  // 150% ("rende −10 de 20") e preço negativo. Salvar fica bloqueado até corrigir, com o motivo
  // no campo e no rodapé — nunca botão desativado sem explicação.
  const erros = {};
  if (!String(r.name || "").trim()) erros.name = "Informe o nome da receita.";
  if (!(r.qtd > 0)) erros.qtd = "Precisa ser maior que zero.";
  if (!(r.waste >= 0 && r.waste < 100)) erros.waste = "Use de 0 a menos de 100%.";
  if (!(r.extra >= 0)) erros.extra = "Não pode ser negativo.";
  if (!(r.venda >= 0)) erros.venda = "Não pode ser negativo.";
  if (r.subUn && !(r.subFator > 0)) erros.subFator = "Precisa ser maior que zero.";
  const itemInvalido = (it) => !(Number(it.q) >= 0);
  const nItensInvalidos = r.grupos.reduce((s, g) => s + g.itens.filter(itemInvalido).length, 0);
  const nErros = Object.keys(erros).length + nItensInvalidos;

  return (
    <div className="mfg-ed">
      {/* [B-07] trilha local: o Breadcrumb do DS só navega por href; aqui a volta é por estado */}
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
                  <span className="mfg-grp-n">{g.itens.length}</span>
                  <span className="v">{fmt(sub)}</span>
                  {/* [TELA] .mfg-mini fica local: o Button do DS não repassa aria-label/title (Button.jsx L6) e o ✕ perderia o nome "Remover grupo …" */}
                  {podeEditar && <button className="mfg-mini danger" onClick={() => delGrupo(gi)} title="Remover grupo" aria-label={"Remover grupo " + g.g}>✕</button>}
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
                        {/* [B-04] passo de milésimo: campo local */}
                        {podeEditar && !travado
                          ? <input className="mfg-inp num" type="number" min="0" step="0.001" value={it.q} aria-label={"Quantidade de " + p.n} aria-invalid={itemInvalido(it) || undefined} style={itemInvalido(it) ? { borderColor: "var(--color-destructive-fg)" } : undefined} onChange={(e) => setItem(gi, ii, e.target.value)} />
                          : <span>{num(it.q, it.q < 1 ? 3 : 2)}</span>}
                      </span>
                      <span className="m">
                        {subUnsDe(it.sku).length > 0 && podeEditar
                          ? <select className="mfg-inp sel" value={it.subUn || ""} aria-label={"Unidade de " + p.n} onChange={(ev) => setSubUn(gi, ii, ev.target.value)}>
                              <option value="">{p.u}</option>
                              {subUnsDe(it.sku).map((s) => <option key={s.u} value={s.u}>{s.u}</option>)}
                            </select>
                          : <em className="mfg-u">{it.subUn || p.u}</em>}
                      </span>
                      <span className="m">{fmt(p.c)}<em className="mfg-u">/ {p.u}</em></span>
                      <span className="m tot">{fmt(it.q * p.c * multDe(it))}</span>
                      <span>{podeEditar && <button className="mfg-mini danger" onClick={() => delItem(gi, ii)} title="Remover" aria-label={"Remover " + p.n}>✕</button>}</span>
                    </div>
                  );
                })}
                {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
                {podeEditar && (addIn === gi
                  ? <BuscaInsumo onPick={(ins) => addItem(gi, ins)} onCancel={() => setAddIn(null)} />
                  : <div className="mfg-add-host"><Button size="sm" onClick={() => setAddIn(gi)}><I.plus size={12} /> Ingrediente em {g.g}</Button></div>)}
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
                <div className="mfg-add-host"><Button size="sm" onClick={() => setNovoGrupo(false)}>Cancelar</Button></div>
              </div>
            : <div className="mfg-add-block"><Button onClick={() => setNovoGrupo(true)} style={{ width: "100%" }}><I.plus size={12} /> Novo grupo de ingredientes</Button></div>)}
        </div>

        <aside className="mfg-ed-side">
          <div className="mfg-sec"><span>Receita</span><span className="ln" /></div>
          <div className="mfg-form">
            <Input label="Nome" value={r.name} error={erros.name} disabled={!podeEditar} onChange={(e) => set({ name: e.target.value })} />
            <div className="mfg-row2">
              <Campo label="Qtd. produzida" erro={erros.qtd}><input className="mfg-inp" type="number" min="0" step="0.01" value={r.qtd} disabled={!podeEditar} onChange={(e) => set({ qtd: Number(e.target.value) })} /></Campo>
              <Select label="Unidade" value={r.un} disabled={!podeEditar} onChange={(e) => set({ un: e.target.value })}>
                <option>m²</option><option>un</option><option>m</option><option>kg</option><option>L</option>
              </Select>
            </div>
            <div className="mfg-row2">
              <Input label="Sub-unidade de saída" help="opcional — como a quantidade aparece na lista"
                value={r.subUn || ""} disabled={!podeEditar} placeholder="ex. m linear" onChange={(e) => set({ subUn: e.target.value || null })} />
              <Campo label="Fator" erro={erros.subFator} hint={r.subUn ? "1 " + r.un + " = " + num(r.subFator || 1, 2) + " " + r.subUn : "—"}>
                <input className="mfg-inp" type="number" min="0" step="0.01" value={r.subFator || 1} disabled={!podeEditar || !r.subUn} onChange={(e) => set({ subFator: Number(e.target.value) })} />
              </Campo>
            </div>
            <Campo label="Desperdício (%)" erro={erros.waste} hint={"rende " + num(c.qtdLiq, 2) + " " + r.un + " de " + num(r.qtd, 2)}>
              <input className="mfg-inp" type="number" min="0" max="100" step="0.5" value={r.waste} disabled={!podeEditar} onChange={(e) => set({ waste: Number(e.target.value) })} />
            </Campo>
            <div className="mfg-row2">
              <Select label="Custo extra" value={r.custoTipo} disabled={!podeEditar} onChange={(e) => set({ custoTipo: e.target.value })}>
                <option value="fixo">Valor fixo</option><option value="percentual">% dos ingredientes</option><option value="unidade">Por unidade produzida</option>
              </Select>
              <Campo label={r.custoTipo === "percentual" ? "Percentual" : r.custoTipo === "unidade" ? "R$ / unidade" : "Valor (R$)"} erro={erros.extra}>
                <input className="mfg-inp" type="number" min="0" step="0.01" value={r.extra} disabled={!podeEditar} onChange={(e) => set({ extra: Number(e.target.value) })} />
              </Campo>
            </div>
            <Campo label="Preço de venda (R$)" erro={erros.venda} hint={"margem " + num(c.margem, 1) + "%"}>
              <input className="mfg-inp" type="number" min="0" step="0.01" value={r.venda} disabled={!podeEditar} onChange={(e) => set({ venda: Number(e.target.value) })} />
            </Campo>
            {(() => {
              const pol = r.politica || (window.OiFsmRegras && window.OiFsmRegras.POLITICAS[r.produto]) || { tipo: "fixo" };
              return <>
                <Campo label="Política de preço" hint="o que acontece com o preço quando o custo dos insumos muda na compra">
                  <select className="mfg-inp" value={pol.tipo} disabled={!podeEditar} onChange={(e) => set({ politica: { tipo: e.target.value, alvo: e.target.value === "margem" ? (pol.alvo || 45) : undefined } })}>
                    <option value="fixo">Preço fixo — só a margem muda</option>
                    <option value="acompanha">Acompanha o custo — mantém o markup</option>
                    <option value="margem">Margem alvo — recalcula pelo custo</option>
                  </select>
                </Campo>
                {pol.tipo === "margem" && <Campo label="Margem alvo (%)">
                  <input className="mfg-inp" type="number" min="0" max="95" step="1" value={pol.alvo || 45} disabled={!podeEditar} onChange={(e) => set({ politica: { tipo: "margem", alvo: Number(e.target.value) } })} />
                </Campo>}
                <Campo label="Natureza fiscal" hint="decide a nota no faturamento — definir com o contador">
                  <select className="mfg-inp" value={r.natureza || "servico-grafico"} disabled={!podeEditar} onChange={(e) => set({ natureza: e.target.value })}>
                    <option value="servico-grafico">Serviço gráfico sob encomenda — NFS-e (ISS)</option>
                    <option value="mercadoria">Mercadoria / revenda — NF-e (ICMS)</option>
                    <option value="misto">Produto + instalação — NF-e e NFS-e</option>
                  </select>
                </Campo>
              </>;
            })()}
          </div>

          <div className="mfg-sec"><span>Custo ao vivo</span><span className="ln" /></div>
          <dl className="mfg-tot">
            <dt>Ingredientes ({nIng})</dt><dd>{fmt(c.ing)}</dd>
            <dt>Custo extra</dt><dd>{fmt(c.extra)}</dd>
            <hr />
            <dt style={{ fontWeight: 600, color: "var(--text)" }}>Custo por {r.un}</dt>
            <dd style={{ fontSize: 15, fontWeight: 600, color: "var(--accent)" }}>{fmt(c.unit)}</dd>
          </dl>
          {travado && <Alert tone="info">Edição de quantidade de ingrediente está bloqueada em Configurações.</Alert>}
          {!podeEditar && <Alert tone="warn">Sua permissão é apenas de leitura (mfg.receita: ver).</Alert>}
        </aside>
      </div>

      <div className="mfg-ed-f">
        {perms.editar && onDelete && <Button variant="danger" onClick={onDelete}>Excluir receita</Button>}
        <span className="sp" />
        {podeEditar && nErros > 0 && (
          <span role="status" style={{ font: "500 12px/1.4 var(--font-sans)", color: "var(--color-destructive-fg)" }}>
            ⚠ {nItensInvalidos > 0 ? nItensInvalidos + " ingrediente" + (nItensInvalidos > 1 ? "s" : "") + " com quantidade negativa · " : ""}corrija os campos marcados para salvar
          </span>
        )}
        <Button onClick={onCancel}>Cancelar</Button>
        <Button variant="primary" disabled={!podeEditar || nIng === 0 || nErros > 0} onClick={() => onSave(r)}>Salvar receita</Button>
      </div>
    </div>
  );
}

window.MfgNovaReceita = MfgNovaReceita;
window.MfgIngredientesEditor = MfgIngredientesEditor;
window.MfgCampo = Campo;
})();

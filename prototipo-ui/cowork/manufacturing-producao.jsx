// manufacturing-producao.jsx — Ondas 2/3/4: ordens de produção, relatório,
// configurações e as pontes com Produtos / Compras / Fila.
// Espelha production/index|create|show|report.blade.php e settings/index.blade.php.
// Expõe window.MfgProducaoView, window.MfgProducaoForm, window.MfgRelatorio, window.MfgConfig.
(() => {
const { useState, useMemo } = React;
const I = window.I;
const G = () => window.MFG;
const Campo = (p) => window.MfgCampo(p);

// ── Lista de ordens de produção ──
function MfgProducaoView({ producoes, recipes, perms, onNew, onOpen }) {
  const { LOCAIS, consumoOP, fmt, num, fmtDate } = G();
  const [local, setLocal] = useState("Todos");
  const [de, setDe] = useState("2026-08-01");
  const [ate, setAte] = useState("2026-08-31");
  const [soFinal, setSoFinal] = useState(false);
  const [ord, setOrd] = useState({ k: "data", dir: "desc" });
  const [pag, setPag] = useState(1);

  const CH = { data: (l) => l.op.data, ref: (l) => l.op.ref, local: (l) => l.op.local, produto: (l) => (l.c.r ? l.c.r.name : ""), qtd: (l) => l.op.qtd, total: (l) => l.c.total, unit: (l) => l.c.unit, sit: (l) => (l.op.final ? 1 : 0) };
  const linhas = useMemo(() => {
    const base = producoes.map((op) => ({ op, c: consumoOP(op, recipes) }))
      .filter(({ op }) => (local === "Todos" || op.local === local) && op.data >= de && op.data <= ate && (!soFinal || op.final));
    const f = CH[ord.k] || CH.data;
    return base.sort((a, b) => { const va = f(a), vb = f(b); const r = va > vb ? 1 : va < vb ? -1 : 0; return ord.dir === "asc" ? r : -r; });
  }, [producoes, recipes, local, de, ate, soFinal, ord]);
  const totalPeriodo = linhas.reduce((s, l) => s + l.c.total, 0);
  const POR_PAG = 10;
  const nPags = Math.max(1, Math.ceil(linhas.length / POR_PAG));
  const pagina = Math.min(pag, nPags);
  const visiveis = linhas.slice((pagina - 1) * POR_PAG, pagina * POR_PAG);
  const ordenar = (k) => { setOrd((o) => ({ k, dir: o.k === k && o.dir === "asc" ? "desc" : "asc" })); setPag(1); };
  const Th = ({ k, children, r: right }) => (
    <button className={"mfg-th sort" + (right ? " r" : "") + (ord.k === k ? " act" : "")} onClick={() => ordenar(k)}>
      {right && <span className="ind">{ord.k === k ? (ord.dir === "asc" ? "↑" : "↓") : "⇵"}</span>}{children}
      {!right && <span className="ind">{ord.k === k ? (ord.dir === "asc" ? "↑" : "↓") : "⇵"}</span>}
    </button>
  );

  return (
    <>
      <div className="mfg-filters">
        <Campo label="Local" w={180}>
          <select className="mfg-inp" value={local} onChange={(e) => setLocal(e.target.value)}>
            <option>Todos</option>{LOCAIS.map((l) => <option key={l}>{l}</option>)}
          </select>
        </Campo>
        <Campo label="De" w={140}><input className="mfg-inp" type="date" value={de} onChange={(e) => setDe(e.target.value)} /></Campo>
        <Campo label="Até" w={140}><input className="mfg-inp" type="date" value={ate} onChange={(e) => setAte(e.target.value)} /></Campo>
        <label className="mfg-check"><input type="checkbox" checked={soFinal} onChange={(e) => setSoFinal(e.target.checked)} /> Só finalizadas</label>
        <span className="sp" />
      </div>

      <div className="mfg-tablewrap">
        <div className="mfg-table op">
          <div className="mfg-tr mfg-thead">
            <Th k="data">Data</Th><Th k="ref">Referência</Th><Th k="local">Local</Th>
            <Th k="produto">Produto</Th><Th k="qtd" r>Qtd</Th><Th k="total" r>Custo total</Th>
            <Th k="unit" r>Custo unit.</Th><Th k="sit" r>Situação</Th>
          </div>
          {visiveis.map(({ op, c }) => (
            <div className="mfg-tr mfg-row" key={op.id} onClick={() => onOpen(op.id)}>
              <span className="mfg-num dim">{fmtDate(op.data)}</span>
              <span className="mfg-sku">{op.ref}</span>
              <span className="mfg-cat">{op.local}</span>
              <span className="mfg-name"><b>{c.r ? c.r.name : "—"}</b><span className="mfg-sku">{c.linhas.length} ingredientes · {op.por}</span></span>
              <span className="mfg-num r">{num(op.qtd, 2)}<span className="mfg-u">{c.r ? c.r.un : ""}</span></span>
              <span className="mfg-num r">{fmt(c.total)}{c.congelado != null && <span className="mfg-u" title="custo congelado na data da produção">fix</span>}</span>
              <span className="mfg-num dim r">{fmt(c.unit)}</span>
              <span className="r"><span className={"mfg-pill " + (op.final ? "ok" : "warn")}>{op.final ? "Finalizada" : "Rascunho"}</span></span>
            </div>
          ))}
          {linhas.length === 0 && (
            <div className="mfg-empty"><b>Nenhuma produção no período</b><span>Amplie o intervalo, troque o local ou desligue o filtro de finalizadas.</span></div>
          )}
        </div>
        {linhas.length > POR_PAG && (
          <div className="mfg-pag">
            <span>{(pagina - 1) * POR_PAG + 1}–{Math.min(pagina * POR_PAG, linhas.length)} de {linhas.length}</span>
            <span className="sp" />
            <button disabled={pagina === 1} onClick={() => setPag(pagina - 1)}>‹</button>
            {Array.from({ length: nPags }, (_, i) => i + 1).map((n) => <button key={n} className={n === pagina ? "act" : ""} onClick={() => setPag(n)}>{n}</button>)}
            <button disabled={pagina === nPags} onClick={() => setPag(pagina + 1)}>›</button>
          </div>
        )}
        {linhas.length > 0 && <p className="mfg-foot">{linhas.length} ordens · custo do período <b>{fmt(totalPeriodo)}</b> · ordens finalizadas mostram o custo congelado na data</p>}
      </div>
    </>
  );
}

// ── Nova / editar produção ──
function MfgProducaoForm({ recipes, producoes, settings, perms, editing, onSave, onCancel }) {
  const { LOCAIS, consumoOP, bySku, fmt, num } = G();
  const base = editing || {
    id: null, ref: settings.prefix + String(43 + producoes.length).padStart(4, "0"),
    data: "2026-08-20", local: "Matriz", recipe: recipes[0] ? recipes[0].id : null, qtd: 1, final: false, consumo: null, obs: "", por: "Larissa",
  };
  const [op, setOp] = useState(base);
  const set = (p) => setOp((x) => ({ ...x, ...p }));
  const c = useMemo(() => consumoOP(op, recipes), [op, recipes]);
  const falta = c.linhas.filter((l) => l.q > l.est);
  const travado = settings.travarQtd;

  const override = (sku, q) => setOp((x) => ({ ...x, consumo: { ...(x.consumo || {}), [sku]: Number(q) } }));

  return (
    <div className="mfg-ed">
      <div className="mfg-crumb">
        <button onClick={onCancel}>Ordens de produção</button><span>/</span><b>{editing ? op.ref : "Nova produção"}</b>
        <span className="sp" />
        <span className="mfg-crumb-meta">consumo calculado da receita · proporcional à quantidade</span>
      </div>

      <div className="mfg-ed-cols">
        <div className="mfg-ed-main">
          <div className="mfg-sec"><span>Consumo de ingredientes</span><span className="ln" /></div>
          <div className="mfg-grp">
            <div className="mfg-ing mfg-ing5 mfg-ing-h"><span className="n">Ingrediente</span><span className="m">Consumo</span><span className="m">Custo unit.</span><span className="m">Subtotal</span><span className="m">Estoque</span></div>
            {c.linhas.map((l) => (
              <div className={"mfg-ing mfg-ing5" + (l.q > l.est ? " falta" : "")} key={l.sku}>
                <span className="n">{l.n}<small>{l.sku} · {l.grupo}{l.mult > 1 ? " · " + num(l.qBase, 2) + " " + l.base : ""}</small></span>
                <span className="m">
                  {!travado
                    ? <input className="mfg-inp num" type="number" min="0" step="0.001" value={Number(l.q.toFixed(3))} onChange={(e) => override(l.sku, e.target.value)} />
                    : <span>{num(l.q, 3)}</span>}
                  <em className="mfg-u">{l.u}</em>
                </span>
                <span className="m">{fmt(l.c)}</span>
                <span className="m tot">{fmt(l.sub)}</span>
                <span className={"m" + (l.q > l.est ? " bad" : "")}>{num(l.est, 0)} {l.u}</span>
              </div>
            ))}
            {c.linhas.length === 0 && <p className="mfg-pick-empty">Escolha uma receita com ingredientes.</p>}
          </div>
          {falta.length > 0 && <p className="mfg-err">Estoque insuficiente em {falta.length} insumo{falta.length > 1 ? "s" : ""} — finalizar vai deixar saldo negativo. Ver em <button className="mfg-link" onClick={() => window.__go && window.__go("compras")}>Compras</button>.</p>}
          <Campo label="Observação"><textarea className="mfg-inp" rows="2" value={op.obs} onChange={(e) => set({ obs: e.target.value })} placeholder="lote, equipamento, quem executou…" /></Campo>
        </div>

        <aside className="mfg-ed-side">
          <div className="mfg-sec"><span>Ordem</span><span className="ln" /></div>
          <Campo label="Referência" hint={"prefixo " + settings.prefix + " definido em Configurações"}>
            <input className="mfg-inp" value={op.ref} onChange={(e) => set({ ref: e.target.value })} />
          </Campo>
          <div className="mfg-row2">
            <Campo label="Data"><input className="mfg-inp" type="date" value={op.data} onChange={(e) => set({ data: e.target.value })} /></Campo>
            <Campo label="Local">
              <select className="mfg-inp" value={op.local} onChange={(e) => set({ local: e.target.value })}>{LOCAIS.map((l) => <option key={l}>{l}</option>)}</select>
            </Campo>
          </div>
          <Campo label="Receita">
            <select className="mfg-inp" value={op.recipe || ""} onChange={(e) => set({ recipe: Number(e.target.value), consumo: null })}>
              {recipes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
          </Campo>
          <Campo label={"Quantidade a produzir" + (c.r ? " (" + c.r.un + ")" : "")} hint={c.r ? "receita rende " + num(c.r.qtd, 2) + " " + c.r.un + " por lote" : null}>
            <input className="mfg-inp" type="number" min="0" step="0.01" value={op.qtd} onChange={(e) => set({ qtd: Number(e.target.value), consumo: null })} />
          </Campo>

          <div className="mfg-sec"><span>Custo</span><span className="ln" /></div>
          <dl className="mfg-tot">
            <dt>Ingredientes (preço de hoje)</dt><dd>{fmt(c.linhas.reduce((s, l) => s + l.sub, 0))}</dd>
            <dt>Custo extra da receita</dt><dd>{fmt(c.extra || 0)}</dd>
            <hr />
            <dt style={{ fontWeight: 600, color: "var(--text)" }}>{c.congelado != null ? "Total hoje" : "Total da ordem"}</dt>
            <dd style={{ fontSize: 15, fontWeight: 600, color: "var(--accent)" }}>{fmt(c.vivo)}</dd>
            <dt>Custo por unidade</dt><dd>{fmt(op.qtd > 0 ? c.vivo / op.qtd : 0)}</dd>
            {c.congelado != null && <><dt>Custo congelado na produção</dt><dd>{fmt(c.congelado)}</dd></>}
          </dl>
          <label className="mfg-check big">
            <input type="checkbox" checked={op.final} onChange={(e) => set({ final: e.target.checked })} />
            Finalizar — dá entrada no produto e baixa os ingredientes
          </label>
          {op.final && settings.permitirPreco && <p className="mfg-note">Ao finalizar, o preço de custo do produto será atualizado ({fmt(c.unit)}) — comportamento ligado em Configurações.</p>}
        </aside>
      </div>

      <div className="mfg-ed-f">
        <span className="sp" />
        <button className="os-btn ghost" onClick={onCancel}>Cancelar</button>
        <button className="os-btn primary" disabled={!perms.criar || c.linhas.length === 0} onClick={() => onSave(op)}>
          {op.final ? "Salvar e finalizar" : "Salvar rascunho"}
        </button>
      </div>
    </div>
  );
}

// ── Detalhe da ordem (production/show) ──
function MfgProducaoDrawer({ op, recipes, onClose, onEdit }) {
  const { consumoOP, fmt, num, fmtDate } = G();
  const c = consumoOP(op, recipes);
  return (
    <>
      <div className="mfg-scrim" onClick={onClose} />
      <aside className="mfg-drw" role="dialog" aria-label={"Produção " + op.ref}>
        <div className="mfg-drw-h">
          <div>
            <h2>{op.ref}</h2>
            <p>{fmtDate(op.data)} · {op.local} · {c.r ? c.r.name : "—"} · {num(op.qtd, 2)} {c.r ? c.r.un : ""} · por {op.por}</p>
          </div>
          <button className="mfg-x" onClick={onClose} aria-label="Fechar">✕</button>
        </div>
        <div className="mfg-drw-b">
          <div className="mfg-sec"><span>Ingredientes consumidos</span><span className="ln" /></div>
          <div className="mfg-grp">
            {c.linhas.map((l) => (
              <div className="mfg-ing" key={l.sku}>
                <span className="n">{l.n}<small>{l.sku} · {l.grupo}</small></span>
                <span className="m">{num(l.q, 3)} {l.u}</span>
                <span className="m">{fmt(l.c)}</span>
                <span className="m tot">{fmt(l.sub)}</span>
              </div>
            ))}
          </div>
          <div className="mfg-sec"><span>Custo</span><span className="ln" /></div>
          <dl className="mfg-tot">
            <dt>Ingredientes (preço de hoje)</dt><dd>{fmt(c.linhas.reduce((s, l) => s + l.sub, 0))}</dd>
            <dt>Custo extra</dt><dd>{fmt(c.extra || 0)}</dd>
            <hr />
            <dt style={{ fontWeight: 600, color: "var(--text)" }}>{c.congelado != null ? "Total hoje" : "Total"}</dt>
            <dd style={{ fontSize: 15, fontWeight: 600, color: "var(--accent)" }}>{fmt(c.vivo)}</dd>
            <dt>Custo por unidade</dt><dd>{fmt(op.qtd > 0 ? c.vivo / op.qtd : 0)}</dd>
            <dt>Situação</dt><dd>{op.final ? "Finalizada · estoque movimentado" : "Rascunho · sem movimento de estoque"}</dd>
            {c.congelado != null && <><hr /><dt>Custo congelado na produção</dt><dd>{fmt(c.congelado)}</dd><dt>Mesma receita hoje</dt><dd>{fmt(c.vivo)} ({c.vivo > c.congelado ? "+" : ""}{num((c.congelado ? (c.vivo - c.congelado) / c.congelado * 100 : 0), 1)}%)</dd></>}
          </dl>
          {op.obs && <p className="mfg-note">{op.obs}</p>}
          <p className="mfg-note">Acompanhar na operação: <button className="mfg-link" onClick={() => window.__go && window.__go("fila")}>Fila de produção</button> · <button className="mfg-link" onClick={() => window.__go && window.__go("produtos")}>ficha do produto</button>.</p>
        </div>
        <div className="mfg-drw-f">
          <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          <button className="os-btn primary" onClick={onEdit}><I.pencil size={13} /> Editar ordem</button>
        </div>
      </aside>
    </>
  );
}

// ── Relatório de produção (/manufacturing/report) ──
function MfgRelatorio({ producoes, recipes }) {
  const { consumoOP, fmt, num } = G();
  const [de, setDe] = useState("2026-08-01");
  const [ate, setAte] = useState("2026-08-31");
  const [soFinal, setSoFinal] = useState(true);

  const linhas = useMemo(() => {
    const acc = {};
    producoes.filter((op) => op.data >= de && op.data <= ate && (!soFinal || op.final)).forEach((op) => {
      const c = consumoOP(op, recipes);
      if (!c.r) return;
      const k = c.r.id;
      acc[k] = acc[k] || { name: c.r.name, un: c.r.un, ordens: 0, qtd: 0, custo: 0 };
      acc[k].ordens++; acc[k].qtd += op.qtd; acc[k].custo += c.total;
    });
    return Object.values(acc).sort((a, b) => b.custo - a.custo);
  }, [producoes, recipes, de, ate, soFinal]);
  const tot = linhas.reduce((s, l) => s + l.custo, 0);

  return (
    <>
      <div className="mfg-filters">
        <Campo label="De" w={140}><input className="mfg-inp" type="date" value={de} onChange={(e) => setDe(e.target.value)} /></Campo>
        <Campo label="Até" w={140}><input className="mfg-inp" type="date" value={ate} onChange={(e) => setAte(e.target.value)} /></Campo>
        <label className="mfg-check"><input type="checkbox" checked={soFinal} onChange={(e) => setSoFinal(e.target.checked)} /> Só finalizadas</label>
      </div>
      <div className="mfg-tablewrap">
        <div className="mfg-table rep">
          <div className="mfg-tr mfg-thead">
            <span className="mfg-th">Produto</span><span className="mfg-th r">Ordens</span><span className="mfg-th r">Quantidade</span>
            <span className="mfg-th r">Custo total</span><span className="mfg-th r">Custo médio</span><span className="mfg-th r">% do período</span>
          </div>
          {linhas.map((l) => (
            <div className="mfg-tr" key={l.name}>
              <span className="mfg-name"><b>{l.name}</b></span>
              <span className="mfg-num dim r">{l.ordens}</span>
              <span className="mfg-num r">{num(l.qtd, 2)}<span className="mfg-u">{l.un}</span></span>
              <span className="mfg-num r">{fmt(l.custo)}</span>
              <span className="mfg-num dim r">{fmt(l.qtd > 0 ? l.custo / l.qtd : 0)}</span>
              <span className="r"><span className="mfg-bar-mini"><i style={{ width: (tot ? l.custo / tot * 100 : 0) + "%" }} /></span><span className="mfg-num dim">{num(tot ? l.custo / tot * 100 : 0, 0)}%</span></span>
            </div>
          ))}
          {linhas.length === 0 && <div className="mfg-empty"><b>Sem produção no período</b><span>Ajuste as datas ou inclua os rascunhos.</span></div>}
        </div>
        {linhas.length > 0 && <p className="mfg-foot">Custo de produção do período <b>{fmt(tot)}</b> · lançado como entrada de estoque no <button className="mfg-link" onClick={() => window.__go && window.__go("financeiro")}>Financeiro</button></p>}
      </div>
    </>
  );
}

// ── Configurações + permissões (settings/index) ──
function MfgConfig({ settings, setSettings, perms, setPerms }) {
  const { SETTINGS } = G();
  const [s, setS] = useState(settings);
  const dirty = JSON.stringify(s) !== JSON.stringify(settings);
  return (
    <div className="mfg-cfg">
      <div className="mfg-card">
        <div className="mfg-sec"><span>Configurações do módulo</span><span className="ln" /></div>
        <Campo label="Prefixo da referência" hint="usado na numeração das ordens de produção" w={220}>
          <input className="mfg-inp" value={s.prefix} onChange={(e) => setS({ ...s, prefix: e.target.value })} />
        </Campo>
        <label className="mfg-check big">
          <input type="checkbox" checked={s.travarQtd} onChange={(e) => setS({ ...s, travarQtd: e.target.checked })} />
          Bloquear edição da quantidade de ingrediente
          <small>quando ligado, a receita manda: nem editor nem ordem de produção permitem ajustar consumo.</small>
        </label>
        <label className="mfg-check big">
          <input type="checkbox" checked={s.permitirPreco} onChange={(e) => setS({ ...s, permitirPreco: e.target.checked })} />
          Atualizar preço do produto ao finalizar produção
          <small>propaga o custo unitário calculado para a ficha do produto.</small>
        </label>
        <div className="mfg-ed-f mfg-inline">
          <span className="mfg-crumb-meta">Manufacturing v{SETTINGS.versao}</span>
          <span className="sp" />
          <button className="os-btn primary" disabled={!dirty} onClick={() => setSettings(s)}>Atualizar</button>
        </div>
      </div>

      <div className="mfg-card">
        <div className="mfg-sec"><span>Permissões (simulação)</span><span className="ln" /></div>
        <p className="mfg-note">Espelha <code>mfg.receita</code> (ver/criar/editar) e <code>mfg.prod</code>. Sem permissão o botão não aparece — nunca aparece desabilitado.</p>
        <div className="mfg-chips">
          {[["ver", "Ver receita"], ["criar", "Criar"], ["editar", "Editar"], ["prod", "Acessar produção"]].map(([k, l]) => (
            <button key={k} className={"mfg-chip" + (perms[k] ? " act" : "")} onClick={() => setPerms({ ...perms, [k]: !perms[k] })}>{l}</button>
          ))}
        </div>
      </div>

      <div className="mfg-card">
        <div className="mfg-sec"><span>Integrações</span><span className="ln" /></div>
        <ul className="mfg-int">
          <li><b>Produtos</b> — a receita pertence a uma variação; o custo calculado alimenta a composição. <button className="mfg-link" onClick={() => window.__go && window.__go("produtos")}>abrir Produtos</button></li>
          <li><b>Compras</b> — salvar nota de insumo recalcula todas as receitas que usam o item. <button className="mfg-link" onClick={() => window.__go && window.__go("compras")}>abrir Compras</button></li>
          <li><b>Fila de produção / OS</b> — a ordem finalizada entra na fila do chão de fábrica. <button className="mfg-link" onClick={() => window.__go && window.__go("fila")}>abrir Fila</button></li>
        </ul>
      </div>
    </div>
  );
}

Object.assign(window, { MfgProducaoView, MfgProducaoForm, MfgProducaoDrawer, MfgRelatorio, MfgConfig });
})();

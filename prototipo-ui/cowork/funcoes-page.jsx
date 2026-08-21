// funcoes-page.jsx — Funções e permissões (legado /roles). Catálogo real: 53 grupos, ~400 permissões.
// Lista de funções (cards) → editor de tela cheia: rail de domínios + linhas com a forma certa
// (escopo · crud · toggle · seletor · chave crua) + busca global + barra de diferenças.
// Dados: window.FUNCOES_PERMS (funcoes-perms.jsx). Expõe window.FuncoesPage.
(() => {
const { useState, useMemo, useRef } = React;
const { G, DOMINIOS, ESCOPO_OPTS, PRESETS } = window.FUNCOES_PERMS;
const { Kpis, Kpi, Sw, Nota, Vazio } = window.AcessosDS;

const FUNCOES = [
  { id:1, nome:"Administrador", desc:"Acesso total — sem restrição de módulo, local ou carteira.", usuarios:1, padrao:true, travada:true, hue:280, preset:null },
  { id:2, nome:"Atendente",  desc:"Balcão: atende, orça e abre OS. Não vê custo nem financeiro.", usuarios:2, padrao:true,  hue:230, preset:"atendente" },
  { id:3, nome:"Produção",   desc:"Fila de impressão, acabamento e apontamento. Sem preço.",      usuarios:2, padrao:true,  hue:70,  preset:"producao" },
  { id:4, nome:"Financeiro", desc:"Títulos, conciliação, fiscal e DRE. Não mexe em produção.",    usuarios:2, padrao:true,  hue:150, preset:"financeiro" },
  { id:5, nome:"Vendas",     desc:"Carteira própria, comissão e orçamento. Vê só os clientes dele.", usuarios:2, padrao:false, hue:25, preset:"vendas" },
  { id:6, nome:"Consulta",   desc:"Somente leitura — para contador e auditoria externa.",         usuarios:0, padrao:false, hue:0,   preset:"consulta" },
];

const GRUPOS_IDS = DOMINIOS.flatMap((d) => d.grupos);
const CONTROLES = GRUPOS_IDS.reduce((n, g) => n + G[g].itens.filter((i) => !i.sub).length, 0);
const LEGACY_N = GRUPOS_IDS.reduce((n, g) => n + G[g].itens.filter((i) => !i.sub).reduce((a, i) => a + (i.legacyN || (i.acoes ? i.acoes.length : 1)), 0), 0);

function tone(hue){ return { bg:`oklch(0.94 0.045 ${hue})`, fg:`oklch(0.42 0.15 ${hue})`, bd:`oklch(0.85 0.065 ${hue})` }; }
const optsDe = (it) => it.t === "escopo" ? (it.opts3 ? [...ESCOPO_OPTS.slice(0,2), { v:"assigned", label:"Só as " + it.opts3 }, ESCOPO_OPTS[2]] : ESCOPO_OPTS) : it.opts;

function valorPadrao(it, todo){
  if (todo) return it.t === "crud" ? it.acoes.slice() : it.t === "escopo" ? "all" : it.t === "sel" ? (it.max || it.opts[0].v) : true;
  return it.t === "crud" ? [] : it.t === "escopo" ? "none" : it.t === "sel" ? it.opts[0].v : false;
}
function ativo(it, v){
  if (it.t === "crud") return Array.isArray(v) && v.length > 0;
  if (it.t === "escopo") return v && v !== "none";
  if (it.t === "sel") return v && v !== "none";
  return !!v;
}
function estadoDoPreset(preset, todo){
  const base = todo ? null : (PRESETS[preset] || {});
  const st = {};
  GRUPOS_IDS.forEach((g) => G[g].itens.forEach((it) => {
    if (it.sub) return;
    st[it.k] = todo ? valorPadrao(it, true) : (base[it.k] !== undefined ? base[it.k] : valorPadrao(it, false));
  }));
  return st;
}
function statsGrupo(gid, st){
  const itens = G[gid].itens.filter((i) => !i.sub);
  let n = 0, risco = 0;
  itens.forEach((it) => { if (ativo(it, st[it.k])) { n++; if (it.risco) risco++; } });
  return { n, total: itens.length, risco };
}

// ─────────── Controles ───────────
function Switch({ on, onToggle, travada }) {
  return <Sw on={on} onToggle={onToggle} disabled={travada} />;
}
function Seg({ value, opts, onChange, travada }) {
  return (
    <div className="fnc-seg">
      {opts.map((o) => (
        <button key={o.v} type="button" className={value === o.v ? "on" : ""} disabled={travada}
          onClick={travada ? undefined : () => onChange(o.v)}>{o.label}</button>
      ))}
    </div>
  );
}
function CrudChips({ value, acoes, onChange, travada }) {
  const v = Array.isArray(value) ? value : [];
  const toggle = (a) => {
    let next = v.includes(a) ? v.filter((x) => x !== a) : [...v, a];
    if (!v.includes(a) && a !== "ver" && acoes.includes("ver") && !next.includes("ver")) next = ["ver", ...next];
    if (v.includes(a) && a === "ver") next = [];
    onChange(next);
  };
  return (
    <div className="fnc-chips">
      {acoes.map((a) => (
        <button key={a} type="button" className={`fnc-chip ${v.includes(a) ? "on" : ""} ${a === "excluir" ? "del" : ""}`}
          disabled={travada} onClick={travada ? undefined : () => toggle(a)}>{a}</button>
      ))}
    </div>
  );
}

function Linha({ it, st, set, travada }) {
  const v = st[it.k];
  const on = ativo(it, v);
  return (
    <div className={`fnc-row ${on ? "" : "off"} ${it.t === "raw" ? "raw" : ""}`}>
      <div className="fnc-row-l">
        <span className="fnc-row-label">
          {it.t === "raw" ? <code>{it.k}</code> : it.label}
          {it.risco && <span className="fnc-risco" title="Ação destrutiva ou financeira">risco</span>}
        </span>
        <span className="fnc-row-meta">
          {it.legacyN > 1 && <span title="permissões do legado colapsadas neste controle">{it.legacyN} permissões legadas</span>}
          {it.neg && <span title={"No banco grava " + it.neg}>grava invertido</span>}
          {it.t === "raw" && <span className="warn">sem tradução no main</span>}
        </span>
      </div>
      <div className="fnc-row-r">
        {it.t === "crud" && <CrudChips value={v} acoes={it.acoes} travada={travada} onChange={(nv) => set(it.k, nv)} />}
        {(it.t === "escopo" || it.t === "sel") && <Seg value={v} opts={optsDe(it)} travada={travada} onChange={(nv) => set(it.k, nv)} />}
        {(it.t === "sw" || it.t === "raw") && <Switch on={!!v} travada={travada} onToggle={() => set(it.k, !v)} />}
      </div>
    </div>
  );
}

// ─────────── Editor ───────────
function Editor({ funcao, onClose }) {
  const travada = !!funcao.travada;
  const base = useMemo(() => estadoDoPreset(funcao.preset, travada), [funcao]);
  const [st, setSt] = useState(base);
  const [nome, setNome] = useState(funcao.nome);
  const [gid, setGid] = useState("pos");
  const [q, setQ] = useState("");
  const [soAtivas, setSoAtivas] = useState(false);
  const scrollRef = useRef(null);
  const set = (k, v) => setSt((s) => ({ ...s, [k]: v }));

  const totalAtivas = GRUPOS_IDS.reduce((n, g) => n + statsGrupo(g, st).n, 0);
  const totalRisco = GRUPOS_IDS.reduce((n, g) => n + statsGrupo(g, st).risco, 0);
  const diff = useMemo(() => {
    let add = 0, rem = 0;
    Object.keys(base).forEach((k) => {
      const a = JSON.stringify(base[k]), b = JSON.stringify(st[k]);
      if (a === b) return;
      const it = GRUPOS_IDS.flatMap((g) => G[g].itens).find((i) => i.k === k);
      ativo(it, st[k]) ? add++ : rem++;
    });
    return { add, rem };
  }, [st, base]);

  const busca = q.trim().toLowerCase();
  const resultados = busca ? GRUPOS_IDS.flatMap((g) => G[g].itens.filter((i) => !i.sub &&
    ((i.label || i.k).toLowerCase().includes(busca) || G[g].label.toLowerCase().includes(busca))).map((i) => ({ i, g }))) : null;

  const grupo = G[gid];
  const gs = statsGrupo(gid, st);
  const setGrupoTudo = (todo) => setSt((s) => {
    const n = { ...s };
    grupo.itens.forEach((it) => { if (!it.sub) n[it.k] = valorPadrao(it, todo); });
    return n;
  });

  return (
    <div className="fnc-editor">
      <header className="fnc-ed-h">
        <button className="fnc-back" onClick={onClose} title="Voltar para as funções">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div className="fnc-ed-h-l">
          <input className="fnc-ed-nome" value={nome} onChange={(e) => setNome(e.target.value)} disabled={travada} aria-label="Nome da função" />
          <p>{totalAtivas} de {CONTROLES} controles ativos · {GRUPOS_IDS.length} grupos · {LEGACY_N} permissões no banco</p>
        </div>
        <div className="fnc-ed-h-r">
          <span className="usr-role" style={{ ...(() => { const t = tone(funcao.hue); return { background:t.bg, color:t.fg, borderColor:t.bd }; })() }}>
            {funcao.padrao ? "padrão" : "personalizada"}
          </span>
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={travada} onClick={onClose}>Salvar função</button>
        </div>
      </header>

      <div className="fnc-ed-body">
        <nav className="fnc-rail">
          <div className="fnc-rail-search">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar permissão…" />
          </div>
          {DOMINIOS.map((d) => (
            <div key={d.id} className="fnc-rail-dom">
              <span className="fnc-rail-dom-l">{d.label}</span>
              {d.grupos.map((g) => {
                const s = statsGrupo(g, st);
                return (
                  <button key={g} className={`fnc-rail-g ${!busca && gid === g ? "on" : ""} ${s.n ? "" : "zero"}`}
                    onClick={() => { setQ(""); setGid(g); scrollRef.current?.scrollTo(0, 0); }}>
                    <span className="fnc-rail-g-l">{G[g].label}</span>
                    {s.risco > 0 && <span className="fnc-rail-risco" title={`${s.risco} permissões de risco ativas`}></span>}
                    <span className="fnc-rail-n">{s.n}/{s.total}</span>
                  </button>
                );
              })}
            </div>
          ))}
        </nav>

        <section className="fnc-pane" ref={scrollRef}>
          {busca ? (
            <>
              <div className="fnc-pane-h">
                <div className="fnc-pane-h-l">
                  <h2>{resultados.length} {resultados.length === 1 ? "permissão" : "permissões"} para “{q}”</h2>
                  <p>Busca em todo o catálogo — {GRUPOS_IDS.length} grupos.</p>
                </div>
                <button className="os-btn sm" onClick={() => setQ("")}>Limpar busca</button>
              </div>
              <div className="fnc-rows">
                {resultados.map(({ i, g }) => (
                  <div key={i.k} className="fnc-hit">
                    <span className="fnc-hit-g" onClick={() => { setQ(""); setGid(g); }}>{G[g].label}</span>
                    <Linha it={i} st={st} set={set} travada={travada} />
                  </div>
                ))}
                {resultados.length === 0 && <Vazio title="Nada encontrado nesse nome." description={`Nenhuma permissão casa “${q}” — tente o nome do módulo ou parte da chave.`} />}
              </div>
            </>
          ) : (
            <>
              <div className="fnc-pane-h">
                <div className="fnc-pane-h-l">
                  <h2>{grupo.label}</h2>
                  <p><code>{grupo.legacy}</code> no /roles · {gs.n} de {gs.total} ativos{gs.risco ? ` · ${gs.risco} de risco` : ""}</p>
                </div>
                <div className="fnc-pane-h-r">
                  <button className={`usr-clear ${soAtivas ? "on" : ""}`} onClick={() => setSoAtivas(!soAtivas)}>{soAtivas ? "Mostrar todas" : "Só as ativas"}</button>
                  {!travada && <button className="os-btn sm" onClick={() => setGrupoTudo(true)}>Tudo</button>}
                  {!travada && <button className="os-btn sm" onClick={() => setGrupoTudo(false)}>Nada</button>}
                </div>
              </div>

              {grupo.raw && (
                <Nota tone="warn" title="Chaves sem tradução">
                  Estas {grupo.itens.length} chaves não têm rótulo em PT no <code>main</code> — o /roles mostra a chave crua.
                  Falta a lang string do módulo; até lá o operador não sabe o que está marcando.
                </Nota>
              )}

              <div className="fnc-rows">
                {grupo.itens.map((it, ix) => it.sub ? (
                  <div key={"s" + ix} className="fnc-sub">
                    <h3>{it.sub}</h3>
                    {it.nota && <p>{it.nota}</p>}
                  </div>
                ) : (soAtivas && !ativo(it, st[it.k])) ? null : (
                  <Linha key={it.k} it={it} st={st} set={set} travada={travada} />
                ))}
              </div>
            </>
          )}
        </section>
      </div>

      <footer className="fnc-ed-f">
        <span className="fnc-ed-f-diff">
          {diff.add > 0 && <b className="add">+{diff.add}</b>}
          {diff.rem > 0 && <b className="rem">−{diff.rem}</b>}
          {diff.add + diff.rem === 0 ? "Igual ao padrão da função" : `${diff.add + diff.rem} ${diff.add + diff.rem === 1 ? "mudança" : "mudanças"} desde o padrão`}
        </span>
        <span className="fnc-ed-f-sep">·</span>
        <span className={totalRisco ? "fnc-ed-f-risco" : ""}>{totalRisco} de risco ativas</span>
        <span className="fnc-ed-f-sep">·</span>
        <span>{funcao.usuarios} {funcao.usuarios === 1 ? "usuário afetado" : "usuários afetados"}</span>
        <span className="fnc-ed-f-r">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={travada} onClick={onClose}>Salvar função</button>
        </span>
      </footer>
    </div>
  );
}

// ─────────── Lista ───────────
function FuncoesPage() {
  const [q, setQ] = useState("");
  const [sel, setSel] = useState(null);

  const comStats = useMemo(() => FUNCOES.map((f) => {
    const st = estadoDoPreset(f.preset, !!f.travada);
    const dom = DOMINIOS.map((d) => {
      const t = d.grupos.reduce((a, g) => { const s = statsGrupo(g, st); return { n: a.n + s.n, total: a.total + s.total }; }, { n:0, total:0 });
      return { id:d.id, label:d.label, pct: Math.round(t.n / t.total * 100) };
    });
    const ativas = GRUPOS_IDS.reduce((n, g) => n + statsGrupo(g, st).n, 0);
    const risco = GRUPOS_IDS.reduce((n, g) => n + statsGrupo(g, st).risco, 0);
    return { ...f, dom, ativas, risco };
  }), []);

  if (sel) return <Editor funcao={sel} onClose={() => setSel(null)} />;

  const filtered = comStats.filter((f) => !q || [f.nome, f.desc].some((v) => v.toLowerCase().includes(q.toLowerCase())));

  return (
    <div className="os-page usr-page fnc-page" data-screen-label="Usuários · Funções e permissões">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Funções e permissões</h1>
          <p>{FUNCOES.length} funções · {GRUPOS_IDS.length} grupos (53 do <code>/roles</code>) · {LEGACY_N} permissões no catálogo</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("usuarios")}>Usuários</button>
          <button className="os-btn primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Nova função
          </button>
        </div>
      </header>

      <Kpis>
        <Kpi v={FUNCOES.length} l="Funções" />
        <Kpi v={CONTROLES} l="Controles na tela" />
        <Kpi v={LEGACY_N} l="Permissões no banco" />
        <Kpi v={FUNCOES.filter((f) => f.usuarios === 0).length} l="Sem usuário" />
      </Kpis>

      <div className="usr-toolbar">
        <div className="usr-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar função…" />
        </div>
        <span className="usr-count">{filtered.length} de {FUNCOES.length}</span>
      </div>

      <div className="fnc-grid">
        {filtered.map((f) => {
          const t = tone(f.hue);
          return (
            <button key={f.id} className="fnc-card" onClick={() => setSel(f)}>
              <div className="fnc-card-h">
                <span className="usr-role" style={{ background:t.bg, color:t.fg, borderColor:t.bd }}>{f.nome}</span>
                {f.travada && (
                  <svg className="fnc-lock-i" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                )}
                {f.risco > 0 && <span className="fnc-card-risco">{f.risco} de risco</span>}
              </div>
              <p className="fnc-card-desc">{f.desc}</p>
              <div className="fnc-doms">
                {f.dom.map((d) => (
                  <div key={d.id} className="fnc-dom" title={`${d.label}: ${d.pct}% dos controles`}>
                    <span className="fnc-dom-bar"><i style={{ width: Math.max(d.pct, 2) + "%" }}></i></span>
                    <span className="fnc-dom-l">{d.label}</span>
                  </div>
                ))}
              </div>
              <div className="fnc-card-f">
                <span>{f.usuarios} {f.usuarios === 1 ? "usuário" : "usuários"}</span>
                <span className="fnc-dot">·</span>
                <span>{f.ativas} controles ativos</span>
                <span className="fnc-card-go">Editar</span>
              </div>
            </button>
          );
        })}
      </div>

      {filtered.length === 0 && <Vazio title="Nenhuma função encontrada." description="Ajuste a busca ou crie uma função nova a partir de uma padrão." />}
      <p className="cms-note">
        O catálogo espelha os grupos do <code>/roles</code> no <code>main</code>. Pares “ver todos / ver próprio” viram um escopo,
        os nove “Desativar …” do PDV viram liberações, e chaves sem tradução aparecem cruas — como no legado.
      </p>
    </div>
  );
}

window.FuncoesPage = FuncoesPage;
})();

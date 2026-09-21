// forja-tarefas.jsx — Tarefas (cópia fiel do conceito de produção Tasks/Index.tsx @main 2026-08-08):
// TODAS as mcp_tasks (não só project=FORJA) · KPIs · Backlog|Quadro internos (SubNav) · status canon
// (dot Stripe) · ActorSeal humano×agente por NOME · estimate_h · Lock em bloqueada · bulk → status ·
// drag otimista no quadro · densidade compacta/normal. Atalhos J/K ↵ X /.
const { useState: useStateT, useMemo: useMemoT, useEffect: useEffectT, useRef: useRefT } = React;

const TK_AGENTS = ["claude-cc", "claude-code", "claude-design", "claude-a11y", "claude-analista"];
const TK_STATUS_ORDER = ["doing", "review", "todo", "blocked", "backlog", "done", "cancelled"];
const TK_KANBAN_COLS = ["todo", "doing", "review", "done"];
const TK_PRIO_ORDER = { P0: 0, P1: 1, P2: 2, P3: 3 };
const TK_GROUPS = [["sprint", "Onda"], ["status", "Status"], ["owner", "Owner"], ["priority", "Prioridade"], ["module", "Módulo"]];

// Mock cross-projeto (Forja + Jana + Fiscal + DS) — universo MAIOR que o backlog da Forja
const TK_TASKS = [
  { id: "T-1042", title: "Completar §TEMPERO (--sh/--ease/--atmo) na fundação", module: "Sistema", owner: "claude-code", sprint: "FA-2", priority: "P0", estimate_h: 6, blocked_by: [], status: "doing" },
  { id: "T-1055", title: "Ghost do Roadmap (Gantt) não alimenta a topnav nova — tela vazia em produção", module: "Forja", owner: "felipe", sprint: "Q1", priority: "P0", estimate_h: 4, blocked_by: [], status: "review" },
  { id: "T-1039", title: "Snap 314 font-size hardcoded ao ramp --fs-1..9", module: "Financeiro", owner: "claude-code", sprint: "FA-2", priority: "P1", estimate_h: 8, blocked_by: ["T-1042"], status: "blocked" },
  { id: "T-1047", title: "G-3 E2E Playwright → pull_request + required", module: "Sistema", owner: "felipe", sprint: "Q1", priority: "P1", estimate_h: 5, blocked_by: [], status: "doing" },
  { id: "T-1050", title: "NF-e rejeição 539: consultar situação antes do reenvio", module: "Fiscal", owner: "eliana", sprint: null, priority: "P1", estimate_h: 3, blocked_by: [], status: "review" },
  { id: "T-1051", title: "Drawer financeiro corta o valor no dark", module: "Financeiro", owner: "maiara", sprint: null, priority: "P1", estimate_h: 2, blocked_by: [], status: "doing" },
  { id: "T-1052", title: "F2 — fila de box da Oficina (re-skin DS v6)", module: "Oficina", owner: "luiz", sprint: "FA-2", priority: "P2", estimate_h: 6, blocked_by: [], status: "review" },
  { id: "T-1031", title: "Jana: drill drawer — filtros J/K ⌘⇧H no Chat", module: "Jana", owner: "claude-code", sprint: null, priority: "P2", estimate_h: 4, blocked_by: [], status: "done" },
  { id: "T-1044", title: "ADR — token --origin-DEV (selo da Forja)", module: "Sistema", owner: "wagner", sprint: null, priority: "P2", estimate_h: 1, blocked_by: [], status: "todo" },
  { id: "T-1048", title: "Rename ds-v5 → ds-v6 no git (ADR 0244)", module: "Sistema", owner: "claude-code", sprint: null, priority: "P2", estimate_h: 1, blocked_by: [], status: "todo" },
  { id: "T-1036", title: "Busca semântica no KB (Meilisearch)", module: "KB", owner: null, sprint: null, priority: "P2", estimate_h: 12, blocked_by: [], status: "backlog" },
  { id: "T-1053", title: "Censo do módulo Fiscal antes do pilar (Regra 7)", module: "Fiscal", owner: "claude-cc", sprint: null, priority: "P1", estimate_h: 3, blocked_by: [], status: "todo" },
  { id: "T-1029", title: "RecurringBilling re-skin DS v6 — UI-Judge 90", module: "Cobrança", owner: "claude-code", sprint: null, priority: "P2", estimate_h: 6, blocked_by: [], status: "done" },
  { id: "T-1054", title: "Painel de saúde — frescor do censo de gates", module: "Sistema", owner: null, sprint: null, priority: "P3", estimate_h: 2, blocked_by: [], status: "backlog" },
];

function TkStatusPill({ s }) {
  const st = window.FORJA.STATUS.find(x => x.id === s) || { label: s, neutral: true };
  return <span className="fj-exec" title={"Status: " + st.label}><i style={{ background: st.neutral ? "var(--text-mute)" : "oklch(0.6 0.14 " + st.hue + ")" }}/>{st.label}</span>;
}
// ActorSeal canon (taskBadges @main): agente = Bot roxo · humano = User neutro · sem dono = —
function TkActorSeal({ owner }) {
  if (!owner) return <span className="tf-seal vazio">—</span>;
  const agente = TK_AGENTS.includes(owner);
  return (
    <span className={"tf-seal" + (agente ? " agente" : "")} title={(agente ? "Agente: " : "Humano: ") + owner}>
      {agente
        ? <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="4" y="8" width="16" height="11" rx="2.5"/><path d="M12 4v4M9 13h.01M15 13h.01"/></svg>
        : <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5"/></svg>}
      {owner}
    </span>
  );
}
function TkLock() {
  return <svg className="tf-lock" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-label="bloqueada"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>;
}

function TarefaDrawer({ task, onClose, onStatus }) {
  if (!task) return null;
  const STATUS = window.FORJA.STATUS;
  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer" onClick={(e) => e.stopPropagation()}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l"><span className="fj-dr-id">{task.id}</span><div className="fj-dr-chips"><TkStatusPill s={task.status}/><span className="fj-prio-chip" style={{ "--pc": { P0: 25, P1: 60, P2: 295, P3: 250 }[task.priority] }}>{task.priority}</span>{task.sprint && <span className="fj-onda-chip">~{task.sprint}</span>}</div></div>
          <div className="fj-dr-head-r"><button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button></div>
        </header>
        <div className="fj-dr-body">
          <h2 className="fj-dr-title">{task.title}</h2>
          <div className="fj-dr-sec">
            <h3>Status</h3>
            <div className="tf-status-row">{STATUS.filter(s => s.id !== "cancelled").map(s => (
              <button key={s.id} className={"fj-gb-btn" + (task.status === s.id ? " active" : "")} onClick={() => onStatus(task.id, s.id)}>{s.label}</button>
            ))}</div>
            <p className="fj-dr-desc" style={{ marginTop: 8 }}>Mudança registra <code>mcp_task_events</code> (autor + de→para) — edição de campo é <code>tasks-update</code> via MCP.</p>
          </div>
          <div className="fj-dr-sec">
            <h3>Atribuição</h3>
            <dl className="fj-dr-meta">
              <dt>Owner</dt><dd><TkActorSeal owner={task.owner}/></dd>
              <dt>Módulo</dt><dd>{task.module}</dd>
              <dt>Onda</dt><dd className="mono">{task.sprint || "—"}</dd>
              <dt>Estimativa</dt><dd className="mono">{task.estimate_h ? task.estimate_h + "h" : "—"}</dd>
              {task.blocked_by.length > 0 && <React.Fragment><dt>Bloqueada por</dt><dd className="mono">{task.blocked_by.join(", ")}</dd></React.Fragment>}
            </dl>
          </div>
        </div>
        <footer className="fj-dr-foot"><span className="fj-foot-spacer"/><span className="fj-foot-note">projeção de mcp_tasks · todas as frentes, não só FORJA</span></footer>
      </aside>
    </div>
  );
}

function ForjaTarefas() {
  const [tab, setTab] = useStateT(() => { try { return localStorage.getItem("oimpresso.forja.tk.tab") || "backlog"; } catch (e) { return "backlog"; } });
  const [groupBy, setGroupBy] = useStateT("sprint");
  const [density, setDensity] = useStateT(() => { try { return localStorage.getItem("oimpresso.forja.tk.density") || "normal"; } catch (e) { return "normal"; } });
  const [search, setSearch] = useStateT("");
  const [collapsed, setCollapsed] = useStateT(() => new Set(["done", "cancelled"]));
  const [patch, setPatch] = useStateT({});
  const [kpiF, setKpiF] = useStateT(null);
  const [ownerF, setOwnerF] = useStateT(null);
  const [ondaF, setOndaF] = useStateT(null);
  const [selId, setSelId] = useStateT(null);
  const [marked, setMarked] = useStateT(() => new Set());
  const [openId, setOpenId] = useStateT(null);
  const [dragId, setDragId] = useStateT(null);
  const [overCol, setOverCol] = useStateT(null);
  const searchRef = useRefT(null);
  useEffectT(() => { try { localStorage.setItem("oimpresso.forja.tk.tab", tab); } catch (e) {} }, [tab]);
  useEffectT(() => { try { localStorage.setItem("oimpresso.forja.tk.density", density); } catch (e) {} }, [density]);

  const tasks = useMemoT(() => TK_TASKS.map(t => patch[t.id] ? { ...t, status: patch[t.id] } : t), [patch]);
  const setStatus = (id, status) => setPatch(p => ({ ...p, [id]: status }));
  const kpis = useMemoT(() => ({
    total: tasks.length,
    p0: tasks.filter(t => t.priority === "P0" && t.status !== "done" && t.status !== "cancelled").length,
    doing: tasks.filter(t => t.status === "doing").length,
    blocked: tasks.filter(t => t.status === "blocked" || t.blocked_by.length > 0).length,
    done: tasks.filter(t => t.status === "done").length,
    horas: tasks.reduce((a, t) => a + (t.estimate_h || 0), 0),
  }), [tasks]);

  const q = search.trim().toLowerCase();
  const visiveis = useMemoT(() => tasks
    .filter(t => !q || (t.id + " " + t.title + " " + (t.owner || "") + " " + t.module).toLowerCase().includes(q))
    .filter(t => !ownerF || t.owner === ownerF)
    .filter(t => !ondaF || t.sprint === ondaF)
    .filter(t => kpiF !== "p0" || (t.priority === "P0" && t.status !== "done" && t.status !== "cancelled"))
    .filter(t => kpiF !== "blocked" || (t.status === "blocked" || t.blocked_by.length > 0))
    .filter(t => kpiF !== "doing" || t.status === "doing")
    .sort((a, b) => {
      const sa = TK_STATUS_ORDER.indexOf(a.status), sb = TK_STATUS_ORDER.indexOf(b.status);
      if (sa !== sb) return sa - sb;
      const pa = TK_PRIO_ORDER[a.priority] ?? 9, pb = TK_PRIO_ORDER[b.priority] ?? 9;
      if (pa !== pb) return pa - pb;
      return a.id.localeCompare(b.id);
    }), [tasks, q, ownerF, ondaF, kpiF]);

  const grupos = useMemoT(() => {
    const gOf = (t) => groupBy === "priority" ? t.priority : groupBy === "status" ? t.status : (t[groupBy] || "__none__");
    const lbl = (v) => v === "__none__" ? (groupBy === "sprint" ? "— sem onda —" : groupBy === "owner" ? "— sem dono —" : "— sem módulo —") : groupBy === "status" ? (window.FORJA.STATUS.find(s => s.id === v)?.label || v) : v;
    const map = new Map();
    visiveis.forEach(t => { const g = gOf(t); if (!map.has(g)) map.set(g, []); map.get(g).push(t); });
    return [...map.entries()].sort((a, b) => {
      if (groupBy === "priority") return (TK_PRIO_ORDER[a[0]] ?? 9) - (TK_PRIO_ORDER[b[0]] ?? 9);
      if (groupBy === "status") return TK_STATUS_ORDER.indexOf(a[0]) - TK_STATUS_ORDER.indexOf(b[0]);
      if (a[0] === "__none__") return 1; if (b[0] === "__none__") return -1;
      return String(a[0]).localeCompare(String(b[0]));
    }).map(([g, items]) => ({ g, lbl: lbl(g), items }));
  }, [visiveis, groupBy]);

  const flat = useMemoT(() => tab === "quadro"
    ? TK_KANBAN_COLS.flatMap(c => visiveis.filter(t => t.status === c))
    : grupos.flatMap(x => collapsed.has(x.g) ? [] : x.items), [tab, visiveis, grupos, collapsed]);
  const open = tasks.find(t => t.id === openId) || null;

  useEffectT(() => {
    const onKey = (e) => {
      const tg = e.target; const typing = tg && (tg.tagName === "INPUT" || tg.tagName === "TEXTAREA" || tg.isContentEditable);
      if (e.key === "/" && !typing) { e.preventDefault(); searchRef.current && searchRef.current.focus(); return; }
      if (e.key === "Escape") { if (openId) setOpenId(null); else if (marked.size) setMarked(new Set()); return; }
      if (typing) return;
      const idx = selId ? flat.findIndex(t => t.id === selId) : -1;
      if (e.key === "j") { e.preventDefault(); setSelId((flat[Math.min(flat.length - 1, idx < 0 ? 0 : idx + 1)] || {}).id || null); }
      else if (e.key === "k") { e.preventDefault(); setSelId((flat[idx <= 0 ? 0 : idx - 1] || {}).id || null); }
      else if (e.key === "Enter" && idx >= 0) { e.preventDefault(); setOpenId(flat[idx].id); }
      else if (e.key === "x" && idx >= 0) { e.preventDefault(); setMarked(s => { const n = new Set(s); n.has(selId) ? n.delete(selId) : n.add(selId); return n; }); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [flat, selId, openId, marked]);

  const bulk = (status) => { marked.forEach(id => setStatus(id, status)); setMarked(new Set()); };
  const rowH = density === "compact" ? " compact" : "";
  return (
    <div className="tf-page">
      <div className="tf-kpis">
        {[["Total", kpis.total, "", null], ["P0 abertas", kpis.p0, kpis.p0 > 0 ? "bad" : "ok", "p0"], ["Fazendo", kpis.doing, "", "doing"], ["Bloqueadas", kpis.blocked, kpis.blocked > 0 ? "warn" : "ok", "blocked"]].map(([l, v, tone, f]) => (
          <button key={l} className={"tf-kpi " + tone + (f ? " click" : "") + (kpiF === f && f ? " on" : "")} disabled={!f} onClick={() => f && setKpiF(k => k === f ? null : f)} title={f ? "Clique pra filtrar" : ""}><span className="tf-kpi-v">{v}</span><span className="tf-kpi-l">{l}</span></button>
        ))}
        <span className="tf-kpi-meta">{kpis.horas}h estimadas · {kpis.done} concluídas · projeção de <code>mcp_tasks</code> (todas as frentes)</span>
      </div>
      <div className="tf-toolbar">
        <div className="fj-viewtabs tf-subnav">
          <button className={tab === "backlog" ? "active" : ""} onClick={() => setTab("backlog")}><I.list size={11}/>Lista</button>
          <button className={tab === "quadro" ? "active" : ""} onClick={() => setTab("quadro")}><I.grid size={11}/>Quadro</button>
        </div>
        {tab === "backlog" && (<div className="fj-groupby"><span className="fj-groupby-lbl">Agrupar</span>{TK_GROUPS.map(([id, lbl]) => (<button key={id} className={"fj-gb-btn" + (groupBy === id ? " active" : "")} onClick={() => setGroupBy(id)}>{lbl}</button>))}</div>)}
        <div className="fj-groupby"><span className="fj-groupby-lbl">Owner</span>
          <button className={"fj-gb-btn" + (!ownerF ? " active" : "")} onClick={() => setOwnerF(null)}>todos</button>
          {[...new Set(TK_TASKS.map(x => x.owner).filter(Boolean))].map(o => (<button key={o} className={"fj-gb-btn" + (ownerF === o ? " active" : "") + (TK_AGENTS.includes(o) ? " tf-ag" : "")} onClick={() => setOwnerF(f => f === o ? null : o)}>{o}</button>))}
        </div>
        <div className="fj-groupby"><span className="fj-groupby-lbl">Onda</span>
          <button className={"fj-gb-btn" + (!ondaF ? " active" : "")} onClick={() => setOndaF(null)}>todas</button>
          {[...new Set(TK_TASKS.map(x => x.sprint).filter(Boolean))].map(o => (<button key={o} className={"fj-gb-btn" + (ondaF === o ? " active" : "")} onClick={() => setOndaF(f => f === o ? null : o)}>~{o}</button>))}
        </div>
        {tab === "backlog" && <button className="fj-gb-btn" onClick={() => setDensity(d => d === "compact" ? "normal" : "compact")}>{density === "compact" ? "densidade: compacta" : "densidade: normal"}</button>}
        <div className="fj-search"><I.search size={12}/><input ref={searchRef} placeholder="Buscar (/)…" value={search} onChange={e => setSearch(e.target.value)}/></div>
      </div>
      {tab === "quadro" ? (
        <div className="tf-kanban">
          {TK_KANBAN_COLS.map(c => {
            const items = visiveis.filter(t => t.status === c);
            return (
              <section key={c} className={"tf-kcol" + (overCol === c ? " over" : "")}
                onDragOver={(e) => { e.preventDefault(); setOverCol(c); }} onDragLeave={() => setOverCol(o => o === c ? null : o)}
                onDrop={(e) => { e.preventDefault(); if (dragId) setStatus(dragId, c); setDragId(null); setOverCol(null); }}>
                <header className="tf-kcol-head"><TkStatusPill s={c}/><span className="fj-kcol-count">{items.length}</span></header>
                <div className="tf-kcol-body">
                  {items.map(t => (
                    <div key={t.id} className={"tf-card" + (selId === t.id ? " sel" : "")} draggable onDragStart={() => { setDragId(t.id); setSelId(t.id); }} onClick={() => { setSelId(t.id); setOpenId(t.id); }}>
                      <div className="tf-card-top"><span className="fj-prio-dot" style={{ background: "oklch(0.6 0.18 " + ({ P0: 25, P1: 60, P2: 295, P3: 250 }[t.priority]) + ")" }}/><span className="fj-id">{t.id}</span>{t.blocked_by.length > 0 && <TkLock/>}</div>
                      <p className="tf-card-t">{t.title}</p>
                      <div className="tf-card-foot"><span className="fj-mod">{t.module}</span><TkActorSeal owner={t.owner}/></div>
                    </div>
                  ))}
                  {items.length === 0 && <div className="fj-kcol-empty">vazio</div>}
                </div>
              </section>
            );
          })}
        </div>
      ) : visiveis.length === 0 ? (
        <div className="tf-empty">
          <p className="tf-empty-h">{q ? 'Nada pra "' + search.trim() + '"' : "Nenhuma task no filtro"}</p>
          <p className="tf-empty-s">Ajuste a busca ou limpe os filtros.</p>
          <button className="os-btn ghost" onClick={() => { setSearch(""); setKpiF(null); setOwnerF(null); setOndaF(null); }}>Limpar tudo</button>
        </div>
      ) : (
        <div className="tf-list">
          {grupos.map(({ g, lbl, items }) => {
            const isC = collapsed.has(g);
            return (
              <div key={g}>
                <button className="tf-group" onClick={() => setCollapsed(s => { const n = new Set(s); n.has(g) ? n.delete(g) : n.add(g); return n; })}>
                  <span className="fj-group-chev" style={{ transform: isC ? "rotate(-90deg)" : "none" }}><I.chev size={12}/></span>
                  <span>{lbl}</span><span className="fj-group-count">{items.length}</span>
                </button>
                {!isC && items.map(t => (
                  <div key={t.id} className={"tf-row" + rowH + (selId === t.id ? " sel" : "") + (marked.has(t.id) ? " mk" : "")} onClick={() => { setSelId(t.id); setOpenId(t.id); }}>
                    <button className={"fj-rowcheck" + (marked.has(t.id) ? " on" : "")} onClick={(e) => { e.stopPropagation(); setMarked(s => { const n = new Set(s); n.has(t.id) ? n.delete(t.id) : n.add(t.id); return n; }); }} aria-label="Selecionar">{marked.has(t.id) && <I.check size={10}/>}</button>
                    <span className="fj-prio-dot" style={{ background: "oklch(0.6 0.18 " + ({ P0: 25, P1: 60, P2: 295, P3: 250 }[t.priority]) + ")" }} title={t.priority}/>
                    <span className="fj-id">{t.id}</span>
                    <span className="fj-title">{t.title}</span>
                    {t.blocked_by.length > 0 && <TkLock/>}
                    <span className="fj-mod">{t.module}</span>
                    <TkActorSeal owner={t.owner}/>
                    <span className="tf-h mono">{t.estimate_h ? t.estimate_h + "h" : ""}</span>
                    <TkStatusPill s={t.status}/>
                  </div>
                ))}
              </div>
            );
          })}
        </div>
      )}
      <div className="fj-totalbar">
        <span><b>{tab === "quadro" ? TK_KANBAN_COLS.reduce((a, c) => a + visiveis.filter(t => t.status === c).length, 0) : flat.length}</b> {tab === "quadro" ? "no quadro" : "de " + tasks.length + " tasks"}</span>
        <span className="fj-total-hint">drag no Quadro atualiza status (registra <code>mcp_task_events</code>) · <kbd>j</kbd><kbd>k</kbd> navega · <kbd>x</kbd> marca · <kbd>/</kbd> busca</span>
      </div>
      {marked.size > 0 && (
        <div className="fj-bulkbar">
          <span className="fj-bulk-n"><b>{marked.size}</b> tasks</span>
          <span className="fj-bulk-lbl">status</span>
          <button className="fj-bulk-fase" onClick={() => bulk("doing")}>→ Fazendo</button>
          <button className="fj-bulk-fase" onClick={() => bulk("review")}>→ Revisão</button>
          <button className="fj-bulk-fase" onClick={() => bulk("done")}>→ Concluído</button>
          <button className="fj-bulk-act" onClick={() => setMarked(new Set())}>limpar</button>
        </div>
      )}
      {open && <TarefaDrawer task={open} onClose={() => setOpenId(null)} onStatus={setStatus}/>}
    </div>
  );
}
window.ForjaTarefas = ForjaTarefas;
window.FORJA_TK_COUNT = TK_TASKS.length;

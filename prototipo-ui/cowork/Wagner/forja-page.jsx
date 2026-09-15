// forja-page.jsx — Forja (Etapa 1 + Refinos #1..#4)
// #1 ⌘K·tree·frescor·cheat  #2 MCP·IA RAG·resumir·auto-sugest  #3 editor·re-verificar·comentários·cross-link·trilhas
// #4 Quadro (kanban drag→fase)·favoritos·handoff/release notes·saúde sparkline
// Chrome = roxo canon. Teal --dev só em selos. Tela = projeção do git; escrita = proposta.
// Shell: roteia as 6 views e é dono do estado. Cada tela vive no seu arquivo (forja-*.jsx).
const { useState: useStateF, useMemo: useMemoF, useEffect: useEffectF, useRef: useRefF } = React;

function ForjaPage() {
  const [view, setView] = useStateF(() => { try { const v = localStorage.getItem("oimpresso.forja.view") || "hoje"; if (v === "triagem") return "hoje"; if (v === "backlog" || v === "quadro" || v === "tarefas") return "trabalho"; return v; } catch (e) { return "hoje"; } });
  const [groupBy, setGroupBy] = useStateF("onda");
  const [query, setQuery] = useStateF("");
  const [sel, setSel] = useStateF(0);
  const [openId, setOpenId] = useStateF(null);
  const [collapsed, setCollapsed] = useStateF({});
  const [palette, setPalette] = useStateF(false);
  const [cheat, setCheat] = useStateF(false);
  const [created, setCreated] = useStateF([]);
  const [patches, setPatches] = useStateF({});
  const [iaPanel, setIaPanel] = useStateF(null);
  const [composer, setComposer] = useStateF(false);
  const [runbook, setRunbook] = useStateF(false);
  const [handoff, setHandoff] = useStateF(null);
  const [favOnly, setFavOnly] = useStateF(false);
  const [healthFilter, setHealthFilter] = useStateF(null);
  const [fav, setFav] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.fav") || "[]")); } catch (e) { return new Set(); } });
  const [dossie, setDossie] = useStateF(null);
  const [notifOpen, setNotifOpen] = useStateF(false);
  const [assigneeFilter, setAssigneeFilter] = useStateF(null);
  const [savedViews, setSavedViews] = useStateF(() => { try { return JSON.parse(localStorage.getItem("oimpresso.forja.views") || "[]"); } catch (e) { return []; } });
  const [follow, setFollow] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.follow") || "[]")); } catch (e) { return new Set(); } });
  const [notifSeen, setNotifSeen] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.seen") || "[]")); } catch (e) { return new Set(); } });
  const [rules, setRules] = useStateF(() => { try { return JSON.parse(localStorage.getItem("oimpresso.forja.rules") || "null") || { gateBlock: true, reverifyF1: true, prMergeF4: false }; } catch (e) { return { gateBlock: true, reverifyF1: true, prMergeF4: false }; } });
  const [selected, setSelected] = useStateF(() => new Set());
  const [pin, setPin] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.pin") || "[]")); } catch (e) { return new Set(); } });
  const [expanded, setExpanded] = useStateF(() => new Set());
  const [suggIdx, setSuggIdx] = useStateF(0);
  const [suggOff, setSuggOff] = useStateF(false);
  const searchRef = useRefF(null);
  // Fusão Backlog+Quadro+Tarefas → Trabalho ([W] 2026-08-08): mesma entidade (mcp_tasks), escopo = Frente
  const [trabFrente] = useStateF("todas"); // [W] 2026-08-08: sem filtro de frente — sempre todas; FORJA acha-se por grupo/busca
  const [trabVis, setTrabVis] = useStateF(() => { try { if (localStorage.getItem("oimpresso.forja.view") === "quadro") return "quadro"; return localStorage.getItem("oimpresso.forja.trabvis") || "lista"; } catch (e) { return "lista"; } });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.trabvis", trabVis); } catch (e) {} }, [trabVis]);
  const isLista = view === "trabalho" && trabVis === "lista";
  const isQuadro = view === "trabalho" && trabVis === "quadro";
  const goLista = () => { setView("trabalho"); setTrabVis("lista"); };
  const [ordemBy, setOrdemBy] = useStateF({ todas: "rank" });
  const [denso, setDenso] = useStateF(false);

  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.pin", JSON.stringify([...pin])); } catch (e) {} }, [pin]);
  const togglePin = (id) => setPin(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const toggleExpand = (id) => setExpanded(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });

  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.view", view); } catch (e) {} }, [view]);
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.fav", JSON.stringify([...fav])); } catch (e) {} }, [fav]);
  const toggleFav = (id) => setFav(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.views", JSON.stringify(savedViews)); } catch (e) {} }, [savedViews]);
  const applyView = (v) => { setGroupBy(v.groupBy); setQuery(v.query || ""); setFavOnly(!!v.favOnly); setHealthFilter(v.healthFilter || null); setAssigneeFilter(v.assignee || null); goLista(); };
  const saveView = () => {
    const parts = [{ onda: "por onda", fase: "por fase", assignee: "por papel", prio: "por prio", modulo: "por módulo" }[groupBy]];
    if (favOnly) parts.push("★"); if (assigneeFilter) parts.push("[" + assigneeFilter + "]"); if (healthFilter) parts.push(healthFilter); if (query) parts.push('"' + query + '"');
    setSavedViews(vs => [...vs, { name: parts.join(" · "), groupBy, query, favOnly, healthFilter, assignee: assigneeFilter }]);
  };
  const delView = (idx) => setSavedViews(vs => vs.filter((_, i) => i !== idx));
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.follow", JSON.stringify([...follow])); } catch (e) {} }, [follow]);
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.seen", JSON.stringify([...notifSeen])); } catch (e) {} }, [notifSeen]);
  const toggleFollow = (id) => setFollow(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const markSeen = (key) => setNotifSeen(s => new Set(s).add(key));
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.rules", JSON.stringify(rules)); } catch (e) {} }, [rules]);
  const toggleRule = (id) => setRules(r => ({ ...r, [id]: !r[id] }));
  const toggleSel = (id) => setSelected(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const bulkPhase = (fase) => { selected.forEach(id => moveFase(id, fase)); setSelected(new Set()); };
  const bulkFav = () => { setFav(s => { const n = new Set(s); selected.forEach(id => n.add(id)); return n; }); setSelected(new Set()); };
  const bulkAssign = (role) => { selected.forEach(id => patchIssue(id, { assignee: role }, { ator: "W", t: "atribuído a [" + role + "] (em massa)", quando: "agora" })); setSelected(new Set()); };
  const bulkPrio = (p) => { selected.forEach(id => patchIssue(id, { prio: p }, { ator: "W", t: "prioridade → " + p + " (em massa)", quando: "agora" })); setSelected(new Set()); };
  const bulkOnda = (o) => { selected.forEach(id => patchIssue(id, { onda: o }, { ator: "W", t: "movido p/ onda ~" + (o || "—") + " (em massa)", quando: "agora" })); setSelected(new Set()); };

  const ISSUES = useMemoF(() => {
    return [...created, ...window.FORJA.ISSUES].map(i => {
      const p = patches[i.id];
      if (!p) return i;
      return { ...i, ...p, subtarefas: p.subtarefas || i.subtarefas, atividade: [...(p.atividade || []), ...i.atividade] };
    });
  }, [created, patches]);



  const backlogIssues = useMemoF(() => ISSUES.filter(i => (i.estado || "backlog") === "backlog"), [ISSUES]);  const triagemIssues = useMemoF(() => ISSUES.filter(i => i.estado === "triagem"), [ISSUES]);
  const unifiedAll = useMemoF(() => {
    const tk = (window.FORJA.TK || []).map(t => ({ id: t.id, frente: t.module, titulo: t.title, tipo: "task", prio: t.priority, fase: null, exec: t.status, assignee: null, ownerNome: t.owner, onda: t.sprint, modulo: t.module, origem: "mcp_tasks", vinculos: [], bloqueado_por: t.blocked_by, desc: t.title, subtarefas: [], atividade: [], estimate_h: t.estimate_h, criados: "", atualizado: "mcp_tasks", ...(patches[t.id] || {}) }));
    return [...backlogIssues.map(i => ({ ...i, frente: "FORJA" })), ...tk];
  }, [backlogIssues, patches]);
  const open = useMemoF(() => ISSUES.find(i => i.id === openId) || unifiedAll.find(i => i.id === openId) || null, [openId, ISSUES, unifiedAll]);
  const relations = useMemoF(() => {
    if (!open) return null;
    const bloqueia = ISSUES.filter(i => (i.bloqueado_por || []).includes(open.id)).map(i => i.id);
    const relacionados = ISSUES.filter(i => i.id !== open.id && i.onda && i.onda === open.onda && !(open.bloqueado_por || []).includes(i.id) && !bloqueia.includes(i.id)).map(i => i.id).slice(0, 4);
    const children = (open.children || []).map(id => ISSUES.find(i => i.id === id)).filter(Boolean);
    return { bloqueia, relacionados, parent: open.parent || null, children };
  }, [open, ISSUES]);
  const approveTriagem = (id, prio) => { const part = { estado: "backlog" }; if (prio) part.prio = prio; patchIssue(id, part, { ator: "AN", t: "triado → aprovado p/ backlog" + (prio ? " (" + prio + ")" : ""), quando: "agora" }); setDossie(null); };
  const rejectTriagem = (id) => { patchIssue(id, { estado: "rejeitado" }, { ator: "W", t: "rejeitado na triagem", quando: "agora" }); setDossie(null); };
  const mergeDup = (id, into) => { patchIssue(id, { estado: "merged" }, { ator: "AN", t: "fundido em " + into, quando: "agora" }); setDossie(null); };
  const notifs = useMemoF(() => ({
    triagem: triagemIssues,
    mine: backlogIssues.filter(i => i.assignee === "W"),
    follow: backlogIssues.filter(i => follow.has(i.id)),
    comments: ISSUES.filter(i => i.atividade.some(a => a.comment)).slice(0, 5),
    inferido: backlogIssues.filter(i => i.frescor === "inferido"),
  }), [ISSUES, triagemIssues, backlogIssues, follow]);
  const notifCount = useMemoF(() => {
    let n = 0;
    ["triagem", "mine", "follow", "comments", "inferido"].forEach(sec => notifs[sec].forEach(i => { if (!notifSeen.has(sec + ":" + i.id)) n++; }));
    return n;
  }, [notifs, notifSeen]);
  const markAllSeen = () => { const keys = []; ["triagem", "mine", "follow", "comments", "inferido"].forEach(sec => notifs[sec].forEach(i => keys.push(sec + ":" + i.id))); setNotifSeen(s => { const n = new Set(s); keys.forEach(k => n.add(k)); return n; }); };

  const patchIssue = (id, partial, act) => setPatches(p => { const cur = p[id] || {}; const next = { ...cur, ...partial }; if (act) next.atividade = [act, ...(cur.atividade || [])]; return { ...p, [id]: next }; });
  const reverify = (id) => patchIssue(id, { frescor: "lido" }, { ator: "W", t: "✓ conferido contra @main nesta sessão", quando: "agora" });
  const addComment = (id, text, replyTo) => patchIssue(id, {}, { ator: "W", t: text, quando: "agora", comment: true, cid: "c" + Date.now() + Math.floor(Math.random() * 999), replyTo: replyTo || null });
  const react = (id, cid) => setPatches(p => { const cur = p[id] || {}; const rxn = { ...(cur.reactions || {}) }; rxn[cid] = (rxn[cid] || 0) + 1; return { ...p, [id]: { ...cur, reactions: rxn } }; });
  const moveFase = (id, fase) => patchIssue(id, { fase, proposto: true }, { ator: "W", t: "movido p/ " + fase + " (proposta)", quando: "agora" });
  const moveExec = (id, exec) => patchIssue(id, { exec, proposto: true }, { ator: "W", t: "status → " + exec + " (proposta)", quando: "agora" });
  const resolveLink = (val) => {
    const iss = ISSUES.find(i => i.id === val);
    if (iss) { goLista(); setOpenId(val); return; }
    const log = window.FORJA.CHANGELOG.find(e => e.ref.includes(val) || val.includes(e.ref.replace(/[^0-9]/g, "")));
    if (log) { setOpenId(null); setView("changelog"); return; }
    setOpenId(null); setIaPanel({ mode: "ask" });
  };
  const drill = (kind) => { setHealthFilter(kind); setFavOnly(false); goLista(); };

  const blocksCount = useMemoF(() => { const m = {}; backlogIssues.forEach(i => (i.bloqueado_por || []).forEach(b => { m[b] = (m[b] || 0) + 1; })); return m; }, [backlogIssues]);

  const filtered = useMemoF(() => {
    const Q = fjParseQuery(query);
    const srcPool = trabFrente === "forja" ? backlogIssues.map(i => ({ ...i, frente: "FORJA" })) : unifiedAll;
    let arr = srcPool.filter(i => {
      if (Q.prio && i.prio !== Q.prio) return false;
      if (Q.assignee && i.assignee !== Q.assignee) return false;
      if (Q.onda && (i.onda || "") !== Q.onda) return false;
      if (Q.tipo && i.tipo !== Q.tipo) return false;
      if (Q.fresco && i.frescor !== Q.fresco) return false;
      if (Q.modulo && !i.modulo.toLowerCase().includes(Q.modulo)) return false;
      if (Q.text.length) { const hay = (i.titulo + " " + i.id + " " + i.modulo).toLowerCase(); if (!Q.text.every(t => hay.includes(t))) return false; }
      return true;
    });
    if (assigneeFilter) arr = arr.filter(i => i.assignee === assigneeFilter);
    if (favOnly) arr = arr.filter(i => fav.has(i.id));
    if (healthFilter === "inferido") arr = arr.filter(i => i.frescor === "inferido");
    else if (healthFilter === "blocked") arr = arr.filter(i => i.bloqueado_por.length || i.exec === "blocked");
    else if (healthFilter === "doing") arr = arr.filter(i => (i.exec || "backlog") === "doing");
    else if (healthFilter === "p0") arr = arr.filter(i => i.prio === "P0");
    // L-1 — pin manual no topo, resto por score automático
    const ordem = ordemBy[trabFrente] || (trabFrente === "forja" ? "rank" : "exec");
    const SO = window.FORJA.STATUS.map(s => s.id);
    arr = [...arr].sort((a, b) => {
      const pa = pin.has(a.id), pb = pin.has(b.id);
      if (pa !== pb) return pa ? -1 : 1;
      if (ordem === "exec") {
        const sa = SO.indexOf(a.exec || "backlog"), sb = SO.indexOf(b.exec || "backlog");
        if (sa !== sb) return sa - sb;
        const pd = (FJ_PRIO_W[a.prio] || 0) - (FJ_PRIO_W[b.prio] || 0);
        if (pd) return -pd;
        return a.id.localeCompare(b.id);
      }
      return fjScore(b, blocksCount[b.id]) - fjScore(a, blocksCount[a.id]);
    });
    // L-5 — sub-issue de épico visível aninha sob o pai, não duplica na lista
    const epics = new Set(arr.filter(i => i.tipo === "epico").map(i => i.id));
    if (epics.size) arr = arr.filter(i => !(i.parent && epics.has(i.parent)));
    return arr;
  }, [query, backlogIssues, unifiedAll, trabFrente, ordemBy, favOnly, fav, healthFilter, assigneeFilter, pin, blocksCount]);

  const pendencias = useMemoF(() => window.FORJA.APROVACOES.length + triagemIssues.length + window.FORJA.HANDOFFS.filter(h => h.estado === "stale" || h.gateConflito).length, [triagemIssues]);

  const kidsOf = useMemoF(() => { const m = {}; ISSUES.forEach(i => { if (i.parent) (m[i.parent] = m[i.parent] || []).push(i); }); return m; }, [ISSUES]);

  // L-8 — autocomplete da gramática de busca que já existia (is: @ ~ tipo: mod:)
  const suggests = useMemoF(() => {
    const m = (query.match(/(\S*)$/) || ["", ""])[1];
    if (!m || suggOff) return [];
    const A = window.FORJA.ACTORS, T = window.FORJA.TYPES;
    let pool = [];
    if (m[0] === "@") pool = Object.keys(A).map(r => ({ tok: "@" + r, sub: A[r].name }));
    else if (m[0] === "~") pool = window.FORJA.ONDAS.map(o => ({ tok: "~" + o.id, sub: o.nome }));
    else if (/^tipo:/i.test(m)) pool = Object.keys(T).map(t => ({ tok: "tipo:" + t, sub: T[t].label }));
    else if (/^mod:/i.test(m)) pool = [...new Set(ISSUES.map(i => i.modulo))].map(x => ({ tok: "mod:" + x.toLowerCase(), sub: "módulo" }));
    else if (/^is:/i.test(m)) pool = ["p0", "p1", "p2", "p3", "inferido", "lido", "sync"].map(x => ({ tok: "is:" + x, sub: /^p/.test(x) ? "prioridade" : "frescor" }));
    else pool = [{ tok: "is:", sub: "prioridade ou frescor" }, { tok: "tipo:", sub: "tipo de issue" }, { tok: "mod:", sub: "módulo" }];
    return pool.filter(s => s.tok.toLowerCase().startsWith(m.toLowerCase())).slice(0, 6);
  }, [query, ISSUES, suggOff]);
  useEffectF(() => { setSuggIdx(0); setSuggOff(false); }, [query]);
  const applySugg = (tok) => { setQuery(q => q.replace(/(\S*)$/, tok) + (tok.endsWith(":") ? "" : " ")); searchRef.current?.focus(); };

  const groups = useMemoF(() => {
    const key = (i) => groupBy === "onda" ? (i.onda || "Sem onda") : groupBy === "frente" ? (i.frente || "FORJA") : groupBy === "fase" ? (i.fase || "Execução (sem fase)") : groupBy === "assignee" ? i.assignee : groupBy === "prio" ? i.prio : i.modulo;
    const map = {};
    filtered.forEach(i => { (map[key(i)] = map[key(i)] || []).push(i); });
    return Object.entries(map).map(([g, items]) => [g, items.flatMap(i => {
      const kids = kidsOf[i.id];
      return (kids && kids.length && expanded.has(i.id)) ? [{ i, sub: false }, ...kids.map(k => ({ i: k, sub: true }))] : [{ i, sub: false }];
    })]);
  }, [filtered, groupBy, kidsOf, expanded]);

  const flat = useMemoF(() => groups.flatMap(([g, items]) => collapsed[g] ? [] : items.map(x => x.i)), [groups, collapsed]);
  useEffectF(() => { if (sel > flat.length - 1) setSel(Math.max(0, flat.length - 1)); }, [flat.length]);

  const commands = useMemoF(() => {
    const cmds = [];
    ISSUES.forEach(i => cmds.push({ id: "i-" + i.id, kind: "issue", kindLabel: "issue", label: i.id + " · " + i.titulo, sub: i.modulo + " · " + i.fase, tag: i.prio, run: () => { goLista(); setOpenId(i.id); } }));
    window.FORJA.ONDAS.forEach(o => cmds.push({ id: "o-" + o.id, kind: "onda", kindLabel: "onda", label: "~" + o.id + " · " + o.nome, sub: o.estado, run: () => { goLista(); setGroupBy("onda"); } }));
    window.FORJA.CHANGELOG.forEach(e => cmds.push({ id: "c-" + e.ref, kind: "log", kindLabel: "log", label: e.ref + " · " + e.resumo, sub: e.data, run: () => setView("changelog") }));
    cmds.push({ id: "a-hoje", kind: "acao", kindLabel: "ir", label: "Ir: Aprovações (mesa)", run: () => setView("hoje") });
    cmds.push({ id: "a-quadro", kind: "acao", kindLabel: "ir", label: "Ir: Trabalho (quadro)", run: () => { setView("trabalho"); setTrabVis("quadro"); } });
    cmds.push({ id: "a-gantt", kind: "acao", kindLabel: "ir", label: "Ir: Trabalho (Gantt — prazos)", run: () => { setView("trabalho"); setTrabVis("gantt"); } });
    cmds.push({ id: "a-backlog", kind: "acao", kindLabel: "ir", label: "Ir: Trabalho (lista)", run: () => goLista() });
    cmds.push({ id: "a-changelog", kind: "acao", kindLabel: "ir", label: "Ir: Changelog", run: () => setView("changelog") });
    cmds.push({ id: "a-mcp", kind: "acao", kindLabel: "ir", label: "Ir: MCP", run: () => setView("mcp") });
    cmds.push({ id: "a-saude", kind: "acao", kindLabel: "ir", label: "Ir: Saúde", run: () => setView("saude") });
    cmds.push({ id: "a-integra", kind: "acao", kindLabel: "ir", label: "Ir: Integrador (Forja ↔ TeamMcp)", run: () => setView("integra") });
    cmds.push({ id: "a-papeis", kind: "acao", kindLabel: "abrir", label: "Trilhas de papel (runbook)", run: () => setRunbook(true) });
    cmds.push({ id: "a-ask", kind: "acao", kindLabel: "IA", label: "Perguntar à memória", run: () => setIaPanel({ mode: "ask" }) });
    FJ_GROUPS.forEach(g => cmds.push({ id: "g-" + g.id, kind: "acao", kindLabel: "agrupar", label: "Agrupar por " + g.label, run: () => { goLista(); setGroupBy(g.id); } }));
    if (open) {
      cmds.push({ id: "ch-fase", kind: "acao", kindLabel: "mover", label: "Mover " + open.id + " p/ fase…", children: window.FORJA.PHASES.map(p => ({ id: "chf-" + p.id, kind: "acao", kindLabel: p.id, label: p.id + " · " + p.label, run: () => moveFase(open.id, p.id) })) });
      cmds.push({ id: "ch-assign", kind: "acao", kindLabel: "atribuir", label: "Atribuir " + open.id + " a…", children: Object.keys(window.FORJA.ACTORS).map(r => ({ id: "cha-" + r, kind: "acao", kindLabel: r, label: "[" + r + "] " + window.FORJA.ACTORS[r].name, run: () => patchIssue(open.id, { assignee: r }, { ator: "W", t: "atribuído a [" + r + "]", quando: "agora" }) })) });
    }
    cmds.push({ id: "ch-filter", kind: "acao", kindLabel: "filtrar", label: "Filtrar por papel…", children: Object.keys(window.FORJA.ACTORS).map(r => ({ id: "chx-" + r, kind: "acao", kindLabel: r, label: "[" + r + "]", run: () => { setView("backlog"); setAssigneeFilter(r); } })) });
    return cmds;
  }, [ISSUES, open]);

  useEffectF(() => {
    const onKey = (e) => {
      const mod = e.metaKey || e.ctrlKey;
      if (mod && e.key.toLowerCase() === "k") { e.preventDefault(); setPalette(p => !p); return; }
      const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || "");
      if (palette || cheat) return;
      if (e.key === "Escape") { if (openId) { setOpenId(null); return; } }
      if (typing) { if (e.key === "Escape") e.target.blur(); return; }
      if (e.key === "?") { e.preventDefault(); setCheat(true); }
      else if (isLista && e.key === "j") { e.preventDefault(); setSel(s => Math.min(s + 1, flat.length - 1)); }
      else if (isLista && e.key === "k") { e.preventDefault(); setSel(s => Math.max(s - 1, 0)); }
      else if (isLista && (e.key === "Enter" || e.key === "e")) { e.preventDefault(); if (flat[sel]) setOpenId(flat[sel].id); }
      else if (isLista && e.key === "x") { e.preventDefault(); if (flat[sel]) toggleSel(flat[sel].id); }
      else if (isLista && e.key === "p") { e.preventDefault(); if (flat[sel]) togglePin(flat[sel].id); }
      else if (view === "trabalho" && e.key === "v") { e.preventDefault(); setTrabVis(x => x === "lista" ? "quadro" : x === "quadro" ? "gantt" : "lista"); }
      else if (isLista && (e.key === "ArrowRight" || e.key === "ArrowLeft")) {
        const it = flat[sel]; const kids = it && kidsOf[it.id];
        if (kids && kids.length) { e.preventDefault(); setExpanded(s => { const n = new Set(s); e.key === "ArrowRight" ? n.add(it.id) : n.delete(it.id); return n; }); }
      }
      else if (e.key === "/" || e.key === "c") { e.preventDefault(); searchRef.current?.focus(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [flat, sel, openId, palette, cheat, view, trabFrente, trabVis, kidsOf]);

  const totals = useMemoF(() => ({
    n: filtered.length, p0: filtered.filter(i => i.prio === "P0").length,
    blocked: filtered.filter(i => i.bloqueado_por.length > 0).length,
    inferido: filtered.filter(i => i.frescor === "inferido").length,
  }), [filtered]);

  const [ondaMsg, setOndaMsg] = useStateF(null);
  const [quadroEixo, setQuadroEixo] = useStateF(() => { try { return localStorage.getItem("oimpresso.forja.quadro") || "fases"; } catch (e) { return "fases"; } });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.quadro", quadroEixo); } catch (e) {} }, [quadroEixo]);
  const encerrarOnda = (id) => {
    const O = window.FORJA.ONDAS;
    const o = O.find(x => x.id === id); if (!o) return;
    const next = O.find(x => (x.depende || []).includes(id)) || O.find(x => x.estado === "planejada");
    const abertos = backlogIssues.filter(i => i.onda === id && i.fase !== "F4");
    abertos.forEach(i => patchIssue(i.id, { onda: next ? next.id : null, carry: (i.carry || 0) + 1 }, { ator: "W", t: "onda " + id + " encerrada → carry pra " + (next ? "~" + next.id : "sem onda"), quando: "agora" }));
    o.estado = "encerrada"; if (next) next.estado = "ativa";
    window.FORJA.CHANGELOG.unshift({ tipo: "onda", ref: id + " encerrada", resumo: abertos.length + " issue(s) não-concluído(s) → " + (next ? "~" + next.id : "sem onda") + " (carry-over) · fechamento vira insumo do ritmo", autor: "W", data: "hoje", modulos: [...new Set(abertos.map(i => i.modulo))], flags: [] });
    setOndaMsg("~" + id + " encerrada — " + abertos.length + " issue(s) carregados pra " + (next ? "~" + next.id : "sem onda"));
    setTimeout(() => setOndaMsg(null), 6000);
  };

  const groupLabel = (g) => {
    if (groupBy === "onda" && g !== "Sem onda") { const o = window.FORJA.ONDAS.find(x => x.id === g); return o ? `${g} · ${o.nome}` : g; }
    if (groupBy === "fase") { const p = window.FORJA.PHASES.find(x => x.id === g); return p ? `${g} ${p.label}` : g; }
    if (groupBy === "assignee") { const a = window.FORJA.ACTORS[g]; return a ? `[${g}] ${a.name}` : g; }
    return g;
  };
  const toggleGroup = (g) => setCollapsed(c => ({ ...c, [g]: !c[g] }));
  const hfLabel = { inferido: "não-verificados", blocked: "bloqueados", p0: "P0", doing: "fazendo" };

  const Toolbar = (
    <div className="fj-toolbar">
      <div className="fj-groupby">
        <span className="fj-groupby-lbl">{isQuadro ? "Eixo" : "Agrupar"}</span>
        {isQuadro && [["fases", "Pipeline de telas"], ["exec", "Execução (status)"]].map(([id, lbl]) => (<button key={id} className={"fj-gb-btn" + (quadroEixo === id ? " active" : "")} onClick={() => setQuadroEixo(id)}>{lbl}</button>))}
        {isLista && FJ_GROUPS.map(g => (<button key={g.id} className={"fj-gb-btn" + (groupBy === g.id ? " active" : "")} onClick={() => setGroupBy(g.id)}>{g.label}</button>))}
        {isLista && <span className="fj-groupby-lbl" style={{ marginLeft: 8 }}>Ordem</span>}
        {isLista && [["rank", "rank"], ["exec", "execução"]].map(([id, lbl]) => (<button key={id} className={"fj-gb-btn" + ((ordemBy[trabFrente] || (trabFrente === "forja" ? "rank" : "exec")) === id ? " active" : "")} onClick={() => setOrdemBy(o => ({ ...o, [trabFrente]: id }))} title={id === "rank" ? "prio × parado × destrava (pin fura)" : "status → prio → id (canon Tasks)"}>{lbl}</button>))}
        {isLista && <button className="fj-gb-btn" onClick={() => setDenso(x => !x)}>{denso ? "densidade: compacta" : "densidade: normal"}</button>}
        <button className={"fj-gb-btn fj-fav-toggle" + (favOnly ? " active" : "")} onClick={() => setFavOnly(f => !f)} title="Só favoritos"><svg className={"fj-fav-glyph" + (favOnly ? " on" : "")} width="13" height="13" viewBox="0 0 24 24" fill={favOnly ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><polygon points="12 2.5 15 9 22 9.6 16.5 14.2 18.2 21 12 17.3 5.8 21 7.5 14.2 2 9.6 9 9"/></svg>favoritos</button>
        {healthFilter && <button className="fj-fchip" onClick={() => setHealthFilter(null)}>{hfLabel[healthFilter]} ✕</button>}
      </div>
      <button className="fj-ia-btn" onClick={() => setRunbook(true)} title="Trilhas de papel"><I.users size={11}/>Papéis</button>
      <button className="fj-ia-btn" onClick={() => setIaPanel({ mode: "ask" })} title="Perguntar à memória"><span className="fj-ia-spark">✦</span>Perguntar</button>
      <div className="fj-search">
        <I.search size={12}/>
        <input ref={searchRef} placeholder="Buscar…  is:p0 @CL ~FA-1 tipo:bug" value={query} onChange={e => setQuery(e.target.value)}
               onKeyDown={e => {
                 if (!suggests.length) return;
                 if (e.key === "Escape") { e.preventDefault(); e.stopPropagation(); setSuggOff(true); return; }
                 if (e.key === "ArrowDown") { e.preventDefault(); setSuggIdx(i => Math.min(i + 1, suggests.length - 1)); }
                 else if (e.key === "ArrowUp") { e.preventDefault(); setSuggIdx(i => Math.max(i - 1, 0)); }
                 else if (e.key === "Tab" || e.key === "Enter") { e.preventDefault(); applySugg(suggests[suggIdx].tok); }
               }}/>
        {suggests.length > 0 && (
          <ul className="fj-sugg">
            {suggests.map((s, i) => (
              <li key={s.tok} className={"fj-sugg-it" + (i === suggIdx ? " sel" : "")} onMouseEnter={() => setSuggIdx(i)} onMouseDown={e => { e.preventDefault(); applySugg(s.tok); }}>
                <span className="fj-sugg-tok">{s.tok}</span><span className="fj-sugg-sub">{s.sub}</span>
              </li>
            ))}
            <li className="fj-sugg-foot"><kbd>tab</kbd> completa · <kbd>↑↓</kbd> escolhe</li>
          </ul>
        )}
      </div>
    </div>
  );

  const FilterBar = (
    <div className="fj-filterbar2">
      <span className="fj-groupby-lbl">Papel</span>
      <button className={"fj-gb-btn" + (!assigneeFilter ? " active" : "")} onClick={() => setAssigneeFilter(null)}>todos</button>
      {Object.keys(window.FORJA.ACTORS).map(r => (<button key={r} className={"fj-gb-btn" + (assigneeFilter === r ? " active" : "")} onClick={() => setAssigneeFilter(a => a === r ? null : r)}>[{r}]</button>))}
      {isLista && (
        <React.Fragment>
          <span className="fj-fb-sep"/>
          <span className="fj-groupby-lbl">Visões</span>
          {savedViews.map((v, i) => (<button key={i} className="fj-view-chip" onClick={() => applyView(v)} title="aplicar visão">{v.name}<span className="fj-view-x" onClick={(e) => { e.stopPropagation(); delView(i); }}>✕</span></button>))}
          <button className="fj-view-save" onClick={saveView}>+ salvar visão</button>
        </React.Fragment>
      )}
    </div>
  );

  return (
    <div className="fj-page">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Forja</h1>
          <p>Cockpit do cowork loop — aprovações da equipe, backlog, pipeline de telas F0→F3.5, tarefas de todas as frentes, changelog e atores (humano vs agente).</p>
        </div>
        <div className="os-page-h-r">
          <button className="fj-bell" onClick={() => setNotifOpen(true)} title="Minha fila">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            {notifCount > 0 && <span className="fj-bell-badge">{notifCount}</span>}
          </button>
          <button className="fj-kbtn" onClick={() => setPalette(true)} title="Paleta de comandos"><I.search size={11}/>Buscar<kbd>⌘K</kbd></button>
          <div className="fj-viewtabs grouped">
            {[["Trabalho", [["hoje", "Aprovações", FjIcHoje], ["trabalho", "Trabalho", I.list]]],
              ["Esteira", [["saude", "Saúde", I.chart], ["mcp", "MCP", I.shield]]],
              ["Histórico", [["changelog", "Changelog", I.clock], ["integra", "Integrador", I.plug]]]].map(([g, items]) => (
              <div key={g} className="fj-navgroup" role="group" aria-label={g}>
                <span className="fj-navgroup-lbl">{g}</span>
                {items.map(([id, lbl, Ic]) => (
                  <button key={id} className={view === id ? "active" : ""} onClick={() => setView(id)}><Ic size={11}/>{lbl}{id === "hoje" && pendencias > 0 && <span className="fj-tab-badge">{pendencias}</span>}</button>
                ))}
              </div>
            ))}
          </div>
          <button className="os-btn primary" onClick={() => setComposer(true)}><I.plus size={11}/>Novo issue</button>
        </div>
      </div>

      {view === "trabalho" && (
        <div className="fj-frentebar">
          <window.CliSeg ariaLabel="Visão do trabalho" size="sm" value={trabVis} onChange={setTrabVis}
            options={[{ key: "lista", label: "Lista", icon: <I.list size={11} /> },
              { key: "quadro", label: "Quadro", icon: <I.grid size={11} /> },
              { key: "gantt", label: "Gantt", icon: <I.clock size={11} /> }]} />
          <span className="fj-frente-note"><b className="mono">{unifiedAll.length}</b> mcp_tasks numa lista só — FORJA junto das demais frentes (agrupe por Frente ou busque)</span>
        </div>
      )}
      {(isLista || isQuadro || (view === "trabalho" && trabVis === "gantt")) && (() => { const pool = trabFrente === "forja" ? backlogIssues : unifiedAll; const kp = { total: pool.length, p0: pool.filter(i => i.prio === "P0" && (i.exec || "backlog") !== "done").length, doing: pool.filter(i => (i.exec || "backlog") === "doing").length, blocked: pool.filter(i => (i.bloqueado_por || []).length || i.exec === "blocked").length }; return (
        <div className="fj-kpirow">
          <button className="tf-kpi" disabled><span className="tf-kpi-v">{kp.total}</span><span className="tf-kpi-l">Total</span></button>
          <button className={"tf-kpi click " + (kp.p0 ? "bad" : "ok") + (healthFilter === "p0" ? " on" : "")} onClick={() => setHealthFilter(h => h === "p0" ? null : "p0")}><span className="tf-kpi-v">{kp.p0}</span><span className="tf-kpi-l">P0 abertas</span></button>
          <button className={"tf-kpi click" + (healthFilter === "doing" ? " on" : "")} onClick={() => setHealthFilter(h => h === "doing" ? null : "doing")}><span className="tf-kpi-v">{kp.doing}</span><span className="tf-kpi-l">Fazendo</span></button>
          <button className={"tf-kpi click " + (kp.blocked ? "warn" : "ok") + (healthFilter === "blocked" ? " on" : "")} onClick={() => setHealthFilter(h => h === "blocked" ? null : "blocked")}><span className="tf-kpi-v">{kp.blocked}</span><span className="tf-kpi-l">Bloqueadas</span></button>
          <span className="fj-kpirow-note">clique filtra a lista e o quadro</span>
        </div>
      ); })()}
      {(isLista || isQuadro || (view === "trabalho" && trabVis === "gantt")) && Toolbar}
      {(isLista || isQuadro || (view === "trabalho" && trabVis === "gantt")) && FilterBar}

      {isLista && <FjListaView ctx={{ ondaMsg, denso, groups, collapsed, toggleGroup, groupLabel, groupBy, encerrarOnda, setIaPanel, flat, sel, setSel, setOpenId, fav, toggleFav, selected, toggleSel, pin, togglePin, blocksCount, kidsOf, expanded, toggleExpand, filtered, bulkPhase, bulkAssign, bulkPrio, bulkOnda, bulkFav, setSelected, moveExec, totals }}/>}

      {view === "hoje" && <window.ForjaAprovacoes triagem={triagemIssues} onTriage={(id) => setDossie(id)} onAprovarProposta={approveTriagem} onRejeitarProposta={rejectTriagem} onDesfazerProposta={(id) => patchIssue(id, { estado: "triagem" }, { ator: "W", t: "decisão desfeita — volta pra fila", quando: "agora" })} onGoHandoffs={() => setView("mcp")}/>}
      {isQuadro && <FjKanbanView issues={filtered} eixo={quadroEixo} onOpen={setOpenId} onMove={moveFase} onMoveExec={moveExec} fav={fav} onFav={toggleFav}/>}
      {view === "trabalho" && trabVis === "gantt" && <FjGanttView issues={filtered} onOpen={setOpenId}/>}
      {view === "changelog" && <FjChangelogFeed/>}
      {view === "mcp" && <window.ForjaMCPView/>}
      {view === "saude" && <FjSaudeView issues={ISSUES} onDrill={drill} rules={rules} onToggleRule={toggleRule}/>}
      {view === "integra" && <window.ForjaIntegrador/>}

      {open && <FjIssueDrawer issue={open} relations={relations} following={follow.has(open.id)} onFollow={toggleFollow} rules={rules} onClose={() => setOpenId(null)} onPatch={patchIssue} onReverify={reverify} onComment={addComment} onReact={react} onLink={resolveLink}/>}
      {palette && <FjCommandPalette commands={commands} onClose={() => setPalette(false)}/>}
      {cheat && <FjCheatSheet onClose={() => setCheat(false)}/>}
      {iaPanel && <window.ForjaIAPanel mode={iaPanel.mode} onda={iaPanel.onda} onClose={() => setIaPanel(null)} onHandoff={(onda) => { setIaPanel(null); setHandoff(onda); }}/>}
      {composer && <window.ForjaNewIssue onCreate={(iss) => setCreated(c => [iss, ...c])} onClose={() => setComposer(false)}/>}
      {runbook && <window.ForjaRunbook onClose={() => setRunbook(false)}/>}
      {handoff && <window.ForjaHandoff onda={handoff} onClose={() => setHandoff(null)}/>}
      {dossie && <window.ForjaDossie issue={ISSUES.find(i => i.id === dossie)} allIssues={ISSUES} onApprove={approveTriagem} onReject={rejectTriagem} onMerge={mergeDup} onClose={() => setDossie(null)}/>}
      {notifOpen && <window.ForjaNotifs notifs={notifs} seen={notifSeen} onSeen={markSeen} onMarkAll={markAllSeen} onOpen={(id) => { setNotifOpen(false); goLista(); setOpenId(id); }} onTriage={(id) => { setNotifOpen(false); setView("hoje"); setDossie(id); }} onClose={() => setNotifOpen(false)}/>}
    </div>
  );
}

window.ForjaPage = ForjaPage;

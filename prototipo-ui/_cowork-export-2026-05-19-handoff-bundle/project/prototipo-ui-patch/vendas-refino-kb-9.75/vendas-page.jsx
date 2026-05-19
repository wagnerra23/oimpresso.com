// vendas-page.jsx — Vendas/Index + Vendas/Create (P0 do protocolo PR #295)
// 2026-05-14: refator A+ pra elevar 8.7 → 9.5
// 8 itens: identidade green · sparkline+ageing · stepper FSM · avatar+comissão
//          Vista toggle · ⌘K · saved views+bulk · NF-e+NFS-e vinculados
const { useState: useStateV, useMemo: useMemoV, useEffect: useEffectV, useRef: useRefV } = React;

// ──────────────────────────────────────────────────────────────
// HELPERS — formatação e derivações
// ──────────────────────────────────────────────────────────────
const vdFmt      = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
const vdFmtShort = (n) => n >= 1000 ? "R$ " + (n/1000).toFixed(1).replace(".",",") + "k" : vdFmt(n);
const vdFmtChave = (k) => (k || "").replace(/(\d{4})/g, "$1 ").trim();

const vdHasProduto = (v) => v.itemsList?.some(i => i.type === "produto");
const vdHasServico = (v) => v.itemsList?.some(i => i.type === "servico");
const vdIsToday    = (v) => v.date === "2026-05-14";

// ──────────────────────────────────────────────────────────────
// SUB-COMPONENTES VISUAIS
// ──────────────────────────────────────────────────────────────
function VdSparkline({ data, color = "currentColor", fill = true, h = 32, w = 240 }) {
  if (!data?.length) return null;
  const pad = 2;
  const min = Math.min(...data), max = Math.max(...data);
  const dx = (w - pad*2) / (data.length - 1);
  const pts = data.map((v, i) => [pad + i*dx, h - pad - ((v - min) / (max - min || 1)) * (h - pad*2)]);
  const line = "M" + pts.map(p => p.join(",")).join(" L");
  const area = line + ` L${pts[pts.length-1][0]},${h} L${pts[0][0]},${h} Z`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{width:"100%",height:h,display:"block"}}>
      {fill && <path d={area} fill={color} opacity="0.18"/>}
      <path d={line} fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
      <circle cx={pts[pts.length-1][0]} cy={pts[pts.length-1][1]} r="2.5" fill={color}/>
    </svg>
  );
}

function VdStepper({ fsm, vertical }) {
  const { FSM_BY_VERTICAL } = window.VENDAS_DATA;
  const set = FSM_BY_VERTICAL[vertical || "cv"] || FSM_BY_VERTICAL.cv;
  return (
    <span className="vd-stp" title={`${set.steps[fsm]} — etapa ${fsm+1} de 5`}>
      {set.steps.map((_, i) => {
        let cls = "vd-stp-dot";
        if (i < fsm) cls += " done";
        else if (i === fsm) cls += " current";
        return <span key={i} className={cls}/>;
      })}
      <span className="vd-stp-lbl">{set.lbls[fsm]}</span>
    </span>
  );
}

// Placa Mercosul (Oficina Mìânica) — inline pequena
function VdPlate({ value }) {
  if (!value) return null;
  return (
    <span className="vd-plate">
      <span className="vd-plate-top">BR·MERCOSUL</span>
      <span className="vd-plate-num">{value}</span>
    </span>
  );
}

// ──────────────────────────────────────────────────────────────
// SLA — fresco · atrasando · estourado (Refino #1 KB-9.75)
// Regra: due = v.date + payTerm. daysRem = due - hoje.
//   fsm>=4  → paga (sem SLA)
//   urgent || daysRem<0 → estourado
//   daysRem<=7 → atrasando
//   else → fresco
// ──────────────────────────────────────────────────────────────
const VD_TODAY = new Date("2026-05-14"); // mock today — alinhar com data-vendas
function vdSlaInfo(v) {
  if (v.fsm >= 4) return { kind: "paid", daysRem: 0, label: "paga", short: "✓" };
  const [Y, M, D] = v.date.split("-").map(Number);
  const due = new Date(Y, M - 1, D);
  due.setDate(due.getDate() + (v.payTerm || 0));
  const daysRem = Math.round((due - VD_TODAY) / 86400000);
  if (v.urgent || daysRem < 0) {
    const overdueDays = Math.max(0, -daysRem);
    return { kind: "overdue", daysRem, label: `estourado · ${overdueDays}d`, short: `-${overdueDays}d` };
  }
  if (daysRem <= 7) return { kind: "warning", daysRem, label: `atrasando · ${daysRem}d`, short: `${daysRem}d` };
  return { kind: "fresh", daysRem, label: `vence ${daysRem}d`, short: `${daysRem}d` };
}

const VD_SLA_ICON = { fresh: "●", warning: "▲", overdue: "✕", paid: "✓" };
function VdSlaPill({ v, compact }) {
  const s = vdSlaInfo(v);
  return (
    <span className={`vd-sla vd-sla-${s.kind}`} title={s.label}>
      <span className="vd-sla-ic">{VD_SLA_ICON[s.kind]}</span>
      <span className="vd-sla-lbl">{compact ? s.short : s.label}</span>
    </span>
  );
}

const VD_FBADGE_MAP = {
  ok:   { cls: "ok",   ic: "✓" },
  wait: { cls: "wait", ic: "⌛" },
  bad:  { cls: "bad",  ic: "✕" },
  canc: { cls: "canc", ic: "⊘" },
  na:   { cls: "na",   ic: "—" },
};
function VdFBadge({ kind, status, doc }) {
  const lbl = kind === "nfe" ? "NF-e" : "NFS-e";
  const m = VD_FBADGE_MAP[status] || VD_FBADGE_MAP.na;
  const tip = status === "ok"   && doc ? `Autorizada · ${doc.numero}/${doc.serie}`
            : status === "wait"        ? "Transmitida · aguardando SEFAZ"
            : status === "bad"         ? "Rejeitada SEFAZ"
            : status === "canc"        ? "Cancelada"
            : "Não emitida";
  return (
    <span className={`vd-fb vd-fb-${m.cls}`}>
      <span className="vd-fb-ic">{m.ic}</span>{lbl}
      <span className="vd-fb-tip">{tip}</span>
    </span>
  );
}

function VdFiscalCell({ v }) {
  const f = v.fiscal || {};
  const nfeS  = f.nfe  ? f.nfe.status  : (vdHasProduto(v) ? "na" : null);
  const nfseS = f.nfse ? f.nfse.status : (vdHasServico(v) ? "na" : null);
  return (
    <span className="vd-fc">
      {nfeS  && <VdFBadge kind="nfe"  status={nfeS}  doc={f.nfe}/>}
      {nfseS && <VdFBadge kind="nfse" status={nfseS} doc={f.nfse}/>}
      {!nfeS && !nfseS && <span className="vd-fb vd-fb-na"><span className="vd-fb-ic">—</span></span>}
    </span>
  );
}

// Avatar do vendedor ou mecânico
function VdAv({ sid, mid }) {
  const { VENDEDORES_MAP, MECANICOS_MAP } = window.VENDAS_DATA;
  const s = (mid && MECANICOS_MAP[mid]) || VENDEDORES_MAP[sid];
  if (!s) return null;
  return <span className={`vd-av vd-av-${s.av}`}>{s.abbr}</span>;
}

// Card NF-e ou NFS-e dentro do drawer
function VdFiscalCard({ kind, doc }) {
  const [copied, setCopied] = useStateV(false);
  const lbl = kind === "nfe" ? "NF-e" : "NFS-e";
  const danfeLbl = kind === "nfe" ? "DANFE PDF" : "DANFS-e PDF";
  const isFail = doc.status === "bad";
  const isCanc = doc.status === "canc";

  const tlSteps = [
    { lbl: "Emitida",     done: true },
    { lbl: "Transmitida", done: true },
    { lbl: "Autorizada",  done: doc.status === "ok" || doc.status === "canc", failed: isFail },
    { lbl: "E-mail OK",   done: doc.status === "ok" },
    { lbl: isCanc ? "Cancelada" : "Aprovada", done: doc.status === "ok" || isCanc },
  ];
  const statusLbl = { ok:"Autorizada", wait:"Processando", bad:"Rejeitada", canc:"Cancelada" }[doc.status];

  const copy = () => {
    navigator.clipboard?.writeText(doc.chave);
    setCopied(true);
    setTimeout(() => setCopied(false), 1200);
  };

  const dateStr = doc.date.split("T")[0].split("-").reverse().join("/");
  const timeStr = doc.date.split("T")[1]?.slice(0,5) || "";

  return (
    <div className={`vd-fcard ${isFail ? "failed" : ""}`}>
      <div className="vd-fcard-h">
        <h4>{lbl}</h4>
        <span className={`vd-fb vd-fb-${VD_FBADGE_MAP[doc.status].cls}`}>
          <span className="vd-fb-ic">{VD_FBADGE_MAP[doc.status].ic}</span>{statusLbl}
        </span>
        <span className="vd-fcard-date">{dateStr} <span>{timeStr}</span></span>
      </div>

      {isFail && (
        <div className="vd-fcard-fail">
          <b>Motivo da rejeição SEFAZ</b>
          {doc.failReason}
        </div>
      )}
      {isCanc && doc.cancelReason && (
        <div className="vd-fcard-fail neutral">
          <b>Motivo do cancelamento</b>
          {doc.cancelReason}
        </div>
      )}

      <dl className="vd-fcard-meta">
        <dt>Número</dt><dd>{doc.numero}</dd>
        <dt>Série</dt><dd>{doc.serie}</dd>
      </dl>

      <div className="vd-fcard-chave">
        <span className="vd-fcard-chave-num">{vdFmtChave(doc.chave)}</span>
        <button className={`vd-fcard-copy ${copied ? "copied" : ""}`} onClick={copy}>
          {copied ? "Copiado ✓" : "Copiar"}
        </button>
      </div>

      <div className="vd-fcard-tl">
        {tlSteps.map((s, i) => (
          <div key={i} className={`vd-fcard-step ${s.done ? "done" : ""} ${s.failed ? "failed" : ""}`}>
            <span className="vd-fcard-step-d">{s.failed ? "✕" : s.done ? "✓" : i+1}</span>
            <span className="vd-fcard-step-l">{s.lbl}</span>
          </div>
        ))}
      </div>

      <details className="vd-fcard-cce">
        <summary>+ CC-e (Carta de Correção)</summary>
        <p>Nenhuma carta de correção emitida pra este documento.</p>
      </details>

      <div className="vd-fcard-ctas">
        <button className="vd-fcard-cta"><I.archive size={11}/>{danfeLbl}</button>
        <button className="vd-fcard-cta"><I.folder size={11}/>XML</button>
        <button className="vd-fcard-cta"><I.message size={11}/>Enviar</button>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// INDEX — VendasListPage
// ──────────────────────────────────────────────────────────────
function VendasListPage() {
  const { VENDAS_LIST, VENDAS_STATUS, VENDEDORES_MAP, VENDAS_SAVED_VIEWS } = window.VENDAS_DATA;

  // UI state — persiste em localStorage onde fizer sentido
  const lsGet = (k, d) => { try { return localStorage.getItem("oimpresso.sells."+k) ?? d; } catch(e) { return d; } };
  const lsSet = (k, v) => { try { localStorage.setItem("oimpresso.sells."+k, v); } catch(e){} };

  const [vista, setVista]         = useStateV(() => lsGet("vista", "caixa"));
  const [savedView, setSavedView] = useStateV(() => lsGet("savedView", "hoje"));
  const [viewsOpen, setViewsOpen] = useStateV(false);
  const [statusF, setStatusF]     = useStateV(() => lsGet("statusF", "todas"));
  const [query, setQuery]         = useStateV(() => lsGet("query", ""));
  const [selected, setSelected]   = useStateV(() => new Set());
  const [openId, setOpenId]       = useStateV(null);
  const [createOpen, setCreateOpen] = useStateV(false);
  const [palOpen, setPalOpen]     = useStateV(false);
  const [palQ, setPalQ]           = useStateV("");
  const [palSel, setPalSel]       = useStateV(0);
  const [loading, setLoading]     = useStateV(false); // demo skeleton
  // ── Refino #1 KB-9.75: cheat-sheet + row focus + tree expand
  const [cheatOpen, setCheatOpen] = useStateV(false);
  const [focusIdx, setFocusIdx]   = useStateV(-1);
  const [viewsExpand, setViewsExpand] = useStateV({ pendentes: true, faturadas: false });
  const [favSet, setFavSet]       = useStateV(() => {
    try { return new Set(JSON.parse(localStorage.getItem("oimpresso.sells.favs") || "[]")); }
    catch (e) { return new Set(); }
  });
  const rowsRef = useRefV([]);
  // Refino #2 KB-9.75: estado da IA no palette
  const [aiPalState, setAiPalState] = useStateV("idle"); // idle | loading | done
  const [aiPalAnswer, setAiPalAnswer] = useStateV("");
  // reset IA ao fechar/limpar palette
  useEffectV(() => {
    if (!palOpen) { setAiPalState("idle"); setAiPalAnswer(""); }
  }, [palOpen]);
  useEffectV(() => {
    setAiPalState("idle"); setAiPalAnswer("");
  }, [palQ]);
  const askAi = async () => {
    if (!window.claude?.complete || !palQ.trim()) { setAiPalState("done"); setAiPalAnswer("Helper IA não disponível."); return; }
    setAiPalState("loading");
    try {
      const text = await window.claude.complete(
        `Você é assistente do ERP Oimpresso (gráfica). Responda em 1-2 frases curtas, português, sobre vendas/clientes/notas fiscais. Se não tiver contexto, diga "Não consegui inferir disso, abre a venda direto".\n\nPergunta: ${palQ}`
      );
      setAiPalAnswer(text);
    } catch (e) { setAiPalAnswer("Erro: " + (e?.message || e)); }
    setAiPalState("done");
  };

  // persist
  useEffectV(() => { lsSet("vista", vista); }, [vista]);
  useEffectV(() => { lsSet("savedView", savedView); }, [savedView]);
  useEffectV(() => { lsSet("statusF", statusF); }, [statusF]);
  useEffectV(() => { lsSet("query", query); }, [query]);

  // demo skeleton: simula 600ms de carga no mount inicial (cobre dim 5 estados)
  useEffectV(() => {
    if (lsGet("skipSkeleton", "0") === "1") return;
    setLoading(true);
    const t = setTimeout(() => setLoading(false), 600);
    return () => clearTimeout(t);
  }, []);

  // shortcuts (Refino #1 KB-9.75 — J/K row-nav · R/F/B/E/X · ? cheat-sheet)
  useEffectV(() => {
    const onKey = (e) => {
      const inField = ["INPUT","TEXTAREA","SELECT"].includes(e.target.tagName) || e.target.isContentEditable;

      // sempre — palette e fechar
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault(); setPalOpen(true); return;
      }
      if (e.key === "Escape") {
        if (cheatOpen)       setCheatOpen(false);
        else if (palOpen)    setPalOpen(false);
        else if (viewsOpen)  setViewsOpen(false);
        else if (openId)     setOpenId(null);
        else if (createOpen) setCreateOpen(false);
        else if (focusIdx >= 0) setFocusIdx(-1);
        return;
      }
      // se digitando em campo, não captura nada além de Esc/⌘K
      if (inField) return;
      // se palette/drawer aberto, atalhos de linha desligados
      if (palOpen || cheatOpen || openId || createOpen) return;

      const total = (window.__vendasFiltered || []).length;
      const cur = focusIdx;

      if (e.key === "j" || e.key === "J" || e.key === "ArrowDown") {
        e.preventDefault();
        const next = Math.min(total - 1, cur < 0 ? 0 : cur + 1);
        setFocusIdx(next);
        rowsRef.current[next]?.scrollIntoView?.({ block: "nearest" });
      } else if (e.key === "k" || e.key === "K" || e.key === "ArrowUp") {
        e.preventDefault();
        const next = Math.max(0, cur < 0 ? 0 : cur - 1);
        setFocusIdx(next);
        rowsRef.current[next]?.scrollIntoView?.({ block: "nearest" });
      } else if (e.key === "Enter") {
        if (cur >= 0 && window.__vendasFiltered?.[cur]) {
          e.preventDefault(); setOpenId(window.__vendasFiltered[cur].id);
        }
      } else if (e.key === "n" || e.key === "N") {
        e.preventDefault(); setCreateOpen(true);
      } else if (e.key === "?") {
        e.preventDefault(); setCheatOpen(true);
      } else if (e.key === "/") {
        e.preventDefault();
        setPalOpen(true);
      } else if (e.key === "F2") {
        e.preventDefault();
        if (window.__vendasPdvOpen) window.__vendasPdvOpen();
      } else if ((e.key === "r" || e.key === "R") && cur >= 0) {
        const v = window.__vendasFiltered?.[cur];
        if (v) { e.preventDefault(); console.log("R · imprimir recibo", v.id); setOpenId(v.id); }
      } else if ((e.key === "f" || e.key === "F") && cur >= 0) {
        const v = window.__vendasFiltered?.[cur];
        if (v) { e.preventDefault(); console.log("F · faturar", v.id); setOpenId(v.id); }
      } else if ((e.key === "b" || e.key === "B") && cur >= 0) {
        const v = window.__vendasFiltered?.[cur];
        if (v) {
          e.preventDefault();
          setFavSet(prev => {
            const next = new Set(prev);
            if (next.has(v.id)) next.delete(v.id); else next.add(v.id);
            try { localStorage.setItem("oimpresso.sells.favs", JSON.stringify([...next])); } catch (er){}
            return next;
          });
        }
      } else if ((e.key === "x" || e.key === "X") && cur >= 0) {
        const v = window.__vendasFiltered?.[cur];
        if (v) {
          e.preventDefault();
          setSelected(prev => {
            const next = new Set(prev);
            if (next.has(v.id)) next.delete(v.id); else next.add(v.id);
            return next;
          });
        }
      } else if ((e.key === "e" || e.key === "E") && cur >= 0) {
        const v = window.__vendasFiltered?.[cur];
        if (v) { e.preventDefault(); setOpenId(v.id); }
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [palOpen, viewsOpen, openId, createOpen, cheatOpen, focusIdx]);

  // filtro composto: saved view (com sub-view "pendentes:bruna") → busca → status
  const [topView, subView] = (savedView || "hoje").split(":");
  const view = VENDAS_SAVED_VIEWS.find(v => v.id === topView) || VENDAS_SAVED_VIEWS[0];
  const filtered = useMemoV(() => {
    // Refino #4 — view especial "favoritas"
    let out;
    if (topView === "favoritas") {
      out = VENDAS_LIST.filter(v => favSet.has(v.id));
    } else {
      out = VENDAS_LIST.filter(view.filter);
    }
    if (subView) {
      if (topView === "pendentes") {
        out = out.filter(v => {
          const fn = (VENDEDORES_MAP[v.sellerId]?.name || "").split(" ")[0].toLowerCase();
          return fn === subView;
        });
      } else if (topView === "faturadas") {
        if (subView === "b2b") out = out.filter(v => v.client !== "Consumidor Final");
        else if (subView === "b2c") out = out.filter(v => v.client === "Consumidor Final");
      }
    }
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(v =>
        v.id.toLowerCase().includes(q) ||
        v.client.toLowerCase().includes(q) ||
        (v.clientNote || "").toLowerCase().includes(q) ||
        (v.fiscal?.nfe?.chave  || "").includes(q.replace(/\s/g,"")) ||
        (v.fiscal?.nfse?.chave || "").includes(q.replace(/\s/g,""))
      );
    }
    if (statusF !== "todas") out = out.filter(v => v.status === statusF);
    return out;
  }, [VENDAS_LIST, view, topView, subView, query, statusF, VENDEDORES_MAP, favSet]);

  // expor pra keyboard handler ler sem fechar sobre o closure
  useEffectV(() => { window.__vendasFiltered = filtered; }, [filtered]);
  // reset focus quando muda a lista
  useEffectV(() => {
    if (focusIdx >= filtered.length) setFocusIdx(filtered.length ? filtered.length - 1 : -1);
  }, [filtered.length]);

  // KPIs — hoje
  const today      = VENDAS_LIST.filter(vdIsToday);
  const kpi_total  = today.reduce((s,v) => s + v.totalNum, 0);
  const kpi_count  = today.length;
  const kpi_avg    = kpi_count ? kpi_total / kpi_count : 0;
  const kpi_pix    = today.filter(v => v.payment === "PIX").reduce((s,v) => s + v.totalNum, 0);
  const kpi_pixPct = kpi_total ? Math.round((kpi_pix / kpi_total) * 100) : 0;

  // A receber (todas pendentes)
  const arItems = VENDAS_LIST.filter(v => v.fsm < 4);
  const kpi_ar  = arItems.reduce((s,v) => s + v.totalNum, 0);
  const ar_ok   = arItems.filter(v => (v.payTerm||0) <= 30).reduce((s,v)=>s+v.totalNum,0);
  const ar_w    = arItems.filter(v => (v.payTerm||0) > 30 && (v.payTerm||0) <= 60).reduce((s,v)=>s+v.totalNum,0);
  const ar_b    = arItems.filter(v => (v.payTerm||0) > 60).reduce((s,v)=>s+v.totalNum,0);
  const ar_tot  = ar_ok + ar_w + ar_b || 1;
  // Refino #1 KB-9.75: contagem por SLA (fresco/atrasando/estourado)
  const slaCounts = arItems.reduce((acc, v) => {
    const k = vdSlaInfo(v).kind;
    if (k === "overdue") acc.o++;
    else if (k === "warning") acc.w++;
    else if (k === "fresh") acc.f++;
    return acc;
  }, { f:0, w:0, o:0 });

  // Faturamento KPIs
  const fiscalCount = VENDAS_LIST.reduce((acc, v) => {
    ["nfe","nfse"].forEach(k => {
      if (v.fiscal?.[k]?.status === "ok")   acc.ok++;
      if (v.fiscal?.[k]?.status === "wait") acc.wait++;
      if (v.fiscal?.[k]?.status === "bad")  acc.bad++;
    });
    return acc;
  }, { ok:0, wait:0, bad:0 });

  // Sparkline 30d simulado
  const sparkData = [3.2,2.8,4.1,3.6,4.8,5.2,3.9,4.4,5.8,4.6,5.1,6.3,5.4,4.9,6.8,7.2,5.9,6.4,7.8,6.2,7.5,8.4,6.9,7.8,9.1,8.2,7.6,8.9,9.4, kpi_total/1000];

  // counts por status (do filtered)
  const countBy = (s) => s === "todas" ? filtered.length : filtered.filter(v => v.status === s).length;

  // selection
  const toggleSel = (id) => setSelected(prev => {
    const next = new Set(prev);
    if (next.has(id)) next.delete(id); else next.add(id);
    return next;
  });
  const toggleAll = () => {
    if (selected.size === filtered.length) setSelected(new Set());
    else setSelected(new Set(filtered.map(v => v.id)));
  };

  // dropdown close on outside click
  const viewsRef = useRefV(null);
  useEffectV(() => {
    if (!viewsOpen) return;
    const onClick = (e) => { if (viewsRef.current && !viewsRef.current.contains(e.target)) setViewsOpen(false); };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [viewsOpen]);

  // Refino #4 polish — dropdown "Visões ▾" (sub-rotas Caixa/Devoluções/etc que saíram do topnav)
  const [visoesOpen, setVisoesOpen] = useStateV(false);
  const visoesRef = useRefV(null);
  useEffectV(() => {
    if (!visoesOpen) return;
    const onClick = (e) => { if (visoesRef.current && !visoesRef.current.contains(e.target)) setVisoesOpen(false); };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [visoesOpen]);

  // ⌘K palette items
  const palAll = useMemoV(() => {
    const recent = VENDAS_LIST.slice(0, 3).map(v => ({
      grp: "Últimas vendas",
      ico: <I.receipt size={14}/>,
      title: `${v.id} · ${v.client}`,
      sub: `${vdFmt(v.totalNum)} · ${v.payment}`,
      action: () => { setOpenId(v.id); setPalOpen(false); setPalQ(""); },
    }));
    const shortcuts = [
      { grp:"Ações", ico:<I.plus size={14}/>,     title:"Nova venda",                sub:"Drawer de cadastro completo", kbd:"N",  action:()=>{setCreateOpen(true);setPalOpen(false);} },
      { grp:"Ações", ico:<I.folder size={14}/>,   title:"Emitir NF-e em lote",       sub:"Pra todas vendas selecionadas",        action:()=>{alert("Emit lote — placeholder");setPalOpen(false);} },
      { grp:"Buscar",ico:<I.search size={14}/>,   title:"Buscar por chave SEFAZ",    sub:"44 dígitos da NF-e ou NFS-e",          action:()=>{setQuery("3526");setPalOpen(false);} },
      { grp:"Buscar",ico:<I.search size={14}/>,   title:"Filtrar Rejeitadas SEFAZ",  sub:"Saved view",                            action:()=>{setSavedView("rejeitadas");setPalOpen(false);} },
      { grp:"Navegar",ico:<I.folder size={14}/>,  title:"Ir pra Orçamentos",         sub:"Módulo Sells/Orçamentos",              action:()=>{alert("→ Orçamentos");setPalOpen(false);} },
      { grp:"Navegar",ico:<I.folder size={14}/>,  title:"Ir pra Clientes",           sub:"Módulo Clientes",                       action:()=>{alert("→ Clientes");setPalOpen(false);} },
    ];
    return [...recent, ...shortcuts];
  }, [VENDAS_LIST]);
  const palFiltered = useMemoV(() => {
    const raw = palQ.trim();
    if (!raw) return palAll;

    // chave SEFAZ — 20+ dígitos contínuos
    const digits = raw.replace(/\D/g, "");
    if (digits.length >= 20) {
      return VENDAS_LIST.filter(v =>
        (v.fiscal?.nfe?.chave  || "").includes(digits) ||
        (v.fiscal?.nfse?.chave || "").includes(digits)
      ).slice(0, 8).map(v => ({
        grp: "Chave SEFAZ",
        ico: <I.search size={14}/>,
        title: `${v.id} · ${v.client}`,
        sub: vdFmtChave(v.fiscal?.nfe?.chave || v.fiscal?.nfse?.chave || ""),
        action: () => { setOpenId(v.id); setPalOpen(false); setPalQ(""); },
      }));
    }

    // prefixo # — busca por ID de venda
    if (raw.startsWith("#")) {
      const q = raw.slice(1).toLowerCase();
      return VENDAS_LIST.filter(v => v.id.toLowerCase().includes(q))
        .slice(0, 12).map(v => ({
          grp: "Vendas por ID",
          ico: <I.receipt size={14}/>,
          title: `${v.id} · ${v.client}`,
          sub: `${vdFmt(v.totalNum)} · ${v.payment}`,
          action: () => { setOpenId(v.id); setPalOpen(false); setPalQ(""); },
        }));
    }

    // prefixo @ — vendedor
    if (raw.startsWith("@")) {
      const q = raw.slice(1).toLowerCase();
      return VENDAS_LIST.filter(v => {
        const name = (VENDEDORES_MAP[v.sellerId]?.name || "").toLowerCase();
        return name.includes(q);
      }).slice(0, 12).map(v => ({
        grp: `Vendas por vendedor "${q || "…"}"`,
        ico: <I.receipt size={14}/>,
        title: `${v.id} · ${v.client}`,
        sub: `${VENDEDORES_MAP[v.sellerId]?.name || "—"} · ${vdFmt(v.totalNum)}`,
        action: () => { setOpenId(v.id); setPalOpen(false); setPalQ(""); },
      }));
    }

    // prefixo $ — valor mínimo
    if (raw.startsWith("$")) {
      const min = parseFloat(raw.slice(1).replace(/\./g,"").replace(",","."));
      if (isNaN(min)) return [];
      return VENDAS_LIST.filter(v => v.totalNum >= min)
        .sort((a,b) => b.totalNum - a.totalNum)
        .slice(0, 12).map(v => ({
          grp: `Vendas ≥ ${vdFmt(min)}`,
          ico: <I.receipt size={14}/>,
          title: `${v.id} · ${v.client}`,
          sub: `${vdFmt(v.totalNum)} · ${v.payment}`,
          action: () => { setOpenId(v.id); setPalOpen(false); setPalQ(""); },
        }));
    }

    // prefixo / — só ações
    if (raw.startsWith("/")) {
      const q = raw.slice(1).toLowerCase();
      return palAll.filter(it =>
        it.grp === "Ações" &&
        (it.title.toLowerCase().includes(q) || it.sub.toLowerCase().includes(q))
      );
    }

    // default — fuzzy em palAll
    const q = raw.toLowerCase();
    return palAll.filter(it => it.title.toLowerCase().includes(q) || it.sub.toLowerCase().includes(q));
  }, [palAll, palQ, VENDAS_LIST, VENDEDORES_MAP]);
  const palGroups = useMemoV(() => {
    const out = []; let last = null;
    palFiltered.forEach((it, i) => {
      if (it.grp !== last) { out.push({ header: it.grp }); last = it.grp; }
      out.push({ item: it, idx: i });
    });
    return out;
  }, [palFiltered]);
  const runPal = (it) => it && it.action && it.action();

  const open = openId ? VENDAS_LIST.find(v => v.id === openId) : null;

  return (
    <div className={`os-page vendas-page vendas-aplus`} data-vista={vista}>

      {/* HEADER */}
      <header className="os-head">
        <div className="os-head-l">
          <h1>Vendas</h1>
          <p>Pedidos · faturamento · NF-e/NFS-e</p>
        </div>

        <button className="vd-cmdk" onClick={() => setPalOpen(true)}>
          <I.search size={12}/>
          <span>Buscar venda, cliente, chave SEFAZ…</span>
          <kbd>⌘K</kbd>
        </button>

        <div className="os-head-r">
          <div className="vd-vista" role="group" aria-label="Foco">
            <span className="vd-vista-lbl">Foco</span>
            <button className={vista==="caixa"?"on":""}        onClick={()=>setVista("caixa")}>Caixa</button>
            <button className={vista==="faturamento"?"on":""}  onClick={()=>setVista("faturamento")}>Faturamento</button>
            <button className={vista==="comissao"?"on":""}     onClick={()=>setVista("comissao")}>Comissão</button>
          </div>

          <div className="vd-views" ref={viewsRef}>
            <button className="vd-views-btn" onClick={()=>setViewsOpen(v=>!v)}>
              {topView === "favoritas" ? `★ Favoritas (${favSet.size})` : (subView ? `${view.label} · ${subView}` : view.label)}
            </button>
            {viewsOpen && (
              <div className="vd-views-menu vd-tree">
                {favSet.size > 0 && (
                  <React.Fragment>
                    <div className={`vd-tree-row l0 vd-tree-fav ${savedView === "favoritas" ? "active" : ""}`}
                         onClick={()=>{ setSavedView("favoritas"); setViewsOpen(false); }}>
                      <span className="vd-tree-arr empty"/>
                      <span className="vd-tree-lbl">★ Favoritas <small>(pessoais · atalho B)</small></span>
                      <span className="ct">{favSet.size}</span>
                    </div>
                    <div className="vd-views-sep"/>
                  </React.Fragment>
                )}
                {VENDAS_SAVED_VIEWS.map(sv => {
                  const items = VENDAS_LIST.filter(sv.filter);
                  const baseCt = items.length;
                  const expandable = sv.id === "pendentes" || sv.id === "faturadas";
                  const expanded = !!viewsExpand[sv.id];
                  const itemActive = savedView === sv.id;
                  // calcular filhos
                  let children = [];
                  if (sv.id === "pendentes") {
                    const byS = {};
                    items.forEach(v => {
                      const fn = (VENDEDORES_MAP[v.sellerId]?.name || "—").split(" ")[0];
                      const key = fn.toLowerCase();
                      if (!byS[key]) byS[key] = { id: key, label: "por " + fn, ct: 0 };
                      byS[key].ct++;
                    });
                    children = Object.values(byS).sort((a,b)=>b.ct-a.ct);
                  } else if (sv.id === "faturadas") {
                    const b2b = items.filter(v => v.client !== "Consumidor Final").length;
                    const b2c = items.length - b2b;
                    children = [
                      { id: "b2b", label: "B2B (com CNPJ)", ct: b2b },
                      { id: "b2c", label: "B2C (consumidor)", ct: b2c },
                    ].filter(c => c.ct > 0);
                  }
                  return (
                    <React.Fragment key={sv.id}>
                      <div className={`vd-tree-row l0 ${itemActive ? "active" : ""}`}>
                        {expandable
                          ? <span className={`vd-tree-arr ${expanded ? "open" : ""}`}
                                  onClick={e=>{e.stopPropagation(); setViewsExpand(p=>({...p, [sv.id]: !p[sv.id]}));}}>›</span>
                          : <span className="vd-tree-arr empty"/>}
                        <span className="vd-tree-lbl" onClick={()=>{ setSavedView(sv.id); setViewsOpen(false); }}>
                          {sv.label}
                        </span>
                        <span className="ct">{baseCt}</span>
                      </div>
                      {expandable && expanded && children.map(ch => (
                        <div key={ch.id}
                             className={`vd-tree-row l1 ${savedView === sv.id + ":" + ch.id ? "active" : ""}`}
                             onClick={()=>{ setSavedView(sv.id + ":" + ch.id); setViewsOpen(false); }}>
                          <span className="vd-tree-arr empty"/>
                          <span className="vd-tree-lbl">{ch.label}</span>
                          <span className="ct">{ch.ct}</span>
                        </div>
                      ))}
                    </React.Fragment>
                  );
                })}
                <div className="vd-views-sep"/>
                <div className="vd-views-item" style={{color:"var(--text-mute)",fontSize:11.5}}>
                  <I.folder size={11}/> Salvar vista atual…
                </div>
              </div>
            )}
          </div>

          <button className="os-btn ghost"><I.printer size={11}/>Imprimir caixa</button>
          <button className="os-btn primary" onClick={()=>setCreateOpen(true)}>
            <I.plus size={11}/>Nova venda <kbd className="kbd-hint">N</kbd>
          </button>

          {/* Visões ▾ — sub-rotas (Caixa/Devoluções/Comissões/Relatórios + PDV) */}
          <div className="vd-visoes" ref={visoesRef}>
            <button className="vd-visoes-btn" onClick={() => setVisoesOpen(v => !v)} title="Outras visões deste módulo">
              <I.folder size={11}/> Visões
            </button>
            {visoesOpen && (
              <div className="vd-visoes-menu">
                <div className="vd-visoes-grp">Visões deste módulo</div>
                {(window.__vendasSubs || [
                  { id:"lista",      label:"Lista de vendas" },
                  { id:"caixa",      label:"Caixa do dia" },
                  { id:"devolucoes", label:"Devoluções" },
                  { id:"comissoes",  label:"Comissões" },
                  { id:"relatorios", label:"Relatórios" },
                ]).map(s => {
                  const isActive = (window.__vendasCurrentSub || "lista") === s.id;
                  return (
                    <div key={s.id}
                         className={`vd-visoes-item ${isActive ? "active" : ""}`}
                         onClick={() => {
                           if (window.__vendasSubSetter) window.__vendasSubSetter(s.id);
                           setVisoesOpen(false);
                         }}>
                      <span className="vd-visoes-dot"/>{s.label}
                      {isActive && <span className="vd-visoes-here">aqui</span>}
                    </div>
                  );
                })}
                <div className="vd-visoes-sep"/>
                <div className="vd-visoes-item primary" onClick={() => {
                  if (window.__vendasPdvOpen) window.__vendasPdvOpen();
                  setVisoesOpen(false);
                }}>
                  <span className="vd-visoes-dot pdv"/>Abrir PDV balcão
                  <kbd>F2</kbd>
                </div>
              </div>
            )}
          </div>
        </div>
      </header>

      {/* KPIs */}
      <div className="os-kpis vd-kpis">
        {/* Hero faturado hoje + sparkline */}
        <div className="os-kpi vd-kpi-hero">
          <span className="os-kpi-label">Faturado hoje</span>
          <span className="os-kpi-value">{vdFmtShort(kpi_total)}</span>
          <span className="os-kpi-sub vd-delta-up">↑ +18% vs ontem · {kpi_count} vendas</span>
          <div className="vd-spark"><VdSparkline data={sparkData} color="oklch(0.72 0.10 155)"/></div>
        </div>

        {/* Ticket médio */}
        <div className="os-kpi">
          <span className="os-kpi-label">Ticket médio</span>
          <span className="os-kpi-value">{vdFmtShort(kpi_avg)}</span>
          <span className="os-kpi-sub vd-delta-up">↑ 12% vs semana passada</span>
        </div>

        {/* A receber + ageing */}
        <div className="os-kpi">
          <span className="os-kpi-label">A receber</span>
          <span className="os-kpi-value">{vdFmtShort(kpi_ar)}</span>
          <span className="os-kpi-sub vd-sla-counts">
            {slaCounts.o > 0 && <span className="vd-sla-mini overdue"><span className="ic">✕</span>{`${slaCounts.o} estourado${slaCounts.o>1?"s":""}`}</span>}
            {slaCounts.w > 0 && <span className="vd-sla-mini warning"><span className="ic">▲</span>{`${slaCounts.w} atrasando`}</span>}
            {slaCounts.f > 0 && <span className="vd-sla-mini fresh"><span className="ic">●</span>{`${slaCounts.f} fresco${slaCounts.f>1?"s":""}`}</span>}
          </span>
          <div className="vd-ageing">
            <div className="vd-ag-bar ok"><div style={{width: (ar_ok/ar_tot*100)+"%"}}/></div>
            <div className="vd-ag-bar warn"><div style={{width:(ar_w/ar_tot*100)+"%"}}/></div>
            <div className="vd-ag-bar bad"><div style={{width: (ar_b/ar_tot*100)+"%"}}/></div>
            <div className="vd-ag-lbls"><span>0–30d</span><span>31–60d</span><span>+60d</span></div>
          </div>
        </div>

        {/* 4º card — varia por Vista */}
        {vista === "caixa" && (
          <div className="os-kpi">
            <span className="os-kpi-label">PIX hoje</span>
            <span className="os-kpi-value">{vdFmtShort(kpi_pix)}<small> / {vdFmtShort(kpi_total)}</small></span>
            <span className="os-kpi-sub">{kpi_pixPct}% do faturamento — imediato</span>
            <div className="vd-pix-prog"><div style={{width:kpi_pixPct+"%"}}/></div>
          </div>
        )}
        {vista === "faturamento" && (
          <div className="os-kpi">
            <span className="os-kpi-label">Notas fiscais</span>
            <span className="os-kpi-value">{fiscalCount.ok}<small>/{fiscalCount.ok+fiscalCount.wait+fiscalCount.bad}</small></span>
            <span className="os-kpi-sub">autorizadas · {fiscalCount.wait} processando · {fiscalCount.bad} rejeitadas</span>
            <div className="vd-fiscal-bar">
              <div style={{flex:fiscalCount.ok,  background:"var(--vd-ok)"}}/>
              <div style={{flex:fiscalCount.wait,background:"var(--vd-warn)"}}/>
              <div style={{flex:fiscalCount.bad, background:"var(--vd-bad)"}}/>
            </div>
          </div>
        )}
        {vista === "comissao" && (
          <div className="os-kpi vd-rank">
            <span className="os-kpi-label">Ranking vendedores · mês</span>
            {Object.values(VENDEDORES_MAP).slice(0,4).map((s, i) => {
              const sales = VENDAS_LIST.filter(v => v.sellerId === s.id);
              const total = sales.reduce((acc, v) => acc + v.totalNum, 0);
              const pct = Math.min(100, (total / s.meta) * 100);
              return (
                <div key={s.id} className="vd-rank-row">
                  <span className={`vd-av vd-av-${s.av}`}>{s.abbr}</span>
                  <span className="vd-rank-info">
                    <div><span>{s.name}</span><span>{vdFmtShort(total)}</span></div>
                    <div className="vd-rank-bar"><div style={{width:pct+"%", background: pct >= 100 ? "var(--vd-ok)" : "var(--vd-green)"}}/></div>
                  </span>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* TABS */}
      <div className="os-tabs">
        {["todas","paga","pendente","faturada","cancelada"].map(s => (
          <button key={s} className={"os-tab" + (statusF===s ? " active" : "")} onClick={()=>setStatusF(s)}>
            {s === "todas" ? "Todas" : (VENDAS_STATUS[s]?.label || s.charAt(0).toUpperCase()+s.slice(1))}
            <span className="os-tab-n">{countBy(s)}</span>
          </button>
        ))}
      </div>

      {/* TABLE */}
      <div className="os-table-wrap">
        <table className="os-table vendas-table vd-aplus-table">
          <thead>
            <tr>
              <th style={{width:24,padding:"0 0 0 12px"}}>
                <input type="checkbox"
                       checked={filtered.length > 0 && selected.size === filtered.length}
                       onChange={toggleAll}/>
              </th>
              <th style={{width:82}}>Venda</th>
              <th style={{width:80}}>Data</th>
              <th>Cliente</th>
              <th style={{width:168}}>Atendido por</th>
              <th style={{width:128}}>Pipeline</th>
              <th style={{width:148}}>Fiscal</th>
              <th style={{width:128}}>Pagamento</th>
              <th style={{width:110}}>Total</th>
              <th style={{width:90}} className="vd-col-commission">Comissão</th>
              <th style={{width:88}}>Status</th>
            </tr>
          </thead>
          <tbody>
            {loading && Array.from({length:6}).map((_, i) => (
              <tr key={"sk"+i} className="vd-sk-row">
                <td colSpan={11}>
                  <div className="vd-sk-bar" style={{animationDelay: (i*60)+"ms"}}/>
                </td>
              </tr>
            ))}
            {!loading && filtered.map((v, ri) => {
              const sel = selected.has(v.id);
              const isFocused = ri === focusIdx;
              const isFav = favSet.has(v.id);
              const s = VENDEDORES_MAP[v.sellerId] || {};
              const comm = v.totalNum * (s.commissionPct || 0);
              return (
                <tr key={v.id}
                    ref={el => { rowsRef.current[ri] = el; }}
                    className={"os-row" + (v.urgent ? " urgent" : "") + (sel ? " selected" : "") + (isFocused ? " row-focused" : "")}
                    onClick={() => { setFocusIdx(ri); setOpenId(v.id); }}>
                  <td className="vd-chk" onClick={e=>e.stopPropagation()}>
                    <input type="checkbox" checked={sel} onChange={()=>toggleSel(v.id)}/>
                  </td>
                  <td className="vd-id">
                    {isFav && <span className="vd-fav" title="Favorita (B)">★</span>}
                    #{v.id}
                  </td>
                  <td className="vd-date">
                    <div>{v.date.slice(8,10)}/{v.date.slice(5,7)}</div>
                    <div className="vd-time">{v.time}</div>
                  </td>
                  <td className="vd-client">
                    {v.vertical === "mec" ? (
                      <div className="vd-client-mec">
                        <VdPlate value={v.plate}/>
                        <div>
                          <div className="vd-client-name">{v.vehicle}</div>
                          <div className="vd-notes">{v.client} · km {v.km}</div>
                        </div>
                      </div>
                    ) : (
                      <React.Fragment>
                        <div className="vd-client-name">{v.client}</div>
                        <div className="vd-notes">{v.clientNote || v.notes}</div>
                      </React.Fragment>
                    )}
                  </td>
                  <td className="vd-seller-cell">
                    {v.vertical === "mec" && v.mechanicId ? (
                      <React.Fragment>
                        <VdAv mid={v.mechanicId}/>
                        <span className="vd-seller-info">
                          <b>{(window.VENDAS_DATA.MECANICOS_MAP[v.mechanicId]?.name || "").split(" ")[0]}</b>
                          <small>vend: {(s.abbr || "").toLowerCase()}</small>
                        </span>
                      </React.Fragment>
                    ) : (
                      <React.Fragment>
                        <VdAv sid={v.sellerId}/>
                        <span className="vd-seller-info">
                          <b>{(s.name || v.seller || "").split(" ")[0]}</b>
                          <small>{v.origin}</small>
                        </span>
                      </React.Fragment>
                    )}
                  </td>
                  <td><VdStepper fsm={v.fsm} vertical={v.vertical}/></td>
                  <td><VdFiscalCell v={v}/></td>
                  <td className="vd-pay">
                    <div className="vd-pay-top">
                      <span>{v.payment}</span>
                      {v.installments > 1 && <span className="vd-inst">{v.installments}×</span>}
                    </div>
                    <div className="vd-pay-sla"><VdSlaPill v={v} compact/></div>
                  </td>
                  <td className="vd-total">{v.total}</td>
                  <td className="vd-total vd-col-commission" style={{color:"var(--vd-green)",fontWeight:700}}>
                    {vdFmt(comm)}
                  </td>
                  <td>
                    <span className="os-stage" style={{
                      background: (VENDAS_STATUS[v.status]?.color || "#888") + "1f",
                      color: VENDAS_STATUS[v.status]?.color || "#888"
                    }}>
                      {VENDAS_STATUS[v.status]?.label || v.status}
                    </span>
                    <div className="vd-row-actions" onClick={e=>e.stopPropagation()}>
                      {v.fiscal?.nfe?.status === "ok" && (
                        <button className="vd-row-act" title="Baixar DANFE PDF"><I.archive size={11}/></button>
                      )}
                      {v.fiscal?.nfe?.status === "ok" && (
                        <button className="vd-row-act" title="Baixar XML"><I.folder size={11}/></button>
                      )}
                      <button className="vd-row-act" title="Imprimir recibo (R)"><I.printer size={11}/></button>
                    </div>
                  </td>
                </tr>
              );
            })}
            {!loading && filtered.length === 0 && (
              <tr><td colSpan={11} className="os-empty">
                {topView === "atrasadas" && <><b>Tudo dentro do prazo ✓</b><br/><small>Nenhuma venda atrasada. Bom trabalho.</small></>}
                {topView === "rejeitadas" && <><b>Zero rejeições da SEFAZ ✓</b><br/><small>Todos os documentos fiscais autorizados.</small></>}
                {topView === "favoritas" && <><b>Sem favoritas ainda</b><br/><small>Foque uma linha com J/K e aperte <kbd>B</kbd> pra pinar.</small></>}
                {topView === "pendentes" && <><b>Tudo pago ✓</b><br/><small>Nada pendente nesta segmentação.</small></>}
                {topView === "faturadas" && <><b>Nada faturado esta semana</b><br/><small>Vendas faturadas aparecem aqui após emissão de NF-e/NFS-e.</small></>}
                {!["atrasadas","rejeitadas","favoritas","pendentes","faturadas"].includes(topView) && (
                  <>Nenhuma venda encontrada. Use <kbd>N</kbd> pra criar ou <kbd>⌘K</kbd> pra buscar.</>
                )}
              </td></tr>
            )}
          </tbody>
        </table>
      </div>

      {/* BULK ACTION BAR */}
      <div className={`vd-bulk ${selected.size > 0 ? "on" : ""}`}>
        <span className="vd-bulk-ct">{selected.size} selecionadas</span>
        <button className="vd-bulk-btn primary"><I.folder size={11}/>Emitir NF-e em lote</button>
        <button className="vd-bulk-btn"><I.check size={11}/>Marcar como pagas</button>
        <button className="vd-bulk-btn"><I.archive size={11}/>Exportar XML/PDF</button>
        <button className="vd-bulk-btn"><I.message size={11}/>Lembrete interno</button>
        <button className="vd-bulk-close" onClick={()=>setSelected(new Set())}>✕</button>
      </div>

      {/* DRAWERS */}
      {open       && <VendaDetailDrawer venda={open} onClose={()=>setOpenId(null)}/>}
      {createOpen && <VendaCreateDrawer onClose={()=>setCreateOpen(false)}/>}

      {/* ⌘K PALETTE */}
      <div className={`vd-pal-bd ${palOpen ? "on" : ""}`} onClick={()=>setPalOpen(false)}>
        <div className="vd-pal" onClick={e=>e.stopPropagation()}>
          <div className="vd-pal-in">
            <I.search size={16}/>
            <input autoFocus placeholder="Buscar venda, cliente, chave SEFAZ, ações…"
                   value={palQ}
                   onChange={e=>{ setPalQ(e.target.value); setPalSel(0); }}
                   onKeyDown={e=>{
                     if (e.key === "ArrowDown") { setPalSel(s => Math.min(s+1, palFiltered.length-1)); e.preventDefault(); }
                     if (e.key === "ArrowUp")   { setPalSel(s => Math.max(s-1, 0));                   e.preventDefault(); }
                     if (e.key === "Enter")     {
                       if (palFiltered.length === 0 && palQ.trim() && aiPalState === "idle") {
                         e.preventDefault(); askAi();
                       } else {
                         runPal(palFiltered[palSel]);
                       }
                     }
                   }}/>
            <kbd>esc</kbd>
          </div>
          <div className="vd-pal-list">
            {palGroups.length === 0 && (
              palQ.trim() ? (
                <div className="vd-pal-ai-wrap">
                  {aiPalState === "idle" && (
                    <button className="vd-pal-ai-cta" onClick={askAi}>
                      <span className="vd-pal-ai-ic">✦</span>
                      <span className="vd-pal-ai-tx">
                        <b>Perguntar à IA</b>
                        <small>sobre "<i>{palQ}</i>"</small>
                      </span>
                      <kbd>↵</kbd>
                    </button>
                  )}
                  {aiPalState === "loading" && (
                    <div className="vd-pal-ai-loading">
                      <span className="vd-pal-ai-ic">✦</span>
                      <span>consultando IA…</span>
                      <span className="vd-pal-ai-dots"><span/><span/><span/></span>
                    </div>
                  )}
                  {aiPalState === "done" && (
                    <div className="vd-pal-ai-answer">
                      <header><span className="vd-pal-ai-ic">✦</span><b>IA</b><span>resposta sobre "{palQ}"</span></header>
                      <p>{aiPalAnswer}</p>
                      <small>Resposta gerada por IA — pode ter alucinações. Sempre confirme com a venda.</small>
                    </div>
                  )}
                </div>
              ) : <div className="vd-pal-empty">Nada encontrado.</div>
            )}
            {palGroups.map((g, i) =>
              g.header
                ? <div key={"h"+i} className="vd-pal-grp">{g.header}</div>
                : (
                  <div key={"i"+i} className={`vd-pal-it ${g.idx === palSel ? "sel" : ""}`}
                       onMouseEnter={()=>setPalSel(g.idx)}
                       onClick={()=>runPal(g.item)}>
                    <span className="vd-pal-ic">{g.item.ico}</span>
                    <span className="vd-pal-tx">
                      <b>{g.item.title}</b>
                      <small>{g.item.sub}</small>
                    </span>
                    {g.item.kbd && <kbd>{g.item.kbd}</kbd>}
                  </div>
                )
            )}
          </div>
          <div className="vd-pal-ft">
            <span><kbd>↑↓</kbd> navegar</span>
            <span><kbd>↵</kbd> abrir</span>
            <span className="vd-pal-prefix"><kbd>#</kbd> ID · <kbd>@</kbd> vendedor · <kbd>$</kbd> valor · <kbd>/</kbd> ações</span>
            <span><kbd>esc</kbd> fechar</span>
          </div>
        </div>
      </div>

      {/* CHEAT-SHEET overlay (atalho ?) — Refino #1 KB-9.75 */}
      {cheatOpen && window.VdCheatSheet && <window.VdCheatSheet onClose={() => setCheatOpen(false)}/>}

    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// DETAIL DRAWER (com Fiscal tab — A+)
// ──────────────────────────────────────────────────────────────
function VendaDetailDrawer({ venda, onClose }) {
  const { VENDAS_STATUS, VENDEDORES_MAP } = window.VENDAS_DATA;
  const v = venda;

  const hasNFe  = !!v.fiscal?.nfe;
  const hasNFSe = !!v.fiscal?.nfse;
  const wantsNFe  = vdHasProduto(v);
  const wantsNFSe = vdHasServico(v);

  const [tab, setTab] = useStateV("itens");
  const [fSub, setFSub] = useStateV(() => hasNFe && hasNFSe ? "ambos" : hasNFe ? "nfe" : "nfse");
  // Refino #4 KB-9.75 — distribuição
  const [transcriptOpen, setTranscriptOpen] = useStateV(false);
  const [presentationOpen, setPresentationOpen] = useStateV(false);

  // Refino #3 KB-9.75 — comentários inline por item (via vendas-curation.jsx)
  const _useVdCom = window.useVdItemComments;
  const comments = _useVdCom ? _useVdCom() : null;

  const s = VENDEDORES_MAP[v.sellerId] || {};
  const comm = v.totalNum * (s.commissionPct || 0);

  const dateBR = `${v.date.slice(8,10)}/${v.date.slice(5,7)}/${v.date.slice(0,4)}`;

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer wide vd-drawer-aplus" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">#{v.id}</span>
            <h2>{v.client}</h2>
            {v.clientNote && (
              <p className="vd-drawer-note">
                {window.VdLinkify ? <window.VdLinkify text={v.clientNote} onPick={(id,kind)=>console.log("→",kind,id)}/> : v.clientNote}
              </p>
            )}
            <p>{dateBR} às {v.time} · {s.name || v.seller}</p>
          </div>
          <div className="os-drawer-head-r">
            <span className="vd-drawer-total">{v.total}</span>
            <span className="os-stage" style={{
              background: (VENDAS_STATUS[v.status]?.color || "#888") + "1f",
              color: VENDAS_STATUS[v.status]?.color || "#888"
            }}>{VENDAS_STATUS[v.status]?.label}</span>
            <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
          </div>
        </header>

        <nav className="vd-drawer-tabs">
          {[
            {k:"itens",    l:"Itens",     ct: v.itemsList?.length || 0},
            {k:"fiscal",   l:"Fiscal",    ct: (hasNFe?1:0)+(hasNFSe?1:0)},
            {k:"pagamento",l:"Pagamento", ct: null},
            {k:"timeline", l:"Timeline",  ct: null},
            {k:"ia",       l:"✦ IA",     ct: null, ai: true},
          ].map(t => (
            <button key={t.k}
                    className={`vd-drawer-tab ${tab===t.k ? "on" : ""} ${t.ai ? "vd-tab-ai" : ""}`}
                    onClick={()=>setTab(t.k)}>
              {t.l}
              {t.ct !== null && t.ct > 0 && <span className="vd-drawer-tab-ct">{t.ct}</span>}
              {t.k === "itens" && comments?.countFor(v.id) > 0 && (
                <span className="vd-drawer-tab-cmt" title={`${comments.countFor(v.id)} comentário(s) inline`}>💬{comments.countFor(v.id)}</span>
              )}
            </button>
          ))}
        </nav>

        <div className="os-drawer-body vd-drawer-body">

          {tab === "itens" && (
            <section className="vd-section">
              <h3>Itens da venda</h3>
              <div className="vd-items-cards">
                {(v.itemsList || []).map((it, i) => (
                  window.VdItemRow && comments ? (
                    <window.VdItemRow key={i}
                      venda={v} item={it} idx={i}
                      comments={comments.get(v.id, i)}
                      onAdd={(t) => comments.add(v.id, i, t, s.name || "você")}
                      onRemove={(ci) => comments.remove(v.id, i, ci)}/>
                  ) : (
                    <div key={i} className="vd-item-card">
                      <div className="vd-item-c-l">
                        <b>{it.name}</b>
                        <small>{it.sku} · {it.type === "produto" ? "Produto (NF-e)" : "Serviço (NFS-e)"}</small>
                      </div>
                      <span className="vd-item-c-qty">{it.qty}×</span>
                      <span className="vd-item-c-unit">{vdFmt(it.unit)}</span>
                      <span className="vd-item-c-sub">{vdFmt(it.qty * it.unit)}</span>
                    </div>
                  )
                ))}
              </div>
              <div className="vd-items-foot">
                <span>Total</span><b>{v.total}</b>
              </div>
            </section>
          )}

          {tab === "fiscal" && (
            <section className="vd-section">
              <h3>Documentos fiscais</h3>

              {/* painel emitir quando falta */}
              {((wantsNFe && !hasNFe) || (wantsNFSe && !hasNFSe)) && (
                <div className="vd-emit">
                  <div>
                    <b>
                      {!hasNFe && wantsNFe && !hasNFSe && wantsNFSe ? "Emitir NF-e e NFS-e"
                      : !hasNFe && wantsNFe ? "Emitir NF-e"
                      : "Emitir NFS-e"}
                    </b>
                    <small>
                      Esta venda tem
                      {wantsNFe  && !hasNFe  && " produto(s)"}
                      {wantsNFe  && wantsNFSe && !hasNFe && !hasNFSe && " e "}
                      {wantsNFSe && !hasNFSe && " serviço(s)"}
                      {" "}sem documento fiscal.
                    </small>
                  </div>
                  <button className="os-btn primary">
                    {!hasNFe && wantsNFe && !hasNFSe && wantsNFSe ? "Emitir ambos" : !hasNFe ? "Emitir NF-e" : "Emitir NFS-e"}
                  </button>
                </div>
              )}

              {/* sub-tabs apenas se mista */}
              {hasNFe && hasNFSe && (
                <div className="vd-fsub">
                  <button className={fSub==="nfe"?"on":""}   onClick={()=>setFSub("nfe")}>NF-e <span>1</span></button>
                  <button className={fSub==="nfse"?"on":""}  onClick={()=>setFSub("nfse")}>NFS-e <span>1</span></button>
                  <button className={fSub==="ambos"?"on":""} onClick={()=>setFSub("ambos")}>Ambos</button>
                </div>
              )}

              <div className={`vd-fcard-grid ${(hasNFe && !hasNFSe) || (!hasNFe && hasNFSe) ? "single" : ""}`}>
                {hasNFe  && fSub !== "nfse" && <VdFiscalCard kind="nfe"  doc={v.fiscal.nfe}/>}
                {hasNFSe && fSub !== "nfe"  && <VdFiscalCard kind="nfse" doc={v.fiscal.nfse}/>}
              </div>

              {/* breakdown total se mista */}
              {hasNFe && hasNFSe && (
                <div className="vd-fbreak">
                  <h4>Breakdown fiscal</h4>
                  <dl>
                    <dt>Produtos (NF-e {v.fiscal.nfe.numero})</dt>
                    <dd>{vdFmt(v.itemsList.filter(i=>i.type==="produto").reduce((s,i)=>s+i.qty*i.unit,0))}</dd>
                    <dt>Serviços (NFS-e {v.fiscal.nfse.numero})</dt>
                    <dd>{vdFmt(v.itemsList.filter(i=>i.type==="servico").reduce((s,i)=>s+i.qty*i.unit,0))}</dd>
                    <dt className="tot">Total da venda</dt>
                    <dd className="tot">{v.total}</dd>
                  </dl>
                </div>
              )}

              {!hasNFe && !hasNFSe && !wantsNFe && !wantsNFSe && (
                <div className="vd-empty-state">
                  Esta venda não emite documento fiscal (Consumidor Final · dinheiro).
                </div>
              )}
            </section>
          )}

          {tab === "pagamento" && (
            <section className="vd-section">
              <h3>Pagamento</h3>
              <div className="vd-pay-meta">
                <dl>
                  <dt>Forma</dt><dd>{v.payment}</dd>
                  <dt>Parcelas</dt><dd>{v.installments > 1 ? `${v.installments}×` : "À vista"}</dd>
                  <dt>Prazo</dt><dd>{v.payTerm || 0} dias <VdSlaPill v={v}/></dd>
                  <dt>Status</dt><dd><span className="os-stage" style={{
                      background: (VENDAS_STATUS[v.status]?.color || "#888") + "1f",
                      color: VENDAS_STATUS[v.status]?.color || "#888"
                    }}>{VENDAS_STATUS[v.status]?.label}</span></dd>
                  <dt>Comissão</dt><dd className="vd-comm">{vdFmt(comm)} <small>({((s.commissionPct||0)*100).toFixed(1)}%)</small></dd>
                </dl>
              </div>

              {v.osIds?.length > 0 && (
                <div style={{marginTop:16}}>
                  <h3>Ordens de Serviço vinculadas</h3>
                  <div className="vd-os-list">
                    {v.osIds.map(id => (
                      <div key={id} className="vd-os-link">
                        <span className="vd-os-pill">#{id}</span>
                        <span>Ver na produção →</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Refino #4 KB-9.75 — Mensagem pra cliente (variáveis live) */}
              {window.VdMessagePreview && (
                <div style={{marginTop:20}}>
                  <window.VdMessagePreview venda={v}/>
                </div>
              )}
            </section>
          )}

          {tab === "timeline" && (
            <section className="vd-section">
              {window.VdAuditTrail
                ? <window.VdAuditTrail venda={v}/>
                : <React.Fragment>
                    <h3>Linha do tempo</h3>
                    <div className="vd-tline">
                      <div className="vd-tline-it"><small>{dateBR} {v.time}</small><b>Venda registrada por {s.name || v.seller}</b></div>
                    </div>
                  </React.Fragment>}
            </section>
          )}

          {tab === "ia" && (
            window.VdAiPanel
              ? <window.VdAiPanel venda={v}/>
              : <section className="vd-section vd-ai-fallback">
                  <p>Módulo IA não carregado. Inclua <code>vendas-ai.jsx</code> no Chat.</p>
                </section>
          )}

        </div>

        <footer className="os-drawer-actions">
          {window.VdTroubleButton && <window.VdTroubleButton venda={v}/>}
          {v.status === "pendente" && <button className="os-btn primary"><I.check size={11}/>Confirmar pagamento</button>}
          {v.status === "paga" && !hasNFe && wantsNFe && <button className="os-btn primary"><I.folder size={11}/>Faturar (NF-e)</button>}
          <button className="os-btn ghost"><I.printer size={11}/>Imprimir recibo</button>
          {window.VdTranscriptPDF && (
            <button className="os-btn ghost" onClick={() => setTranscriptOpen(true)} title="Transcript completo (PDF jurídico)">
              <I.archive size={11}/>Transcript
            </button>
          )}
          {window.VdPresentationMode && (
            <button className="os-btn ghost vd-btn-present" onClick={() => setPresentationOpen(true)} title="Modo apresentação · reunião com cliente">
              ▶ Apresentar
            </button>
          )}
        </footer>
      </aside>

      {/* Refino #4 — overlays */}
      {transcriptOpen   && window.VdTranscriptPDF      && <window.VdTranscriptPDF      venda={v} onClose={() => setTranscriptOpen(false)}/>}
      {presentationOpen && window.VdPresentationMode   && <window.VdPresentationMode   venda={v} onClose={() => setPresentationOpen(false)}/>}
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// CREATE DRAWER (P0 — preservado do v1, sem mudanças)
// ──────────────────────────────────────────────────────────────
function VendaCreateDrawer({ onClose }) {
  const { OS_CLIENTS, OS_PRODUCTS } = window.OS_DATA;
  const { VENDAS_PAYMENTS } = window.VENDAS_DATA;

  const [step, setStep] = useStateV(1);
  const [clientQuery, setClientQuery] = useStateV("");
  const [client, setClient] = useStateV(null);
  const [contact, setContact] = useStateV("");
  const [phone, setPhone] = useStateV("");

  const clientMatches = useMemoV(() => {
    if (!clientQuery.trim()) return OS_CLIENTS.slice(0, 6);
    const q = clientQuery.toLowerCase();
    return OS_CLIENTS.filter(c =>
      c.name.toLowerCase().includes(q) ||
      (c.cnpj || "").includes(q) ||
      (c.contact || "").toLowerCase().includes(q)
    ).slice(0, 8);
  }, [clientQuery, OS_CLIENTS]);

  const [items, setItems] = useStateV([]);
  const [prodQuery, setProdQuery] = useStateV("");
  const prodMatches = useMemoV(() => {
    const q = prodQuery.trim().toLowerCase();
    if (!q) return OS_PRODUCTS.slice(0, 6);
    return OS_PRODUCTS.filter(p => (p.name || p.label || "").toLowerCase().includes(q)).slice(0, 6);
  }, [prodQuery, OS_PRODUCTS]);

  const fmtPrice = (n) => typeof n === "number"
    ? n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" })
    : (n || "R$ 0,00");
  const addItem = (p) => {
    setItems(prev => [...prev, {
      key: Date.now() + Math.random(),
      product: p.name || p.label || "—",
      qty: 1, unitPrice: fmtPrice(p.price),
      generatesOs: !(p.readyStock || false),
    }]);
    setProdQuery("");
  };
  const updateItem = (k, patch) => setItems(prev => prev.map(it => it.key === k ? { ...it, ...patch } : it));
  const removeItem = (k) => setItems(prev => prev.filter(it => it.key !== k));

  const subtotal = useMemoV(() => items.reduce((s, it) => {
    const p = parseFloat((it.unitPrice || "0").replace(/[^\d,]/g,"").replace(",","."));
    return s + (isNaN(p) ? 0 : p) * (parseInt(it.qty) || 0);
  }, 0), [items]);

  const [payment, setPayment] = useStateV("pix");
  const [installments, setInstallments] = useStateV(1);
  const [discount, setDiscount] = useStateV(0);
  const total = Math.max(0, subtotal - discount);
  const fmt = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" });

  const steps = [
    { n:1, label:"Cliente",   ok: !!client },
    { n:2, label:"Itens",     ok: items.length > 0 },
    { n:3, label:"Pagamento", ok: !!payment },
    { n:4, label:"Confirmar", ok: false },
  ];
  const canNext = steps[step-1].ok;
  const generatesAnyOs = items.some(it => it.generatesOs);

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer wide vd-create" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">Nova venda</span>
            <h2>Balcão · {new Date().toLocaleDateString("pt-BR")}</h2>
            <p>Atalho: <kbd>Esc</kbd> cancelar · <kbd>Enter</kbd> avançar</p>
          </div>
          <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
        </header>

        <nav className="vd-stepper">
          {steps.map((s, i) => (
            <button key={s.n}
                    className={"vd-step" + (step === s.n ? " active" : "") + (s.ok ? " done" : "")}
                    onClick={() => s.ok || step > s.n ? setStep(s.n) : null}>
              <span className="vd-step-num">{s.ok && step !== s.n ? "✓" : s.n}</span>
              <span>{s.label}</span>
              {i < steps.length - 1 && <span className="vd-step-sep">›</span>}
            </button>
          ))}
        </nav>

        <div className="os-drawer-body vd-create-body">
          {step === 1 && (
            <section className="vd-section">
              <h3>Cliente</h3>
              {!client ? (
                <React.Fragment>
                  <div className="vd-search-wrap">
                    <I.search size={12}/>
                    <input autoFocus type="text" placeholder="Nome, CNPJ ou telefone..."
                           value={clientQuery} onChange={e => setClientQuery(e.target.value)}/>
                    <button className="vd-walkin"
                            onClick={() => setClient({ id:"walkin", name:"Consumidor Final", cnpj:"—", contact:"—", phone:"" })}>
                      Consumidor Final
                    </button>
                  </div>
                  <div className="vd-client-list">
                    {clientMatches.map(c => (
                      <button key={c.id} className="vd-client-card"
                              onClick={() => { setClient(c); setContact(c.contact || ""); setPhone(c.phone || ""); }}>
                        <div className="vd-client-card-name">{c.name}</div>
                        <div className="vd-client-card-meta">{c.cnpj || "—"} · {c.contact || "—"}</div>
                      </button>
                    ))}
                  </div>
                </React.Fragment>
              ) : (
                <div className="vd-client-selected">
                  <div>
                    <strong>{client.name}</strong>
                    <div className="vd-meta">{client.cnpj || "—"}</div>
                  </div>
                  <div className="vd-fields">
                    <label>Contato<input value={contact} onChange={e => setContact(e.target.value)}/></label>
                    <label>Telefone<input value={phone} onChange={e => setPhone(e.target.value)}/></label>
                  </div>
                  <button className="os-btn ghost" onClick={() => setClient(null)}>Trocar cliente</button>
                </div>
              )}
            </section>
          )}

          {step === 2 && (
            <section className="vd-section">
              <h3>Itens</h3>
              <div className="vd-search-wrap">
                <I.search size={12}/>
                <input autoFocus type="text" placeholder="Adicionar produto do catálogo..."
                       value={prodQuery} onChange={e => setProdQuery(e.target.value)}/>
              </div>
              {prodQuery && (
                <div className="vd-prod-suggest">
                  {prodMatches.map((p, i) => (
                    <button key={i} className="vd-prod-row" onClick={() => addItem(p)}>
                      <span>{p.name || p.label}</span>
                      <span className="vd-prod-price">{p.price || "—"}</span>
                    </button>
                  ))}
                  {prodMatches.length === 0 && <div className="vd-empty-mini">Nenhum produto</div>}
                </div>
              )}

              {items.length === 0 ? (
                <div className="vd-empty-state">Nenhum item adicionado. Use o campo acima para buscar do catálogo.</div>
              ) : (
                <table className="vd-items-table">
                  <thead>
                    <tr><th>Produto</th><th style={{width:80}}>Qtd</th><th style={{width:130}}>Unit.</th><th style={{width:130}}>Subtotal</th><th style={{width:100}}>Gera OS?</th><th style={{width:36}}></th></tr>
                  </thead>
                  <tbody>
                    {items.map(it => {
                      const p = parseFloat((it.unitPrice || "0").replace(/[^\d,]/g,"").replace(",","."));
                      const sub = (isNaN(p) ? 0 : p) * (parseInt(it.qty) || 0);
                      return (
                        <tr key={it.key}>
                          <td>{it.product}</td>
                          <td><input type="number" min="1" value={it.qty} onChange={e => updateItem(it.key, { qty: e.target.value })}/></td>
                          <td><input value={it.unitPrice} onChange={e => updateItem(it.key, { unitPrice: e.target.value })}/></td>
                          <td className="vd-strong">{fmt(sub)}</td>
                          <td>
                            <label className="vd-toggle">
                              <input type="checkbox" checked={it.generatesOs} onChange={e => updateItem(it.key, { generatesOs: e.target.checked })}/>
                              <span>{it.generatesOs ? "Sim" : "Pronta-entrega"}</span>
                            </label>
                          </td>
                          <td><button className="icon-btn" onClick={() => removeItem(it.key)}><I.close size={12}/></button></td>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot>
                    <tr><td colSpan={3} className="vd-foot-l">Subtotal</td><td colSpan={3} className="vd-foot-r">{fmt(subtotal)}</td></tr>
                  </tfoot>
                </table>
              )}
            </section>
          )}

          {step === 3 && (
            <section className="vd-section">
              <h3>Pagamento</h3>
              <div className="vd-pay-grid">
                {VENDAS_PAYMENTS.map(p => (
                  <button key={p.id} className={"vd-pay-card" + (payment === p.id ? " active" : "")} onClick={() => setPayment(p.id)}>
                    <span className="vd-pay-icon">{p.icon}</span>
                    <span className="vd-pay-label">{p.label}</span>
                    <span className="vd-pay-clear">{p.clearing}</span>
                  </button>
                ))}
              </div>
              {(payment === "cartao" || payment.startsWith("boleto")) && (
                <div className="vd-fields">
                  <label>Parcelas
                    <select value={installments} onChange={e => setInstallments(parseInt(e.target.value))}>
                      {[1,2,3,4,5,6,10,12].map(n => <option key={n} value={n}>{n}× de {fmt(total/n)}</option>)}
                    </select>
                  </label>
                  <label>Desconto<input type="number" min="0" value={discount} onChange={e => setDiscount(parseFloat(e.target.value) || 0)}/></label>
                </div>
              )}
              <dl className="vd-totals">
                <dt>Subtotal</dt><dd>{fmt(subtotal)}</dd>
                <dt>Desconto</dt><dd>-{fmt(discount)}</dd>
                <dt className="vd-total-row">Total</dt><dd className="vd-total-row">{fmt(total)}</dd>
              </dl>
            </section>
          )}

          {step === 4 && (
            <section className="vd-section vd-confirm">
              <h3>Confirmar venda</h3>
              <div className="vd-confirm-grid">
                <div className="vd-confirm-block">
                  <span className="vd-confirm-label">Cliente</span>
                  <strong>{client?.name || "—"}</strong>
                  <span className="vd-meta">{client?.cnpj || "—"}</span>
                </div>
                <div className="vd-confirm-block">
                  <span className="vd-confirm-label">Itens</span>
                  <strong>{items.length} {items.length === 1 ? "item" : "itens"}</strong>
                  <span className="vd-meta">{items.filter(it => it.generatesOs).length} gera{items.filter(it => it.generatesOs).length === 1 ? "" : "m"} OS</span>
                </div>
                <div className="vd-confirm-block">
                  <span className="vd-confirm-label">Pagamento</span>
                  <strong>{(VENDAS_PAYMENTS.find(p => p.id === payment) || {}).label}</strong>
                  <span className="vd-meta">{installments > 1 ? `${installments}× de ${fmt(total/installments)}` : "À vista"}</span>
                </div>
                <div className="vd-confirm-block vd-confirm-total">
                  <span className="vd-confirm-label">Total</span>
                  <strong className="vd-total-big">{fmt(total)}</strong>
                </div>
              </div>
              {generatesAnyOs && (
                <div className="vd-callout">
                  <strong>Esta venda gerará {items.filter(it => it.generatesOs).length} OS</strong> automaticamente após confirmação. As OS irão para a fila de produção da etapa <em>Pré-impressão</em>.
                </div>
              )}
              {!generatesAnyOs && items.length > 0 && (
                <div className="vd-callout vd-callout-ok">
                  <strong>Pronta-entrega.</strong> Nenhuma OS será gerada — entregue os itens ao cliente direto do estoque/balcão.
                </div>
              )}
            </section>
          )}
        </div>

        <footer className="os-drawer-actions vd-foot">
          <div className="vd-foot-summary">
            {items.length > 0 && (
              <React.Fragment>
                <span>{items.length} {items.length === 1 ? "item" : "itens"}</span>
                <span className="vd-foot-total">{fmt(total)}</span>
              </React.Fragment>
            )}
          </div>
          <div className="vd-foot-actions">
            {step > 1 && <button className="os-btn ghost" onClick={() => setStep(step-1)}>← Voltar</button>}
            {step < 4 && <button className="os-btn primary" disabled={!canNext} onClick={() => setStep(step+1)}>Avançar →</button>}
            {step === 4 && (
              <button className="os-btn primary" onClick={() => { alert("Venda registrada (mock)"); onClose(); }}>
                <I.check size={11}/>Confirmar venda
              </button>
            )}
          </div>
        </footer>
      </aside>
    </div>
  );
}

window.VendasListPage = VendasListPage;

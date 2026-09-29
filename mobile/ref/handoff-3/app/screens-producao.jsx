// ────────────────────────────────────────────────
// PRODUÇÃO — consulta operacional (triagem + fila + estações)
// Foco: enxergar atrasos/urgências, achar job e avançar etapa com 1 toque.
// ────────────────────────────────────────────────
const { Ic } = window;
const { PRODUCAO_ESTACOES, PRODUCAO_JOBS, PRODUCAO_ETAPAS } = window.MOCK;
const { DetailHeader, OriginBadge } = window.Screens;
const BRLp = window.BRL;

const ETAPAS = PRODUCAO_ETAPAS; // ["Em fila","Imprimindo","Acabamento","Pronto"]
const PROD_TONE = { danger: "var(--danger)", warn: "var(--warn)", accent: "var(--accent)", ok: "var(--ok)", muted: "var(--text-mute)" };

// minutos → "40min" / "1h10" / "8h"
function prodDur(min) {
  min = Math.round(min);
  if (min < 60) return min + "min";
  const h = Math.floor(min / 60), m = min % 60;
  return m ? h + "h" + String(m).padStart(2, "0") : h + "h";
}

// selo de prazo a partir do dueMin
function dueInfo(j) {
  if (j.expedido) return { label: "Expedido", tone: "ok", icon: "check" };
  if (j.etapa === "Pronto") return { label: j.prazo, tone: "muted", icon: "clock" };
  const d = j.dueMin;
  if (d < 0) return { label: "Atrasado " + prodDur(-d), tone: "danger", icon: "alert" };
  if (d <= 120) return { label: "Vence em " + prodDur(d), tone: "warn", icon: "clock" };
  return { label: j.prazo, tone: "muted", icon: "clock" };
}

function etapaTone(etapa) {
  if (etapa === "Pronto") return "ok";
  if (etapa === "Imprimindo" || etapa === "Acabamento") return "accent";
  return ""; // Em fila → neutro
}

function nextAction(j) {
  if (j.expedido) return null;
  if (j.etapa === "Em fila")     return { label: "Iniciar",  icon: "zap",   kind: "primary" };
  if (j.etapa === "Imprimindo")  return { label: "Concluir", icon: "check", kind: "primary" };
  if (j.etapa === "Acabamento")  return { label: "Concluir", icon: "check", kind: "primary" };
  if (j.etapa === "Pronto")      return { label: "Expedir",  icon: "truck", kind: "action" };
  return null;
}

function bucketOf(j) {
  if (j.expedido || j.etapa === "Pronto") return "pronto";
  if (j.dueMin < 0) return "atrasado";
  if (j.dueMin <= 600) return "hoje";
  if (j.dueMin <= 1440) return "amanha";
  return "depois";
}

const PROD_BUCKETS = [
  { id: "atrasado", label: "Atrasados",               tone: "danger" },
  { id: "hoje",     label: "Vence hoje",              tone: "warn" },
  { id: "amanha",   label: "Amanhã",                  tone: "muted" },
  { id: "depois",   label: "Próximos dias",           tone: "muted" },
  { id: "pronto",   label: "Prontos p/ expedição",    tone: "ok" },
];

function prioBorder(prio) {
  if (prio === "alta")  return "3px solid var(--danger)";
  if (prio === "media") return "3px solid var(--warn)";
  return "3px solid var(--border)";
}

// ─── Mini-pipeline: barra segmentada das 4 etapas ───
function ProdMiniPipe({ idx, prog }) {
  return (
    <div style={{ display: "flex", gap: 3 }}>
      {ETAPAS.map((_, i) => {
        let bg = "var(--border)";
        if (i < idx) bg = "var(--ok)";
        else if (i === idx) bg = "var(--accent)";
        return <span key={i} style={{ flex: 1, height: 4, borderRadius: 99, background: bg }} />;
      })}
    </div>
  );
}

// ─── Card de job (consulta) ───
function ProdJobCard({ j, nav, onAdvance }) {
  const due = dueInfo(j);
  const eTone = etapaTone(j.etapa);
  const action = nextAction(j);
  const sIdx = j.expedido ? ETAPAS.length : Math.max(0, ETAPAS.indexOf(j.etapa));
  const est = (PRODUCAO_ESTACOES.find(e => e.id === j.estacao) || {}).nome || "—";

  return (
    <div className="oi-card tight" onClick={() => nav.push("producao-job", { id: j.id })}
         style={{ cursor: "pointer", padding: 12, gap: 8, borderLeft: prioBorder(j.prio),
                  opacity: j.expedido ? 0.62 : 1 }}>
      {/* linha 1: id + etapa + prazo */}
      <div style={{ display: "flex", alignItems: "center", gap: 7 }}>
        <span className="oi-origin o-MFG">MFG</span>
        <span className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-mute)", fontWeight: 600 }}>{j.id}</span>
        <span className={"oi-status " + eTone}><span className="dot" />{j.expedido ? "Expedido" : j.etapa}</span>
        <span style={{ marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: 4,
                       fontSize: 11, fontWeight: 600, fontFamily: "var(--font-mono)",
                       color: PROD_TONE[due.tone] }}>
          {React.createElement(Ic[due.icon] || Ic.clock, { size: 13 })}
          {due.label}
        </span>
      </div>

      {/* linha 2: produto + qtd */}
      <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
        <div style={{ flex: 1, minWidth: 0, fontSize: 14, fontWeight: 600, lineHeight: 1.3,
                      letterSpacing: "-0.005em" }}>{j.produto}</div>
        <span className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-dim)", flex: "0 0 auto" }}>{j.qtd}</span>
      </div>

      {/* linha 3: cliente · os · estação */}
      <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 11.5, color: "var(--text-dim)",
                    whiteSpace: "nowrap", overflow: "hidden" }}>
        <span style={{ overflow: "hidden", textOverflow: "ellipsis" }}>{j.cliente}</span>
        <span style={{ color: "var(--text-mute)" }}>·</span>
        <span className="oi-mono">{j.os}</span>
        <span style={{ color: "var(--text-mute)" }}>·</span>
        <span style={{ display: "inline-flex", alignItems: "center", gap: 3 }}>
          {React.createElement(Ic.printer, { size: 12, color: "var(--text-mute)" })}{est}
        </span>
      </div>

      {/* mini-pipeline das 4 etapas */}
      <ProdMiniPipe idx={sIdx} prog={j.prog} />

      {/* nota (ex: aguardando arte) */}
      {j.nota && !j.expedido && (
        <div style={{ fontSize: 11, color: "var(--text-mute)", display: "flex", alignItems: "center", gap: 5 }}>
          {React.createElement(Ic.alert, { size: 12 })}{j.nota}
        </div>
      )}

      {/* footer: operador + ação rápida */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginTop: 1 }}>
        <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 11.5,
                       color: j.op ? "var(--text-dim)" : "var(--text-mute)" }}>
          {j.op ? (
            <span style={{ width: 20, height: 20, borderRadius: "50%", background: "var(--accent-soft)",
                           color: "var(--accent)", display: "grid", placeItems: "center",
                           fontSize: 9.5, fontWeight: 700 }}>
              {j.op.slice(0, 2).toUpperCase()}
            </span>
          ) : React.createElement(Ic.user, { size: 14 })}
          {j.op || "Sem operador"}
        </span>
        <span style={{ marginLeft: "auto" }}>
          {action ? (
            <button className={"oi-btn sm " + action.kind}
                    onClick={(e) => { e.stopPropagation(); onAdvance(j.id); }}
                    style={{ height: 30 }}>
              {React.createElement(Ic[action.icon], { size: 14 })} {action.label}
            </button>
          ) : (
            <span style={{ display: "inline-flex", alignItems: "center", gap: 5, fontSize: 11.5,
                           color: "var(--ok)", fontWeight: 600 }}>
              <Ic.checkCircle size={14} /> Expedido
            </span>
          )}
        </span>
      </div>
    </div>
  );
}

// ─── Stat de triagem (toque para filtrar) ───
function TriageStat({ count, label, tone, active, onClick }) {
  const col = PROD_TONE[tone];
  return (
    <button onClick={onClick}
            style={{
              appearance: "none", cursor: "pointer", textAlign: "left",
              flex: "1 1 0", minWidth: 0,
              background: active ? "color-mix(in oklch, " + col + " 16%, var(--surface))" : "var(--surface)",
              border: "1px solid " + (active ? col : "var(--border)"),
              borderRadius: var_radius(), padding: "8px 9px",
              display: "flex", flexDirection: "column", gap: 2,
            }}>
      <span style={{ display: "flex", alignItems: "center", gap: 5 }}>
        <span style={{ width: 7, height: 7, borderRadius: 99, background: col, flex: "0 0 auto" }} />
        <span className="oi-mono" style={{ fontSize: 19, fontWeight: 700, lineHeight: 1, color: "var(--text)" }}>{count}</span>
      </span>
      <span style={{ fontSize: 9.5, fontWeight: 700, letterSpacing: ".04em", textTransform: "uppercase",
                     color: active ? col : "var(--text-mute)", lineHeight: 1.2 }}>{label}</span>
    </button>
  );
}
function var_radius() { return "10px"; }

// ─── Tela principal ───
function ProducaoScreen({ nav, ctx }) {
  const [jobs, setJobs] = window.useStore("producao.jobs", () => PRODUCAO_JOBS.map(j => ({ ...j })));
  const [view, setViewRaw] = React.useState(ctx.prodView || "prazo");
  const [q, setQ] = React.useState("");
  const [triage, setTriage] = React.useState("todos");
  const [estacao, setEstacao] = React.useState("todas");

  const setView = (v) => { setViewRaw(v); if (v !== "estacao") setEstacao("todas"); };

  const advance = (id) => setJobs(js => js.map(j => {
    if (j.id !== id) return j;
    if (j.etapa === "Pronto") return { ...j, expedido: true };
    const i = ETAPAS.indexOf(j.etapa);
    const ne = ETAPAS[Math.min(i + 1, ETAPAS.length - 1)];
    let prog = j.prog;
    if (ne === "Imprimindo") prog = 0.12;
    else if (ne === "Acabamento") prog = 0.45;
    else if (ne === "Pronto") prog = 1;
    return { ...j, etapa: ne, prog, op: j.op || "Você", nota: null };
  }));

  // busca + estação (base p/ contagens)
  const base = jobs.filter(j => {
    if (estacao !== "todas" && j.estacao !== estacao) return false;
    if (q) {
      const hay = (j.produto + " " + j.cliente + " " + j.os + " " + j.id).toLowerCase();
      if (!hay.includes(q.toLowerCase())) return false;
    }
    return true;
  });

  // contagens de triagem
  const cnt = {
    atrasado: base.filter(j => bucketOf(j) === "atrasado").length,
    hoje:     base.filter(j => bucketOf(j) === "hoje").length,
    producao: base.filter(j => !j.expedido && (j.etapa === "Imprimindo" || j.etapa === "Acabamento")).length,
    pronto:   base.filter(j => j.etapa === "Pronto").length,
  };

  const list = base.filter(j => {
    if (triage === "atrasado") return bucketOf(j) === "atrasado";
    if (triage === "hoje")     return bucketOf(j) === "hoje";
    if (triage === "producao") return !j.expedido && (j.etapa === "Imprimindo" || j.etapa === "Acabamento");
    if (triage === "pronto")   return j.etapa === "Pronto";
    return true;
  });

  const ativos = jobs.filter(j => !j.expedido && j.etapa !== "Pronto").length;
  const atrasadosTot = jobs.filter(j => bucketOf(j) === "atrasado").length;

  // agrupamentos
  const groupBy = (keyFn, order) => {
    const g = {};
    list.forEach(j => { const k = keyFn(j); (g[k] = g[k] || []).push(j); });
    return order.filter(k => g[k] && g[k].length).map(k => ({ k, items: g[k] }));
  };

  return (
    <>
      <DetailHeader nav={nav} title="Produção"
                    eyebrow={ativos + " ativos" + (atrasadosTot ? " · " + atrasadosTot + " atrasados" : "")} />

      {/* controles fixos */}
      <div className="oi-head" style={{ paddingTop: 4, paddingBottom: 10, borderBottom: "1px solid var(--border)" }}>
        <div className="oi-search">
          <Ic.search size={16} />
          <input placeholder="Cliente, OS, produto, MFG…" value={q} onChange={e => setQ(e.target.value)} />
          {q && <button className="oi-iconbtn" style={{ width: 26, height: 26, background: "transparent" }}
                        onClick={() => setQ("")}><Ic.x size={15} /></button>}
        </div>

        {/* triagem por urgência */}
        <div style={{ display: "flex", gap: 6, marginTop: 10 }}>
          <TriageStat count={cnt.atrasado} label="Atrasados" tone="danger"
                      active={triage === "atrasado"} onClick={() => setTriage(triage === "atrasado" ? "todos" : "atrasado")} />
          <TriageStat count={cnt.hoje} label="Vence hoje" tone="warn"
                      active={triage === "hoje"} onClick={() => setTriage(triage === "hoje" ? "todos" : "hoje")} />
          <TriageStat count={cnt.producao} label="Em produção" tone="accent"
                      active={triage === "producao"} onClick={() => setTriage(triage === "producao" ? "todos" : "producao")} />
          <TriageStat count={cnt.pronto} label="Prontos" tone="ok"
                      active={triage === "pronto"} onClick={() => setTriage(triage === "pronto" ? "todos" : "pronto")} />
        </div>

        {/* alternância de visão */}
        <div style={{ display: "flex", gap: 4, marginTop: 10, background: "var(--bg-2)",
                      border: "1px solid var(--border)", borderRadius: 9, padding: 3 }}>
          {[{ id: "prazo", label: "Por prazo", ic: "clock" },
            { id: "etapa", label: "Por etapa", ic: "layers" },
            { id: "estacao", label: "Estações", ic: "printer" }].map(v => (
            <button key={v.id} onClick={() => setView(v.id)}
                    style={{
                      appearance: "none", cursor: "pointer", flex: 1,
                      display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 5,
                      height: 32, borderRadius: 7, border: 0,
                      background: view === v.id ? "var(--surface)" : "transparent",
                      boxShadow: view === v.id ? "var(--shadow-soft)" : "none",
                      color: view === v.id ? "var(--text)" : "var(--text-mute)",
                      fontSize: 12, fontWeight: 600, fontFamily: "inherit",
                    }}>
              {React.createElement(Ic[v.ic], { size: 14 })} {v.label}
            </button>
          ))}
        </div>
      </div>

      <div className="oi-scroll">
        {/* indicador de filtro ativo */}
        {triage !== "todos" && (
          <div className="oi-section" style={{ paddingBottom: 0 }}>
            <button onClick={() => setTriage("todos")}
                    style={{ appearance: "none", cursor: "pointer", display: "inline-flex", alignItems: "center", gap: 6,
                             background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 999,
                             padding: "4px 10px 4px 12px", fontSize: 11.5, color: "var(--text-dim)", fontFamily: "inherit" }}>
              Filtro: {PROD_BUCKETS.find(b => b.id === triage)?.label ||
                       (triage === "producao" ? "Em produção" : "Prontos")}
              <Ic.x size={13} />
            </button>
          </div>
        )}

        {list.length === 0 && (
          <div className="oi-empty" style={{ marginTop: 24 }}>
            <div className="oi-empty-ico ok"><Ic.checkCircle size={24} /></div>
            <b>Nada por aqui</b>
            <small>Nenhum job de produção com esse filtro.</small>
          </div>
        )}

        {/* VISÃO: ESTAÇÕES — carga + agrupado por estação */}
        {view === "estacao" && list.length > 0 && (
          <>
            <div className="oi-section">
              <h3 className="oi-section-h">Carga das estações <span style={{ marginLeft: "auto", fontWeight: 500, letterSpacing: 0, textTransform: "none", color: "var(--text-mute)" }}>toque p/ filtrar</span></h3>
              <div className="oi-card pad" style={{ gap: 11 }}>
                {(window.MOCK.estacoesProducao ? window.MOCK.estacoesProducao() : PRODUCAO_ESTACOES).map(e => {
                  const n = jobs.filter(j => !j.expedido && j.estacao === e.id).length;
                  const on = estacao === e.id;
                  return (
                    <div key={e.id} style={{ display: "flex", flexDirection: "column", gap: 4, cursor: "pointer" }}
                         onClick={() => setEstacao(on ? "todas" : e.id)}>
                      <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 13 }}>
                        <span style={{ fontWeight: on ? 700 : 500, color: on ? "var(--accent)" : "var(--text)" }}>{e.nome}</span>
                        {e.fromEquip && <span style={{ fontSize: 8.5, fontWeight: 700, letterSpacing: ".04em", textTransform: "uppercase", color: "var(--origin-OFI-fg)", background: "var(--origin-OFI-bg)", padding: "1px 5px", borderRadius: 4 }}>Equip.</span>}
                        <span style={{ marginLeft: "auto", fontSize: 11.5, color: "var(--text-mute)" }}>
                          <span className="oi-mono" style={{ color: "var(--text-dim)" }}>{n}</span> na fila · {(e.carga * 100).toFixed(0)}%
                        </span>
                      </div>
                      <div className={"oi-progress " + (e.carga > 0.85 ? "danger" : e.carga > 0.7 ? "warn" : "")}>
                        <i style={{ width: (e.carga * 100) + "%" }} />
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
            {groupBy(j => j.estacao, PRODUCAO_ESTACOES.map(e => e.id)).map(({ k, items }) => (
              <div className="oi-section" key={k}>
                <h3 className="oi-section-h">
                  {(PRODUCAO_ESTACOES.find(e => e.id === k) || {}).nome}
                  <span style={{ marginLeft: 6, color: "var(--text-mute)" }}>{items.length}</span>
                </h3>
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {items.map(j => <ProdJobCard key={j.id} j={j} nav={nav} onAdvance={advance} />)}
                </div>
              </div>
            ))}
          </>
        )}

        {/* VISÃO: ETAPA — pipeline */}
        {view === "etapa" && list.length > 0 &&
          groupBy(j => (j.expedido ? "Pronto" : j.etapa), ETAPAS).map(({ k, items }) => (
            <div className="oi-section" key={k}>
              <h3 className="oi-section-h">
                <span className={"oi-status " + etapaTone(k)} style={{ textTransform: "none", letterSpacing: 0 }}>
                  <span className="dot" />{k}
                </span>
                <span style={{ marginLeft: "auto", color: "var(--text-mute)" }}>{items.length}</span>
              </h3>
              <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                {items.map(j => <ProdJobCard key={j.id} j={j} nav={nav} onAdvance={advance} />)}
              </div>
            </div>
          ))}

        {/* VISÃO: PRAZO — agrupado por urgência */}
        {view === "prazo" && list.length > 0 &&
          groupBy(bucketOf, PROD_BUCKETS.map(b => b.id)).map(({ k, items }) => {
            const b = PROD_BUCKETS.find(x => x.id === k);
            return (
              <div className="oi-section" key={k}>
                <h3 className="oi-section-h">
                  <span style={{ width: 7, height: 7, borderRadius: 99, background: PROD_TONE[b.tone] }} />
                  <span style={{ color: b.tone === "muted" ? "var(--text-mute)" : PROD_TONE[b.tone] }}>{b.label}</span>
                  <span style={{ marginLeft: "auto", color: "var(--text-mute)" }}>{items.length}</span>
                </h3>
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {items.map(j => <ProdJobCard key={j.id} j={j} nav={nav} onAdvance={advance} />)}
                </div>
              </div>
            );
          })}

        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

// ─── Detalhe do job ───
function ProducaoJobDetalheScreen({ nav, ctx, params }) {
  const [jobs, setJobs] = window.useStore("producao.jobs", () => PRODUCAO_JOBS.map(j => ({ ...j })));
  const j = jobs.find(x => x.id === params.id) || jobs[0];
  const due = dueInfo(j);
  const isDone = j.expedido || j.etapa === "Pronto";
  const sIdx = Math.max(0, ETAPAS.indexOf(j.etapa));
  const action = nextAction(j);

  const advance = () => setJobs(js => js.map(prev => {
    if (prev.id !== j.id) return prev;
    if (prev.etapa === "Pronto") return { ...prev, expedido: true };
    const i = ETAPAS.indexOf(prev.etapa);
    const ne = ETAPAS[Math.min(i + 1, ETAPAS.length - 1)];
    let prog = prev.prog;
    if (ne === "Imprimindo") prog = 0.12;
    else if (ne === "Acabamento") prog = 0.45;
    else if (ne === "Pronto") prog = 1;
    return { ...prev, etapa: ne, prog, op: prev.op || "Você", nota: null };
  }));

  return (
    <>
      <DetailHeader nav={nav} title={j.id} eyebrow={"MFG · " + j.cliente} />
      <div className="oi-scroll">
        <div className="oi-section">
          <div className="oi-card pad">
            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
              <OriginBadge origin="MFG" size="lg" />
              <span className={"oi-status " + etapaTone(j.etapa)}>
                <span className="dot" />{j.expedido ? "Expedido" : j.etapa}
              </span>
              <span style={{ marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: 4,
                             fontSize: 12, fontWeight: 600, fontFamily: "var(--font-mono)", color: PROD_TONE[due.tone] }}>
                {React.createElement(Ic[due.icon] || Ic.clock, { size: 14 })}{due.label}
              </span>
            </div>
            <div style={{ marginTop: 8, fontSize: 16, fontWeight: 600, lineHeight: 1.35 }}>{j.produto}</div>
            <div style={{ fontSize: 12.5, color: "var(--text-dim)" }}>{j.cliente} · {j.os} · {j.qtd}</div>
            {j.nota && !j.expedido && (
              <div className="oi-hint" style={{ marginTop: 4 }}>
                <Ic.alert size={14} />{j.nota}
              </div>
            )}
          </div>
        </div>

        {/* Stepper de etapas com histórico */}
        <div className="oi-section">
          <h3 className="oi-section-h">Etapas e histórico</h3>
          <div className="oi-card pad">
            {ETAPAS.map((s, i) => {
              const passed = i < sIdx || j.expedido;
              const current = i === sIdx && !j.expedido;
              const stageTimes = ["08:10", "09:05", "10:40", "11:30"];
              const meta = passed ? "Concluído · " + (j.op || "Operador") + " · Hoje " + stageTimes[i]
                         : current ? "Em andamento · " + (j.op || "Sem operador")
                         : null;
              return (
                <div key={s} style={{ display: "flex", alignItems: "flex-start", gap: 12, padding: "8px 0",
                                      borderBottom: i < ETAPAS.length - 1 ? "1px solid var(--border-2)" : 0 }}>
                  <div style={{
                    width: 26, height: 26, borderRadius: 99, flex: "0 0 auto", marginTop: 1,
                    background: passed ? "var(--ok)" : current ? "var(--accent)" : "var(--bg-2)",
                    border: "2px solid " + (passed ? "var(--ok)" : current ? "var(--accent)" : "var(--border)"),
                    color: passed || current ? "#fff" : "var(--text-mute)",
                    display: "grid", placeItems: "center", fontSize: 11, fontWeight: 700,
                  }}>
                    {passed ? <Ic.check size={13} color="#fff" strokeWidth={3} /> : (i + 1)}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                      <span style={{ fontSize: 13.5, fontWeight: current ? 600 : 500,
                                    color: current ? "var(--text)" : passed ? "var(--text-dim)" : "var(--text-mute)" }}>{s}</span>
                      {current && typeof j.prog === "number" && j.prog > 0 && j.prog < 1 && (
                        <span className="oi-mono" style={{ marginLeft: "auto", fontSize: 11, color: "var(--accent)", fontWeight: 600 }}>
                          {(j.prog * 100).toFixed(0)}%
                        </span>
                      )}
                    </div>
                    {meta && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 2 }}>{meta}</div>}
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Detalhes</h3>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>Estação</dt>   <dd>{(PRODUCAO_ESTACOES.find(e => e.id === j.estacao) || {}).nome}</dd>
              <dt>Operador</dt>  <dd>{j.op || "—"}</dd>
              <dt>Quantidade</dt><dd>{j.qtd}</dd>
              <dt>Duração est.</dt><dd>{j.duracao}</dd>
              <dt>Prazo</dt>     <dd className="oi-mono">{j.prazo}</dd>
              <dt>Prioridade</dt><dd style={{ textTransform: "capitalize",
                                              color: j.prio === "alta" ? "var(--danger)" : j.prio === "media" ? "var(--warn)" : "var(--text)" }}>{j.prio}</dd>
              <dt>OS vinculada</dt><dd className="oi-mono">{j.os}</dd>
            </dl>
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>

      {/* Barra de ação */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)",
                    display: "flex", gap: 8 }}>
        {j.expedido ? (
          <button className="oi-btn block" onClick={() => nav.pop()}>
            <Ic.check size={18} /> Concluído — voltar
          </button>
        ) : j.etapa === "Pronto" ? (
          <button className="oi-btn action block" onClick={advance}>
            <Ic.truck size={18} /> Enviar para expedição
          </button>
        ) : (
          <>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }}>
              <Ic.user size={16} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }} onClick={advance}>
              {React.createElement(Ic[action.icon], { size: 18 })}
              {j.etapa === "Em fila" ? " Iniciar impressão" : " Concluir " + j.etapa.toLowerCase() + " → " + ETAPAS[sIdx + 1]}
            </button>
          </>
        )}
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { ProducaoScreen, ProducaoJobDetalheScreen });

// All app screens. Each screen is { headerTitle, render(nav, ctx) }.
// `nav` provides nav.push(name, params), nav.pop(), nav.replace(name).
// `ctx` provides tenant, taskState, density, theme, etc.

const { Ic } = window;
const { TENANTS, USER, PEDIDOS, PRODUTOS, TRANSACOES, NOTIFS, OPS_KPIS,
        TASKS_FULL, TASKS_EMPTY, TASKS_URGENT } = window.MOCK;
const BRL = window.BRL;
const BRLcompact = window.BRLcompact;

// ────────────────────────────────────────────────
// SHARED PRIMITIVES
// ────────────────────────────────────────────────
function ScreenHeader({ eyebrow, title, actions, search, sticky = true, nav }) {
  const canBack = nav && typeof nav.canPop === "function" && nav.canPop();
  return (
    <div className="oi-head" style={{ position: sticky ? "sticky" : "static", top: 0, zIndex: 5 }}>
      <div className="oi-head-row">
        {canBack && (
          <button className="oi-iconbtn" onClick={() => nav.pop()}
                  style={{ marginLeft: -8, color: "var(--accent)" }}>
            <Ic.chevL size={24} />
          </button>
        )}
        <div style={{ flex: "1 1 auto", minWidth: 0 }}>
          {eyebrow && <div className="oi-head-eyebrow">{eyebrow}</div>}
          <div className="oi-head-title">{title}</div>
        </div>
        {actions}
      </div>
      {search}
    </div>
  );
}

function OriginBadge({ origin, size = "sm" }) {
  return <span className={"oi-origin o-" + origin + (size === "lg" ? " lg" : "")}>{origin}</span>;
}

function StageStatus({ etapaKey, label }) {
  const map = {
    aprov:  { cls: "warn", txt: label || "Aguardando aprovação" },
    prod:   { cls: "accent", txt: label || "Em produção" },
    entrega:{ cls: "info", txt: label || "Saiu para entrega" },
    done:   { cls: "ok",   txt: label || "Concluído" },
    orc:    { cls: "",     txt: label || "Orçamento" },
    cancel: { cls: "danger", txt: label || "Cancelado" },
  };
  const s = map[etapaKey] || { cls: "", txt: label };
  return <span className={"oi-status " + s.cls}><span className="dot" />{s.txt}</span>;
}

function NotifBell({ onClick }) {
  const [read] = window.useStore("notifs.read", []);
  const unread = NOTIFS.filter(n => n.unread && !read.includes(n.id)).length;
  return (
    <div className="oi-iconbtn-wrap">
      <button className="oi-iconbtn" onClick={onClick}>
        <Ic.bell />
        {unread > 0 && <span className="badge">{unread}</span>}
      </button>
    </div>
  );
}

function HeaderTopRight({ nav, tenant }) {
  const t = TENANTS.find(x => x.id === tenant) || TENANTS[0];
  return (
    <>
      <NotifBell onClick={() => nav.push("notificacoes")} />
      <button className="oi-iconbtn" onClick={() => nav.push("perfil")}>
        <div className={"oi-av " + t.color}
             style={{ width: 30, height: 30, borderRadius: "50%", fontSize: 11 }}>
          {USER.initials}
        </div>
      </button>
    </>
  );
}

// Reusable detail header (back arrow)
function DetailHeader({ nav, title, eyebrow, actions }) {
  return (
    <div className="oi-head" style={{ paddingTop: 6 }}>
      <div className="oi-head-row" style={{ minHeight: 32 }}>
        <button className="oi-iconbtn" onClick={() => nav.pop()} style={{ marginLeft: -8, color: "var(--accent)" }}>
          <Ic.chevL size={24} />
        </button>
        <div style={{ flex: "1 1 auto", minWidth: 0 }}>
          {eyebrow && <div className="oi-head-eyebrow">{eyebrow}</div>}
          <div className="oi-head-title" style={{ fontSize: 18 }}>{title}</div>
        </div>
        {actions}
      </div>
    </div>
  );
}

// ────────────────────────────────────────────────
// HOME / OPS
// ────────────────────────────────────────────────
function HomeScreen({ nav, ctx }) {
  const t = TENANTS.find(x => x.id === ctx.tenant) || TENANTS[0];
  const hora = new Date().getHours();
  const saud = hora < 12 ? "Bom dia" : hora < 18 ? "Boa tarde" : "Boa noite";
  const meta = OPS_KPIS.faturamento_meta;
  const fat = OPS_KPIS.faturamento_hoje;
  const pct = Math.min(1, fat / meta);
  const urgentes = ctx.taskState === "vazio" ? 0 : ctx.taskState === "urgente" ? 5 : 3;

  return (
    <>
      <ScreenHeader
        nav={nav}
        eyebrow="Início · Hoje, 16 mai"
        title={saud + ", " + USER.nome.split(" ")[0]}
        actions={<HeaderTopRight nav={nav} tenant={ctx.tenant} />}
      />
      <div className="oi-scroll">
        <div className="oi-section">
          <button className="oi-tenant-pill" onClick={() => nav.push("empresa")} style={{ marginBottom: 12 }}>
            <span className={"av oi-av " + t.color}>{t.short}</span>
            <span className="nm">{t.name}</span>
            <Ic.chevD size={14} />
          </button>

          {/* Faturamento card — KPI hero (modelo aprovado) */}
          <div className="oi-card kpi" style={{ padding: 18 }}>
            <span className="oi-cube-wm"><Ic.oimpresso size={132} strokeWidth={3} /></span>
            <div style={{ display: "flex", alignItems: "baseline", gap: 8, position: "relative" }}>
              <small style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".08em", textTransform: "uppercase", opacity: .85 }}>
                Faturamento hoje
              </small>
              <span style={{ marginLeft: "auto", fontSize: 11, opacity: .85 }}>
                meta {BRLcompact(meta)}
              </span>
            </div>
            <div className="oi-money" style={{ fontSize: 32, fontWeight: 700, letterSpacing: "-0.02em", lineHeight: 1.1, position: "relative" }}>
              {BRL(fat)}
            </div>
            <div className="oi-progress"><i style={{ width: (pct * 100) + "%" }} /></div>
            <div style={{ display: "flex", fontSize: 11.5, opacity: .92, position: "relative" }}>
              <span>{(pct * 100).toFixed(0)}% da meta</span>
              <span style={{ marginLeft: "auto", fontWeight: 600 }}>
                <Ic.trendingUp size={12} style={{ marginRight: 4, verticalAlign: "middle" }} />
                +18% vs ontem
              </span>
            </div>
          </div>
        </div>

        {/* KPI cube tiles + sparklines + resumo semanal + assistente (modelo) */}
        <Dashboard nav={nav} />

        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

// ── Painel de widgets do Início (replicação do modelo) ──
function Dashboard({ nav }) {
  const W = window.Widgets;
  const D = window.MOCK.HOME_DASH;
  return (
    <>
      <div className="oi-section">
        <div className="oi-cubes">
          {D.tiles.map((t, i) => <W.CubeTile key={i} tile={t} />)}
        </div>
      </div>

      <div className="oi-section">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 9 }}>
          {D.spark.map((s, i) => <W.SparkCard key={i} item={s} />)}
        </div>
      </div>

      <div className="oi-section">
        <h3 className="oi-section-h">
          Resumo semanal
          <span style={{ marginLeft: "auto", fontSize: 11.5, color: "var(--text-dim)", textTransform: "none", letterSpacing: 0, fontWeight: 500, display: "inline-flex", alignItems: "center", gap: 4 }}>
            Esta semana <Ic.chevD size={13} />
          </span>
        </h3>
        <div className="oi-minis">
          {D.weekly.map((w, i) => <W.MiniTrend key={i} item={w} />)}
        </div>
      </div>

      <div className="oi-section">
        <h3 className="oi-section-h">
          Assistente Office
          <span className="oi-status accent" style={{ marginLeft: 8, padding: "1px 7px", fontSize: 9.5 }}>BETA</span>
          <a className="more" onClick={() => nav.gotoTab("tarefas")}>Ver todos</a>
        </h3>
        <W.AssistCard items={D.assist} nav={nav} />
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// TAREFAS (inbox unificada)
// ────────────────────────────────────────────────
function TarefasScreen({ nav, ctx }) {
  const [filter, setFilter] = React.useState("todas");
  const [q, setQ] = React.useState("");
  const [sheet, setSheet] = React.useState(null); // "nova"
  const [mut, setMut] = window.useStore("tasks.mut", window.OITasks.EMPTY_MUT);
  const base = ctx.taskState === "vazio" ? TASKS_EMPTY :
               ctx.taskState === "urgente" ? TASKS_URGENT : TASKS_FULL;
  const all = window.OITasks.apply(base, mut);

  const filtered = all.filter(t => {
    if (filter !== "todas" && t.origin !== filter) return false;
    if (q && !(t.title + " " + (t.sub || "")).toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const hoje = filtered.filter(t => t.bucket === "hoje");
  const amanha = filtered.filter(t => t.bucket === "amanha");
  const semana = filtered.filter(t => t.bucket === "semana");

  return (
    <>
      <ScreenHeader
        nav={nav}
        title="Tarefas"
        eyebrow={filtered.length + " pendentes · " + filtered.filter(t => t.urgent).length + " urgentes"}
        actions={<HeaderTopRight nav={nav} tenant={ctx.tenant} />}
        search={
          <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 8 }}>
            <div className="oi-search">
              <Ic.search size={16} />
              <input placeholder="Buscar tarefas" value={q} onChange={e => setQ(e.target.value)} />
            </div>
            <div className="oi-chips">
              {[
                { id: "todas", label: "Todas", c: all.length },
                { id: "OS", label: "OS", c: all.filter(t => t.origin === "OS").length },
                { id: "FIN", label: "Financeiro", c: all.filter(t => t.origin === "FIN").length },
                { id: "CRM", label: "Clientes", c: all.filter(t => t.origin === "CRM").length },
                { id: "MFG", label: "Produção", c: all.filter(t => t.origin === "MFG").length },
                { id: "PNT", label: "Ponto", c: all.filter(t => t.origin === "PNT").length },
              ].map(c => (
                <button key={c.id} className={"oi-chip" + (filter === c.id ? " on" : "")}
                        onClick={() => setFilter(c.id)}>
                  {c.label} <span className="c">{c.c}</span>
                </button>
              ))}
            </div>
          </div>
        }
      />
      <div className="oi-scroll">
        {filtered.length === 0 && (
          <div className="oi-empty" style={{ marginTop: 32 }}>
            <div className="oi-empty-ico ok"><Ic.check size={26} /></div>
            <b>Inbox zero</b>
            <small>Nada pendente nos seus módulos. Toque no botão flutuante para criar uma tarefa manual.</small>
          </div>
        )}

        {hoje.length > 0 && <TaskGroup label="Hoje" tasks={hoje} nav={nav} />}
        {amanha.length > 0 && <TaskGroup label="Amanhã" tasks={amanha} nav={nav} />}
        {semana.length > 0 && <TaskGroup label="Esta semana" tasks={semana} nav={nav} />}

        <div style={{ height: 80 }} />
      </div>
      <div className="oi-fab" onClick={() => setSheet("nova")} role="button" aria-label="Criar nova tarefa">
        <Ic.plus size={26} />
      </div>
      {sheet === "nova" && (
        <NovaTarefaSheet onClose={() => setSheet(null)}
          onCreate={(t) => {
            setMut(m => ({ ...m, created: [...(m.created || []), t] }));
            setSheet(null);
            window.oiToast("Tarefa criada", "ok");
          }} />
      )}
    </>
  );
}

// ─── Sheet: nova tarefa manual ───
function NovaTarefaSheet({ onClose, onCreate }) {
  const [titulo, setTitulo] = React.useState("");
  const [origin, setOrigin] = React.useState("OS");
  const [bucket, setBucket] = React.useState("hoje");
  const [urgent, setUrgent] = React.useState(false);
  const whenLabel = { hoje: "Hoje", amanha: "Amanhã", semana: "Esta semana" };
  const criar = () => {
    if (!titulo.trim()) return;
    onCreate({
      id: "t-man-" + Date.now(),
      origin, title: titulo.trim(), sub: "Tarefa manual · criada agora",
      when: whenLabel[bucket], bucket, urgent, viewer: null, manual: true,
    });
  };
  return (
    <window.OISheet title="Nova tarefa" onClose={onClose}>
      <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
        <div>
          <div className="oi-field-l" style={{ fontSize: 11, fontWeight: 600, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 6 }}>Título</div>
          <input className="oi-input" autoFocus placeholder="Ex.: Ligar para fornecedor de lona"
                 value={titulo} onChange={e => setTitulo(e.target.value)}
                 onKeyDown={e => { if (e.key === "Enter") criar(); }} />
        </div>
        <div>
          <div style={{ fontSize: 11, fontWeight: 600, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 6 }}>Módulo</div>
          <div className="oi-chips">
            {["OS", "CRM", "FIN", "MFG", "PNT"].map(o => (
              <button key={o} className={"oi-chip" + (origin === o ? " on" : "")} onClick={() => setOrigin(o)}>{o}</button>
            ))}
          </div>
        </div>
        <div>
          <div style={{ fontSize: 11, fontWeight: 600, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 6 }}>Prazo</div>
          <div className="oi-chips">
            {[["hoje", "Hoje"], ["amanha", "Amanhã"], ["semana", "Esta semana"]].map(([id, l]) => (
              <button key={id} className={"oi-chip" + (bucket === id ? " on" : "")} onClick={() => setBucket(id)}>{l}</button>
            ))}
          </div>
        </div>
        <button className="oi-chip" style={{ alignSelf: "flex-start", display: "inline-flex", alignItems: "center", gap: 6,
                                              ...(urgent ? { borderColor: "var(--danger)", color: "var(--danger)", background: "color-mix(in oklch, var(--danger) 10%, transparent)" } : {}) }}
                onClick={() => setUrgent(u => !u)}>
          <Ic.flame size={13} /> Urgente {urgent ? "✓" : ""}
        </button>
        <button className="oi-btn block primary" disabled={!titulo.trim()} onClick={criar}>Criar tarefa</button>
      </div>
    </window.OISheet>
  );
}

function TaskGroup({ label, tasks, nav }) {
  return (
    <div className="oi-section" style={{ paddingTop: 14 }}>
      <h3 className="oi-section-h">{label} <span style={{ marginLeft: 6, color: "var(--text-mute)", fontWeight: 500 }}>· {tasks.length}</span></h3>
      <div className="oi-list card">
        {tasks.map(t => (
          <div key={t.id} className="oi-list-row" onClick={() => nav.push("tarefa", { id: t.id })}
               style={t.urgent ? { borderLeft: "3px solid var(--danger)", paddingLeft: 13 } : {}}>
            <div style={{ flex: "1 1 auto", minWidth: 0, display: "flex", flexDirection: "column", gap: 4 }}>
              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <OriginBadge origin={t.origin} />
                {t.urgent && <Ic.flame size={12} color="var(--danger)" />}
                <span style={{ marginLeft: "auto", fontSize: 11,
                               color: t.urgent ? "var(--danger)" : "var(--text-mute)",
                               fontWeight: t.urgent ? 600 : 400,
                               fontFamily: "var(--font-mono)" }}>
                  {t.when}
                </span>
              </div>
              <div style={{ fontWeight: 600, fontSize: 13.5, letterSpacing: "-0.005em", lineHeight: 1.3 }}>
                {t.title}
              </div>
              <div style={{ fontSize: 11.5, color: "var(--text-dim)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                {t.sub}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ────────────────────────────────────────────────
// TAREFA DETALHE (com viewer por origem)
// ────────────────────────────────────────────────
function TarefaDetalheScreen({ nav, ctx, params }) {
  const [mut, setMut] = window.useStore("tasks.mut", window.OITasks.EMPTY_MUT);
  const all = TASKS_FULL.concat(mut.created || []);
  const t = all.find(x => x.id === params.id) || TASKS_FULL[0];
  const [sheet, setSheet] = React.useState(null); // "adiar" | "opcoes"

  const concluir = () => {
    setMut(m => ({ ...m, done: [...m.done, t.id] }));
    nav.pop();
    window.oiToast("Tarefa concluída", "ok");
  };
  const adiarPara = (bucket, label) => {
    setMut(m => ({ ...m, snooze: { ...m.snooze, [t.id]: bucket } }));
    setSheet(null);
    nav.pop();
    window.oiToast("Adiada para " + label.toLowerCase(), "ok");
  };
  const duplicar = () => {
    setMut(m => ({ ...m, created: [...(m.created || []), { ...t, id: "t-man-" + Date.now(), title: t.title + " (cópia)", sub: "Duplicada agora", manual: true, viewer: null }] }));
    setSheet(null);
    window.oiToast("Tarefa duplicada", "ok");
  };
  const excluir = () => {
    setMut(m => ({ ...m, done: [...m.done, t.id] }));
    setSheet(null);
    nav.pop();
    window.oiToast("Tarefa excluída");
  };

  return (
    <>
      <DetailHeader nav={nav} title={t.id.toUpperCase()} eyebrow={t.origin + " · " + (t.cliente || "—")}
                    actions={<button className="oi-iconbtn" aria-label="Opções da tarefa" onClick={() => setSheet("opcoes")}><Ic.dotsV /></button>} />
      <div className="oi-scroll">
        <div className="oi-section">
          <div className="oi-card pad">
            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
              <OriginBadge origin={t.origin} size="lg" />
              {t.urgent && <span className="oi-status danger"><Ic.flame size={12} color="currentColor" /> Urgente</span>}
              <span style={{ marginLeft: "auto", fontSize: 12, color: "var(--text-mute)" }} className="oi-mono">{t.when}</span>
            </div>
            <h2 style={{ margin: "10px 0 0", fontSize: 18, fontWeight: 600, letterSpacing: "-0.01em", lineHeight: 1.3 }}>{t.title}</h2>
            <p style={{ margin: "4px 0 0", fontSize: 13, color: "var(--text-dim)", lineHeight: 1.5 }}>{t.sub}</p>
          </div>
        </div>

        {/* Viewer específico */}
        <TaskViewer task={t} nav={nav} />

        {/* Histórico */}
        <div className="oi-section">
          <h3 className="oi-section-h">Histórico</h3>
          <div className="oi-card tight" style={{ padding: 0 }}>
            {(t.manual ? [
              { t: "agora", who: "Você", text: "Criou a tarefa manualmente" },
            ] : [
              { t: "12min", who: "Cliente", text: "Enviou nova versão da arte (v3)" },
              { t: "1h",   who: "Você",    text: "Solicitou ajuste no telefone" },
              { t: "Ontem", who: "Sistema", text: "OS criada via orçamento aprovado" },
            ]).map((h, i, arr) => (
              <div key={i} style={{ display: "flex", gap: 10, padding: "10px 14px",
                                    borderBottom: i < arr.length - 1 ? "1px solid var(--border-2)" : 0, fontSize: 12.5 }}>
                <span className="oi-mono" style={{ color: "var(--text-mute)", minWidth: 48 }}>{h.t}</span>
                <span style={{ flex: 1 }}><b style={{ fontWeight: 600 }}>{h.who}:</b> <span style={{ color: "var(--text-dim)" }}>{h.text}</span></span>
              </div>
            ))}
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>

      {/* Bottom action bar */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }} onClick={() => setSheet("adiar")}>
          <Ic.clock size={16} /> Adiar
        </button>
        <button className="oi-btn primary" style={{ flex: "1 1 auto" }} onClick={concluir}>
          <Ic.check size={18} /> Concluir
        </button>
      </div>

      {sheet === "adiar" && (
        <window.OISheet title="Adiar para" onClose={() => setSheet(null)}>
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {[["amanha", "Amanhã", "calendar"], ["semana", "Esta semana", "clock"]].map(([b, l, ic]) => (
              <button key={b} className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10 }} onClick={() => adiarPara(b, l)}>
                {React.createElement(Ic[ic], { size: 17 })} {l}
              </button>
            ))}
          </div>
        </window.OISheet>
      )}

      {sheet === "opcoes" && (
        <window.OISheet title="Opções da tarefa" onClose={() => setSheet(null)}>
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            <button className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10 }} onClick={duplicar}>
              <Ic.plus size={17} /> Duplicar tarefa
            </button>
            <button className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10 }} onClick={() => { setSheet("adiar"); }}>
              <Ic.clock size={17} /> Adiar…
            </button>
            <button className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10, color: "var(--danger)" }} onClick={excluir}>
              <Ic.x size={17} /> Excluir tarefa
            </button>
          </div>
        </window.OISheet>
      )}
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, {
  HomeScreen, TarefasScreen, TarefaDetalheScreen,
  ScreenHeader, DetailHeader, HeaderTopRight, OriginBadge, StageStatus,
});

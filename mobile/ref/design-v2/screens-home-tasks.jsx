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
    entrega:{ cls: "accent", txt: label || "Em entrega" },
    done:   { cls: "ok",   txt: label || "Concluído" },
    orc:    { cls: "",     txt: label || "Orçamento" },
    cancel: { cls: "danger", txt: label || "Cancelado" },
  };
  const s = map[etapaKey] || { cls: "", txt: label };
  return <span className={"oi-status " + s.cls}><span className="dot" />{s.txt}</span>;
}

function NotifBell({ onClick }) {
  const unread = NOTIFS.filter(n => n.unread).length;
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

          {/* Faturamento card */}
          <div className="oi-card hi" style={{ padding: 16 }}>
            <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
              <small style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".08em", textTransform: "uppercase", color: "var(--text-mute)" }}>
                Faturado hoje
              </small>
              <span style={{ marginLeft: "auto", fontSize: 11, color: "var(--text-dim)" }}>
                meta {BRLcompact(meta)}
              </span>
            </div>
            <div className="oi-money" style={{ fontSize: 30, fontWeight: 600, letterSpacing: "-0.02em", lineHeight: 1.1 }}>
              {BRL(fat)}
            </div>
            <div className="oi-progress"><i style={{ width: (pct * 100) + "%" }} /></div>
            <div style={{ display: "flex", fontSize: 11.5, color: "var(--text-dim)" }}>
              <span>{(pct * 100).toFixed(0)}% da meta</span>
              <span style={{ marginLeft: "auto", color: "var(--ok)", fontWeight: 600 }}>
                <Ic.trendingUp size={12} style={{ marginRight: 4, verticalAlign: "middle" }} />
                +18% vs ontem
              </span>
            </div>
          </div>
        </div>

        {/* KPIs */}
        <div className="oi-section">
          <div className="oi-kpis">
            <div className="oi-kpi">
              <small>OS Hoje</small>
              <b>{OPS_KPIS.pedidos_hoje}</b>
              <span className="trend">+2</span>
            </div>
            <div className="oi-kpi">
              <small>Em aberto</small>
              <b>{OPS_KPIS.os_em_aberto}</b>
              <span style={{ fontSize: 11, color: "var(--text-mute)" }}>{urgentes} urgentes</span>
            </div>
            <div className="oi-kpi warn">
              <small>Estoque baixo</small>
              <b>{OPS_KPIS.estoque_baixo}</b>
              <span style={{ fontSize: 11, color: "var(--text-mute)" }}>itens</span>
            </div>
          </div>
        </div>

        {/* Atalhos rápidos */}
        <div className="oi-section">
          <h3 className="oi-section-h">Atalhos</h3>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 8 }}>
            {[
              { ic: "plus", label: "Novo pedido", to: () => nav.push("novo-pedido") },
              { ic: "scan", label: "Venda rápida", to: () => nav.push("venda-rapida") },
              { ic: "qr", label: "Cobrar PIX", to: () => alert("PIX") },
              { ic: "dollar", label: "Conciliar", to: () => nav.push("financas") },
            ].map((a, i) => (
              <button key={i} className="oi-card tight"
                      onClick={a.to}
                      style={{ alignItems: "center", textAlign: "center", padding: "12px 6px", gap: 6, cursor: "pointer", background: "var(--surface)" }}>
                <div style={{ width: 36, height: 36, borderRadius: 10, background: "var(--accent-soft)", color: "var(--accent)", display: "grid", placeItems: "center" }}>
                  {React.createElement(Ic[a.ic], { size: 18 })}
                </div>
                <small style={{ fontSize: 11, color: "var(--text-dim)", fontWeight: 500, lineHeight: 1.2 }}>{a.label}</small>
              </button>
            ))}
          </div>
        </div>

        {/* Próximas tarefas */}
        <div className="oi-section">
          <h3 className="oi-section-h">
            Próximas tarefas
            <a className="more" onClick={() => nav.gotoTab("tarefas")}>Ver todas</a>
          </h3>
          {(ctx.taskState === "vazio" ? [] : (ctx.taskState === "urgente" ? TASKS_URGENT : TASKS_FULL)).slice(0, 3).map(t => (
            <div key={t.id} className="oi-card tight" style={{ marginBottom: 8, cursor: "pointer" }}
                 onClick={() => { nav.gotoTab("tarefas"); nav.push("tarefa", { id: t.id }); }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <OriginBadge origin={t.origin} />
                {t.urgent && <Ic.flame size={13} color="var(--danger)" />}
                <span style={{ marginLeft: "auto", fontSize: 11, color: t.urgent ? "var(--danger)" : "var(--text-mute)", fontWeight: t.urgent ? 600 : 400 }}>{t.when}</span>
              </div>
              <div style={{ fontWeight: 600, fontSize: 13.5, letterSpacing: "-0.005em", lineHeight: 1.35 }}>{t.title}</div>
              <div style={{ fontSize: 11.5, color: "var(--text-dim)" }}>{t.sub}</div>
            </div>
          ))}
          {ctx.taskState === "vazio" && (
            <div className="oi-empty" style={{ padding: "32px 16px" }}>
              <div className="oi-empty-ico ok"><Ic.check size={22} /></div>
              <b>Tudo em dia</b>
              <small>Nenhuma tarefa pendente. Boa tarde, vá pegar um café.</small>
            </div>
          )}
        </div>

        {/* Saldo financeiro */}
        <div className="oi-section">
          <h3 className="oi-section-h">Financeiro
            <a className="more" onClick={() => nav.push("financas")}>Detalhes</a>
          </h3>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
            <div className="oi-card tight">
              <small style={{ fontSize: 10, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>
                A receber
              </small>
              <div className="oi-money" style={{ fontSize: 17, fontWeight: 600, color: "var(--ok)" }}>
                {BRL(OPS_KPIS.contas_a_receber)}
              </div>
              <small style={{ fontSize: 11, color: "var(--text-dim)" }}>14 títulos</small>
            </div>
            <div className="oi-card tight">
              <small style={{ fontSize: 10, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>
                A pagar
              </small>
              <div className="oi-money" style={{ fontSize: 17, fontWeight: 600, color: "var(--danger)" }}>
                {BRL(OPS_KPIS.contas_a_pagar)}
              </div>
              <small style={{ fontSize: 11, color: "var(--text-dim)" }}>9 títulos</small>
            </div>
          </div>
        </div>

        <div style={{ height: 24 }} />
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
  const all = ctx.taskState === "vazio" ? TASKS_EMPTY :
              ctx.taskState === "urgente" ? TASKS_URGENT : TASKS_FULL;

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
      {filtered.length > 0 && (
        <div className="oi-fab" onClick={() => alert("Criar tarefa")}>
          <Ic.plus size={26} />
        </div>
      )}
    </>
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
  const t = TASKS_FULL.find(x => x.id === params.id) || TASKS_FULL[0];
  return (
    <>
      <DetailHeader nav={nav} title={t.id.toUpperCase()} eyebrow={t.origin + " · " + (t.cliente || "—")}
                    actions={<button className="oi-iconbtn"><Ic.dotsV /></button>} />
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
            {[
              { t: "12min", who: "Cliente", text: "Enviou nova versão da arte (v3)" },
              { t: "1h",   who: "Você",    text: "Solicitou ajuste no telefone" },
              { t: "Ontem", who: "Sistema", text: "OS criada via orçamento aprovado" },
            ].map((h, i) => (
              <div key={i} style={{ display: "flex", gap: 10, padding: "10px 14px",
                                    borderBottom: i < 2 ? "1px solid var(--border-2)" : 0, fontSize: 12.5 }}>
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
        <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }} onClick={() => nav.pop()}>
          <Ic.clock size={16} /> Adiar
        </button>
        <button className="oi-btn primary" style={{ flex: "1 1 auto" }} onClick={() => nav.pop()}>
          <Ic.check size={18} /> Concluir
        </button>
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, {
  HomeScreen, TarefasScreen, TarefaDetalheScreen,
  ScreenHeader, DetailHeader, HeaderTopRight, OriginBadge, StageStatus,
});

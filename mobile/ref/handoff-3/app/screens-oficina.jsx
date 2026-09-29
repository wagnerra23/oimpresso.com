// ────────────────────────────────────────────────
// OFICINA / MANUTENÇÃO — consulta (Pátio + Status + Prazo) e detalhe da OS
// ────────────────────────────────────────────────
const { Ic } = window;
const M = window.MOCK;
const { DetailHeader, OriginBadge } = window.Screens;
const BRLo = window.BRL;

const MANUT_TONE = { danger: "var(--danger)", warn: "var(--warn)", accent: "var(--accent)", ok: "var(--ok)", muted: "var(--text-mute)" };
const ITEM_STATUS = {
  aplicado:   { label: "Aplicado",   tone: "ok",     next: null,        nextLabel: null },
  aprovado:   { label: "Aprovado",   tone: "accent", next: "aplicado",  nextLabel: "Aplicar" },
  aguardando: { label: "Aguardando aprovação", tone: "warn", next: "aprovado", nextLabel: "Aprovar" },
  comprar:    { label: "Sem estoque", tone: "danger", next: "aprovado",  nextLabel: "Marcar disponível" },
};

function mDur(min) {
  min = Math.round(Math.abs(min));
  if (min < 60) return min + "min";
  const h = Math.floor(min / 60), m = min % 60;
  if (h < 24) return m ? h + "h" + String(m).padStart(2, "0") : h + "h";
  return Math.round(h / 24) + "d";
}
function mDue(os) {
  if (os.status === "Pronto") return { label: os.prazo, tone: "muted", icon: "clock" };
  if (os.dueMin < 0) return { label: "Atrasado " + mDur(os.dueMin), tone: "danger", icon: "alert" };
  if (os.dueMin <= 120) return { label: "Vence em " + mDur(os.dueMin), tone: "warn", icon: "clock" };
  return { label: os.prazo, tone: "muted", icon: "clock" };
}
function statusTone(status) { return (M.MANUT_STATUS[status] || {}).tone || ""; }
function prioBorderM(prio) {
  if (prio === "alta")  return "3px solid var(--danger)";
  if (prio === "media") return "3px solid var(--warn)";
  return "3px solid var(--border)";
}

// Placa estilo Mercosul (mono, contornada)
function Placa({ placa, size = "sm" }) {
  return (
    <span className="oi-mono" style={{
      display: "inline-flex", alignItems: "center",
      fontSize: size === "lg" ? 14 : 11.5, fontWeight: 700, letterSpacing: ".06em",
      padding: size === "lg" ? "3px 10px" : "1px 7px", borderRadius: 5,
      border: "1px solid var(--border)", background: "var(--bg-2)", color: "var(--text)",
    }}>{placa}</span>
  );
}

// ─── Mini-pipeline: barra segmentada do progresso de etapas ───
function MiniPipe({ idx, total, paused }) {
  return (
    <div style={{ display: "flex", gap: 3, flex: 1 }}>
      {Array.from({ length: total }).map((_, i) => (
        <span key={i} style={{
          flex: 1, height: 4, borderRadius: 99,
          background: i < idx ? "var(--ok)" : i === idx ? (paused ? "var(--danger)" : "var(--accent)") : "var(--border)",
        }} />
      ))}
    </div>
  );
}

// ─── Card de OS de manutenção ───
function OsCard({ os, nav }) {
  const due = mDue(os);
  const pend = M.osPendentes(os);
  const mec = M.OFICINA_MECANICOS.find(m => m.id === os.mecanico);
  const sIdx = (M.MANUT_STATUS[os.status] || {}).pipe || 0;
  const chk = (M.OFICINA_CHECKLISTS || {})[os.id] || [];
  const chkDone = chk.filter(c => c.done).length;
  return (
    <div className="oi-card tight" onClick={() => nav.push("manut-os", { id: os.id })}
         style={{ cursor: "pointer", padding: 12, gap: 8, borderLeft: prioBorderM(os.prio) }}>
      <div style={{ display: "flex", alignItems: "center", gap: 7 }}>
        <span className="oi-origin o-OFI">MAN</span>
        <span className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-mute)", fontWeight: 600 }}>{os.id}</span>
        <Placa placa={os.placa} />
        <span style={{ marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: 4,
                       fontSize: 11, fontWeight: 600, fontFamily: "var(--font-mono)", color: MANUT_TONE[due.tone] }}>
          {React.createElement(Ic[due.icon] || Ic.clock, { size: 13 })}{due.label}
        </span>
      </div>

      <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
        <div style={{ flex: 1, minWidth: 0, fontSize: 14, fontWeight: 600, lineHeight: 1.3, letterSpacing: "-0.005em" }}>{os.veiculo}</div>
        <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-dim)", flex: "0 0 auto" }}>{os.km ? (os.km / 1000).toFixed(0) + "k km" : os.tipoVeic.split(" ")[0]}</span>
      </div>

      {/* mini-pipeline + etapa atual */}
      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        <MiniPipe idx={sIdx} total={M.MANUT_PIPE.length} paused={os.status === "Aguardando peça"} />
        <span className={"oi-status " + statusTone(os.status)} style={{ fontSize: 10, flex: "0 0 auto" }}><span className="dot" />{os.status}</span>
      </div>

      <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 11.5, color: "var(--text-dim)", flexWrap: "wrap" }}>
        <span style={{ display: "inline-flex", alignItems: "center", gap: 3 }}>
          {React.createElement(Ic.location, { size: 12, color: "var(--text-mute)" })}{M.localNome(os.local)}
        </span>
        <span style={{ color: "var(--text-mute)" }}>·</span>
        <span style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", maxWidth: 150 }}>{os.cliente}</span>
        {chk.length > 0 && (
          <span style={{ display: "inline-flex", alignItems: "center", gap: 3, color: chkDone === chk.length ? "var(--ok)" : "var(--text-mute)" }}>
            <Ic.checkCircle size={12} /><span className="oi-mono" style={{ fontSize: 10.5 }}>{chkDone}/{chk.length}</span>
          </span>
        )}
      </div>

      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        <span style={{ display: "inline-flex", alignItems: "center", gap: 6, fontSize: 11.5, color: mec ? "var(--text-dim)" : "var(--text-mute)" }}>
          {mec ? (
            <span className={"oi-av " + mec.av} style={{ width: 20, height: 20, borderRadius: "50%", fontSize: 9.5 }}>
              {mec.nome.slice(0, 2).toUpperCase()}
            </span>
          ) : React.createElement(Ic.user, { size: 14 })}
          {mec ? mec.nome : "Sem mecânico"}
        </span>
        <span style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 8 }}>
          {pend > 0 && (
            <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, fontWeight: 600, color: "var(--warn)" }}>
              <Ic.alert size={13} />{pend} p/ aprovar
            </span>
          )}
          <span className="oi-money" style={{ fontSize: 13, fontWeight: 600 }}>{BRLo(M.osTotal(os))}</span>
        </span>
      </div>
    </div>
  );
}

// ─── Triage stat (igual produção) ───
function MTriage({ count, label, tone, active, onClick }) {
  const col = MANUT_TONE[tone];
  return (
    <button onClick={onClick} style={{
      appearance: "none", cursor: "pointer", textAlign: "left", flex: "1 1 0", minWidth: 0,
      background: active ? "color-mix(in oklch, " + col + " 16%, var(--surface))" : "var(--surface)",
      border: "1px solid " + (active ? col : "var(--border)"), borderRadius: 10, padding: "8px 8px",
      display: "flex", flexDirection: "column", gap: 2,
    }}>
      <span style={{ display: "flex", alignItems: "center", gap: 5 }}>
        <span style={{ width: 7, height: 7, borderRadius: 99, background: col, flex: "0 0 auto" }} />
        <span className="oi-mono" style={{ fontSize: 19, fontWeight: 700, lineHeight: 1, color: "var(--text)" }}>{count}</span>
      </span>
      <span style={{ fontSize: 9, fontWeight: 700, letterSpacing: ".03em", textTransform: "uppercase",
                     color: active ? col : "var(--text-mute)", lineHeight: 1.2 }}>{label}</span>
    </button>
  );
}

// ─── Tela principal ───
function ManutencaoScreen({ nav, ctx }) {
  const [view, setView] = React.useState("patio");
  const [q, setQ] = React.useState("");
  const [triage, setTriage] = React.useState("todos");
  const [osMap] = window.useStore("oficina.os", {});
  const all = M.OFICINA_OS.map(o => osMap[o.id] ? { ...o, ...osMap[o.id] } : o);

  const base = all.filter(os => {
    if (!q) return true;
    const hay = (os.placa + " " + os.veiculo + " " + os.id + " " + os.cliente + " " + os.chassi).toLowerCase();
    return hay.includes(q.toLowerCase());
  });

  const isExec = (os) => ["Diagnóstico", "Em execução", "Qualidade"].includes(os.status);
  const cnt = {
    atrasado: base.filter(os => os.dueMin < 0 && os.status !== "Pronto").length,
    aprov:    base.filter(os => os.status === "Aguardando aprovação" || M.osPendentes(os) > 0).length,
    peca:     base.filter(os => os.status === "Aguardando peça").length,
    pronto:   base.filter(os => os.status === "Pronto").length,
  };
  const list = base.filter(os => {
    if (triage === "atrasado") return os.dueMin < 0 && os.status !== "Pronto";
    if (triage === "aprov")    return os.status === "Aguardando aprovação" || M.osPendentes(os) > 0;
    if (triage === "peca")     return os.status === "Aguardando peça";
    if (triage === "pronto")   return os.status === "Pronto";
    return true;
  });

  const ativas = all.filter(os => os.status !== "Pronto").length;
  const atrasadas = all.filter(os => os.dueMin < 0 && os.status !== "Pronto").length;

  const groupBy = (keyFn, order) => {
    const g = {};
    list.forEach(j => { const k = keyFn(j); (g[k] = g[k] || []).push(j); });
    return order.filter(k => g[k] && g[k].length).map(k => ({ k, items: g[k] }));
  };
  const STATUS_ORDER = ["Aguardando peça", "Aguardando aprovação", "Em execução", "Qualidade", "Diagnóstico", "Triagem", "Pronto"];
  const bucketPrazo = (os) => os.status === "Pronto" ? "pronto" : os.dueMin < 0 ? "atrasado" : os.dueMin <= 600 ? "hoje" : "depois";
  const PRAZO_BK = [
    { id: "atrasado", label: "Atrasados", tone: "danger" },
    { id: "hoje", label: "Vence hoje", tone: "warn" },
    { id: "depois", label: "Próximos dias", tone: "muted" },
    { id: "pronto", label: "Prontos / entrega", tone: "ok" },
  ];

  return (
    <>
      <DetailHeader nav={nav} title="Oficina"
                    eyebrow={ativas + " OS ativas" + (atrasadas ? " · " + atrasadas + " atrasadas" : "")}
                    actions={<button className="oi-iconbtn" onClick={() => nav.push("locais")}><Ic.settings size={18} /></button>} />

      <div className="oi-head" style={{ paddingTop: 4, paddingBottom: 10, borderBottom: "1px solid var(--border)" }}>
        <div className="oi-search">
          <Ic.search size={16} />
          <input placeholder="Placa, veículo, OS, cliente, chassi…" value={q} onChange={e => setQ(e.target.value)} />
          {q && <button className="oi-iconbtn" style={{ width: 26, height: 26, background: "transparent" }} onClick={() => setQ("")}><Ic.x size={15} /></button>}
        </div>
        <div style={{ display: "flex", gap: 6, marginTop: 10 }}>
          <MTriage count={cnt.atrasado} label="Atrasadas" tone="danger" active={triage === "atrasado"} onClick={() => setTriage(triage === "atrasado" ? "todos" : "atrasado")} />
          <MTriage count={cnt.aprov} label="Aprovação" tone="warn" active={triage === "aprov"} onClick={() => setTriage(triage === "aprov" ? "todos" : "aprov")} />
          <MTriage count={cnt.peca} label="Aguard. peça" tone="accent" active={triage === "peca"} onClick={() => setTriage(triage === "peca" ? "todos" : "peca")} />
          <MTriage count={cnt.pronto} label="Prontas" tone="ok" active={triage === "pronto"} onClick={() => setTriage(triage === "pronto" ? "todos" : "pronto")} />
        </div>
        <div style={{ display: "flex", gap: 4, marginTop: 10, background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 9, padding: 3 }}>
          {[{ id: "patio", label: "Pátio", ic: "location" },
            { id: "status", label: "Por status", ic: "layers" },
            { id: "prazo", label: "Por prazo", ic: "clock" }].map(v => (
            <button key={v.id} onClick={() => setView(v.id)} style={{
              appearance: "none", cursor: "pointer", flex: 1, display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 5,
              height: 32, borderRadius: 7, border: 0,
              background: view === v.id ? "var(--surface)" : "transparent",
              boxShadow: view === v.id ? "var(--shadow-soft)" : "none",
              color: view === v.id ? "var(--text)" : "var(--text-mute)", fontSize: 12, fontWeight: 600, fontFamily: "inherit",
            }}>{React.createElement(Ic[v.ic], { size: 14 })} {v.label}</button>
          ))}
        </div>
      </div>

      <div className="oi-scroll">
        {triage !== "todos" && (
          <div className="oi-section" style={{ paddingBottom: 0 }}>
            <button onClick={() => setTriage("todos")} style={{ appearance: "none", cursor: "pointer", display: "inline-flex", alignItems: "center", gap: 6,
              background: "var(--bg-2)", border: "1px solid var(--border)", borderRadius: 999, padding: "4px 10px 4px 12px", fontSize: 11.5, color: "var(--text-dim)", fontFamily: "inherit" }}>
              Filtro ativo <Ic.x size={13} />
            </button>
          </div>
        )}

        {/* PÁTIO */}
        {view === "patio" && (
          <PatioView list={list} nav={nav} />
        )}

        {/* STATUS */}
        {view === "status" && (list.length === 0 ? <EmptyM /> :
          groupBy(os => os.status, STATUS_ORDER).map(({ k, items }) => (
            <div className="oi-section" key={k}>
              <h3 className="oi-section-h">
                <span className={"oi-status " + statusTone(k)} style={{ textTransform: "none", letterSpacing: 0 }}><span className="dot" />{k}</span>
                <span style={{ marginLeft: "auto", color: "var(--text-mute)" }}>{items.length}</span>
              </h3>
              <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                {items.map(os => <OsCard key={os.id} os={os} nav={nav} />)}
              </div>
            </div>
          )))}

        {/* PRAZO */}
        {view === "prazo" && (list.length === 0 ? <EmptyM /> :
          groupBy(bucketPrazo, PRAZO_BK.map(b => b.id)).map(({ k, items }) => {
            const b = PRAZO_BK.find(x => x.id === k);
            return (
              <div className="oi-section" key={k}>
                <h3 className="oi-section-h">
                  <span style={{ width: 7, height: 7, borderRadius: 99, background: MANUT_TONE[b.tone] }} />
                  <span style={{ color: b.tone === "muted" ? "var(--text-mute)" : MANUT_TONE[b.tone] }}>{b.label}</span>
                  <span style={{ marginLeft: "auto", color: "var(--text-mute)" }}>{items.length}</span>
                </h3>
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                  {items.map(os => <OsCard key={os.id} os={os} nav={nav} />)}
                </div>
              </div>
            );
          }))}

        <div style={{ height: 24 }} />
      </div>

      <div className="oi-fab" onClick={() => nav.push("nova-manut")}><Ic.plus size={26} /></div>
    </>
  );
}

function EmptyM() {
  return (
    <div className="oi-empty" style={{ marginTop: 24 }}>
      <div className="oi-empty-ico ok"><Ic.checkCircle size={24} /></div>
      <b>Nada por aqui</b>
      <small>Nenhuma OS de manutenção com esse filtro.</small>
    </div>
  );
}

// ─── Pátio: ocupação dos locais ───
function PatioView({ list, nav }) {
  const locais = M.OFICINA_LOCAIS;
  const ocup = (lid) => list.filter(os => os.local === lid && os.status !== "Pronto");
  const naEntrega = (lid) => list.filter(os => os.local === lid && os.status === "Pronto");
  return (
    <div className="oi-section">
      <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
        {locais.map(l => {
          const dentro = list.filter(os => os.local === l.id);
          const ativos = ocup(l.id);
          const usados = dentro.length;
          const cheio = usados >= l.cap;
          const livre = usados === 0;
          return (
            <div key={l.id} className="oi-card" style={{ padding: 12, gap: 10,
                 borderColor: ativos.some(o => o.dueMin < 0) ? "color-mix(in oklch, var(--danger) 45%, var(--border))" : "var(--border)" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                <div style={{ width: 38, height: 38, borderRadius: 10, flex: "0 0 auto",
                              background: livre ? "var(--bg-2)" : "color-mix(in oklch, var(--ok) 20%, transparent)",
                              color: livre ? "var(--text-mute)" : "oklch(from var(--ok) l calc(c + 0.05) h)", display: "grid", placeItems: "center" }}>
                  {React.createElement(Ic[l.ic] || Ic.location, { size: 20 })}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <span style={{ fontSize: 14, fontWeight: 600 }}>{l.nome}</span>
                    {l.cap > 1 && <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{usados}/{l.cap}</span>}
                  </div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{l.obs}</div>
                </div>
                <span className={"oi-status " + (livre ? "ok" : cheio ? "warn" : "")} style={{ fontSize: 10.5 }}>
                  <span className="dot" />{livre ? "Livre" : cheio ? "Cheio" : "Disponível"}
                </span>
              </div>

              {dentro.length > 0 && (
                <div style={{ display: "flex", flexDirection: "column", gap: 6, borderTop: "1px solid var(--border-2)", paddingTop: 8 }}>
                  {dentro.map(os => (
                    <div key={os.id} onClick={() => nav.push("manut-os", { id: os.id })}
                         style={{ display: "flex", alignItems: "center", gap: 8, cursor: "pointer", padding: "2px 0" }}>
                      <Placa placa={os.placa} />
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 12.5, fontWeight: 600, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{os.veiculo}</div>
                        <div style={{ fontSize: 10.5, color: "var(--text-mute)" }}>{os.id} · {M.mecNome(os.mecanico)}</div>
                      </div>
                      <span className={"oi-status " + statusTone(os.status)} style={{ fontSize: 10 }}><span className="dot" />{os.status}</span>
                      <Ic.chevR size={15} color="var(--text-mute)" />
                    </div>
                  ))}
                </div>
              )}
              {livre && (
                <div style={{ borderTop: "1px dashed var(--border)", paddingTop: 8, fontSize: 11.5, color: "var(--text-mute)",
                              display: "flex", alignItems: "center", gap: 6 }}>
                  <Ic.plus size={13} /> Posição livre — pronta para receber veículo
                </div>
              )}
            </div>
          );
        })}
      </div>
      <button className="oi-btn block" style={{ marginTop: 12 }} onClick={() => nav.push("locais")}>
        <Ic.settings size={16} /> Gerenciar locais
      </button>
    </div>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { ManutencaoScreen, OsCard, Placa, mDue, statusTone, MANUT_TONE, ITEM_STATUS, mDur });

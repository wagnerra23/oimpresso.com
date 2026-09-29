// ────────────────────────────────────────────────
// OFICINA — Detalhe da OS de manutenção (itens orgânicos + aprovação + local)
// ────────────────────────────────────────────────
const { Ic: IcO } = window;
const MO = window.MOCK;
const { DetailHeader: DH, OriginBadge: OB } = window.OIUi;
const { Placa: PlacaC, mDue: mDueF, statusTone: stTone, MANUT_TONE: MT, ITEM_STATUS: ITS } = window.OIManut;
const BRLm = window.BRL;

const ADVANCE_ORDER = ["Triagem", "Diagnóstico", "Aguardando aprovação", "Em execução", "Qualidade", "Pronto"];
// Motivos de pausa da execução (quem pausa/retoma: Gerente de oficina ou Admin)
const PAUSA_MOTIVOS = ["Aguardando chegada de peça", "Aguardando aprovação do cliente", "Falta de mecânico disponível", "Aguardando box / elevador", "Fim de expediente"];
function nextStatus(status) {
  let s = status === "Aguardando peça" ? "Em execução" : status;
  const i = ADVANCE_ORDER.indexOf(s);
  return i >= 0 && i < ADVANCE_ORDER.length - 1 ? ADVANCE_ORDER[i + 1] : null;
}

// ─── Histórico/timeline: deriva carimbos de hora plausíveis a partir da abertura ───
const DAY_OFF = { "Anteontem": -2, "Ontem": -1, "Hoje": 0, "Amanhã": 1 };
const OFF_DAY = { "-2": "Anteontem", "-1": "Ontem", "0": "Hoje", "1": "Amanhã" };
function parseWhen(s) {
  if (!s) return 0;
  const [d, t] = s.split(" ");
  const [h, m] = (t || "0:0").split(":").map(Number);
  return (DAY_OFF[d] ?? 0) * 1440 + h * 60 + m;
}
function fmtWhen(min) {
  const d = Math.floor(min / 1440);
  const rem = ((min % 1440) + 1440) % 1440;
  const day = OFF_DAY[String(d)] || (d > 1 ? "+" + d + "d" : d + "d");
  const h = Math.floor(rem / 60), mm = rem % 60;
  return day + " " + String(h).padStart(2, "0") + ":" + String(mm).padStart(2, "0");
}
// Notas por etapa do pipeline (índice 0..5)
function stageNote(os, i) {
  if (i === 0) return "Veículo recebido — " + os.tipoManut.toLowerCase();
  if (i === 1) return os.diagnostico || "Inspeção e leitura de falhas";
  if (i === 2) {
    const pend = (os.itens || []).filter(x => x.status === "aguardando").length;
    return pend > 0 ? pend + (pend === 1 ? " item enviado ao cliente" : " itens enviados ao cliente") : "Orçamento liberado";
  }
  if (i === 3) return "Mão de obra e peças em andamento";
  if (i === 4) return "Conferência e teste final";
  return "Liberado para entrega";
}
function buildTimeline(os) {
  const pipe = MO.MANUT_PIPE; // 6 etapas
  const cur = (MO.MANUT_STATUS[os.status] || {}).pipe || 0;
  const base = parseWhen(os.abertura);
  const offs = [0, 55, 140, 210, 320, 410]; // min acumulados por etapa
  const mecNome = (MO.OFICINA_MECANICOS.find(m => m.id === os.mecanico) || {}).nome;
  return pipe.map((stage, i) => {
    const done = i < cur, active = i === cur;
    const paused = active && (os.status === "Aguardando peça" || os.pausado);
    return {
      stage, done, active, paused,
      at: (done || active) ? fmtWhen(base + offs[i]) : null,
      mec: (done || active) && i >= 1 ? mecNome : null,
      nota: (done || active) ? stageNote(os, i) : null,
    };
  });
}

function ManutOsDetalheScreen({ nav, ctx, params }) {
  // OS compartilhada via store — mudanças refletem na consulta do pátio e persistem
  const [osMap, setOsMap] = window.useStore("oficina.os", {});
  const baseOs = MO.OFICINA_OS.find(x => x.id === params.id) || MO.OFICINA_OS[0];
  const freshOs = () => ({ ...baseOs, itens: baseOs.itens.map(i => ({ ...i })) });
  const os = osMap[baseOs.id] || freshOs();
  const setOs = (next) => setOsMap(m => {
    const cur = m[baseOs.id] || freshOs();
    return { ...m, [baseOs.id]: typeof next === "function" ? next(cur) : next };
  });
  const [check, setCheck] = React.useState(() => {
    const f = MO.OFICINA_OS.find(x => x.id === params.id) || MO.OFICINA_OS[0];
    return ((MO.OFICINA_CHECKLISTS || {})[f.id] || []).map(c => ({ ...c }));
  });
  const [newCheck, setNewCheck] = React.useState("");
  const [sheet, setSheet] = React.useState(null); // 'add' | 'local' | 'mec' | 'faturar'
  const [flash, setFlash] = React.useState(null); // id do item recém-adicionado
  const [parcOs, setParcOs] = React.useState(1);
  const [meioOs, setMeioOs] = React.useState("Boleto");
  const fatOs = window.OIFlow.faturamentoDe(os.id);

  const pipe = MO.MANUT_PIPE; // ["Triagem","Diagnóstico","Aprovação","Execução","Qualidade","Pronto"]
  const sIdx = (MO.MANUT_STATUS[os.status] || {}).pipe || 0;
  const due = mDueF(os);
  const mec = MO.OFICINA_MECANICOS.find(m => m.id === os.mecanico);
  const local = MO.OFICINA_LOCAIS.find(l => l.id === os.local);
  const pend = os.itens.filter(i => i.status === "aguardando");
  const totServ = os.itens.filter(i => i.tipo === "servico").reduce((s, i) => s + i.preco * i.qty, 0);
  const totPec = os.itens.filter(i => i.tipo === "peca").reduce((s, i) => s + i.preco * i.qty, 0);
  const totGeral = totServ + totPec;
  const totAprov = os.itens.filter(i => i.status === "aplicado" || i.status === "aprovado").reduce((s, i) => s + i.preco * i.qty, 0);

  const addItem = (cat, qty) => {
    const st = cat.tipo === "peca" && cat.estoque === 0 ? "comprar" : "aguardando";
    const novo = { id: "i" + Date.now(), ref: cat.id, nome: cat.nome, tipo: cat.tipo, qty, un: cat.un, preco: cat.preco, status: st };
    setOs(o => ({ ...o, itens: [...o.itens, novo] }));
    setFlash(novo.id);
    setSheet(null);
    setTimeout(() => setFlash(null), 1600);
  };
  const setItemStatus = (id, status) => setOs(o => ({ ...o, itens: o.itens.map(i => i.id === id ? { ...i, status } : i) }));
  const removeItem = (id) => setOs(o => ({ ...o, itens: o.itens.filter(i => i.id !== id) }));
  const aprovarTodos = () => setOs(o => ({ ...o, itens: o.itens.map(i => i.status === "aguardando" ? { ...i, status: "aprovado" } : i) }));
  const advance = () => { const n = nextStatus(os.status); if (n) setOs(o => ({ ...o, status: n })); };
  // Faturar OS finalizada → cria título+parcelas no financeiro (itens aprovados/aplicados)
  const confirmarFaturamentoOs = () => {
    const r = window.OIFlow.faturar({
      origemId: os.id, parte: os.cliente, desc: os.veiculo + " — " + os.tipoManut,
      valor: totAprov, parcelas: parcOs, primeiroVencDias: 7, meio: meioOs,
      categoria: "Serviços de oficina",
    });
    setSheet(null);
    if (!r.ok) { window.oiToast("OS já faturada", "warn"); return; }
    window.oiToast(parcOs > 1 ? `Faturado em ${parcOs}× · títulos no financeiro` : "Faturado · título no financeiro", "ok");
  };
  const ns = nextStatus(os.status);
  const toggleCheck = (id) => setCheck(cs => cs.map(c => c.id === id ? { ...c, done: !c.done } : c));
  const addCheck = () => {
    const t = newCheck.trim();
    if (!t) return;
    setCheck(cs => [...cs, { id: "c" + Date.now(), label: t, done: false }]);
    setNewCheck("");
  };
  const checkDone = check.filter(c => c.done).length;
  const timeline = buildTimeline(os);

  // ─── Pausar / retomar execução (autorização: Gerente de oficina ou Admin) ───
  const paused = os.status === "Aguardando peça" || !!os.pausado;
  const motivoPausa = os.pausaMotivo || (os.status === "Aguardando peça" ? "Aguardando chegada de peça" : null);
  const podeAutorizar = ((ctx && ctx.papel) || "gerente") !== "operador";
  const semPermissao = () => window.oiToast && window.oiToast("Sem permissão — apenas Gerente de oficina ou Admin pausam/retomam", "danger");
  const pedirPausa = () => { if (!podeAutorizar) return semPermissao(); setSheet("pausa"); };
  const pausar = (motivo) => { setOs(o => ({ ...o, status: o.status === "Aguardando peça" ? "Em execução" : o.status, pausado: true, pausaMotivo: motivo })); setSheet(null); window.oiToast && window.oiToast("Execução pausada"); };
  const retomar = () => { if (!podeAutorizar) return semPermissao(); setOs(o => ({ ...o, status: o.status === "Aguardando peça" ? "Em execução" : o.status, pausado: false, pausaMotivo: null })); window.oiToast && window.oiToast("Execução retomada", "ok"); };

  return (
    <>
      <DH nav={nav} title={os.id} eyebrow={"Manutenção · " + os.placa} />
      <div className="oi-scroll">
        {/* Hero do veículo */}
        <div className="oi-section">
          <div className="oi-card pad" style={{ gap: 12 }}>
            <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
              <div style={{ width: 46, height: 46, borderRadius: 12, flex: "0 0 auto", background: "var(--origin-OFI-bg)",
                            color: "var(--origin-OFI-fg)", display: "grid", placeItems: "center" }}>
                <IcO.truck size={26} />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 17, fontWeight: 600, letterSpacing: "-0.01em", lineHeight: 1.2 }}>{os.veiculo}</div>
                <div style={{ fontSize: 12, color: "var(--text-dim)" }}>{os.tipoVeic} · {os.ano} · {os.cor}</div>
              </div>
              <PlacaC placa={os.placa} size="lg" />
            </div>

            <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
              <span className={"oi-status " + (paused ? "danger" : stTone(os.status))}><span className="dot" />{paused ? "Pausado" : os.status}</span>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 12, fontWeight: 600,
                             fontFamily: "var(--font-mono)", color: MT[due.tone] }}>
                {React.createElement(IcO[due.icon] || IcO.clock, { size: 14 })}{due.label}
              </span>
              <span style={{ marginLeft: "auto", fontSize: 11, fontWeight: 700, letterSpacing: ".04em", textTransform: "uppercase",
                             color: os.prio === "alta" ? "var(--danger)" : os.prio === "media" ? "var(--warn)" : "var(--text-mute)" }}>
                {os.tipoManut} · {os.prio}
              </span>
            </div>

            {/* dados do veículo */}
            <dl className="oi-dl" style={{ borderTop: "1px solid var(--border-2)", paddingTop: 10 }}>
              <dt>Chassi</dt>      <dd className="oi-mono" style={{ fontSize: 12 }}>{os.chassi}</dd>
              <dt>Renavam</dt>     <dd className="oi-mono" style={{ fontSize: 12 }}>{os.renavam}</dd>
              <dt>Hodômetro</dt>   <dd className="oi-mono">{os.km.toLocaleString("pt-BR")} km</dd>
              <dt>Frota</dt>       <dd>{os.frota}</dd>
              <dt>Cliente</dt>     <dd>{os.cliente}</dd>
              <dt>Motorista</dt>   <dd>{os.motorista}</dd>
            </dl>
          </div>
        </div>

        {/* Local + mecânico (editáveis) */}
        <div className="oi-section">
          <div style={{ display: "flex", gap: 8 }}>
            <button className="oi-card tight" onClick={() => setSheet("local")}
                    style={{ flex: 1, cursor: "pointer", padding: 12, gap: 4, textAlign: "left", alignItems: "flex-start" }}>
              <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Local</div>
              <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 14, fontWeight: 600 }}>
                {React.createElement(IcO[(local || {}).ic] || IcO.location, { size: 16, color: "var(--accent)" })}
                {local ? local.nome : "Definir"}
                <IcO.chevD size={14} color="var(--text-mute)" />
              </div>
            </button>
            <button className="oi-card tight" onClick={() => setSheet("mec")}
                    style={{ flex: 1, cursor: "pointer", padding: 12, gap: 4, textAlign: "left", alignItems: "flex-start" }}>
              <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)" }}>Mecânico</div>
              <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 14, fontWeight: 600 }}>
                {mec ? (
                  <span className={"oi-av " + mec.av} style={{ width: 20, height: 20, borderRadius: "50%", fontSize: 9.5 }}>{mec.nome.slice(0, 2).toUpperCase()}</span>
                ) : <IcO.user size={16} color="var(--text-mute)" />}
                {mec ? mec.nome : "Atribuir"}
                <IcO.chevD size={14} color="var(--text-mute)" />
              </div>
            </button>
          </div>
        </div>

        {/* Andamento — timeline vertical com histórico */}
        <div className="oi-section">
          <h3 className="oi-section-h">Andamento e histórico</h3>
          <div className="oi-card pad" style={{ gap: 0 }}>
            {timeline.map((t, i) => {
              const last = i === timeline.length - 1;
              const dotBg = t.done ? "var(--ok)" : t.paused ? "var(--danger)" : t.active ? "var(--accent)" : "var(--bg-2)";
              const dotBd = t.done ? "var(--ok)" : t.paused ? "var(--danger)" : t.active ? "var(--accent)" : "var(--border)";
              return (
                <div key={t.stage} style={{ display: "flex", gap: 11 }}>
                  {/* trilho */}
                  <div style={{ display: "flex", flexDirection: "column", alignItems: "center", flex: "0 0 auto" }}>
                    <div style={{ width: 26, height: 26, borderRadius: 99, background: dotBg, border: "2px solid " + dotBd,
                                  color: t.done || t.active || t.paused ? "#fff" : "var(--text-mute)",
                                  display: "grid", placeItems: "center", fontSize: 11, fontWeight: 700, flex: "0 0 auto" }}>
                      {t.done ? <IcO.check size={13} color="#fff" strokeWidth={3} />
                        : t.paused ? <IcO.alert size={14} color="#fff" />
                        : (i + 1)}
                    </div>
                    {!last && <div style={{ width: 2, flex: 1, minHeight: 22, background: t.done ? "var(--ok)" : "var(--border)", margin: "3px 0" }} />}
                  </div>
                  {/* conteúdo */}
                  <div style={{ flex: 1, minWidth: 0, paddingBottom: last ? 0 : 14 }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                      <span style={{ fontSize: 13.5, fontWeight: t.active || t.paused ? 700 : 600,
                                     color: t.active ? "var(--accent)" : t.paused ? "var(--danger)" : t.done ? "var(--text)" : "var(--text-mute)" }}>{t.stage}</span>
                      {t.active && !t.paused && <span className="oi-status accent" style={{ fontSize: 9.5 }}><span className="dot" />Em andamento</span>}
                      {t.paused && <span className="oi-status danger" style={{ fontSize: 9.5 }}><span className="dot" />Pausado</span>}
                      {t.at && <span className="oi-mono" style={{ marginLeft: "auto", fontSize: 10.5, color: "var(--text-mute)" }}>{t.at}</span>}
                    </div>
                    {t.nota && <div style={{ fontSize: 11.5, color: "var(--text-dim)", lineHeight: 1.35, marginTop: 2 }}>{t.nota}</div>}
                    {t.mec && (
                      <div style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 11, color: "var(--text-mute)", marginTop: 3 }}>
                        <IcO.user size={11} /> {t.mec}
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
            {paused && (
              <div className="oi-hint" style={{ marginTop: 4, background: "color-mix(in oklch, var(--danger) 14%, transparent)", color: "var(--danger)" }}>
                <IcO.alert size={14} /> Execução pausada{motivoPausa ? " — " + motivoPausa.toLowerCase() : ""}. {podeAutorizar ? "Toque em Retomar para continuar." : "Retomada requer Gerente de oficina."}
              </div>
            )}
          </div>
        </div>

        {/* Checklist de serviço — X/Y, marcável */}
        <div className="oi-section">
          <h3 className="oi-section-h">
            Checklist de serviço
            {check.length > 0 && (
              <span style={{ marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: 6, fontWeight: 600, textTransform: "none", letterSpacing: 0 }}>
                <span className="oi-mono" style={{ fontSize: 12, color: checkDone === check.length ? "var(--ok)" : "var(--text-dim)" }}>{checkDone}/{check.length}</span>
              </span>
            )}
          </h3>
          <div className="oi-card pad" style={{ gap: 0 }}>
            {check.length > 0 && (
              <div className="oi-progress" style={{ height: 5, marginBottom: 10 }}>
                <i style={{ width: (check.length ? (checkDone / check.length) * 100 : 0) + "%", background: checkDone === check.length ? "var(--ok)" : "var(--accent)" }} />
              </div>
            )}
            {check.map((c, i) => (
              <button key={c.id} onClick={() => toggleCheck(c.id)}
                      style={{ appearance: "none", border: 0, background: "transparent", textAlign: "left", cursor: "pointer",
                               display: "flex", alignItems: "center", gap: 10, padding: "9px 0",
                               borderBottom: i < check.length - 1 ? "1px solid var(--border-2)" : 0, width: "100%" }}>
                <span style={{ width: 22, height: 22, borderRadius: 6, flex: "0 0 auto", display: "grid", placeItems: "center",
                               background: c.done ? "var(--ok)" : "transparent",
                               border: "2px solid " + (c.done ? "var(--ok)" : "var(--border)") }}>
                  {c.done && <IcO.check size={13} color="#fff" strokeWidth={3} />}
                </span>
                <span style={{ flex: 1, fontSize: 13, lineHeight: 1.3, fontWeight: 500,
                               color: c.done ? "var(--text-mute)" : "var(--text)",
                               textDecoration: c.done ? "line-through" : "none" }}>{c.label}</span>
              </button>
            ))}
            {check.length === 0 && (
              <div style={{ fontSize: 12, color: "var(--text-mute)", padding: "4px 0 10px" }}>
                Nenhum passo ainda — registre o roteiro de serviço conforme avança.
              </div>
            )}
            {/* adicionar passo */}
            <div style={{ display: "flex", gap: 8, alignItems: "center", marginTop: 10, paddingTop: 10, borderTop: "1px solid var(--border-2)" }}>
              <div className="oi-search" style={{ flex: 1, height: 38 }}>
                <IcO.plus size={15} />
                <input placeholder="Adicionar passo ao checklist" value={newCheck}
                       onChange={e => setNewCheck(e.target.value)}
                       onKeyDown={e => { if (e.key === "Enter") addCheck(); }} />
              </div>
              <button className="oi-btn sm primary" onClick={addCheck} disabled={!newCheck.trim()}
                      style={{ height: 38, opacity: newCheck.trim() ? 1 : 0.5 }}>Add</button>
            </div>
          </div>
        </div>

        {/* Sintomas / diagnóstico */}
        <div className="oi-section">
          <h3 className="oi-section-h">Relato e diagnóstico</h3>
          <div className="oi-card pad" style={{ gap: 12 }}>
            <div>
              <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)", marginBottom: 4 }}>Relato do motorista</div>
              <div style={{ fontSize: 13, lineHeight: 1.45 }}>{os.sintomas}</div>
            </div>
            <div style={{ borderTop: "1px solid var(--border-2)", paddingTop: 10 }}>
              <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)", marginBottom: 4 }}>Diagnóstico técnico</div>
              <div style={{ fontSize: 13, lineHeight: 1.45, color: os.diagnostico ? "var(--text)" : "var(--text-mute)" }}>
                {os.diagnostico || "Pendente — registrar após inspeção."}
              </div>
            </div>
          </div>
        </div>

        {/* ITENS — adição orgânica */}
        <div className="oi-section">
          <h3 className="oi-section-h">
            Peças e serviços
            <span style={{ marginLeft: "auto", color: "var(--text-mute)", fontWeight: 500, textTransform: "none", letterSpacing: 0 }}>{os.itens.length} {os.itens.length === 1 ? "item" : "itens"}</span>
          </h3>

          {pend.length > 0 && (
            <div className="oi-card" style={{ flexDirection: "row", alignItems: "center", gap: 10, padding: 12, marginBottom: 8,
                 background: "color-mix(in oklch, var(--warn) 12%, var(--surface))", borderColor: "color-mix(in oklch, var(--warn) 40%, var(--border))" }}>
              <IcO.alert size={18} color="var(--warn)" />
              <div style={{ flex: 1, fontSize: 12, lineHeight: 1.35 }}>
                <b style={{ color: "var(--warn)" }}>{pend.length} {pend.length === 1 ? "item aguarda" : "itens aguardam"} aprovação</b>
                <div style={{ color: "var(--text-dim)" }}>{BRLm(pend.reduce((s, i) => s + i.preco * i.qty, 0))} — surgiu durante o serviço</div>
              </div>
              <button className="oi-btn sm primary" onClick={aprovarTodos}><IcO.check size={14} /> Aprovar</button>
            </div>
          )}

          <div className="oi-list card">
            {os.itens.length === 0 && (
              <div style={{ padding: "20px 16px", textAlign: "center", color: "var(--text-mute)", fontSize: 12.5 }}>
                Nenhum item ainda. Adicione peças e serviços conforme o diagnóstico.
              </div>
            )}
            {os.itens.map(i => {
              const meta = ITS[i.status];
              return (
                <div key={i.id} className="oi-list-row" style={{ alignItems: "center", gap: 10,
                     background: flash === i.id ? "var(--accent-soft)" : "transparent", transition: "background .4s" }}>
                  <div style={{ width: 30, height: 30, borderRadius: 8, flex: "0 0 auto", display: "grid", placeItems: "center",
                                background: i.tipo === "servico" ? "color-mix(in oklch, var(--accent) 16%, transparent)" : "var(--bg-2)",
                                color: i.tipo === "servico" ? "var(--accent)" : "var(--text-dim)", border: "1px solid var(--border)" }}>
                    {React.createElement(i.tipo === "servico" ? IcO.wrench : IcO.box, { size: 15 })}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13, fontWeight: 600, lineHeight: 1.25 }}>{i.nome}</div>
                    <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 2 }}>
                      <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{i.qty} {i.un} · {BRLm(i.preco)}</span>
                      <span className={"oi-status " + meta.tone} style={{ fontSize: 9.5, padding: "1px 6px" }}>{meta.label}</span>
                    </div>
                  </div>
                  <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4, flex: "0 0 auto" }}>
                    <span className="oi-money" style={{ fontSize: 13, fontWeight: 600 }}>{BRLm(i.preco * i.qty)}</span>
                    {meta.next ? (
                      <button className="oi-btn sm" onClick={() => setItemStatus(i.id, meta.next)}
                              style={{ height: 26, padding: "0 10px", borderColor: MT[meta.tone], color: MT[meta.tone] }}>
                        {meta.nextLabel}
                      </button>
                    ) : (
                      <IcO.checkCircle size={16} color="var(--ok)" />
                    )}
                  </div>
                  {i.status === "aguardando" && (
                    <button onClick={() => removeItem(i.id)} style={{ appearance: "none", border: 0, background: "transparent",
                            color: "var(--text-mute)", cursor: "pointer", padding: 2, flex: "0 0 auto" }}>
                      <IcO.x size={15} />
                    </button>
                  )}
                </div>
              );
            })}
          </div>

          <button className="oi-btn block action" style={{ marginTop: 8 }} onClick={() => setSheet("add")}>
            <IcO.plus size={18} /> Adicionar peça ou serviço
          </button>

          {/* Totais */}
          <div className="oi-card pad" style={{ gap: 7, marginTop: 8 }}>
            <div style={{ display: "flex", fontSize: 12.5, color: "var(--text-dim)" }}>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 5 }}><IcO.wrench size={13} /> Serviços</span>
              <span className="oi-money" style={{ marginLeft: "auto" }}>{BRLm(totServ)}</span>
            </div>
            <div style={{ display: "flex", fontSize: 12.5, color: "var(--text-dim)" }}>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 5 }}><IcO.box size={13} /> Peças</span>
              <span className="oi-money" style={{ marginLeft: "auto" }}>{BRLm(totPec)}</span>
            </div>
            <div style={{ display: "flex", fontSize: 12, color: "var(--ok)" }}>
              <span>Aprovado</span>
              <span className="oi-money" style={{ marginLeft: "auto" }}>{BRLm(totAprov)}</span>
            </div>
            <div style={{ height: 1, background: "var(--border-2)", margin: "3px 0" }} />
            <div style={{ display: "flex", alignItems: "baseline" }}>
              <span style={{ fontSize: 13, fontWeight: 600 }}>Total da OS</span>
              <span className="oi-money" style={{ marginLeft: "auto", fontSize: 21, fontWeight: 600, letterSpacing: "-0.02em" }}>{BRLm(totGeral)}</span>
            </div>
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>

      {/* Barra de ação — avançar status */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        {os.status === "Pronto" ? (
          fatOs.faturado ? (
            <button className="oi-btn action block" onClick={() => nav.pop()}><IcO.truck size={18} /> Entregar veículo</button>
          ) : (
            <button className="oi-btn primary block" onClick={() => setSheet("faturar")}>
              {React.createElement(IcO.dollar || IcO.check, { size: 18 })} Faturar OS · {BRLm(totAprov)}
            </button>
          )
        ) : paused ? (
          <>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }} onClick={() => nav.push("cliente", {})}>
              <IcO.whatsapp size={16} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }} onClick={retomar}>
              {React.createElement(podeAutorizar ? IcO.play : IcO.lock, { size: 17 })} Retomar execução
            </button>
          </>
        ) : (
          <>
            {os.status === "Em execução" && (
              <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 13px" }} onClick={pedirPausa} title="Pausar execução">
                {React.createElement(podeAutorizar ? IcO.pause : IcO.lock, { size: 16 })}
              </button>
            )}
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 13px" }} onClick={() => nav.push("cliente", {})}>
              <IcO.whatsapp size={16} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }} onClick={advance}>
              <IcO.chevR size={18} /> Avançar para {ns}
            </button>
          </>
        )}
      </div>

      {sheet === "add" && <AddItemSheet onClose={() => setSheet(null)} onAdd={addItem} />}      {sheet === "faturar" && (
        <window.OISheet title="Faturar OS" onClose={() => setSheet(null)}
          footer={<button className="oi-btn primary block" onClick={confirmarFaturamentoOs}>{React.createElement(IcO.checkCircle || IcO.check, { size: 18 })} Confirmar faturamento</button>}>
          <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
              <span style={{ fontSize: 12.5, color: "var(--text-mute)" }}>Total aprovado da OS</span>
              <span className="oi-mono" style={{ fontSize: 19, fontWeight: 700 }}>{BRLm(totAprov)}</span>
            </div>
            <div>
              <div style={{ fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".05em", color: "var(--text-mute)", marginBottom: 7 }}>Parcelas</div>
              <div style={{ display: "flex", gap: 6 }}>
                {[1, 2, 3, 6].map(n => (
                  <button key={n} onClick={() => setParcOs(n)}
                          style={{ flex: 1, height: 38, borderRadius: 8, cursor: "pointer", fontFamily: "inherit", fontSize: 13, fontWeight: 700,
                                   border: "1px solid " + (parcOs === n ? "var(--accent)" : "var(--border)"),
                                   background: parcOs === n ? "var(--accent)" : "var(--bg-2)", color: parcOs === n ? "#fff" : "var(--text)" }}>{n}×</button>
                ))}
              </div>
            </div>
            <div>
              <div style={{ fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".05em", color: "var(--text-mute)", marginBottom: 7 }}>Meio</div>
              <div style={{ display: "flex", gap: 6 }}>
                {["Boleto", "PIX", "Cartão"].map(mp => (
                  <button key={mp} onClick={() => setMeioOs(mp)}
                          style={{ flex: 1, height: 38, borderRadius: 8, cursor: "pointer", fontFamily: "inherit", fontSize: 12.5, fontWeight: 600,
                                   border: "1px solid " + (meioOs === mp ? "var(--accent)" : "var(--border)"),
                                   background: meioOs === mp ? "var(--accent-soft)" : "var(--bg-2)", color: meioOs === mp ? "var(--accent)" : "var(--text-dim)" }}>{mp}</button>
                ))}
              </div>
            </div>
            <div style={{ background: "var(--bg-2)", borderRadius: 10, padding: "10px 12px" }}>
              <div style={{ fontSize: 11, color: "var(--text-mute)", marginBottom: 6 }}>Prévia das parcelas</div>
              {window.OIFlow.gerarParcelas(totAprov, parcOs, 7).map(pp => (
                <div key={pp.numero} style={{ display: "flex", justifyContent: "space-between", padding: "3px 0", fontSize: 12.5 }}>
                  <span style={{ color: "var(--text-dim)" }}>{parcOs > 1 ? pp.numero + "ª parcela" : "À vista"} · <span style={{ fontFamily: "var(--font-mono)", color: "var(--text-mute)" }}>{pp.vencLabel}</span></span>
                  <span className="oi-mono" style={{ fontWeight: 700 }}>{BRLm(pp.valor)}</span>
                </div>
              ))}
            </div>
          </div>
        </window.OISheet>
      )}

      {sheet === "pausa" && <PausaSheet onClose={() => setSheet(null)} onPick={pausar} />}
      {sheet === "local" && <LocalSheet current={os.local} onClose={() => setSheet(null)} onPick={(id) => { setOs(o => ({ ...o, local: id })); setSheet(null); }} />}
      {sheet === "mec" && <MecSheet current={os.mecanico} onClose={() => setSheet(null)} onPick={(id) => { setOs(o => ({ ...o, mecanico: id })); setSheet(null); }} />}
    </>
  );
}

// ─── Sheet: adicionar item do catálogo ───
function AddItemSheet({ onClose, onAdd }) {
  const [tab, setTab] = React.useState("peca");
  const [q, setQ] = React.useState("");
  const list = MO.OFICINA_CATALOGO.filter(c => c.tipo === tab && (!q || (c.nome + " " + (c.sku || "")).toLowerCase().includes(q.toLowerCase())));
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h"><b>Adicionar à OS</b><button className="oi-iconbtn close" onClick={onClose}><IcO.x /></button></div>
        <div style={{ padding: "12px 16px 8px", display: "flex", flexDirection: "column", gap: 10 }}>
          <div className="oi-seg">
            <button className={tab === "peca" ? "on" : ""} onClick={() => setTab("peca")}><IcO.box size={15} /> Peças</button>
            <button className={tab === "servico" ? "on" : ""} onClick={() => setTab("servico")}><IcO.wrench size={15} /> Serviços</button>
          </div>
          <div className="oi-search">
            <IcO.search size={16} />
            <input placeholder={tab === "peca" ? "Nome ou SKU da peça" : "Buscar serviço"} value={q} onChange={e => setQ(e.target.value)} />
          </div>
        </div>
        <div className="oi-list" style={{ borderTop: 0, borderBottom: 0 }}>
          {list.map(c => (
            <div key={c.id} className="oi-list-row" onClick={() => onAdd(c, 1)}>
              <div style={{ width: 32, height: 32, borderRadius: 8, flex: "0 0 auto", display: "grid", placeItems: "center",
                            background: c.tipo === "servico" ? "color-mix(in oklch, var(--accent) 16%, transparent)" : "var(--bg-2)",
                            color: c.tipo === "servico" ? "var(--accent)" : "var(--text-dim)", border: "1px solid var(--border)" }}>
                {React.createElement(c.tipo === "servico" ? IcO.wrench : IcO.box, { size: 15 })}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{c.nome}</div>
                <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>
                  {c.tipo === "peca"
                    ? (c.sku + " · " + (c.estoque > 0 ? c.estoque + " em estoque" : "sem estoque"))
                    : (c.horas + "h de mão de obra")}
                </div>
              </div>
              <div style={{ textAlign: "right" }}>
                <div className="oi-money" style={{ fontSize: 13, fontWeight: 600 }}>{BRLm(c.preco)}</div>
                <div style={{ display: "inline-flex", alignItems: "center", gap: 3, fontSize: 11, color: "var(--action)", fontWeight: 600 }}>
                  <IcO.plus size={13} /> Adicionar
                </div>
              </div>
            </div>
          ))}
        </div>
        <div style={{ padding: "8px 16px 4px", fontSize: 11, color: "var(--text-mute)", textAlign: "center" }}>
          Itens novos entram como “aguardando aprovação” do cliente.
        </div>
      </div>
    </div>
  );
}

// ─── Sheet: motivo da pausa ───
function PausaSheet({ onClose, onPick }) {
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h"><b>Pausar execução</b><button className="oi-iconbtn close" onClick={onClose}><IcO.x /></button></div>
        <div style={{ padding: "4px 16px 6px", fontSize: 11.5, color: "var(--text-mute)" }}>
          Selecione o motivo. A pausa fica registrada no histórico da OS.
        </div>
        <div className="oi-list" style={{ borderTop: 0, borderBottom: 0 }}>
          {PAUSA_MOTIVOS.map(m => (
            <div key={m} className="oi-list-row" onClick={() => onPick(m)}>
              <div style={{ width: 30, height: 30, borderRadius: 8, flex: "0 0 auto", display: "grid", placeItems: "center",
                            background: "color-mix(in oklch, var(--danger) 14%, transparent)", color: "var(--danger)", border: "1px solid var(--border)" }}>
                <IcO.pause size={15} />
              </div>
              <div style={{ flex: 1, fontSize: 13.5, fontWeight: 600 }}>{m}</div>
              <IcO.chevR size={15} color="var(--text-mute)" />
            </div>
          ))}
        </div>
        <div style={{ padding: "8px 16px 4px", fontSize: 11, color: "var(--text-mute)", textAlign: "center" }}>
          Autorização: Gerente de oficina ou Administrador.
        </div>
      </div>
    </div>
  );
}

// ─── Sheet: mover veículo de local ───
function LocalSheet({ current, onClose, onPick }) {
  const ocupacao = (lid) => MO.OFICINA_OS.filter(o => o.local === lid && o.status !== "Pronto").length;
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h"><b>Mover veículo</b><button className="oi-iconbtn close" onClick={onClose}><IcO.x /></button></div>
        <div className="oi-list" style={{ borderTop: 0, borderBottom: 0 }}>
          {MO.OFICINA_LOCAIS.map(l => {
            const usados = ocupacao(l.id);
            const cheio = usados >= l.cap && current !== l.id;
            return (
              <div key={l.id} className="oi-list-row" onClick={() => onPick(l.id)}
                   style={{ opacity: cheio ? 0.5 : 1 }}>
                <div style={{ width: 32, height: 32, borderRadius: 8, flex: "0 0 auto", display: "grid", placeItems: "center",
                              background: current === l.id ? "var(--accent)" : "var(--bg-2)",
                              color: current === l.id ? "#fff" : "var(--text-dim)", border: "1px solid var(--border)" }}>
                  {React.createElement(IcO[l.ic] || IcO.location, { size: 16 })}
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 13.5, fontWeight: 600 }}>{l.nome}</div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{l.obs}</div>
                </div>
                {l.cap > 1
                  ? <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{usados}/{l.cap}</span>
                  : <span className={"oi-status " + (usados === 0 || current === l.id ? "ok" : "warn")} style={{ fontSize: 10 }}><span className="dot" />{usados === 0 || current === l.id ? "Livre" : "Ocupado"}</span>}
                {current === l.id && <IcO.check size={16} color="var(--accent)" />}
              </div>
            );
          })}
        </div>
        <div style={{ height: 12 }} />
      </div>
    </div>
  );
}

// ─── Sheet: atribuir mecânico ───
function MecSheet({ current, onClose, onPick }) {
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h"><b>Atribuir mecânico</b><button className="oi-iconbtn close" onClick={onClose}><IcO.x /></button></div>
        <div className="oi-list" style={{ borderTop: 0, borderBottom: 0 }}>
          {MO.OFICINA_MECANICOS.map(m => (
            <div key={m.id} className="oi-list-row" onClick={() => onPick(m.id)}>
              <span className={"oi-av " + m.av} style={{ width: 34, height: 34, borderRadius: "50%", fontSize: 12 }}>{m.nome.slice(0, 2).toUpperCase()}</span>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 13.5, fontWeight: 600 }}>{m.nome}</div>
                <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{m.esp}</div>
              </div>
              {current === m.id && <IcO.check size={16} color="var(--accent)" />}
            </div>
          ))}
        </div>
        <div style={{ height: 12 }} />
      </div>
    </div>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { ManutOsDetalheScreen });

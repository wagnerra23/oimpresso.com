// oficina-page.jsx — Oficina Auto (vertical) embedada no shell unificado.
// Migrado de "Produção Oficina - Tela.html". CSS em oficina-page.css.
// IIFE encapsula tudo, expõe window.OficinaPage.
(() => {
const { useState, useMemo } = React;

// ── Inline icons (subset, sized for cards) ──
const Ico = {
  car:    (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 17h14M3 13l2-5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2l2 5"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>,
  gauge:  (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 13l4-3"/><path d="M12 5V3"/></svg>,
  wrench: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14.7 6.3a4 4 0 0 0 5.4 5.4L21 12l-9 9-2-2 9-9-1.7-1.7a4 4 0 0 0-5.4-5.4L13 5l-9 9 2 2 9-9-.3-.7Z"/></svg>,
  box:    (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>,
  clock:  (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>,
  check:  (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 13l4 4 10-10"/></svg>,
  x:      (p) => <svg width={p.size||14} height={p.size||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 6l12 12M6 18L18 6"/></svg>,
  plus:   (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14"/></svg>,
  grid:   (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>,
  list:   (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>,
  print:  (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="9" rx="2"/><path d="M6 14h12v7H6z"/></svg>,
  camera: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 8h4l2-3h6l2 3h4v11H3z"/><circle cx="12" cy="13" r="3.5"/></svg>,
  alert:  (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l10 18H2L12 3Z"/><path d="M12 10v5"/><circle cx="12" cy="18" r="0.5" fill="currentColor"/></svg>,
  msg:    (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>,
  file:   (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>,
};

const RECURSOS = [
  { id: "box1",  label: "Box 1",      kind: "Box",      cls: "box" },
  { id: "box2",  label: "Box 2",      kind: "Box",      cls: "box" },
  { id: "box3",  label: "Box 3",      kind: "Box",      cls: "box" },
  { id: "elev1", label: "Elevador 1", kind: "Elevador", cls: "elev" },
  { id: "elev2", label: "Elevador 2", kind: "Elevador", cls: "elev" },
];

const MECANICOS = [
  { id: "m1", nome: "João Lima",    ini: "JL" },
  { id: "m2", nome: "Pedro Souza",  ini: "PS" },
  { id: "m3", nome: "Carlos Rocha", ini: "CR" },
  { id: "m4", nome: "Diego Alves",  ini: "DA" },
];

const OS_LIST = [
  { id: "8821", stage: "recepcao",    plate: "RBA-2H78", veh: "Honda Civic 2019",     km: "84.220",  client: "Marcos Aleixo",        symptom: "Barulho nas rodas dianteiras em curva",        recurso: null,    mech: null, deadline: "Hoje 17h",  urgent: false, value: "R$ 0,00",    arrived: "08:14" },
  { id: "8822", stage: "recepcao",    plate: "FZJ-4F12", veh: "VW Saveiro 2021",       km: "62.140",  client: "Frota Boa Esperança",  symptom: "Revisão 60.000 km + troca de óleo",            recurso: null,    mech: null, deadline: "Amanhã",    urgent: false, value: "R$ 0,00",    arrived: "09:02" },
  { id: "8819", stage: "diagnostico", plate: "QXM-1B33", veh: "Fiat Strada 2022",      km: "31.580",  client: "Construtora Lince",    symptom: "Luz de injeção acendendo intermitente",        recurso: "elev1", mech: "m2", deadline: "Hoje 18h",  urgent: true,  value: "R$ 380,00",  progress: 45, etaDiag: "30 min" },
  { id: "8815", stage: "diagnostico", plate: "GHS-8E22", veh: "Ford Ka 2018",          km: "108.900", client: "Larissa Nunes",        symptom: "Embreagem patinando em rampa",                  recurso: "box2",  mech: "m4", deadline: "Hoje 15h",  urgent: false, value: "—",          progress: 70, etaDiag: "20 min" },
  { id: "8810", stage: "pecas",       plate: "OWD-5R09", veh: "Toyota Hilux 2020",     km: "97.300",  client: "Agropecuária Vale",    symptom: "Troca de pastilhas + disco dianteiro",         recurso: null,    mech: "m1", deadline: "Amanhã 14h",urgent: false, value: "R$ 1.840,00",partsStatus: "encomendado", partsLabel: "Disco BR-2188 chega 09/05 manhã" },
  { id: "8807", stage: "pecas",       plate: "MNT-3T55", veh: "Renault Sandero 2017",  km: "134.500", client: "Eduardo Pessoa",       symptom: "Suspensão dianteira completa",                  recurso: null,    mech: "m3", deadline: "Sex 17h",   urgent: false, value: "R$ 2.250,00",partsStatus: "approval",    partsLabel: "Aguardando OK do cliente no orçamento" },
  { id: "8804", stage: "pecas",       plate: "KKQ-7H44", veh: "Chevrolet Onix 2023",   km: "18.650",  client: "Marina Tavares",       symptom: "Filtro de combustível + velas",                 recurso: null,    mech: "m2", deadline: "Hoje 16h",  urgent: true,  value: "R$ 540,00",  partsStatus: "ok",          partsLabel: "Peças no balcão · pronto p/ executar" },
  { id: "8801", stage: "execucao",    plate: "PLT-2C18", veh: "Hyundai HB20 2019",     km: "76.400",  client: "Roberto Coelho",       symptom: "Substituição de correia dentada",               recurso: "elev2", mech: "m1", deadline: "Hoje 19h",  urgent: true,  value: "R$ 920,00",  progress: 35, etaDone: "2h 40min" },
  { id: "8799", stage: "execucao",    plate: "BCY-9G07", veh: "Jeep Renegade 2021",    km: "42.110",  client: "Patrícia Lemos",       symptom: "Alinhamento + balanceamento + pneus",           recurso: "box1",  mech: "m3", deadline: "Hoje 18h",  urgent: false, value: "R$ 1.180,00",progress: 60, etaDone: "1h 10min" },
  { id: "8795", stage: "execucao",    plate: "ZTH-6L91", veh: "VW Gol 2016",           km: "162.800", client: "Auto Escola Norte",    symptom: "Junta do cabeçote",                             recurso: "elev1", mech: "m4", deadline: "Sex 12h",   urgent: false, value: "R$ 1.640,00",progress: 18, etaDone: "8h" },
  { id: "8788", stage: "pronto",      plate: "VRP-5K27", veh: "Nissan Versa 2020",     km: "55.300",  client: "Helena Bastos",        symptom: "Revisão 50.000 km",                              recurso: null,    mech: "m2", deadline: "Aguarda 1d",urgent: false, value: "R$ 780,00",  finishedAt: "Ontem 17:42", paid: false },
  { id: "8786", stage: "pronto",      plate: "WBL-3D88", veh: "Fiat Mobi 2019",        km: "89.100",  client: "Tiago Ramires",        symptom: "Bateria + alternador",                           recurso: null,    mech: "m1", deadline: "Aguarda 2d",urgent: false, value: "R$ 1.020,00",finishedAt: "07/05 16:10", paid: true },
];

const STAGES = [
  { id: "recepcao",    label: "Recepção",          dot: "slate"   },
  { id: "diagnostico", label: "Diagnóstico",       dot: "indigo"  },
  { id: "pecas",       label: "Aguardando peças",  dot: "rose"    },
  { id: "execucao",    label: "Em execução",       dot: "emerald" },
  { id: "pronto",      label: "Pronto p/ retirar", dot: "green"   },
];

const recursoOf = (id) => RECURSOS.find(r => r.id === id);
const mechOf    = (id) => MECANICOS.find(m => m.id === id);
const valorBR   = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const valorNumOf = (s) => parseFloat((s || "").replace(/[^\d,]/g, "").replace(",", ".")) || 0;

function Plate({ value }) {
  return (
    <div className="ofc-plate">
      <div className="top">BR · MERCOSUL</div>
      <div className="num">{value}</div>
    </div>
  );
}

function MechAv({ id }) {
  const m = mechOf(id);
  if (!m) return <span className="ofc-row dim"><Ico.wrench size={11}/>Sem mecânico</span>;
  return (
    <span className="ofc-row">
      <span className="ofc-mech-av">{m.ini}</span>
      <span className="ofc-mech-name">{m.nome}</span>
    </span>
  );
}

function StageChip({ stage }) {
  const s = STAGES.find(x => x.id === stage);
  if (!s) return null;
  return (
    <span className="ofc-stage-chip">
      <span className={"ofc-stage-chip-dot prod-col-dot " + s.dot}/>
      {s.label}
    </span>
  );
}

const LAST_ACTIVITY = {
  "8821": "Cliente entregou chave às 08:14",
  "8822": "Aguarda primeira triagem",
  "8819": "OBD-II conectado · scan rodando",
  "8815": "Test drive: rampa do Quartel",
  "8810": "Disco BR-2188 chega 09/05 manhã",
  "8807": "WhatsApp enviado 11:30",
  "8804": "Peças no balcão · pronto",
  "8801": "Polia da bomba d'água removida",
  "8799": "3º pneu sendo balanceado",
  "8795": "Cabeçote no banco · medindo plano",
  "8788": "Cliente avisado 17:42 ontem",
  "8786": "Pago no PIX 07/05 16:30",
};
const PHOTO_TAG = {
  "8821": "frente", "8822": "painel", "8819": "OBD",
  "8815": "emb.",   "8810": "disco",  "8807": "susp.",
  "8804": "velas",  "8801": "correia","8799": "pneus",
  "8795": "cab.",   "8788": "revis.", "8786": "bat.",
};

function CardExtra({ os }) {
  return (
    <div className="ofc-card-extra">
      <div className="ofc-card-extra-thumb">{PHOTO_TAG[os.id] || "foto"}</div>
      <div className="ofc-card-extra-last"><b>últ.</b> {LAST_ACTIVITY[os.id] || "sem registro recente"}</div>
    </div>
  );
}

function Countdown({ deadline, urgent }) {
  if (!urgent) return null;
  const m = (deadline || "").match(/(\d{1,2})h/);
  const txt = m ? `T-${Math.max(1, 24 - parseInt(m[1]))}h` : "T-?";
  return <span className="ofc-countdown">⏱ {txt}</span>;
}

function BoxPill({ id }) {
  const r = recursoOf(id);
  if (!r) return null;
  return <span className={"ofc-box-pill " + r.cls}><Ico.box size={9}/>{r.label}</span>;
}

function CardRecepcao({ os, onOpen }) {
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
      {os.urgent && <span className="ofc-card-urgent-strip"/>}
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <span className="dim t-mute" style={{ marginLeft: "auto", fontSize: "10px" }}>chegou {os.arrived}</span>
      </div>
      <div className="ofc-veh-row">
        <Plate value={os.plate}/>
        <div className="ofc-veh-meta">
          <span className="ofc-veh-name">{os.veh}</span>
          <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
        </div>
      </div>
      <p className="ofc-symptom">{os.symptom}</p>
      <div className="prod-card-foot" style={{ marginTop: 8 }}>
        <span className="prod-deadline"><Ico.clock size={10}/> {os.deadline}<Countdown deadline={os.deadline} urgent={os.urgent}/></span>
        <button className="os-btn primary" onClick={(e) => e.stopPropagation()}>Triagem →</button>
      </div>
      <CardExtra os={os}/>
    </div>
  );
}

function CardDiagnostico({ os, onOpen }) {
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
      {os.urgent && <span className="ofc-card-urgent-strip"/>}
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <StageChip stage={os.stage}/>
        {os.recurso && <span style={{ marginLeft: "auto" }}><BoxPill id={os.recurso}/></span>}
      </div>
      <div className="ofc-veh-row">
        <Plate value={os.plate}/>
        <div className="ofc-veh-meta">
          <span className="ofc-veh-name">{os.veh}</span>
          <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
        </div>
      </div>
      <p className="ofc-symptom">{os.symptom}</p>
      <div className="prod-progress" style={{ marginTop: 6 }}>
        <div className="prod-progress-bar" style={{ width: `${os.progress}%` }}/>
        <span className="prod-progress-label">{os.progress}%</span>
      </div>
      <MechAv id={os.mech}/>
      <div className="ofc-eta-row">
        <span><b>ETA diag.</b> {os.etaDiag}</span>
        <span>prazo {os.deadline}<Countdown deadline={os.deadline} urgent={os.urgent}/></span>
      </div>
      <CardExtra os={os}/>
    </div>
  );
}

function CardPecas({ os, onOpen }) {
  const sLabel = { ok: "Peças OK", encomendado: "Encomendado", approval: "Aguardando aprovação" };
  const sCls   = { ok: "ok",       encomendado: "warn",         approval: "await" };
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
      {os.urgent && <span className="ofc-card-urgent-strip"/>}
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <StageChip stage={os.stage}/>
        <span style={{ marginLeft: "auto", fontSize: "10.5px", color: "var(--text-mute)" }} className="mono">{os.value}</span>
      </div>
      <div className="ofc-veh-row">
        <Plate value={os.plate}/>
        <div className="ofc-veh-meta">
          <span className="ofc-veh-name">{os.veh}</span>
          <span className="ofc-veh-sub">{os.client}</span>
        </div>
      </div>
      <p className="ofc-symptom">{os.symptom}</p>
      <div className={"ofc-parts " + sCls[os.partsStatus]}>
        <span className="ofc-parts-dot"/>
        <span style={{ fontWeight: 500 }}>{sLabel[os.partsStatus]}</span>
        <span style={{ marginLeft: "auto" }} className="dim ofc-parts-label">·</span>
        <span style={{ flex: 1, textAlign: "right" }} className="ofc-parts-label">{os.partsLabel}</span>
      </div>
      <MechAv id={os.mech}/>
      <div className="ofc-eta-row">
        <span>prazo <b>{os.deadline}</b><Countdown deadline={os.deadline} urgent={os.urgent}/></span>
        {os.partsStatus === "ok" && <button className="os-btn primary" style={{ padding: "3px 8px", fontSize: "10.5px" }} onClick={(e) => e.stopPropagation()}>Iniciar →</button>}
        {os.partsStatus === "approval" && <button className="os-btn ghost" style={{ padding: "3px 8px", fontSize: "10.5px", color: "oklch(0.42 0.13 20)" }} onClick={(e) => e.stopPropagation()}>Cobrar OK</button>}
      </div>
      <CardExtra os={os}/>
    </div>
  );
}

function CardExecucao({ os, onOpen }) {
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
      {os.urgent && <span className="ofc-card-urgent-strip"/>}
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <StageChip stage={os.stage}/>
        {os.recurso && <span style={{ marginLeft: "auto" }}><BoxPill id={os.recurso}/></span>}
      </div>
      <div className="ofc-veh-row">
        <Plate value={os.plate}/>
        <div className="ofc-veh-meta">
          <span className="ofc-veh-name">{os.veh}</span>
          <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
        </div>
      </div>
      <p className="ofc-symptom">{os.symptom}</p>
      <div className="prod-progress" style={{ marginTop: 6 }}>
        <div className="prod-progress-bar" style={{ width: `${os.progress}%` }}/>
        <span className="prod-progress-label">{os.progress}%</span>
      </div>
      <MechAv id={os.mech}/>
      <div className="ofc-eta-row">
        <span><b>resta</b> {os.etaDone}</span>
        <span>prazo {os.deadline}<Countdown deadline={os.deadline} urgent={os.urgent}/></span>
      </div>
      <CardExtra os={os}/>
    </div>
  );
}

function CardPronto({ os, onOpen }) {
  return (
    <div className={"prod-card"} onClick={() => onOpen(os)}>
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <StageChip stage={os.stage}/>
        <span style={{ marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: 4, fontSize: "10.5px", color: "oklch(0.36 0.10 145)" }}>
          <Ico.check size={11}/>finalizado
        </span>
      </div>
      <div className="ofc-veh-row">
        <Plate value={os.plate}/>
        <div className="ofc-veh-meta">
          <span className="ofc-veh-name">{os.veh}</span>
          <span className="ofc-veh-sub">{os.client}</span>
        </div>
      </div>
      <div className="ofc-eta-row" style={{ marginTop: 4 }}>
        <span><b>concluído</b> {os.finishedAt}</span>
        <span className="mono">{os.value}</span>
      </div>
      <div className="prod-card-foot" style={{ marginTop: 8 }}>
        <span className={os.paid ? "" : "prod-deadline"} style={!os.paid ? { color: "oklch(0.42 0.13 20)" } : {}}>
          {os.paid ? <><Ico.check size={10}/> Pago</> : <><Ico.alert size={10}/> Aguarda pagamento · {os.deadline}</>}
        </span>
        <button className="os-btn primary" onClick={(e) => e.stopPropagation()}>Entregar →</button>
      </div>
    </div>
  );
}

function CardSwitch({ os, onOpen }) {
  switch (os.stage) {
    case "recepcao":    return <CardRecepcao os={os} onOpen={onOpen}/>;
    case "diagnostico": return <CardDiagnostico os={os} onOpen={onOpen}/>;
    case "pecas":       return <CardPecas os={os} onOpen={onOpen}/>;
    case "execucao":    return <CardExecucao os={os} onOpen={onOpen}/>;
    case "pronto":      return <CardPronto os={os} onOpen={onOpen}/>;
    default: return null;
  }
}

function ProdColumn({ stage, items, capacity, onOpen }) {
  return (
    <section className={"prod-col prod-col-" + stage.dot}>
      <header className="prod-col-head">
        <div className="prod-col-head-l">
          <span className={"prod-col-dot " + stage.dot}/>
          <h3>{stage.label}</h3>
          <span className="prod-col-count">{items.length}</span>
        </div>
        {capacity && <span className="prod-col-cap">{capacity}</span>}
      </header>
      <div className="prod-col-body">
        {items.length === 0 && <div className="prod-empty" style={{ fontSize: "11px", color: "var(--text-mute)", padding: "14px 8px", textAlign: "center" }}>—</div>}
        {items.map(os => <CardSwitch key={os.id} os={os} onOpen={onOpen}/>)}
      </div>
    </section>
  );
}

function Drawer({ os, onClose }) {
  if (!os) return null;
  const r = recursoOf(os.recurso);
  const m = mechOf(os.mech);

  const parts = {
    "8810": [
      { name: "Disco freio dianteiro BR-2188", qty: "2 un", stat: "warn", statL: "encomendado" },
      { name: "Pastilha freio cerâmica",         qty: "1 jg", stat: "ok",   statL: "estoque" },
      { name: "Fluido DOT-4",                    qty: "1 L",  stat: "ok",   statL: "estoque" },
    ],
    "8807": [
      { name: "Amortecedor diant. par",   qty: "2 un", stat: "wait", statL: "ag. aprovação" },
      { name: "Bandeja LE/LD",            qty: "2 un", stat: "wait", statL: "ag. aprovação" },
      { name: "Pivô",                     qty: "2 un", stat: "wait", statL: "ag. aprovação" },
      { name: "Mão de obra",              qty: "4 h",  stat: "wait", statL: "ag. aprovação" },
    ],
  };
  const partsList = parts[os.id] || [
    { name: "Mão de obra",  qty: "—", stat: "ok", statL: "estimado" },
    { name: "Itens da OS",  qty: "—", stat: "ok", statL: "estoque" },
  ];

  const tl = [
    { when: "Hoje 08:14", what: "Veículo recepcionado",      by: "Larissa (recep.)",     status: "done" },
    { when: "Hoje 09:10", what: "Triagem inicial",            by: m?.nome || "—",          status: "done" },
    { when: "Hoje 10:45", what: "Diagnóstico em andamento",   by: m?.nome || "—",          status: os.stage === "diagnostico" ? "now" : "done" },
    ...(os.stage === "pecas" || os.stage === "execucao" || os.stage === "pronto" ? [
      { when: "Hoje 11:30", what: "Orçamento enviado ao cliente", by: m?.nome || "—",      status: "done" },
    ] : []),
    ...(os.stage === "pecas" ? [
      { when: "agora", what: "Aguardando peças/aprovação", by: "—", status: "now" },
    ] : []),
    ...(os.stage === "execucao" ? [
      { when: "Hoje 13:20", what: "Aprovação do cliente recebida", by: "WhatsApp",          status: "done" },
      { when: "agora",       what: "Execução em andamento",        by: m?.nome || "—",      status: "now" },
    ] : []),
    ...(os.stage === "pronto" ? [
      { when: os.finishedAt, what: "Serviço concluído",        by: m?.nome || "—",          status: "done" },
      { when: "—",           what: "Aguardando retirada",      by: os.client,               status: "now" },
    ] : []),
  ];

  const showApproval = os.stage === "pecas" && os.partsStatus === "approval";

  return (
    <div className="prod-drawer-backdrop" onClick={onClose}>
      <aside className="prod-drawer" onClick={e => e.stopPropagation()}>
        <header className="prod-drawer-head">
          <div>
            <div className="prod-drawer-eyebrow">OS #{os.id} · {STAGES.find(s => s.id === os.stage)?.label}</div>
            <h2 style={{ margin: "4px 0 2px" }}>{os.veh}</h2>
            <p style={{ margin: 0, fontSize: "12px", color: "var(--text-dim)" }}>{os.client}</p>
          </div>
          <button className="icon-btn" onClick={onClose}><Ico.x size={14}/></button>
        </header>

        <div className="prod-drawer-body">
          <div className="ofc-veh-card">
            <Plate value={os.plate}/>
            <dl>
              <dt>KM</dt><dd>{os.km}</dd>
              <dt>Box</dt><dd>{r ? r.label : "— (sem alocação)"}</dd>
              <dt>Mecânico</dt><dd>{m ? m.nome : "—"}</dd>
              <dt>Valor</dt><dd>{os.value}</dd>
            </dl>
          </div>

          <div className="ofc-drawer-section">
            <h4>Sintoma reportado</h4>
            <p style={{ margin: 0, fontSize: "13px", lineHeight: 1.45, color: "var(--text)" }}>{os.symptom}</p>
          </div>

          {/* Vistoria Digital (DVI) — padrão do mercado (Shopmonkey, Tekmetric, ServiceTitan).
              Lista de sistemas vistoriados com status verde/amarelo/vermelho + foto + ação. */}
          <div className="ofc-drawer-section">
            <h4>Vistoria Digital · DVI</h4>
            <div className="ofc-dvi-summary">
              <span className="ofc-dvi-pill ok"><b>8</b> ok</span>
              <span className="ofc-dvi-pill warn"><b>2</b> atenção</span>
              <span className="ofc-dvi-pill bad"><b>1</b> crítico</span>
              <button className="os-btn ghost sm" style={{marginLeft:"auto"}}>📤 Enviar p/ cliente</button>
            </div>
            <ul className="ofc-dvi-list">
              <li className="ok">
                <span className="dot"/>
                <div><b>Motor · óleo + filtro</b><small>nível ok · 4.500 km restantes</small></div>
                <span className="th">📷</span>
              </li>
              <li className="warn">
                <span className="dot"/>
                <div><b>Freios dianteiros · pastilhas</b><small>3mm · vida útil 60% · R$ 145</small></div>
                <span className="th">📷</span>
              </li>
              <li className="bad">
                <span className="dot"/>
                <div><b>Correia dentada · trincada</b><small>recomenda troca imediata · R$ 480 + 2h MO</small></div>
                <span className="th">📷</span>
              </li>
              <li className="ok">
                <span className="dot"/>
                <div><b>Bateria · 12.4V em carga</b><small>4 anos · saúde 78%</small></div>
                <span className="th">📷</span>
              </li>
              <li className="warn">
                <span className="dot"/>
                <div><b>Pneus traseiros · desgaste 70%</b><small>trocar nos próximos 5.000 km</small></div>
                <span className="th">📷</span>
              </li>
            </ul>
            <div className="ofc-dvi-foot">
              <div>
                <small>Total recomendado · cliente</small>
                <b>R$ 1.870,00</b>
              </div>
              <button className="os-btn primary sm">📲 Pedir aprovação WhatsApp</button>
            </div>
          </div>

          {showApproval && (
            <div className="ofc-approval pending">
              <Ico.alert size={14}/>
              <div style={{ flex: 1 }}>
                <b>Aguardando aprovação do cliente.</b> Orçamento enviado por WhatsApp há 2h.
              </div>
              <button className="os-btn" style={{ padding: "3px 8px", fontSize: "10.5px" }}>Cobrar</button>
            </div>
          )}

          <div className="ofc-drawer-section">
            <h4>Fotos & Laudo</h4>
            <div className="ofc-photos">
              <div className="ofc-photo">FOTO·1<br/>frente</div>
              <div className="ofc-photo">FOTO·2<br/>painel</div>
              <div className="ofc-photo">FOTO·3<br/>peça</div>
            </div>
            <button className="os-btn ghost" style={{ marginTop: 8, fontSize: "11px" }}><Ico.camera size={11}/>Adicionar foto</button>
          </div>

          <div className="ofc-drawer-section">
            <h4>Peças & Mão de obra</h4>
            <ul className="ofc-parts-list">
              {partsList.map((p, i) => (
                <li key={i}>
                  <span>{p.name}</span>
                  <span className="qty">{p.qty}</span>
                  <span className={"stat " + p.stat}>{p.statL}</span>
                </li>
              ))}
            </ul>
          </div>

          <div className="ofc-drawer-section">
            <h4>Linha do tempo</h4>
            <div className="ofc-timeline">
              {tl.map((t, i) => (
                <div key={i} className={"ofc-tl-item " + t.status}>
                  <div className="ofc-tl-when">{t.when}</div>
                  <div className="ofc-tl-what">{t.what}</div>
                  <div className="ofc-tl-by">{t.by}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="prod-drawer-actions" style={{ marginTop: 14, display: "flex", gap: 8, flexWrap: "wrap" }}>
            <button className="os-btn ghost"><Ico.msg size={11}/>Conversa cliente</button>
            <button className="os-btn ghost"><Ico.file size={11}/>Imprimir OS</button>
            <button className="os-btn primary" style={{ marginLeft: "auto" }}>Avançar etapa →</button>
          </div>
        </div>
      </aside>
    </div>
  );
}

function ProducaoOficina({ foco, recursoFilter, setRecursoFilter, view, setView }) {
  const [open, setOpen] = useState(null);

  const filtered = useMemo(() => {
    if (recursoFilter === "all") return OS_LIST;
    return OS_LIST.filter(o => o.recurso === recursoFilter);
  }, [recursoFilter]);

  const pivot = useMemo(() => {
    if (foco === "box") {
      return [
        ...RECURSOS.map(r => ({
          id: r.id, label: r.label,
          dot: r.cls === "box" ? "rose" : "indigo",
          filter: o => o.recurso === r.id,
        })),
        { id: "_none", label: "Sem alocação", dot: "slate", filter: o => !o.recurso },
      ];
    }
    if (foco === "mecanico") {
      return [
        ...MECANICOS.map(m => ({
          id: m.id, label: m.nome, dot: "indigo",
          filter: o => o.mech === m.id,
        })),
        { id: "_none", label: "Sem mecânico", dot: "slate", filter: o => !o.mech },
      ];
    }
    return STAGES.map(s => ({
      id: s.id, label: s.label, dot: s.dot,
      filter: o => o.stage === s.id,
    }));
  }, [foco]);

  const byCol = useMemo(() => {
    const m = {};
    pivot.forEach(c => { m[c.id] = filtered.filter(c.filter); });
    return m;
  }, [filtered, pivot]);

  const totals = {
    recepcao:    OS_LIST.filter(o => o.stage === "recepcao").length,
    diagnostico: OS_LIST.filter(o => o.stage === "diagnostico").length,
    pecas:       OS_LIST.filter(o => o.stage === "pecas").length,
    execucao:    OS_LIST.filter(o => o.stage === "execucao").length,
    pronto:      OS_LIST.filter(o => o.stage === "pronto").length,
    urgent:      OS_LIST.filter(o => o.urgent).length,
    valor:       OS_LIST.reduce((a, o) => a + valorNumOf(o.value), 0),
    approval:    OS_LIST.filter(o => o.stage === "pecas" && o.partsStatus === "approval").length,
  };

  return (
    <div className="prod-page" data-screen-label="01 Oficina Auto">
      <div className="prod-header">
        <div className="prod-header-l">
          <h1>Oficina Auto</h1>
          <p>Recepção, diagnóstico, peças, execução e entrega de veículos</p>
        </div>
        <div className="prod-header-r">
          <div className="prod-view-toggle">
            <button className={view === "kanban" ? "active" : ""} onClick={() => setView("kanban")}>
              <Ico.grid size={11}/>Kanban
            </button>
            <button className={view === "list" ? "active" : ""} onClick={() => setView("list")}>
              <Ico.list size={11}/>Lista
            </button>
            <button className={view === "grade" ? "active" : ""} onClick={() => setView("grade")}>
              <Ico.grid size={11}/>Grade
            </button>
          </div>
          <button className="os-btn ghost"><Ico.print size={11}/>Imprimir fila</button>
          <button className="os-btn primary"><Ico.plus size={11}/>Nova OS</button>
        </div>
      </div>

      <div className="prod-kpis">
        <div className="prod-kpi">
          <span className="prod-kpi-label">Recepção</span>
          <span className="prod-kpi-value">{totals.recepcao}</span>
          <span className="prod-kpi-sub">veículos aguardando triagem</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">Em diagnóstico</span>
          <span className="prod-kpi-value">{totals.diagnostico}</span>
          <span className="prod-kpi-sub">{RECURSOS.length} boxes/elevadores</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">Aguardando peças</span>
          <span className="prod-kpi-value">{totals.pecas}</span>
          <span className="prod-kpi-sub">{totals.approval} aguardam OK do cliente</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">Em execução</span>
          <span className="prod-kpi-value">{totals.execucao}</span>
          <span className="prod-kpi-sub">boxes ocupados agora</span>
        </div>
        <div className="prod-kpi prod-kpi-urgent">
          <span className="prod-kpi-label">Urgentes</span>
          <span className="prod-kpi-value">{totals.urgent}</span>
          <span className="prod-kpi-sub">prazo crítico</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">Valor em curso</span>
          <span className="prod-kpi-value">{valorBR(totals.valor).replace(",00", "")}</span>
          <span className="prod-kpi-sub">faturamento previsto</span>
        </div>
      </div>

      <div className="prod-equip-filters">
        <button className={"prod-equip-tab" + (recursoFilter === "all" ? " active" : "")} onClick={() => setRecursoFilter("all")}>
          Todos os boxes <span className="count">{OS_LIST.length}</span>
        </button>
        {RECURSOS.map(r => {
          const n = OS_LIST.filter(o => o.recurso === r.id).length;
          return (
            <button key={r.id} className={"prod-equip-tab" + (recursoFilter === r.id ? " active" : "")} onClick={() => setRecursoFilter(r.id)}>
              <span className={"prod-equip-dot " + r.cls}/>
              {r.label} <span className="count">{n}</span>
            </button>
          );
        })}
      </div>

      {view === "kanban" && (
        <div className={"prod-kanban " + (foco === "etapa" ? "ofc-5" : "ofc-many")} style={foco !== "etapa" ? { "--ofc-cols": pivot.length } : undefined}>
          {pivot.map(c => (
            <ProdColumn key={c.id} stage={{ id: c.id, label: c.label, dot: c.dot }} items={byCol[c.id] || []}
              capacity={foco === "etapa" && c.id === "execucao" ? `${byCol.execucao.length}/${RECURSOS.length} boxes` : null}
              onOpen={setOpen}/>
          ))}
        </div>
      )}

      {view === "grade" && (
        <div className="prod-list ofc-grade-wrap">
          <table className="ofc-grade">
            <thead>
              <tr>
                <th className="ofc-grade-veh">Veículo / Serviço</th>
                <th>Diagnóstico</th>
                <th>Pastilhas freio</th>
                <th>Discos freio</th>
                <th>Óleo + filtro</th>
                <th>Correia/Polia</th>
                <th>Embreagem</th>
                <th>Bateria</th>
                <th>Suspensão</th>
                <th>Alinhamento</th>
                <th>Injeção</th>
                <th>Funilaria</th>
                <th>Pintura</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(os => {
                const sym = (os.symptom || "").toLowerCase();
                // Heurística visual: mapeia sintoma para serviços relacionados
                const apl = {
                  diag:    sym.includes("intermitente") || sym.includes("luz") || sym.includes("barulho"),
                  past:    sym.includes("pastilha") || sym.includes("freio"),
                  disc:    sym.includes("disco") || sym.includes("freio"),
                  oleo:    sym.includes("revisão") || sym.includes("óleo"),
                  corr:    sym.includes("correia") || sym.includes("polia"),
                  embr:    sym.includes("embreagem") || sym.includes("patinando"),
                  bat:     sym.includes("bateria") || sym.includes("alternador"),
                  susp:    sym.includes("suspensão"),
                  alin:    sym.includes("alinhamento") || sym.includes("balanceamento") || sym.includes("pneu"),
                  inj:     sym.includes("injeção") || sym.includes("injetor"),
                  fun:     sym.includes("funilaria") || sym.includes("batida"),
                  pint:    sym.includes("pintura"),
                };
                const statusFor = (key) => {
                  if (!apl[key]) return null;
                  if (os.stage === "pronto")    return { l: "✓",  c: "done" };
                  if (os.stage === "execucao")  return { l: "●",  c: "prog" };
                  if (os.stage === "pecas")     return { l: "◦",  c: "wait" };
                  if (os.stage === "diagnostico") return { l: "?", c: "diag" };
                  if (os.stage === "recepcao") return { l: "·", c: "new" };
                  return { l: "·", c: "new" };
                };
                const Cell = ({k}) => {
                  const s = statusFor(k);
                  if (!s) return <td className="ofc-grade-c"></td>;
                  return <td className="ofc-grade-c"><span className={"ofc-grade-mark ofc-grade-" + s.c}>{s.l}</span></td>;
                };
                return (
                  <tr key={os.id} onClick={() => setOpen(os)} style={{cursor:"pointer"}}>
                    <td className="ofc-grade-veh">
                      <Plate value={os.plate}/>
                      <div className="ofc-grade-veh-meta">
                        <b>{os.veh}</b>
                        <small>{os.client} · OS #{os.id}</small>
                      </div>
                    </td>
                    <Cell k="diag"/><Cell k="past"/><Cell k="disc"/><Cell k="oleo"/>
                    <Cell k="corr"/><Cell k="embr"/><Cell k="bat"/><Cell k="susp"/>
                    <Cell k="alin"/><Cell k="inj"/><Cell k="fun"/><Cell k="pint"/>
                  </tr>
                );
              })}
            </tbody>
          </table>
          <div className="ofc-grade-legend">
            <span><span className="ofc-grade-mark ofc-grade-done">✓</span> concluído</span>
            <span><span className="ofc-grade-mark ofc-grade-prog">●</span> em execução</span>
            <span><span className="ofc-grade-mark ofc-grade-wait">◦</span> aguarda peça</span>
            <span><span className="ofc-grade-mark ofc-grade-diag">?</span> diagnóstico</span>
            <span><span className="ofc-grade-mark ofc-grade-new">·</span> agendado</span>
            <span className="ofc-grade-hint">click no veículo abre OS</span>
          </div>
        </div>
      )}

      {view === "list" && (
        <div className="prod-list">
          <table className="os-table">
            <thead>
              <tr><th>OS</th><th>Placa</th><th>Veículo</th><th>Cliente</th><th>Etapa</th><th>Box</th><th>Mecânico</th><th>Prazo</th><th style={{ textAlign: "right" }}>Valor</th></tr>
            </thead>
            <tbody>
              {filtered.map(os => {
                const r = recursoOf(os.recurso);
                const m = mechOf(os.mech);
                const s = STAGES.find(x => x.id === os.stage);
                return (
                  <tr key={os.id} onClick={() => setOpen(os)} style={{ cursor: "pointer" }}>
                    <td className="mono">#{os.id}</td>
                    <td><Plate value={os.plate}/></td>
                    <td>{os.veh} <small style={{ color: "var(--text-mute)" }}>{os.km} km</small></td>
                    <td>{os.client}</td>
                    <td><span className="stage-pill" style={{ display: "inline-flex", alignItems: "center", gap: 5 }}><span className={"prod-col-dot " + s.dot}/>{s.label}</span></td>
                    <td>{r ? r.label : "—"}</td>
                    <td>{m ? m.nome : "—"}</td>
                    <td>{os.deadline}</td>
                    <td className="mono" style={{ textAlign: "right" }}>{os.value}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      <Drawer os={open} onClose={() => setOpen(null)}/>
    </div>
  );
}

// ── Page entry: oferece tweaks inline ao invés de TweaksPanel ──
function OficinaPage() {
  const [foco,      setFoco]      = useState("etapa");
  const [densidade, setDensidade] = useState("padrao");
  const [pressao,   setPressao]   = useState("padrao");
  const [recursoFilter, setRecursoFilter] = useState("all");
  const [view, setView] = useState("kanban");

  const rootClass = [
    "oficina-root",
    "ofc-foco-" + foco,
    "ofc-density-" + densidade,
    "ofc-mood-" + pressao,
  ].join(" ");

  const Seg = ({ value, set, options }) => (
    <div className="seg">
      {options.map(o => (
        <button key={o.value} className={value === o.value ? "on" : ""} onClick={() => set(o.value)}>{o.label}</button>
      ))}
    </div>
  );

  return (
    <div className={rootClass}>
      <div className="ofc-toolbar">
        <div className="group">
          <label>Foco</label>
          <Seg value={foco} set={setFoco} options={[
            { value: "etapa",    label: "Etapa" },
            { value: "box",      label: "Box" },
            { value: "mecanico", label: "Mecânico" },
          ]}/>
        </div>
        <div className="group">
          <label>Densidade</label>
          <Seg value={densidade} set={setDensidade} options={[
            { value: "compacto", label: "Compacto" },
            { value: "padrao",   label: "Padrão" },
            { value: "detalhe",  label: "Detalhe" },
          ]}/>
        </div>
        <div className="group">
          <label>Pressão</label>
          <Seg value={pressao} set={setPressao} options={[
            { value: "calmo",   label: "Calmo" },
            { value: "padrao",  label: "Padrão" },
            { value: "pressao", label: "Pressão" },
          ]}/>
        </div>
      </div>
      <ProducaoOficina foco={foco} recursoFilter={recursoFilter} setRecursoFilter={setRecursoFilter} view={view} setView={setView}/>
    </div>
  );
}

window.OficinaPage = OficinaPage;
})();

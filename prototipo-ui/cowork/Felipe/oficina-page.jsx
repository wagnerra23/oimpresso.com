// oficina-page.jsx — Oficina Auto (vertical) embedada no shell unificado.
// Migrado de "Produção Oficina - Tela.html". CSS em oficina-page.css.
// IIFE encapsula tudo, expõe window.OficinaPage.
//
// Sprint paridade CRUD (2026-05-26 m0193):
//   ▸ Botão "Nova OS" + drawer create/edit (oficina-forms.jsx OsCreateDrawer)
//   ▸ Items inline editável (peça/MO/terceiro) no drawer
//   ▸ DVI inline editável (Vistoria Digital)
//   ▸ StageGate — checklist de bloqueio por etapa (cliente curtiu m0193)
(() => {
const { useState, useMemo, useEffect } = React;

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
  sliders:(p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="4" y1="8" x2="20" y2="8"/><line x1="4" y1="16" x2="20" y2="16"/><circle cx="9" cy="8" r="2"/><circle cx="15" cy="16" r="2"/></svg>,
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

function slaOf(os) {
  if (os.stage === "pronto") return "green";
  if (os.urgent) return "red";
  const d = (os.deadline || "").toLowerCase();
  if (d.includes("hoje")) return "amber";
  return "green";
}

// Placeholder de foto de laudo (demo) — canvas neutro com legenda; substituído por
// upload real (Modules/Arquivos) no F3. F1 OS-V2-1.
const __laudoPhCache = {};
function demoLaudoPhoto(label, tones) {
  if (__laudoPhCache[label]) return __laudoPhCache[label];
  const c = document.createElement("canvas"); c.width = 480; c.height = 360;
  const g = c.getContext("2d");
  const grad = g.createLinearGradient(0, 0, 480, 360);
  grad.addColorStop(0, tones[0]); grad.addColorStop(1, tones[1]);
  g.fillStyle = grad; g.fillRect(0, 0, 480, 360);
  g.fillStyle = "rgba(255,255,255,.06)";
  for (let i = 0; i < 5; i++) g.fillRect(0, 60 + i * 64, 480, 22);
  g.fillStyle = "rgba(0,0,0,.34)"; g.fillRect(0, 282, 480, 78);
  g.fillStyle = "rgba(255,255,255,.94)";
  g.font = "600 24px system-ui, sans-serif";
  g.fillText(label, 20, 318);
  g.font = "500 14px ui-monospace, monospace"; g.fillStyle = "rgba(255,255,255,.55)";
  g.fillText("FOTO DEMO · vistoria", 20, 342);
  return (__laudoPhCache[label] = c.toDataURL("image/jpeg", 0.85));
}
const LAUDO_LABEL_SUGEST = ["frente", "traseira", "painel · km", "motor", "peça", "avaria"];
function nowHM() { const d = new Date(); return "Hoje " + String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0"); }
const OS_STAGE_LABEL = { diagnostico: "Diagnóstico", pecas: "Aguardando peças/aprovação", execucao: "Em execução", pronto: "Pronto p/ retirar" };
const prodCardCls = (os) => "prod-card" + (os.urgent ? " urgent" : "") + " ofc-sla-" + slaOf(os);

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
  const F = window.OficinaForms || {};
  const ctx = window.__OFICINA_CTX || { osItems: {}, osDvi: {} };
  const items = ctx.osItems[os.id] || [];
  const dviItems = ctx.osDvi[os.id] || [];
  const gateCtx = {
    dviCount: dviItems.length,
    dviBad: dviItems.filter(d => d.status === "bad").length,
    itemsCount: items.length,
    itemsDone: items.filter(i => i.done).length,
  };
  return (
    <div className="ofc-card-extra">
      <div className="ofc-card-extra-thumb">{PHOTO_TAG[os.id] || "foto"}</div>
      <div className="ofc-card-extra-last"><b>últ.</b> {LAST_ACTIVITY[os.id] || "sem registro recente"}</div>
      {F.StageGateMini && <F.StageGateMini os={os} ctx={gateCtx}/>}
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

function CardRecepcao({ os, onOpen, onAdvance }) {
  return (
    <div className={prodCardCls(os)} onClick={() => onOpen(os)}>
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
        <button className="os-btn primary" onClick={(e) => { e.stopPropagation(); onAdvance && onAdvance(os); }}>Triagem →</button>
      </div>
      <CardExtra os={os}/>
    </div>
  );
}

function CardDiagnostico({ os, onOpen }) {
  return (
    <div className={prodCardCls(os)} onClick={() => onOpen(os)}>
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

function CardPecas({ os, onOpen, onAdvance, onNotify }) {
  const sLabel = { ok: "Peças OK", encomendado: "Encomendado", approval: "Aguardando aprovação" };
  const sCls   = { ok: "ok",       encomendado: "warn",         approval: "await" };
  return (
    <div className={prodCardCls(os)} onClick={() => onOpen(os)}>
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
        {os.partsStatus === "ok" && <button className="os-btn primary" style={{ padding: "3px 8px", fontSize: "10.5px" }} onClick={(e) => { e.stopPropagation(); onAdvance && onAdvance(os); }}>Iniciar →</button>}
        {os.partsStatus === "approval" && <button className="os-btn ghost" style={{ padding: "3px 8px", fontSize: "10.5px", color: "oklch(0.42 0.13 20)" }} onClick={(e) => { e.stopPropagation(); onNotify && onNotify("Cobrança enviada ao cliente por WhatsApp · OS #" + os.id); }}>Cobrar OK</button>}
      </div>
      <CardExtra os={os}/>
    </div>
  );
}

function CardExecucao({ os, onOpen }) {
  return (
    <div className={prodCardCls(os)} onClick={() => onOpen(os)}>
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

function CardPronto({ os, onOpen, onAdvance }) {
  return (
    <div className={prodCardCls(os)} onClick={() => onOpen(os)}>
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
        <button className="os-btn primary" onClick={(e) => { e.stopPropagation(); onAdvance && onAdvance(os); }}>Entregar →</button>
      </div>
    </div>
  );
}

function CardSwitch({ os, onOpen, onAdvance, onNotify }) {
  switch (os.stage) {
    case "recepcao":    return <CardRecepcao os={os} onOpen={onOpen} onAdvance={onAdvance}/>;
    case "diagnostico": return <CardDiagnostico os={os} onOpen={onOpen}/>;
    case "pecas":       return <CardPecas os={os} onOpen={onOpen} onAdvance={onAdvance} onNotify={onNotify}/>;
    case "execucao":    return <CardExecucao os={os} onOpen={onOpen}/>;
    case "pronto":      return <CardPronto os={os} onOpen={onOpen} onAdvance={onAdvance}/>;
    default: return null;
  }
}

function ProdColumn({ stage, items, capacity, onOpen, onAdvance, dnd, onNotify, focusedId }) {
  const isNext = dnd && dnd.drag && stage.id === dnd.drag.next;
  const dropCls = dnd && dnd.drag
    ? (isNext ? (" ofc-drop-ok " + (dnd.drag.ready ? "ofc-drop-ready" : "ofc-drop-warn")) : (stage.id !== dnd.drag.from ? " ofc-drop-no" : ""))
      + (dnd.overCol === stage.id ? " ofc-drop-over" : "")
    : "";
  return (
    <section className={"prod-col prod-col-" + stage.dot + dropCls}
      onDragOver={dnd ? (e) => dnd.onColDragOver(stage.id, e) : undefined}
      onDragLeave={dnd ? () => dnd.onColDragLeave(stage.id) : undefined}
      onDrop={dnd ? () => dnd.onColDrop(stage.id) : undefined}>
      <header className="prod-col-head">
        <div className="prod-col-head-l">
          <span className={"prod-col-dot " + stage.dot}/>
          <h3>{stage.label}</h3>
          <span className="prod-col-count">{items.length}</span>
        </div>
        {isNext
          ? <span className={"prod-col-cap ofc-drop-hint " + (dnd.drag.ready ? "ok" : "warn")}>{dnd.drag.ready ? "solte p/ avançar" : `faltam ${dnd.drag.pend} · abre checklist`}</span>
          : (capacity && <span className="prod-col-cap">{capacity}</span>)}
      </header>
      <div className="prod-col-body">
        {items.length === 0 && <div className="prod-empty" style={{ fontSize: "11px", color: "var(--text-mute)", padding: "14px 8px", textAlign: "center" }}>—</div>}
        {items.map(os => dnd
          ? <div key={os.id} className={"ofc-drag-wrap" + (dnd.drag && dnd.drag.id === os.id ? " ofc-dragging" : "") + (focusedId === os.id ? " ofc-card-focused" : "")}
              draggable onDragStart={(e) => dnd.onDragStart(os, e)} onDragEnd={dnd.onDragEnd}>
              <CardSwitch os={os} onOpen={onOpen} onAdvance={onAdvance} onNotify={onNotify}/>
            </div>
          : <div key={os.id} className={focusedId === os.id ? "ofc-card-focused" : ""}><CardSwitch os={os} onOpen={onOpen} onAdvance={onAdvance} onNotify={onNotify}/></div>
        )}
      </div>
    </section>
  );
}

function Drawer({ os, onClose, onEdit, onAdvance, osItems, setOsItems, osDvi, setOsDvi, osPhotos, setOsPhotos, osApproval, setOsApproval, osLog, setOsLog, onToast }) {
  const photoInputRef = React.useRef(null);
  const [dragOver, setDragOver] = useState(false);
  const [lightboxId, setLightboxId] = useState(null);
  useEffect(() => { setLightboxId(null); setDragOver(false); }, [os && os.id]);
  // Esc fecha o lightbox (sem fechar o drawer)
  useEffect(() => {
    if (!lightboxId) return;
    const onKey = (e) => { if (e.key === "Escape") { e.stopPropagation(); setLightboxId(null); } };
    window.addEventListener("keydown", onKey, true);
    return () => window.removeEventListener("keydown", onKey, true);
  }, [lightboxId]);

  const osId = os && os.id;
  const photos = (osPhotos && osId && osPhotos[osId]) || [];
  const patchPhotos = (fn) => setOsPhotos(prev => ({ ...prev, [osId]: fn(prev[osId] || []) }));
  // Trilha auditável (F1 OS-V2-4) — quem fez o quê, quando, de→pra
  const addLog = (e) => setOsLog(prev => ({ ...prev, [osId]: [...(prev[osId] || []), { when: nowHM(), status: "done", ...e }] }));
  const addFiles = (files) => {
    const imgs = [...files].filter(f => f.type && f.type.startsWith("image/"));
    imgs.forEach((f, k) => {
      const id = "ph" + Date.now() + "_" + k;
      const label = (f.name || "").replace(/\.[^.]+$/, "") || LAUDO_LABEL_SUGEST[(photos.length + k) % LAUDO_LABEL_SUGEST.length];
      patchPhotos(p => [...p, { id, url: URL.createObjectURL(f), label, status: "uploading", progress: 6 }]);
      const t = window.setInterval(() => {
        patchPhotos(p => p.map(x => x.id === id ? { ...x, progress: Math.min(96, (x.progress || 0) + 12 + Math.random() * 16) } : x));
      }, 150);
      window.setTimeout(() => {
        window.clearInterval(t);
        patchPhotos(p => p.map(x => x.id === id ? { ...x, status: "done", progress: 100 } : x));
        addLog({ by: "Você", what: "Foto anexada ao laudo" });
        onToast && onToast("Foto anexada ao laudo da OS #" + osId);
      }, 1300 + k * 280);
    });
  };
  const addPhoto = (e) => { if (e.target.files && e.target.files.length) addFiles(e.target.files); e.target.value = ""; };
  const dragProps = {
    onDragOver: (e) => { e.preventDefault(); setDragOver(true); },
    onDragLeave: () => setDragOver(false),
    onDrop: (e) => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files) addFiles(e.dataTransfer.files); },
  };
  if (!os) return null;
  const r = recursoOf(os.recurso);
  const m = mechOf(os.mech);
  const F = window.OficinaForms || {};

  // Integração Vendas × Oficina (2026-05-25) — venda derivada
  // Quando a OS chega no estágio "pronto", o módulo Vendas cria/expõe a venda equivalente
  // com origin:"oficina" + osRef. Aqui consultamos VENDAS_LIST e mostramos card highlight.
  const vendaFromOs = (window.VENDAS_DATA?.VENDAS_LIST || []).find(
    v => v.source === "oficina" && (v.osIds || []).includes(os.id)
  );
  const showVendaCard = os.stage === "pronto" && vendaFromOs;

  // items por OS — vem do estado liftado (osItems[os.id]) editável via ItemsEditor
  const items = osItems[os.id] || [];
  const dviItems = osDvi[os.id] || [];

  // ctx pra avaliar gate de etapa
  const gateCtx = {
    dviCount: dviItems.length,
    dviBad:   dviItems.filter(d => d.status === "bad").length,
    itemsCount: items.length,
    itemsDone:  items.filter(i => i.done).length,
  };

  // Linha do tempo FSM auditável (F1 OS-V2-4): seed das transições (quem · quando · de→pra)
  // + eventos vivos do osLog (gate de aprovação, fotos, avanço de etapa).
  const tl = [
    { when: "Hoje 08:14", what: "OS aberta — veículo recebido", by: "Larissa (recep.)", to: "Diagnóstico", status: "done" },
    { when: "Hoje 09:10", what: "Triagem inicial",               by: m?.nome || "—",     status: "done" },
    { when: "Hoje 10:45", what: "Diagnóstico em andamento",      by: m?.nome || "—",     status: os.stage === "diagnostico" ? "now" : "done" },
    ...(os.stage === "pecas" || os.stage === "execucao" || os.stage === "pronto" ? [
      { when: "Hoje 11:30", what: "Orçamento enviado ao cliente", by: m?.nome || "—", from: "Diagnóstico", to: "Aguardando aprovação", status: "done" },
    ] : []),
    ...(os.stage === "pecas" ? [
      { when: "agora", what: "Aguardando peças/aprovação", by: "—", status: "now" },
    ] : []),
    ...(os.stage === "execucao" ? [
      { when: "Hoje 13:20", what: "Aprovação do cliente recebida", by: os.client + " · WhatsApp", from: "Aguardando aprovação", to: "Em execução", status: "done" },
      { when: "agora",       what: "Execução em andamento",        by: m?.nome || "—",            status: "now" },
    ] : []),
    ...(os.stage === "pronto" ? [
      { when: os.finishedAt, what: "Serviço concluído",   by: m?.nome || "—", from: "Em execução", to: "Pronto p/ retirar", status: "done" },
      { when: "—",           what: "Aguardando retirada", by: os.client,      status: "now" },
    ] : []),
    ...((osLog && osLog[os.id]) || []),
  ];

  // Gate de aprovação (F1 OS-V2-3) — estado por OS com default derivado do estágio
  const approval = (osApproval && osApproval[os.id]) || (
    os.stage === "pecas" && os.partsStatus === "approval" ? { status: "pending", sentLabel: "há 2h" } :
    (os.stage === "execucao" || os.stage === "pronto")    ? { status: "approved", decidedLabel: "hoje 13:20" } :
    { status: "none" }
  );
  const dviTotal = dviItems.reduce((s, d) => s + (d.valor || 0), 0);
  const fmtTot = (v) => "R$ " + (v || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2 });
  const handleApproval = (action) => {
    if (action === "request") {
      setOsApproval(p => ({ ...p, [os.id]: { status: "pending", sentAt: Date.now(), total: dviTotal } }));
      addLog({ by: "Você", what: "Orçamento " + fmtTot(dviTotal) + " enviado por WhatsApp", to: "Aguardando aprovação" });
      onToast && onToast("Link de aprovação enviado por WhatsApp ao cliente.");
    } else if (action === "cobrar") {
      addLog({ by: "Você", what: "Cobrança de aprovação reenviada" });
      onToast && onToast("Cobrança enviada ao cliente · OS #" + os.id);
    } else if (action === "sim-approve") {
      setOsApproval(p => ({ ...p, [os.id]: { ...approval, status: "approved", decidedAt: Date.now() } }));
      addLog({ by: os.client + " · WhatsApp", what: "Orçamento aprovado", from: "Aguardando aprovação", to: "Em execução" });
      onToast && onToast("Cliente aprovou o orçamento · OS #" + os.id);
    } else if (action === "sim-decline") {
      setOsApproval(p => ({ ...p, [os.id]: { ...approval, status: "declined", decidedAt: Date.now() } }));
      addLog({ by: os.client + " · WhatsApp", what: "Orçamento recusado", from: "Aguardando aprovação" });
      onToast && onToast("Cliente recusou o orçamento · OS #" + os.id);
    } else if (action === "reopen") {
      setOsApproval(p => ({ ...p, [os.id]: { status: "none" } }));
      addLog({ by: "Você", what: "Orçamento reaberto pra revisão" });
    }
  };

  return (
    <div className="prod-drawer-backdrop" onClick={onClose}>
      <aside className="prod-drawer" onClick={e => e.stopPropagation()}>
        <header className="prod-drawer-head">
          <div>
            <div className="prod-drawer-eyebrow">OS #{os.id} · {STAGES.find(s => s.id === os.stage)?.label}</div>
            <h2 style={{ margin: "4px 0 2px" }}>{os.veh}</h2>
            <p style={{ margin: 0, fontSize: "12px", color: "var(--text-dim)" }}>{os.client}</p>
          </div>
          <div style={{display:"flex", gap:6, alignItems:"flex-start"}}>
            <button className="os-btn ghost sm" onClick={() => onEdit?.(os)} title="Editar campos da OS">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 4l6 6-11 11H3v-6z"/></svg>
              Editar
            </button>
            <button className="icon-btn" onClick={onClose}><Ico.x size={14}/></button>
          </div>
        </header>

        <div className="prod-drawer-body">
          {showVendaCard && (
            <div className="ofc-venda-card" data-comment-anchor="ofc-venda-card">
              <div className="ofc-venda-flag">✎ Integração Vendas×Oficina</div>
              <div className="ofc-venda-head">
                <div>
                  <b>Esta OS gerou a venda <code>#{vendaFromOs.id}</code></b>
                  <small>auto-criada na transição Pronto p/ retirar · fatura quando cliente confirmar</small>
                </div>
                <span className="ofc-venda-stage">{(window.VENDAS_DATA?.VENDAS_STATUS || {})[vendaFromOs.status]?.label || vendaFromOs.status}</span>
              </div>
              {(() => {
                const items = vendaFromOs.itemsList || [];
                const prod = items.filter(i => i.type === "produto");
                const serv = items.filter(i => i.type === "servico");
                const tProd = prod.reduce((s,i) => s + i.qty*i.unit, 0);
                const tServ = serv.reduce((s,i) => s + i.qty*i.unit, 0);
                const _fmt = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
                return (
                  <div className="ofc-venda-grid">
                    <div className="ofc-vc">
                      <small>Total venda</small>
                      <b>{vendaFromOs.total}</b>
                    </div>
                    <div className="ofc-vc">
                      <small>Peças (NF-e)</small>
                      <b className="sm">{_fmt(tProd)} · {prod.length} ite{prod.length>1?"ns":"m"}</b>
                    </div>
                    <div className="ofc-vc">
                      <small>Mão-de-obra (NFS-e)</small>
                      <b className="sm">{_fmt(tServ)} · {serv.length} serviço{serv.length!==1?"s":""}</b>
                    </div>
                  </div>
                );
              })()}
              <div className="ofc-venda-fiscal">
                {vendaFromOs.fiscal?.nfe && (
                  <span className={`ofc-fb ofc-fb-${vendaFromOs.fiscal.nfe.status}`}>
                    {vendaFromOs.fiscal.nfe.status === "ok" ? "✓" : vendaFromOs.fiscal.nfe.status === "wait" ? "⧖" : "×"} NF-e {vendaFromOs.fiscal.nfe.numero}
                  </span>
                )}
                {vendaFromOs.fiscal?.nfse && (
                  <span className={`ofc-fb ofc-fb-${vendaFromOs.fiscal.nfse.status}`}>
                    {vendaFromOs.fiscal.nfse.status === "ok" ? "✓" : vendaFromOs.fiscal.nfse.status === "wait" ? "⧖" : "×"} NFS-e {vendaFromOs.fiscal.nfse.numero}
                  </span>
                )}
                {!vendaFromOs.fiscal?.nfe && !vendaFromOs.fiscal?.nfse && (
                  <span className="ofc-fb ofc-fb-na">— sem nota emitida ainda</span>
                )}
              </div>
              <div className="ofc-venda-actions">
                <button className="ofc-venda-cta primary"
                  onClick={() => {
                    if (window.__vendasSubSetter) window.__vendasSubSetter("lista");
                    onClose?.();
                    setTimeout(() => {
                      const ev = new CustomEvent("oimpresso:open-venda", { detail: { id: vendaFromOs.id } });
                      window.dispatchEvent(ev);
                    }, 200);
                  }}>
                  Abrir #{vendaFromOs.id} ↗
                </button>
                {vendaFromOs.fiscal?.nfe?.status === "ok" && <button className="ofc-venda-cta" onClick={() => onToast && onToast("Abrindo DANFE da NF-e " + vendaFromOs.fiscal.nfe.numero)}>DANFE NF-e</button>}
                {vendaFromOs.fiscal?.nfse?.status === "ok" && <button className="ofc-venda-cta" onClick={() => onToast && onToast("Abrindo DANFS-e " + vendaFromOs.fiscal.nfse.numero)}>DANFS-e</button>}
                <button className="ofc-venda-cta" onClick={() => onToast && onToast("Abrindo o Caixa do dia…")}>Ver no Caixa do dia</button>
              </div>
            </div>
          )}

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

          {/* Vistoria Digital (DVI) — editável (Wave 3 US-OFICINA-035) */}
          <div className="ofc-drawer-section">
            <h4>Vistoria Digital · DVI</h4>
            {F.DviEditor ? (
              <F.DviEditor items={dviItems}
                onChange={(next) => setOsDvi({ ...osDvi, [os.id]: next })}
                approval={approval}
                onApproval={handleApproval}
              />
            ) : <p className="dim sm">DVI editor não carregado.</p>}
          </div>


          {/* Fotos & Laudo (F1 OS-V2-1) — vazio / enviando / preenchido + lightbox.
              Fotos entram no print A4 (oficina-print.js). Upload real = F3 (Modules/Arquivos). */}
          <div className="ofc-drawer-section">
            <h4>Fotos &amp; Laudo{photos.length > 0 ? " · " + photos.filter(p => p.status === "done").length : ""}</h4>
            {photos.length === 0 ? (
              <div className={"ofc-photos-empty" + (dragOver ? " drag" : "")} role="button" tabIndex={0}
                onClick={() => photoInputRef.current && photoInputRef.current.click()}
                onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); photoInputRef.current && photoInputRef.current.click(); } }}
                {...dragProps}>
                <Ico.camera size={18}/>
                <b>Sem fotos na OS</b>
                <span>Toque pra fotografar ou arraste imagens — entram no laudo e na impressão</span>
              </div>
            ) : (
              <div className="ofc-photos">
                {photos.map(ph => ph.status === "uploading" ? (
                  <div key={ph.id} className="ofc-photo uploading" aria-label={"Enviando " + ph.label}>
                    <img src={ph.url} alt=""/>
                    <span className="ph-progress"><i style={{ width: (ph.progress || 0) + "%" }}></i></span>
                  </div>
                ) : (
                  <button key={ph.id} type="button" className="ofc-photo has-img" title="Ampliar foto" onClick={() => setLightboxId(ph.id)}>
                    <img src={ph.url} alt={ph.label}/>
                    <span className="ph-label">{ph.label}</span>
                  </button>
                ))}
                <div className={"ofc-photo add" + (dragOver ? " drag" : "")} role="button" tabIndex={0} title="Adicionar foto"
                  onClick={() => photoInputRef.current && photoInputRef.current.click()}
                  onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); photoInputRef.current && photoInputRef.current.click(); } }}
                  {...dragProps}><Ico.camera size={14}/></div>
              </div>
            )}
            <input ref={photoInputRef} type="file" accept="image/*" multiple style={{ display: "none" }} onChange={addPhoto}/>
          </div>

          <div className="ofc-drawer-section">
            <h4>Peças & Mão de obra</h4>
            {F.ItemsEditor ? (
              <F.ItemsEditor items={items}
                onChange={(next) => setOsItems({ ...osItems, [os.id]: next })}
              />
            ) : <p className="dim sm">Editor de items não carregado.</p>}
          </div>

          {/* StageGate — checklist de bloqueio por etapa (m0193) */}
          <div className="ofc-drawer-section">
            <h4>Checklist de etapa</h4>
            {F.StageGate ? (
              <F.StageGate os={os} ctx={gateCtx}
                onAdvance={(nextStage) => { addLog({ by: "Você", what: "Etapa avançada", from: OS_STAGE_LABEL[os.stage] || os.stage, to: OS_STAGE_LABEL[nextStage] || nextStage }); onAdvance?.(os, nextStage); }}
              />
            ) : <p className="dim sm">Gate de etapa não carregado.</p>}
          </div>

          <div className="ofc-drawer-section">
            <h4>Linha do tempo</h4>
            <div className="ofc-timeline">
              {tl.map((t, i) => (
                <div key={i} className={"ofc-tl-item " + t.status}>
                  <div className="ofc-tl-when">{t.when}</div>
                  <div className="ofc-tl-what">{t.what}
                    {(t.from || t.to) ? (
                      <span className="ofc-tl-fsm">
                        {t.from ? <i>{t.from}</i> : null}
                        {t.from && t.to ? <em>→</em> : null}
                        {t.to ? <i>{t.to}</i> : null}
                      </span>
                    ) : null}
                  </div>
                  <div className="ofc-tl-by">{t.by}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="prod-drawer-actions" style={{ marginTop: 14, display: "flex", gap: 8, flexWrap: "wrap" }}>
            <button className="os-btn ghost" onClick={() => { window.dispatchEvent(new CustomEvent("oimpresso:open-conversa", { detail: { cliente: os.client } })); onToast && onToast("Abrindo conversa com " + os.client + "…"); }}><Ico.msg size={11}/>Conversa cliente</button>
            <button className="os-btn ghost" onClick={() => { onToast && onToast("Preparando impressão da OS #" + os.id + "…"); window.OficinaPrint && window.OficinaPrint.printOS(os, { items: osItems[os.id] || [], dvi: osDvi[os.id] || [], fotos: photos.filter(p => p.status === "done") }); }}><Ico.file size={11}/>Imprimir OS</button>
          </div>
        </div>

        {/* Lightbox — ampliar foto, editar legenda, remover (Esc fecha) */}
        {(() => {
          const lb = photos.find(p => p.id === lightboxId);
          if (!lb) return null;
          return (
            <div className="ofc-lightbox" onClick={(e) => { e.stopPropagation(); setLightboxId(null); }}>
              <figure onClick={(e) => e.stopPropagation()}>
                <img src={lb.url} alt={lb.label}/>
                <figcaption>
                  <input value={lb.label} aria-label="Legenda da foto"
                    onChange={(e) => patchPhotos(p => p.map(x => x.id === lb.id ? { ...x, label: e.target.value } : x))}/>
                  <button className="os-btn ghost sm" onClick={() => { patchPhotos(p => p.filter(x => x.id !== lb.id)); setLightboxId(null); onToast && onToast("Foto removida do laudo."); }}>Remover</button>
                  <button className="icon-btn" title="Fechar" onClick={() => setLightboxId(null)}><Ico.x size={14}/></button>
                </figcaption>
              </figure>
            </div>
          );
        })()}
      </aside>
    </div>
  );
}

const Seg = ({ value, set, options }) => (
  <div className="seg">
    {options.map(o => (
      <button key={o.value} type="button" className={value === o.value ? "on" : ""} onClick={() => set(o.value)}>{o.label}</button>
    ))}
  </div>
);

function ProducaoOficina({ foco, setFoco, densidade, setDensidade, pressao, setPressao, recursoFilter, setRecursoFilter, view, setView }) {
  const [open, setOpen] = useState(null);
  const [adjOpen, setAdjOpen] = useState(false);
  const adjRef = React.useRef(null);
  // Estado liftado pra suportar Create/Edit OS (m0193 paridade CRUD)
  const [osList, setOsList] = useState(OS_LIST);
  const [filaSel, setFilaSel] = useState(null); // OS selecionada na view Fila
  const [createOpen, setCreateOpen] = useState(false);
  const [editingOs, setEditingOs] = useState(null);
  // Busca livre (placa, modelo, cliente, sintoma, OS#) — toolbar acima do kanban
  const [query, setQuery] = useState("");
  // D-05/D-07 — filtro por KPI + foco de teclado (DECLARAR ANTES de `filtered`: o IIFE lê kpiFilter;
  // Babel transpila const→var sem TDZ, então declarar depois fazia kpiFilter=undefined no cálculo).
  const [kpiFilter, setKpiFilter] = useState(null);
  const [focusedId, setFocusedId] = useState(null);
  const searchRef = React.useRef(null);
  // Items & DVI por OS — Map { osId: [...] }
  const [osItems, setOsItems] = useState(() => {
    // Seed mock: items pré-existentes para OS específicas (preserva visual antigo)
    return {
      "8810": [
        { id: "i1", tipo: "peca", nome: "Disco freio dianteiro BR-2188", qty: 2, unit: 280, stat: "warn", done: false },
        { id: "i2", tipo: "peca", nome: "Pastilha freio cerâmica",        qty: 1, unit: 180, stat: "ok",   done: false },
        { id: "i3", tipo: "peca", nome: "Fluido DOT-4",                   qty: 1, unit: 45,  stat: "ok",   done: false },
        { id: "i4", tipo: "mao_obra", nome: "Troca de disco + pastilha + sangria", qty: 2, unit: 220, stat: "ok", done: false },
      ],
      "8807": [
        { id: "i1", tipo: "peca", nome: "Amortecedor dianteiro (par)",   qty: 1, unit: 580, stat: "wait", done: false },
        { id: "i2", tipo: "peca", nome: "Bandeja LE/LD",                 qty: 2, unit: 240, stat: "wait", done: false },
        { id: "i3", tipo: "peca", nome: "Pivô",                          qty: 2, unit: 95,  stat: "wait", done: false },
        { id: "i4", tipo: "mao_obra", nome: "Suspensão dianteira completa", qty: 4, unit: 130, stat: "wait", done: false },
      ],
      "8801": [
        { id: "i1", tipo: "peca", nome: "Correia dentada kit",           qty: 1, unit: 380, stat: "ok",   done: true },
        { id: "i2", tipo: "peca", nome: "Bomba d'água",                  qty: 1, unit: 220, stat: "ok",   done: true },
        { id: "i3", tipo: "mao_obra", nome: "Substituição correia + bomba", qty: 1, unit: 320, stat: "ok", done: false },
      ],
    };
  });
  const [osDvi, setOsDvi] = useState(() => ({
    "8801": [
      { id: "d1", sistema: "Motor · óleo + filtro",        status: "ok",   obs: "nível ok · 4.500 km restantes",         valor: 0 },
      { id: "d2", sistema: "Freios dianteiros · pastilhas",status: "warn", obs: "3mm · vida útil 60%",                    valor: 145 },
      { id: "d3", sistema: "Correia dentada",              status: "bad",  obs: "trincada · troca imediata",              valor: 480 },
      { id: "d4", sistema: "Bateria + sistema elétrico",   status: "ok",   obs: "12.4V em carga · 4 anos · saúde 78%",   valor: 0 },
      { id: "d5", sistema: "Pneus · traseiros",            status: "warn", obs: "desgaste 70% · trocar em 5.000 km",     valor: 1245 },
    ],
  }));
  // Fotos do laudo por OS (F1 OS-V2-1) — 8801 nasce preenchida (demo), demais vazias.
  const [osPhotos, setOsPhotos] = useState(() => ({
    "8801": [
      { id: "p1", url: demoLaudoPhoto("Correia dentada · antes", ["#3d4456", "#1c2029"]), label: "Correia dentada · antes", status: "done" },
      { id: "p2", url: demoLaudoPhoto("Painel · km 76.400",      ["#4a4034", "#251f17"]), label: "Painel · km 76.400",      status: "done" },
      { id: "p3", url: demoLaudoPhoto("Bomba d'água · peça nova", ["#35434a", "#161e22"]), label: "Bomba d'água · peça nova", status: "done" },
    ],
  }));
  // Gate de aprovação por OS (F1 OS-V2-3) — none|pending|approved|declined; default derivado do estágio.
  const [osApproval, setOsApproval] = useState({});
  // Trilha auditável por OS (F1 OS-V2-4) — eventos vivos somados ao seed FSM.
  const [osLog, setOsLog] = useState({});

  // Expõe ctx global pro StageGateMini nos cards do kanban ler sem prop-drilling.
  // E expõe OFICINA_REF (RECURSOS/MECANICOS/STAGES) pro oficina-forms.jsx acessar.
  useEffect(() => {
    window.__OFICINA_CTX = { osItems, osDvi };
  }, [osItems, osDvi]);
  useEffect(() => {
    window.OFICINA_REF = { RECURSOS, MECANICOS, STAGES, OS_LIST: osList };
  }, [osList]);
  // Costura CRM/Ficha → Oficina (segue a convenção `oimpresso:open-venda`/detail._id
  // do Sells/Index.charter@main; aqui `oimpresso:open-os`/os_id é a equivalente p/ OS).
  useEffect(() => {
    const onOpenOs = (e) => { const os = osList.find(o => o.id === (e.detail && e.detail.os_id)); if (os) setOpen(os); };
    const onNovaOs = (e) => { setEditingOs(null); setCreateOpen(true); if (e.detail && e.detail.frota) setToast({ kind: "ok", msg: `Nova OS a partir de ${e.detail.frota}` }); };
    window.addEventListener("oimpresso:open-os", onOpenOs);
    window.addEventListener("oimpresso:nova-os", onNovaOs);
    return () => { window.removeEventListener("oimpresso:open-os", onOpenOs); window.removeEventListener("oimpresso:nova-os", onNovaOs); };
  }, [osList]);

  const filtered = (() => {
    let out = osList;
    if (recursoFilter !== "all") out = out.filter(o => o.recurso === recursoFilter);
    if (kpiFilter) out = out.filter(o => kpiFilter === "urgent" ? o.urgent : o.stage === kpiFilter);
    const q = query.trim().toLowerCase();
    if (q) {
      out = out.filter(o =>
        o.plate.toLowerCase().includes(q) ||
        o.veh.toLowerCase().includes(q) ||
        o.client.toLowerCase().includes(q) ||
        o.symptom.toLowerCase().includes(q) ||
        ("#" + o.id).includes(q) ||
        o.id.includes(q)
      );
    }
    return out;
  })();

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

  const byCol = (() => {
    const m = {};
    pivot.forEach(c => { m[c.id] = filtered.filter(c.filter); });
    return m;
  })();

  const totals = {
    recepcao:    osList.filter(o => o.stage === "recepcao").length,
    diagnostico: osList.filter(o => o.stage === "diagnostico").length,
    pecas:       osList.filter(o => o.stage === "pecas").length,
    execucao:    osList.filter(o => o.stage === "execucao").length,
    pronto:      osList.filter(o => o.stage === "pronto").length,
    urgent:      osList.filter(o => o.urgent).length,
    valor:       osList.reduce((a, o) => a + valorNumOf(o.value), 0),
    approval:    osList.filter(o => o.stage === "pecas" && o.partsStatus === "approval").length,
  };

  // ── D-01/D-02 (TESTE) — uma máquina de avanço, duas portas: arrasto + botão do card ──
  // Avaliar→Testar (PROCESSO_MEMORIA_CC). NÃO toca no drawer travado — só o ABRE no checklist quando o gate barra.
  const [drag, setDrag] = useState(null);
  const [overCol, setOverCol] = useState(null);
  const [toast, setToast] = useState(null);
  const notify = (msg, kind = "ok") => setToast({ kind, msg });
  const kpiClick = (key) => { setKpiFilter(f => f === key ? null : key); setFocusedId(null); };
  useEffect(() => { if (!toast) return; const t = setTimeout(() => setToast(null), 3200); return () => clearTimeout(t); }, [toast]);
  // D-04+ · fecha o menu "Visão" ao clicar fora
  useEffect(() => {
    if (!adjOpen) return;
    const onDoc = (e) => { if (adjRef.current && !adjRef.current.contains(e.target)) setAdjOpen(false); };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, [adjOpen]);

  const ctxFor = (os) => {
    const its = osItems[os.id] || [];
    const dvi = osDvi[os.id] || [];
    return { dviCount: dvi.length, dviBad: dvi.filter(d => d.status === "bad").length, itemsCount: its.length, itemsDone: its.filter(i => i.done).length };
  };
  const gateOf = (os) => { const F = window.OficinaForms; return F && F.evalGate ? F.evalGate(os, ctxFor(os)) : null; };

  // Avanço único — a porta de ARRASTO e a porta de BOTÃO chamam isto. Gate é o guarda.
  const tryAdvance = (os) => {
    const g = gateOf(os);
    if (!g || !g.gate) return;                 // etapa terminal (sem próxima coluna)
    if (g.done === g.total) {
      setOsList(list => list.map(o => o.id === os.id ? { ...o, stage: g.gate.next } : o));
      setToast({ kind: "ok", msg: `OS #${os.id} avançou para ${g.gate.nextLabel}` });
    } else {
      const pend = g.total - g.done;
      setToast({ kind: "block", msg: `OS #${os.id} bloqueada · ${pend} requisito${pend === 1 ? "" : "s"} para ${g.gate.nextLabel}` });
      setOpen(os);                             // abre o drawer (travado) no checklist
    }
  };

  // D-07 · atalhos de teclado (Larissa é teclado-first: N nova · / busca · setas navegam · Enter abre · Esc fecha)
  useEffect(() => {
    const onKey = (e) => {
      const tag = (e.target.tagName || "").toLowerCase();
      const typing = tag === "input" || tag === "textarea" || tag === "select" || e.target.isContentEditable;
      if (e.key === "Escape") { setOpen(null); setCreateOpen(false); setAdjOpen(false); return; }
      if (typing) return;
      if (e.key === "/") { e.preventDefault(); searchRef.current && searchRef.current.focus(); return; }
      if (e.key === "n" || e.key === "N") { e.preventDefault(); setEditingOs(null); setCreateOpen(true); return; }
      const list = filtered;
      if (!list.length) return;
      if (e.key === "ArrowDown" || e.key === "ArrowRight") {
        e.preventDefault();
        setFocusedId(id => { const i = list.findIndex(o => o.id === id); return list[Math.min(list.length - 1, i + 1)].id; });
      } else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
        e.preventDefault();
        setFocusedId(id => { const i = list.findIndex(o => o.id === id); return list[i <= 0 ? 0 : i - 1].id; });
      } else if (e.key === "Enter" && focusedId) {
        const os = list.find(o => o.id === focusedId); if (os) setOpen(os);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [filtered, focusedId]);

  const dnd = (foco === "etapa") ? {
    drag, overCol,
    onDragStart: (os, e) => {
      const g = gateOf(os);
      const ready = !!(g && g.gate && g.done === g.total);
      setDrag({ id: os.id, from: os.stage, next: g && g.gate ? g.gate.next : null, nextLabel: g && g.gate ? g.gate.nextLabel : null, ready, pend: g ? g.total - g.done : 0 });
      try { e.dataTransfer.effectAllowed = "move"; e.dataTransfer.setData("text/plain", os.id); } catch (_) {}
    },
    onDragEnd: () => { setDrag(null); setOverCol(null); },
    onColDragOver: (colId, e) => { if (drag && colId === drag.next) { e.preventDefault(); setOverCol(colId); } },
    onColDragLeave: (colId) => { setOverCol(c => c === colId ? null : c); },
    onColDrop: (colId) => {
      const d = drag;
      setDrag(null); setOverCol(null);
      if (!d || colId !== d.next) return;
      const os = osList.find(o => o.id === d.id);
      if (os) tryAdvance(os);
    },
  } : null;

  return (
    <div className={"prod-page" + (view === "fila" ? " ofc-fila-mode" : "")} data-screen-label="01 Oficina Auto">
      <div className="prod-header">
        <div className="prod-header-l">
          <h1>Oficina Auto</h1>
          <p>Recepção, diagnóstico, peças, execução e entrega de veículos</p>
        </div>
        <div className="prod-header-r">
          <button className="os-btn ghost" onClick={() => { notify("Preparando impressão da fila…"); window.OficinaPrint && window.OficinaPrint.printFila(filtered, { filtro: recursoFilter !== "all" ? (recursoOf(recursoFilter) || {}).label : null }); }}><Ico.print size={11}/>Imprimir fila</button>
          <button className="os-btn primary" onClick={() => { setEditingOs(null); setCreateOpen(true); }}>
            <Ico.plus size={11}/>Nova OS
          </button>
        </div>
      </div>

      <div className={"prod-kpis" + (kpiFilter ? " ofc-kpi-filtering" : "")}>
        <div className={"prod-kpi" + (kpiFilter === "recepcao" ? " ofc-kpi-active" : "")} role="button" tabIndex={0} onClick={() => kpiClick("recepcao")}>
          <span className="prod-kpi-label">Recepção</span>
          <span className="prod-kpi-value">{totals.recepcao}</span>
          <span className="prod-kpi-sub">veículos aguardando triagem</span>
        </div>
        <div className={"prod-kpi" + (kpiFilter === "diagnostico" ? " ofc-kpi-active" : "")} role="button" tabIndex={0} onClick={() => kpiClick("diagnostico")}>
          <span className="prod-kpi-label">Em diagnóstico</span>
          <span className="prod-kpi-value">{totals.diagnostico}</span>
          <span className="prod-kpi-sub">{RECURSOS.length} boxes/elevadores</span>
        </div>
        <div className={"prod-kpi" + (kpiFilter === "pecas" ? " ofc-kpi-active" : "")} role="button" tabIndex={0} onClick={() => kpiClick("pecas")}>
          <span className="prod-kpi-label">Aguardando peças</span>
          <span className="prod-kpi-value">{totals.pecas}</span>
          <span className="prod-kpi-sub">{totals.approval} aguardam OK do cliente</span>
        </div>
        <div className={"prod-kpi" + (kpiFilter === "execucao" ? " ofc-kpi-active" : "")} role="button" tabIndex={0} onClick={() => kpiClick("execucao")}>
          <span className="prod-kpi-label">Em execução</span>
          <span className="prod-kpi-value">{totals.execucao}</span>
          <span className="prod-kpi-sub">boxes ocupados agora</span>
        </div>
        <div className={"prod-kpi prod-kpi-urgent" + (kpiFilter === "urgent" ? " ofc-kpi-active" : "")} role="button" tabIndex={0} onClick={() => kpiClick("urgent")}>
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
          Todos os boxes <span className="count">{osList.length}</span>
        </button>
        {RECURSOS.map(r => {
          const n = osList.filter(o => o.recurso === r.id).length;
          return (
            <button key={r.id} className={"prod-equip-tab" + (recursoFilter === r.id ? " active" : "")} onClick={() => setRecursoFilter(r.id)}>
              <span className={"prod-equip-dot " + r.cls}/>
              {r.label} <span className="count">{n}</span>
            </button>
          );
        })}
      </div>

      {/* Toolbar — busca + view toggle (sempre visível) */}
      <div className="ofc-view-toolbar">
        <div className="ofc-view-search">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input ref={searchRef} type="text" placeholder="placa · veículo · cliente · sintoma · #OS"
            value={query} onChange={e => setQuery(e.target.value)}/>
          {query && <button className="clear" onClick={() => setQuery("")} title="limpar"><Ico.x size={11}/></button>}
          {query && <span className="ct">{filtered.length} {filtered.length === 1 ? "OS" : "OSs"}</span>}
          {kpiFilter && <button className="ofc-kpi-clear" onClick={() => setKpiFilter(null)}><Ico.x size={9}/>limpar filtro</button>}
        </div>
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
          <button className={view === "fila" ? "active" : ""} onClick={() => setView("fila")}>
            <Ico.list size={11}/>Fila
          </button>
        </div>
        <div className="ofc-adjust" ref={adjRef}>
          <button type="button" className={"ofc-adjust-trigger" + (adjOpen ? " on" : "")} onClick={() => setAdjOpen(o => !o)} aria-haspopup="true" aria-expanded={adjOpen} title="Ajustar visão (foco · densidade · pressão)">
            <Ico.sliders size={13}/>Visão
          </button>
          {adjOpen && (
            <div className="ofc-adjust-pop" role="menu">
              <div className="ofc-adjust-row">
                <label>Foco</label>
                <Seg value={foco} set={setFoco} options={[
                  { value: "etapa", label: "Etapa" },
                  { value: "box", label: "Box" },
                  { value: "mecanico", label: "Mecânico" },
                ]}/>
              </div>
              <div className="ofc-adjust-row">
                <label>Densidade</label>
                <Seg value={densidade} set={setDensidade} options={[
                  { value: "compacto", label: "Compacto" },
                  { value: "padrao", label: "Padrão" },
                  { value: "detalhe", label: "Detalhe" },
                ]}/>
              </div>
              <div className="ofc-adjust-row">
                <label>Pressão</label>
                <Seg value={pressao} set={setPressao} options={[
                  { value: "calmo", label: "Calmo" },
                  { value: "padrao", label: "Padrão" },
                  { value: "pressao", label: "Pressão" },
                ]}/>
              </div>
            </div>
          )}
        </div>
      </div>

      {view === "kanban" && (
        <div className={"prod-kanban " + (foco === "etapa" ? "ofc-5" : "ofc-many")} style={foco !== "etapa" ? { "--ofc-cols": pivot.length } : undefined}>
          {pivot.map(c => (
            <ProdColumn key={c.id} stage={{ id: c.id, label: c.label, dot: c.dot }} items={byCol[c.id] || []}
              capacity={foco === "etapa" && c.id === "execucao" ? `${byCol.execucao.length}/${RECURSOS.length} boxes` : null}
              onOpen={setOpen} onAdvance={foco === "etapa" ? tryAdvance : undefined} dnd={dnd} onNotify={notify} focusedId={focusedId}/>
          ))}
        </div>
      )}

      {view === "fila" && window.OficinaFila && (
        <window.OficinaFila.FilaView
          list={filtered} osList={osList}
          selectedId={filaSel} setSelectedId={setFilaSel}
          osItems={osItems} setOsItems={setOsItems}
          osDvi={osDvi} setOsDvi={setOsDvi}
          onAdvance={tryAdvance}
          onOpenFull={(os) => setOpen(os)}/>
      )}

      {toast && (
        <div className={"ofc-toast ofc-toast-" + toast.kind} role="status">
          <span className="ofc-toast-ic">{toast.kind === "ok" ? <Ico.check size={13}/> : <Ico.alert size={13}/>}</span>
          {toast.msg}
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

      <Drawer
        os={open ? osList.find(o => o.id === open.id) || open : null}
        onClose={() => setOpen(null)}
        onToast={notify}
        onEdit={(os) => { setOpen(null); setEditingOs(os); setCreateOpen(true); }}
        onAdvance={(os, nextStage) => {
          setOsList(list => list.map(o => o.id === os.id ? { ...o, stage: nextStage } : o));
        }}
        osItems={osItems} setOsItems={setOsItems}
        osDvi={osDvi}     setOsDvi={setOsDvi}
        osPhotos={osPhotos} setOsPhotos={setOsPhotos}
        osApproval={osApproval} setOsApproval={setOsApproval}
        osLog={osLog} setOsLog={setOsLog}
      />

      {createOpen && window.OficinaForms?.OsCreateDrawer && (
        <window.OficinaForms.OsCreateDrawer
          initialOs={editingOs}
          onClose={() => { setCreateOpen(false); setEditingOs(null); }}
          onSaved={(os) => {
            setOsList(list => {
              const idx = list.findIndex(o => o.id === os.id);
              if (idx >= 0) {
                const next = [...list];
                next[idx] = os;
                return next;
              }
              return [os, ...list];
            });
            setOpen(os);
          }}
        />
      )}
    </div>
  );
}

// ── Page entry: oferece tweaks inline ao invés de TweaksPanel ──
function OficinaPage() {
  const ls = (k, d) => { try { return localStorage.getItem("oimpresso.oficina." + k) || d; } catch (e) { return d; } };
  const [foco,      setFoco]      = useState(() => ls("foco", "etapa"));
  const [densidade, setDensidade] = useState(() => ls("dens", "padrao"));
  const [pressao,   setPressao]   = useState(() => ls("mood", "padrao"));
  const [recursoFilter, setRecursoFilter] = useState("all");
  const [view, setView] = useState(() => ls("view", "kanban"));
  const [casos, setCasos] = useState(false);
  useEffect(() => {
    try {
      localStorage.setItem("oimpresso.oficina.foco", foco);
      localStorage.setItem("oimpresso.oficina.dens", densidade);
      localStorage.setItem("oimpresso.oficina.mood", pressao);
      localStorage.setItem("oimpresso.oficina.view", view);
    } catch (e) {}
  }, [foco, densidade, pressao, view]);

  const rootClass = [
    "oficina-root",
    "ofc-foco-" + foco,
    "ofc-density-" + densidade,
    "ofc-mood-" + pressao,
  ].join(" ");

  return (
    <div className={rootClass}>
      <ProducaoOficina
        foco={foco} setFoco={setFoco}
        densidade={densidade} setDensidade={setDensidade}
        pressao={pressao} setPressao={setPressao}
        recursoFilter={recursoFilter} setRecursoFilter={setRecursoFilter}
        view={view} setView={setView}/>
    </div>
  );
}

window.OficinaPage = OficinaPage;
})();

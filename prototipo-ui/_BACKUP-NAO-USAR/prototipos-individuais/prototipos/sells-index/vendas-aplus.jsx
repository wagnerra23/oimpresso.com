// vendas-aplus.jsx — F1 protótipo Vendas (meta A+ 9.5)
// 8 itens implementados:
// (1) identidade forest-green   (2) hero KPIs com sparkline/ageing
// (3) mini-stepper FSM inline    (4) avatar vendedor + comissão
// (5) tweaks Vista inline        (6) ⌘K rico
// (7) saved views + bulk actions (8) NF-e + NFS-e vinculados

const { useState, useMemo, useEffect, useRef } = React;

// ──────────────────────────────────────────────────────────────
// MOCK DATA
// ──────────────────────────────────────────────────────────────

// FSM: orcamento(0) → pedido(1) → faturada(2) → entregue(3) → paga(4)
const FSM_STEPS = ["Orçamento", "Pedido", "Faturada", "Entregue", "Paga"];
const FSM_LBLS  = ["orç", "ped", "fat", "ent", "pag"];

const VENDEDORES = {
  bruna:  { id: "bruna",  name: "Bruna",   abbr: "BR", av: 1, meta: 18000 },
  carlos: { id: "carlos", name: "Carlos",  abbr: "CR", av: 2, meta: 22000 },
  larissa:{ id: "larissa",name: "Larissa", abbr: "LA", av: 3, meta: 15000 },
  patrick:{ id: "patrick",name: "Patrick", abbr: "PT", av: 4, meta: 12000 },
};

// Fiscal status: ok | wait | bad | canc | na
const VENDAS = [
  {
    id: "V-7821", date: "2026-05-14", time: "14:32",
    client: "Padaria Estrela do Vale", clientNote: "Banner 3×2m fachada — recorrente",
    seller: "bruna",
    items: [
      { sku: "BAN-001", name: "Banner lona 3×2m fachada", qty: 1, unit: 1640, type: "produto" },
      { sku: "INS-002", name: "Instalação no local", qty: 1, unit: 200, type: "servico" },
    ],
    payment: "PIX", payTerm: 1,
    fsm: 4, // paga
    fiscal: {
      nfe:  { status: "ok",   numero: "001234", serie: "1", date: "2026-05-14T14:35", chave: "35260534567890000112550010000012341000123412" },
      nfse: { status: "ok",   numero: "00087",  serie: "—", date: "2026-05-14T14:38", chave: "21250014038720250000087001234561" },
    },
    urgent: false,
  },
  {
    id: "V-7822", date: "2026-05-14", time: "15:10",
    client: "Mercado União Atacarejo", clientNote: "Folder A4 4×4 cores · 2000un",
    seller: "carlos",
    items: [
      { sku: "FLD-A4", name: "Folder A4 4×4 — 2.000 un", qty: 1, unit: 3420, type: "produto" },
    ],
    payment: "Boleto 30d", payTerm: 30,
    fsm: 2,  // faturada
    fiscal: {
      nfe:  { status: "ok",   numero: "001235", serie: "1", date: "2026-05-14T15:12", chave: "35260534567890000112550010000012351000123512" },
      nfse: null,
    },
    urgent: false,
  },
  {
    id: "V-7823", date: "2026-05-14", time: "16:45",
    client: "Auto Posto Águia I-95", clientNote: "Adesivo bombas + placa LED",
    seller: "bruna",
    items: [
      { sku: "ADS-BMB", name: "Adesivo bombas (4 un)", qty: 4, unit: 220, type: "produto" },
      { sku: "PLC-LED", name: "Placa LED 80×40cm", qty: 1, unit: 1300, type: "produto" },
    ],
    payment: "Cartão 2×", payTerm: 30,
    fsm: 3,  // entregue
    fiscal: {
      nfe:  { status: "wait", numero: "001236", serie: "1", date: "2026-05-14T16:48", chave: "35260534567890000112550010000012361000123612" },
      nfse: null,
    },
    urgent: true,
  },
  {
    id: "V-7824", date: "2026-05-14", time: "17:20",
    client: "Consumidor Final", clientNote: "Cartão visita pronta-entrega",
    seller: "larissa",
    items: [
      { sku: "CRT-100", name: "Cartão visita 100un", qty: 1, unit: 80, type: "produto" },
    ],
    payment: "Dinheiro", payTerm: 0,
    fsm: 4,  // paga
    fiscal: { nfe: null, nfse: null },  // não emite (consumidor)
    urgent: false,
  },
  {
    id: "V-7825", date: "2026-05-13", time: "11:05",
    client: "Farmácia Vida Plena", clientNote: "Sacolas 5.000un · parcelado 3×",
    seller: "carlos",
    items: [
      { sku: "SAC-5K", name: "Sacola kraft 5.000un", qty: 1, unit: 4860, type: "produto" },
    ],
    payment: "Boleto 30/60/90", payTerm: 90,
    fsm: 2,  // faturada
    fiscal: {
      nfe:  { status: "ok",   numero: "001233", serie: "1", date: "2026-05-13T11:08", chave: "35260534567890000112550010000012331000123312" },
      nfse: null,
    },
    urgent: false,
  },
  {
    id: "V-7826", date: "2026-05-13", time: "13:48",
    client: "Pet Shop Latido Feliz", clientNote: "Cartão fidelidade + folder",
    seller: "bruna",
    items: [
      { sku: "CRT-FID", name: "Cartão fidelidade 500un", qty: 1, unit: 380, type: "produto" },
      { sku: "FLD-A5",  name: "Folder A5 200un",         qty: 1, unit: 600, type: "produto" },
    ],
    payment: "PIX", payTerm: 1,
    fsm: 4,  // paga
    fiscal: {
      nfe:  { status: "ok",   numero: "001232", serie: "1", date: "2026-05-13T13:50", chave: "35260534567890000112550010000012321000123212" },
      nfse: null,
    },
    urgent: false,
  },
  {
    id: "V-7827", date: "2026-05-13", time: "16:30",
    client: "Construtora Horizonte LTDA", clientNote: "Banner obra 6×3m + tapume 18m",
    seller: "carlos",
    items: [
      { sku: "BAN-OBR", name: "Banner obra 6×3m",         qty: 1, unit: 4200, type: "produto" },
      { sku: "TAP-18",  name: "Tapume impressão 18m",     qty: 1, unit: 3200, type: "produto" },
      { sku: "INS-OBR", name: "Instalação obra",          qty: 1, unit: 1540, type: "servico" },
    ],
    payment: "Boleto 60d", payTerm: 60,
    fsm: 1,  // pedido
    fiscal: {
      nfe:  null,
      nfse: null,
    },
    urgent: true,
  },
  {
    id: "V-7828", date: "2026-05-13", time: "10:15",
    client: "Salão Beleza Pura", clientNote: "Diagramação social media (15 posts)",
    seller: "patrick",
    items: [
      { sku: "SOC-15", name: "Pacote social media 15 posts", qty: 1, unit: 420, type: "servico" },
    ],
    payment: "PIX", payTerm: 1,
    fsm: 4,  // paga
    fiscal: {
      nfe:  null,
      nfse: { status: "ok",   numero: "00086", serie: "—", date: "2026-05-13T10:18", chave: "21250013510860010000086001234560" },
    },
    urgent: false,
  },
  {
    id: "V-7829", date: "2026-05-12", time: "14:50",
    client: "Distribuidora Brasil Foods", clientNote: "Catálogo A4 200un · parcelado 6×",
    seller: "carlos",
    items: [
      { sku: "CAT-A4", name: "Catálogo A4 100p · 200un", qty: 1, unit: 11200, type: "produto" },
      { sku: "DGR-CAT",name: "Diagramação catálogo",     qty: 1, unit: 1200,  type: "servico" },
    ],
    payment: "Boleto 6×", payTerm: 180,
    fsm: 2,  // faturada
    fiscal: {
      nfe:  { status: "ok",   numero: "001231", serie: "1", date: "2026-05-12T14:52", chave: "35260534567890000112550010000012311000123112" },
      nfse: { status: "ok",   numero: "00085", serie: "—", date: "2026-05-12T14:55", chave: "21250013510850010000085001234560" },
    },
    urgent: false,
  },
  {
    id: "V-7830", date: "2026-05-12", time: "11:22",
    client: "Restaurante Sabor de Casa", clientNote: "Cardápio impresso 50un",
    seller: "bruna",
    items: [
      { sku: "CRD-50", name: "Cardápio plastificado 50un", qty: 1, unit: 1320, type: "produto" },
    ],
    payment: "Cartão", payTerm: 30,
    fsm: 2,  // faturada (NF-e rejeitada)
    fiscal: {
      nfe:  { status: "bad",  numero: "001230", serie: "1", date: "2026-05-12T11:25", chave: "35260534567890000112550010000012301000123012", failReason: "Rejeição 539: NCM divergente do cadastro SEFAZ" },
      nfse: null,
    },
    urgent: false,
  },
  {
    id: "V-7831", date: "2026-05-12", time: "09:48",
    client: "Studio Alfa Design", clientNote: "Adesivo carro 4 lados",
    seller: "larissa",
    items: [
      { sku: "ADS-CAR", name: "Adesivo recorte carro",    qty: 1, unit: 680, type: "produto" },
    ],
    payment: "PIX", payTerm: 1,
    fsm: 4,  // paga
    fiscal: {
      nfe:  { status: "canc", numero: "001229", serie: "1", date: "2026-05-12T09:51", chave: "35260534567890000112550010000012291000122912", cancelReason: "Cliente alterou medidas — re-emitida" },
      nfse: null,
    },
    urgent: false,
  },
  {
    id: "V-7832", date: "2026-05-12", time: "15:30",
    client: "Padaria Pão Quente", clientNote: "Etiquetas adesivas 1000un",
    seller: "patrick",
    items: [
      { sku: "ETQ-1K", name: "Etiqueta adesiva 1000un",   qty: 1, unit: 290, type: "produto" },
    ],
    payment: "Boleto 30d", payTerm: 30,
    fsm: 1,  // pedido
    fiscal: { nfe: null, nfse: null },
    urgent: false,
  },
];

// ──────────────────────────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────────────────────────
const fmt = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtShort = (n) => {
  if (n >= 1000) return "R$ " + (n/1000).toFixed(1).replace(".",",") + "k";
  return fmt(n);
};
const totalOf = (v) => v.items.reduce((s, i) => s + i.qty * i.unit, 0);
const hasProduto = (v) => v.items.some(i => i.type === "produto");
const hasServico = (v) => v.items.some(i => i.type === "servico");
const isToday    = (v) => v.date === "2026-05-14";
const commissionOf = (v) => totalOf(v) * 0.035; // 3.5% padrão

const STATUS_OF = (v) => {
  if (v.fsm === 4) return "paga";
  if (v.fsm === 2 || v.fsm === 3) return "faturada";
  if (v.fsm === 1) return "pendente";
  if (v.fsm === 0) return "orcamento";
  return "cancelada";
};

const formatChave = (k) => k.replace(/(\d{4})/g, "$1 ").trim();

// ──────────────────────────────────────────────────────────────
// SMALL ICONS (lucide-flavored, inline)
// ──────────────────────────────────────────────────────────────
const Ic = {
  search: (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>,
  plus:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M5 12h14M12 5v14"/></svg>,
  download:(p)=> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>,
  printer:(p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>,
  mail:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 5L2 7"/></svg>,
  file:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>,
  x:      (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M18 6 6 18M6 6l12 12"/></svg>,
  copy:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>,
  trend:  (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>,
  list:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1.2"/><circle cx="3.5" cy="12" r="1.2"/><circle cx="3.5" cy="18" r="1.2"/></svg>,
  pkg:    (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>,
  cart:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>,
  bolt:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>,
  users:  (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
  check:  (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" {...p}><polyline points="20 6 9 17 4 12"/></svg>,
  filter: (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>,
  home:   (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>,
  inbox:  (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>,
};

// ──────────────────────────────────────────────────────────────
// SPARKLINE
// ──────────────────────────────────────────────────────────────
function Sparkline({ data, color = "currentColor", fill = true }) {
  const w = 240, h = 32, pad = 2;
  const min = Math.min(...data), max = Math.max(...data);
  const dx = (w - pad*2) / (data.length - 1);
  const pts = data.map((v, i) => {
    const x = pad + i * dx;
    const y = h - pad - ((v - min) / (max - min || 1)) * (h - pad*2);
    return [x, y];
  });
  const linePath = "M" + pts.map(p => p.join(",")).join(" L");
  const areaPath = linePath + ` L${pts[pts.length-1][0]},${h} L${pts[0][0]},${h} Z`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none">
      {fill && <path d={areaPath} fill={color} opacity="0.18"/>}
      <path d={linePath} fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
      <circle cx={pts[pts.length-1][0]} cy={pts[pts.length-1][1]} r="2.5" fill={color}/>
    </svg>
  );
}

// ──────────────────────────────────────────────────────────────
// MINI STEPPER FSM (inline na linha)
// ──────────────────────────────────────────────────────────────
function StepperInline({ fsm }) {
  return (
    <span className="stepper" title={`${FSM_STEPS[fsm]} — etapa ${fsm+1} de 5`}>
      {FSM_STEPS.map((_, i) => {
        let cls = "dot";
        if (i < fsm) cls += " done";
        else if (i === fsm) cls += " current";
        return <span key={i} className={cls}/>;
      })}
      <span className="lbl">{FSM_LBLS[fsm]}</span>
    </span>
  );
}

// ──────────────────────────────────────────────────────────────
// FISCAL BADGE (na lista)
// ──────────────────────────────────────────────────────────────
function FBadge({ kind, status, doc }) {
  const lbl = kind === "nfe" ? "NF-e" : "NFS-e";
  const map = {
    ok:   { cls: "ok",   ic: "✓",  tip: doc ? `Autorizada · ${doc.numero}/${doc.serie}` : "" },
    wait: { cls: "wait", ic: "⌛", tip: "Transmitida · aguardando SEFAZ" },
    bad:  { cls: "bad",  ic: "✕",  tip: "Rejeitada SEFAZ" },
    canc: { cls: "canc", ic: "⊘",  tip: "Cancelada" },
    na:   { cls: "na",   ic: "—",  tip: "Não emitida" },
  };
  const m = map[status] || map.na;
  return (
    <span className={`fbadge ${m.cls}`}>
      <span className="ic">{m.ic}</span>{lbl}
      {m.tip && <span className="tip">{m.tip}</span>}
    </span>
  );
}

function FiscalCell({ v }) {
  const nfeS  = v.fiscal.nfe  ? v.fiscal.nfe.status  : (hasProduto(v) ? "na" : null);
  const nfseS = v.fiscal.nfse ? v.fiscal.nfse.status : (hasServico(v) ? "na" : null);
  return (
    <span className="fiscal-cell">
      {nfeS  && <FBadge kind="nfe"  status={nfeS}  doc={v.fiscal.nfe}/>}
      {nfseS && <FBadge kind="nfse" status={nfseS} doc={v.fiscal.nfse}/>}
      {!nfeS && !nfseS && <span className="fbadge na">—</span>}
    </span>
  );
}

// ──────────────────────────────────────────────────────────────
// FISCAL CARD (drawer)
// ──────────────────────────────────────────────────────────────
function FiscalCard({ kind, doc }) {
  const [copied, setCopied] = useState(false);
  const lbl = kind === "nfe" ? "NF-e" : "NFS-e";
  const danfeLbl = kind === "nfe" ? "DANFE PDF" : "DANFS-e PDF";
  const isFail = doc.status === "bad";
  const isCanc = doc.status === "canc";

  // SEFAZ timeline:
  // 1. Emitida (sempre done)
  // 2. Transmitida (sempre done se chegou aqui)
  // 3. Autorizada (done se ok/canc, failed se bad)
  // 4. Entregue ao destinatário (done se ok)
  // 5. Cancelada (done apenas se canc)
  const tlSteps = [
    { lbl: "Emitida",     done: true },
    { lbl: "Transmitida", done: true },
    { lbl: "Autorizada",  done: doc.status === "ok" || doc.status === "canc", failed: isFail },
    { lbl: "E-mail OK",   done: doc.status === "ok" },
    { lbl: isCanc ? "Cancelada" : "Aprovada", done: doc.status === "ok" || isCanc },
  ];

  const statusLbl = {
    ok:   "Autorizada",
    wait: "Processando",
    bad:  "Rejeitada",
    canc: "Cancelada",
  }[doc.status];
  const statusCls = {
    ok: "ok", wait: "wait", bad: "bad", canc: "canc",
  }[doc.status];

  const copy = () => {
    navigator.clipboard?.writeText(doc.chave);
    setCopied(true);
    setTimeout(() => setCopied(false), 1200);
  };

  return (
    <div className={`fcard ${isFail ? "failed" : ""}`}>
      <div className="fh">
        <h4>{lbl}</h4>
        <span className={`fbadge ${statusCls}`}>
          <span className="ic">{ {ok:"✓",wait:"⌛",bad:"✕",canc:"⊘"}[doc.status] }</span>
          {statusLbl}
        </span>
        <div className="right" style={{fontSize:11,color:"var(--text-mute)",fontFamily:"var(--font-mono)"}}>
          {doc.date.split("T")[0].split("-").reverse().join("/")}
          <span style={{marginLeft:6}}>{doc.date.split("T")[1]?.slice(0,5)}</span>
        </div>
      </div>

      {isFail && (
        <div className="fail-msg">
          <b>Motivo da rejeição SEFAZ</b>
          {doc.failReason}
        </div>
      )}
      {isCanc && doc.cancelReason && (
        <div className="fail-msg" style={{background:"var(--neutral-soft)", borderLeftColor:"var(--neutral)", color:"var(--text-dim)"}}>
          <b>Motivo do cancelamento</b>
          {doc.cancelReason}
        </div>
      )}

      <dl className="fmeta">
        <dt>Número</dt><dd>{doc.numero}</dd>
        <dt>Série</dt><dd>{doc.serie}</dd>
      </dl>

      <div className="chave">
        <span className="chave-num">{formatChave(doc.chave)}</span>
        <button className={`copy-btn ${copied ? "copied" : ""}`} onClick={copy}>
          {copied ? "Copiado ✓" : "Copiar"}
        </button>
      </div>

      <div className="ftimeline">
        {tlSteps.map((s, i) => (
          <div key={i} className={`fstep ${s.done ? "done" : ""} ${s.failed ? "failed" : ""}`}>
            <span className="sd">{s.failed ? "✕" : s.done ? "✓" : i+1}</span>
            <span className="stl">{s.lbl}</span>
          </div>
        ))}
      </div>

      <details className="fcce">
        <summary>+ CC-e (Carta de Correção)</summary>
        <p>Nenhuma carta de correção emitida pra este documento.</p>
      </details>

      <div className="fctas">
        <button className="fcta"><Ic.download/> {danfeLbl}</button>
        <button className="fcta"><Ic.file/> XML</button>
        <button className="fcta"><Ic.mail/> Enviar</button>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// MAIN APP
// ──────────────────────────────────────────────────────────────
function VendasAPP() {
  // VIEW STATE
  const [vista, setVista] = useState("caixa"); // caixa | faturamento | comissao
  const [statusF, setStatusF] = useState("todas");
  const [savedView, setSavedView] = useState("hoje");
  const [viewsOpen, setViewsOpen] = useState(false);
  const [openId, setOpenId] = useState(null);
  const [drawerTab, setDrawerTab] = useState("itens");
  const [fiscalSubTab, setFiscalSubTab] = useState("nfe");
  const [palOpen, setPalOpen] = useState(false);
  const [palQ, setPalQ] = useState("");
  const [palSel, setPalSel] = useState(0);
  const [selected, setSelected] = useState(new Set());

  // ao abrir uma venda mista, default sub-tab = ambos
  useEffect(() => {
    if (!openId) return;
    const v = VENDAS.find(x => x.id === openId);
    if (v && v.fiscal.nfe && v.fiscal.nfse) setFiscalSubTab("ambos");
    else setFiscalSubTab("nfe");
  }, [openId]);

  // body data-vista
  useEffect(() => {
    document.body.dataset.vista = vista;
  }, [vista]);

  // shortcuts
  useEffect(() => {
    const onKey = (e) => {
      const inField = ["INPUT","TEXTAREA"].includes(e.target.tagName);
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setPalOpen(true);
      }
      if (e.key === "Escape") {
        setPalOpen(false);
        setViewsOpen(false);
        if (openId) setOpenId(null);
      }
      if (!inField && (e.key === "n" || e.key === "N")) {
        e.preventDefault();
        alert("Atalho N — Nova venda (drawer Create — não renderizado neste F1)");
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [openId]);

  // filtros
  const filtered = useMemo(() => {
    let out = VENDAS;
    if (savedView === "hoje")       out = out.filter(v => isToday(v));
    if (savedView === "pendentes")  out = out.filter(v => v.fsm < 4);
    if (savedView === "faturadas")  out = out.filter(v => v.fsm === 2);
    if (savedView === "atrasadas")  out = out.filter(v => v.urgent && v.fsm < 4);
    if (savedView === "rejeitadas") out = out.filter(v => v.fiscal.nfe?.status === "bad" || v.fiscal.nfse?.status === "bad");
    if (statusF !== "todas")        out = out.filter(v => STATUS_OF(v) === statusF);
    return out;
  }, [savedView, statusF]);

  // KPIs (depende de filtro Hoje)
  const todaySales = VENDAS.filter(isToday);
  const kpi_total  = todaySales.reduce((s,v)=> s + totalOf(v), 0);
  const kpi_count  = todaySales.length;
  const kpi_avg    = kpi_count ? kpi_total / kpi_count : 0;
  const kpi_ar     = VENDAS.filter(v => v.fsm < 4).reduce((s,v)=> s + totalOf(v), 0);
  const kpi_pix    = todaySales.filter(v => v.payment === "PIX").reduce((s,v)=> s + totalOf(v), 0);
  const kpi_pixPct = kpi_total ? Math.round((kpi_pix / kpi_total) * 100) : 0;

  // KPIs comissão (vista=comissao)
  const myCommission = VENDAS.filter(v => v.seller === "bruna" && isToday(v))
    .reduce((s,v) => s + commissionOf(v), 0);

  // KPIs faturamento (vista=faturamento)
  const totalFiscais = VENDAS.reduce((acc, v) => {
    if (v.fiscal.nfe?.status === "ok")  acc.ok++;
    if (v.fiscal.nfe?.status === "wait")acc.wait++;
    if (v.fiscal.nfe?.status === "bad") acc.bad++;
    if (v.fiscal.nfse?.status === "ok") acc.ok++;
    if (v.fiscal.nfse?.status === "wait")acc.wait++;
    if (v.fiscal.nfse?.status === "bad")acc.bad++;
    return acc;
  }, { ok: 0, wait: 0, bad: 0 });

  // sparkline data (30d faturamento simulado)
  const sparkData = [3.2,2.8,4.1,3.6,4.8,5.2,3.9,4.4,5.8,4.6,5.1,6.3,5.4,4.9,6.8,7.2,5.9,6.4,7.8,6.2,7.5,8.4,6.9,7.8,9.1,8.2,7.6,8.9,9.4,kpi_total/1000];

  // ageing for "a receber"
  const arItems = VENDAS.filter(v => v.fsm < 4);
  const ar_ok = arItems.filter(v => v.payTerm <= 30).reduce((s,v)=>s+totalOf(v),0);
  const ar_w  = arItems.filter(v => v.payTerm > 30 && v.payTerm <= 60).reduce((s,v)=>s+totalOf(v),0);
  const ar_b  = arItems.filter(v => v.payTerm > 60).reduce((s,v)=>s+totalOf(v),0);
  const ar_tot = ar_ok + ar_w + ar_b || 1;

  // counts por status
  const countBy = (s) => {
    if (s === "todas") return filtered.length;
    return filtered.filter(v => STATUS_OF(v) === s).length;
  };

  // selection
  const toggleSel = (id) => {
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };
  const toggleAll = () => {
    if (selected.size === filtered.length) setSelected(new Set());
    else setSelected(new Set(filtered.map(v => v.id)));
  };

  const openVenda = filtered.find(v => v.id === openId) || VENDAS.find(v => v.id === openId);

  // ⌘K palette items
  const palAll = useMemo(() => {
    const recent = [...VENDAS].slice(0, 3).map(v => ({
      grp: "Últimas vendas",
      ico: <Ic.cart/>,
      title: v.id + " · " + v.client,
      sub: fmt(totalOf(v)) + " · " + v.payment,
      action: () => { setOpenId(v.id); setPalOpen(false); },
    }));
    const shortcuts = [
      { grp: "Ações", ico: <Ic.bolt/>,    title: "Nova venda PIX rápida",     sub: "Pula direto pra modal de balcão",    kbd: "⇧N", action: () => alert("Nova venda PIX rápida") },
      { grp: "Ações", ico: <Ic.plus/>,    title: "Nova venda completa",       sub: "Drawer de cadastro completo",         kbd: "N",  action: () => alert("Nova venda") },
      { grp: "Ações", ico: <Ic.file/>,    title: "Emitir NF-e em lote",       sub: "Pra todas vendas faturadas selecionadas", action: () => alert("Emit lote") },
      { grp: "Buscar",ico: <Ic.search/>,  title: "Buscar por chave SEFAZ",    sub: "44 dígitos da NF-e ou NFS-e",         action: () => alert("Buscar SEFAZ") },
      { grp: "Buscar",ico: <Ic.users/>,   title: "Buscar por cliente",        sub: "Nome, CNPJ ou contato",               action: () => alert("Buscar cliente") },
      { grp: "Navegar", ico: <Ic.list/>,  title: "Ir pra Orçamentos",         sub: "Módulo Sells/Orçamentos",            action: () => alert("→ Orçamentos") },
      { grp: "Navegar", ico: <Ic.users/>, title: "Ir pra Clientes",            sub: "Módulo Clientes",                    action: () => alert("→ Clientes") },
    ];
    return [...recent, ...shortcuts];
  }, []);

  const palFiltered = useMemo(() => {
    if (!palQ.trim()) return palAll;
    const q = palQ.toLowerCase();
    return palAll.filter(it => it.title.toLowerCase().includes(q) || it.sub.toLowerCase().includes(q));
  }, [palAll, palQ]);

  const runPal = (it) => it && it.action && it.action();

  // grupos do palette
  const palGroups = useMemo(() => {
    const out = []; let last = null;
    palFiltered.forEach((it, i) => {
      if (it.grp !== last) { out.push({ header: it.grp }); last = it.grp; }
      out.push({ item: it, idx: i });
    });
    return out;
  }, [palFiltered]);

  return (
    <div className="page">

      {/* SIDEBAR — só visual */}
      <aside className="sb">
        <div className="sb-logo">Oi</div>
        <button className="sb-item" title="Início"><Ic.home/></button>
        <button className="sb-item" title="Inbox"><Ic.inbox/></button>
        <button className="sb-item active" title="Vendas"><Ic.cart/></button>
        <button className="sb-item" title="Clientes"><Ic.users/></button>
        <button className="sb-item" title="Produtos"><Ic.pkg/></button>
        <button className="sb-item" title="Relatórios"><Ic.trend/></button>
      </aside>

      <div className="main">

        {/* HEADER */}
        <header className="hdr">
          <div className="hdr-row">
            <div className="hdr-title">
              <h1>Vendas</h1>
              <span className="sub">Pedidos · faturamento · NF-e/NFS-e</span>
            </div>

            <button className="cmdk" onClick={() => setPalOpen(true)}>
              <Ic.search style={{width:13,height:13}}/>
              <span className="ph">Buscar venda, cliente, chave SEFAZ…</span>
              <kbd>⌘K</kbd>
            </button>

            <div className="hdr-r">
              <div className="vista" role="group" aria-label="Vista">
                <button className={vista==="caixa"?"on":""}      onClick={()=>setVista("caixa")}>Caixa</button>
                <button className={vista==="faturamento"?"on":""}onClick={()=>setVista("faturamento")}>Faturamento</button>
                <button className={vista==="comissao"?"on":""}   onClick={()=>setVista("comissao")}>Comissão</button>
              </div>

              <div className="views">
                <button className="views-btn" onClick={()=>setViewsOpen(v=>!v)}>
                  {savedView === "hoje" ? "Hoje" : savedView === "pendentes" ? "Pendentes" : savedView === "faturadas" ? "Faturadas semana" : savedView === "atrasadas" ? "Atrasadas" : "Rejeitadas SEFAZ"}
                </button>
                {viewsOpen && (
                  <div className="views-menu" onMouseLeave={()=>setViewsOpen(false)}>
                    <div className={`views-item ${savedView==="hoje" ? "active" : ""}`}    onClick={()=>{setSavedView("hoje"); setViewsOpen(false);}}>
                      Hoje <span className="ct">{VENDAS.filter(isToday).length}</span>
                    </div>
                    <div className={`views-item ${savedView==="pendentes" ? "active" : ""}`}onClick={()=>{setSavedView("pendentes"); setViewsOpen(false);}}>
                      Pendentes pagamento <span className="ct">{VENDAS.filter(v=>v.fsm<4).length}</span>
                    </div>
                    <div className={`views-item ${savedView==="faturadas" ? "active" : ""}`}onClick={()=>{setSavedView("faturadas"); setViewsOpen(false);}}>
                      Faturadas semana <span className="ct">{VENDAS.filter(v=>v.fsm===2).length}</span>
                    </div>
                    <div className={`views-item ${savedView==="atrasadas" ? "active" : ""}`}onClick={()=>{setSavedView("atrasadas"); setViewsOpen(false);}}>
                      Atrasadas <span className="ct">{VENDAS.filter(v=>v.urgent && v.fsm<4).length}</span>
                    </div>
                    <div className={`views-item ${savedView==="rejeitadas" ? "active" : ""}`}onClick={()=>{setSavedView("rejeitadas"); setViewsOpen(false);}}>
                      Rejeitadas SEFAZ <span className="ct">{VENDAS.filter(v=>v.fiscal.nfe?.status==="bad" || v.fiscal.nfse?.status==="bad").length}</span>
                    </div>
                    <div className="views-sep"/>
                    <div className="views-item" style={{color:"var(--text-mute)",fontSize:11.5}}>
                      <Ic.filter style={{width:11,height:11}}/> Salvar vista atual…
                    </div>
                  </div>
                )}
              </div>

              <button className="btn ghost"><Ic.printer/> Imprimir caixa</button>
              <button className="btn primary" onClick={()=>alert("Nova venda")}>
                <Ic.plus/> Nova venda <kbd>N</kbd>
              </button>
            </div>
          </div>
        </header>

        <div className="body">

          {/* KPIs */}
          <div className="kpis">
            {/* 1. Hero faturado hoje com sparkline */}
            <div className="kpi hero">
              <span className="lbl">Faturado hoje</span>
              <b>{fmtShort(kpi_total)}</b>
              <div className="delta up"><Ic.trend style={{width:11,height:11}}/> +18% vs ontem · {kpi_count} vendas</div>
              <div className="spark">
                <Sparkline data={sparkData} color="oklch(0.72 0.10 155)"/>
              </div>
            </div>

            {/* 2. Ticket médio com Δ% */}
            <div className="kpi">
              <span className="lbl">Ticket médio</span>
              <b>{fmtShort(kpi_avg)}</b>
              <div className="delta up">↑ 12% vs semana passada</div>
              {vista === "comissao" && (
                <div style={{marginTop:10,fontSize:11.5,color:"var(--text-dim)"}}>
                  <b style={{fontSize:14,fontFamily:"var(--font-mono)"}}>{fmtShort(myCommission)}</b>{" "}
                  comissão Bruna hoje
                </div>
              )}
            </div>

            {/* 3. A receber com ageing visual */}
            <div className="kpi">
              <span className="lbl">A receber</span>
              <b>{fmtShort(kpi_ar)}</b>
              <div className="delta">{arItems.length} vendas em aberto</div>
              <div className="ageing">
                <div className="ag-bar ok">  <div className="fill" style={{width: (ar_ok/ar_tot*100)+"%"}}/></div>
                <div className="ag-bar warn"><div className="fill" style={{width: (ar_w/ar_tot*100)+"%"}}/></div>
                <div className="ag-bar bad"> <div className="fill" style={{width: (ar_b/ar_tot*100)+"%"}}/></div>
                <div className="ag-lbls">
                  <span>0–30d</span><span>31–60d</span><span>+60d</span>
                </div>
              </div>
            </div>

            {/* 4. PIX hoje OU Fiscal pendente OU Comissão equipe */}
            {vista === "caixa" && (
              <div className="kpi">
                <span className="lbl">PIX hoje</span>
                <b>{fmtShort(kpi_pix)}<small> / {fmtShort(kpi_total)}</small></b>
                <div className="delta">{kpi_pixPct}% do faturamento — imediato</div>
                <div className="pix-prog"><div className="fill" style={{width: kpi_pixPct+"%"}}/></div>
              </div>
            )}
            {vista === "faturamento" && (
              <div className="kpi">
                <span className="lbl">Notas fiscais</span>
                <b>{totalFiscais.ok}<small>/{totalFiscais.ok+totalFiscais.wait+totalFiscais.bad}</small></b>
                <div className="delta">autorizadas · {totalFiscais.wait} processando · {totalFiscais.bad} rejeitadas</div>
                <div style={{display:"flex",gap:2,marginTop:10,height:6,borderRadius:99,overflow:"hidden",background:"var(--border-2)"}}>
                  <div style={{flex: totalFiscais.ok,  background:"var(--ok)"}}/>
                  <div style={{flex: totalFiscais.wait,background:"var(--warn)"}}/>
                  <div style={{flex: totalFiscais.bad, background:"var(--bad)"}}/>
                </div>
              </div>
            )}
            {vista === "comissao" && (
              <div className="kpi">
                <span className="lbl">Ranking vendedores · mês</span>
                {Object.values(VENDEDORES).map((s, i) => {
                  const sales = VENDAS.filter(v => v.seller === s.id);
                  const total = sales.reduce((acc, v) => acc + totalOf(v), 0);
                  const pct = Math.min(100, (total / s.meta) * 100);
                  return (
                    <div key={s.id} style={{display:"flex",alignItems:"center",gap:8,marginTop: i===0 ? 8 : 6}}>
                      <span className={`av av-${s.av}`}>{s.abbr}</span>
                      <span style={{flex:1,fontSize:11.5}}>
                        <div style={{display:"flex",justifyContent:"space-between",fontWeight:600,marginBottom:2}}>
                          <span>{s.name}</span>
                          <span style={{fontFamily:"var(--font-mono)",fontSize:10.5,color:"var(--text-dim)"}}>{fmtShort(total)}</span>
                        </div>
                        <div style={{height:3,borderRadius:99,background:"var(--border-2)",overflow:"hidden"}}>
                          <div style={{height:"100%",width:pct+"%",background: pct >= 100 ? "var(--ok)" : "var(--sells)"}}/>
                        </div>
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* STATUS TABS */}
          <div className="tabs">
            {["todas","paga","pendente","faturada","cancelada"].map(s => (
              <button key={s} className={`tab ${statusF===s ? "on" : ""}`} onClick={()=>setStatusF(s)}>
                {s === "todas" ? "Todas" : s.charAt(0).toUpperCase() + s.slice(1)}
                <span className="ct">{countBy(s)}</span>
              </button>
            ))}
          </div>

          {/* TABLE */}
          <div className="panel">
            <table>
              <thead>
                <tr>
                  <th style={{width:26,padding:"0 0 0 14px"}}>
                    <input type="checkbox"
                           checked={selected.size > 0 && selected.size === filtered.length}
                           onChange={toggleAll}/>
                  </th>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Vendedor</th>
                  <th>Pipeline</th>
                  <th>Fiscal</th>
                  <th>Pagamento</th>
                  <th className="num">Total</th>
                  <th className="num commission">Comissão</th>
                  <th className="tax-info">Status fiscal</th>
                  <th style={{width:90}}></th>
                </tr>
              </thead>
              <tbody>
                {filtered.map(v => {
                  const t = totalOf(v);
                  const sel = selected.has(v.id);
                  const status = STATUS_OF(v);
                  return (
                    <tr key={v.id}
                        className={`${sel ? "selected" : ""} ${v.urgent ? "urgent" : ""}`}
                        onClick={()=>setOpenId(v.id)}>
                      <td className="chk" onClick={e=>e.stopPropagation()}>
                        <input type="checkbox" checked={sel} onChange={()=>toggleSel(v.id)}/>
                      </td>
                      <td className="id">{v.id}</td>
                      <td className="client">
                        <b>{v.client}</b>
                        <small>{v.clientNote}</small>
                      </td>
                      <td>
                        <div className="seller" style={{display:"flex",alignItems:"center",gap:8}}>
                          <span className={`av av-${VENDEDORES[v.seller].av}`}>{VENDEDORES[v.seller].abbr}</span>
                          <span style={{display:"flex",flexDirection:"column",lineHeight:1.2}}>
                            <b style={{fontSize:12,fontWeight:600}}>{VENDEDORES[v.seller].name}</b>
                            <small style={{fontSize:10.5,color:"var(--text-mute)",fontFamily:"var(--font-mono)"}}>
                              {v.date.slice(-5).replace("-","/")} {v.time}
                            </small>
                          </span>
                        </div>
                      </td>
                      <td><StepperInline fsm={v.fsm}/></td>
                      <td><FiscalCell v={v}/></td>
                      <td style={{fontSize:11.5,color:"var(--text-dim)"}}>
                        <span style={{fontWeight:600,color:"var(--text)"}}>{v.payment}</span>
                      </td>
                      <td className="num">{fmt(t)}</td>
                      <td className="num commission" style={{color:"var(--sells)",fontWeight:700}}>
                        {fmt(commissionOf(v))}
                      </td>
                      <td className="tax-info" style={{fontSize:11}}>
                        <span className={`sbadge ${status}`}>{status}</span>
                      </td>
                      <td onClick={e=>e.stopPropagation()}>
                        <div className="rowact">
                          <button className="btn ghost" title="Baixar XML"><Ic.download/></button>
                          <button className="btn ghost" title="Imprimir"><Ic.printer/></button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
                {filtered.length === 0 && (
                  <tr><td colSpan="11" style={{textAlign:"center",padding:40,color:"var(--text-mute)",fontStyle:"italic"}}>
                    Nenhuma venda encontrada.
                  </td></tr>
                )}
              </tbody>
            </table>
          </div>

        </div>
      </div>

      {/* BULK ACTION BAR */}
      <div className={`bulk ${selected.size > 0 ? "on" : ""}`}>
        <span className="ct">{selected.size} selecionadas</span>
        <button className="b-btn primary"><Ic.file/> Emitir NF-e em lote</button>
        <button className="b-btn"><Ic.check/> Marcar como pagas</button>
        <button className="b-btn"><Ic.download/> Exportar XML/PDF</button>
        <button className="b-btn"><Ic.mail/> Lembrete interno</button>
        <button className="b-close" onClick={()=>setSelected(new Set())}>✕</button>
      </div>

      {/* DRAWER */}
      <div className={`drw-bd ${openId ? "on" : ""}`} onClick={()=>setOpenId(null)}/>
      <div className={`drw ${openId ? "on" : ""}`}>
        {openVenda && <DrawerContent v={openVenda} tab={drawerTab} setTab={setDrawerTab}
                                     fSub={fiscalSubTab} setFSub={setFiscalSubTab}
                                     onClose={()=>setOpenId(null)}/>}
      </div>

      {/* ⌘K PALETTE */}
      <div className={`pal-bd ${palOpen ? "on" : ""}`} onClick={()=>setPalOpen(false)}>
        <div className="pal" onClick={e=>e.stopPropagation()}>
          <div className="pal-in">
            <Ic.search style={{width:18,height:18,color:"var(--text-mute)"}}/>
            <input autoFocus placeholder="Buscar venda, cliente, chave SEFAZ, ações…"
                   value={palQ}
                   onChange={e=>{ setPalQ(e.target.value); setPalSel(0); }}
                   onKeyDown={e=>{
                     if (e.key === "ArrowDown") { setPalSel(s => Math.min(s+1, palFiltered.length-1)); e.preventDefault(); }
                     if (e.key === "ArrowUp")   { setPalSel(s => Math.max(s-1, 0));                   e.preventDefault(); }
                     if (e.key === "Enter")     { runPal(palFiltered[palSel]); }
                   }}/>
            <kbd>esc</kbd>
          </div>
          <div className="pal-list">
            {palGroups.length === 0 && <div style={{padding:24,textAlign:"center",color:"var(--text-mute)",fontSize:12.5}}>Nada encontrado.</div>}
            {palGroups.map((g, i) =>
              g.header
                ? <div key={"h"+i} className="pal-grp">{g.header}</div>
                : (
                  <div key={"i"+i} className={`pal-it ${g.idx === palSel ? "sel" : ""}`}
                       onMouseEnter={()=>setPalSel(g.idx)}
                       onClick={()=>runPal(g.item)}>
                    <span className="pal-ic">{g.item.ico}</span>
                    <span className="pal-tx">
                      <b>{g.item.title}</b>
                      <small>{g.item.sub}</small>
                    </span>
                    {g.item.kbd && <kbd>{g.item.kbd}</kbd>}
                  </div>
                )
            )}
          </div>
          <div className="pal-ft">
            <span><kbd>↑↓</kbd> navegar</span>
            <span><kbd>↵</kbd> abrir</span>
            <span><kbd>esc</kbd> fechar</span>
          </div>
        </div>
      </div>

    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// DRAWER CONTENT
// ──────────────────────────────────────────────────────────────
function DrawerContent({ v, tab, setTab, fSub, setFSub, onClose }) {
  const status = STATUS_OF(v);
  const hasNFe  = !!v.fiscal.nfe;
  const hasNFSe = !!v.fiscal.nfse;
  const wantsNFe  = hasProduto(v);
  const wantsNFSe = hasServico(v);
  const total = totalOf(v);

  return (
    <React.Fragment>
      <div className="drw-h">
        <span className="id-tag">{v.id}</span>
        <h2>{v.client}</h2>
        <span className={`sbadge ${status}`}>{status}</span>
        <span className="total">{fmt(total)}</span>
        <button className="btn ghost close" onClick={onClose}><Ic.x/></button>
      </div>

      <div className="drw-tabs">
        {[
          {k:"itens",   l:"Itens", ct: v.items.length},
          {k:"fiscal",  l:"Fiscal", ct: (hasNFe?1:0) + (hasNFSe?1:0) || "—"},
          {k:"pagamento",l:"Pagamento"},
          {k:"timeline",l:"Timeline"},
          {k:"cliente", l:"Cliente"},
        ].map(t => (
          <button key={t.k} className={`drw-tab ${tab===t.k?"on":""}`} onClick={()=>setTab(t.k)}>
            {t.l}{t.ct !== undefined && <span style={{marginLeft:6,fontFamily:"var(--font-mono)",fontSize:10,color:"var(--text-mute)"}}>{t.ct}</span>}
          </button>
        ))}
      </div>

      <div className="drw-body">

        {tab === "itens" && (
          <div className="sec">
            <div className="sec-h"><h3>Itens da venda</h3><span className="right">{v.items.length} {v.items.length===1?"item":"itens"}</span></div>
            <div className="items-list">
              {v.items.map((it, i) => (
                <div key={i} className="item-row">
                  <div className="it-name">
                    <b>{it.name}</b>
                    <small>{it.sku} · {it.type === "produto" ? "Produto (NF-e)" : "Serviço (NFS-e)"}</small>
                  </div>
                  <div className="it-num">{it.qty}×</div>
                  <div className="it-num">{fmt(it.unit)}</div>
                  <div className="it-num" style={{fontWeight:700}}>{fmt(it.qty*it.unit)}</div>
                </div>
              ))}
            </div>
            <div style={{display:"flex",justifyContent:"flex-end",padding:"14px 4px 0",fontFamily:"var(--font-mono)",fontSize:13}}>
              <span style={{color:"var(--text-mute)",marginRight:14}}>Total</span>
              <b style={{fontWeight:700,fontSize:16,letterSpacing:"-0.01em"}}>{fmt(total)}</b>
            </div>
          </div>
        )}

        {tab === "fiscal" && (
          <React.Fragment>
            {/* Quando precisa de NF-e ou NFS-e mas ainda não emitiu, mostra emit panel */}
            {((wantsNFe && !hasNFe) || (wantsNFSe && !hasNFSe)) && (
              <div className="emit-panel" style={{marginBottom: hasNFe || hasNFSe ? 14 : 0}}>
                <div className="em-txt">
                  <b>{!hasNFe && wantsNFe && !hasNFSe && wantsNFSe ? "Emitir NF-e e NFS-e" : !hasNFe && wantsNFe ? "Emitir NF-e" : "Emitir NFS-e"}</b>
                  <small>
                    Esta venda tem {wantsNFe && !hasNFe ? "produto(s)" : ""}{wantsNFe && wantsNFSe && !hasNFe && !hasNFSe ? " e " : ""}{wantsNFSe && !hasNFSe ? "serviço(s)" : ""} sem documento fiscal.
                  </small>
                </div>
                <div className="em-cta">
                  <button>{!hasNFe && wantsNFe && !hasNFSe && wantsNFSe ? "Emitir ambos" : !hasNFe ? "Emitir NF-e" : "Emitir NFS-e"}</button>
                </div>
              </div>
            )}

            {/* Sub-tabs apenas se mistos */}
            {hasNFe && hasNFSe && (
              <div className="fsub">
                <button className={fSub==="nfe"?"on":""}  onClick={()=>setFSub("nfe")}>NF-e <span className="ct">1</span></button>
                <button className={fSub==="nfse"?"on":""} onClick={()=>setFSub("nfse")}>NFS-e <span className="ct">1</span></button>
                <button className={fSub==="ambos"?"on":""}onClick={()=>setFSub("ambos")}>Ambos</button>
              </div>
            )}

            <div className={`fisc-grid ${(hasNFe && !hasNFSe) || (!hasNFe && hasNFSe) ? "single" : ""}`}>
              {hasNFe  && (fSub !== "nfse") && <FiscalCard kind="nfe"  doc={v.fiscal.nfe}/>}
              {hasNFSe && (fSub !== "nfe")  && <FiscalCard kind="nfse" doc={v.fiscal.nfse}/>}
            </div>

            {/* Total breakdown se mistos */}
            {hasNFe && hasNFSe && (
              <div style={{marginTop:16,background:"var(--surface)",border:"1px solid var(--border)",borderRadius:8,padding:"12px 14px"}}>
                <div className="sec-h" style={{margin:0}}><h3>Breakdown fiscal</h3></div>
                <div style={{display:"flex",justifyContent:"space-between",fontSize:12,marginTop:8}}>
                  <span>Produtos (NF-e {v.fiscal.nfe.numero})</span>
                  <b style={{fontFamily:"var(--font-mono)"}}>{fmt(v.items.filter(i=>i.type==="produto").reduce((s,i)=>s+i.qty*i.unit,0))}</b>
                </div>
                <div style={{display:"flex",justifyContent:"space-between",fontSize:12,marginTop:4}}>
                  <span>Serviços (NFS-e {v.fiscal.nfse.numero})</span>
                  <b style={{fontFamily:"var(--font-mono)"}}>{fmt(v.items.filter(i=>i.type==="servico").reduce((s,i)=>s+i.qty*i.unit,0))}</b>
                </div>
                <div style={{display:"flex",justifyContent:"space-between",borderTop:"1px solid var(--border-2)",paddingTop:8,marginTop:8,fontWeight:700,fontSize:13}}>
                  <span>Total da venda</span>
                  <b style={{fontFamily:"var(--font-mono)"}}>{fmt(total)}</b>
                </div>
              </div>
            )}

            {!hasNFe && !hasNFSe && !wantsNFe && !wantsNFSe && (
              <div style={{padding:32,textAlign:"center",color:"var(--text-mute)",fontSize:12.5,fontStyle:"italic"}}>
                Esta venda não emite documento fiscal (Consumidor Final, dinheiro).
              </div>
            )}
          </React.Fragment>
        )}

        {tab === "pagamento" && (
          <div className="sec">
            <div className="sec-h"><h3>Pagamento</h3></div>
            <div style={{background:"var(--surface)",border:"1px solid var(--border)",borderRadius:8,padding:"14px 16px"}}>
              <dl className="fmeta" style={{gridTemplateColumns:"100px 1fr"}}>
                <dt style={{color:"var(--text-mute)",fontSize:11.5}}>Forma</dt><dd style={{fontFamily:"var(--font-mono)"}}>{v.payment}</dd>
                <dt style={{color:"var(--text-mute)",fontSize:11.5,marginTop:6}}>Prazo</dt><dd style={{fontFamily:"var(--font-mono)",marginTop:6}}>{v.payTerm} dias</dd>
                <dt style={{color:"var(--text-mute)",fontSize:11.5,marginTop:6}}>Status</dt><dd style={{marginTop:6}}><span className={`sbadge ${status}`}>{status}</span></dd>
                <dt style={{color:"var(--text-mute)",fontSize:11.5,marginTop:6}}>Comissão</dt><dd style={{fontFamily:"var(--font-mono)",marginTop:6,color:"var(--sells)",fontWeight:700}}>{fmt(commissionOf(v))} <small style={{color:"var(--text-mute)",fontWeight:500}}>(3,5%)</small></dd>
              </dl>
            </div>
          </div>
        )}

        {tab === "timeline" && (
          <div className="sec">
            <div className="sec-h"><h3>Linha do tempo</h3></div>
            <div className="tline">
              <div className="tline-it">
                <small>{v.date.split("-").reverse().join("/")} {v.time}</small>
                <b>Venda registrada por {VENDEDORES[v.seller].name}</b>
              </div>
              {v.fsm >= 1 && <div className="tline-it">
                <small>{v.date.split("-").reverse().join("/")} {v.time}</small>
                <b>Pedido confirmado</b>
              </div>}
              {v.fiscal.nfe?.status === "ok" && <div className="tline-it">
                <small>{v.fiscal.nfe.date.split("T")[0].split("-").reverse().join("/")} {v.fiscal.nfe.date.split("T")[1]?.slice(0,5)}</small>
                <b>NF-e {v.fiscal.nfe.numero} autorizada SEFAZ</b>
              </div>}
              {v.fiscal.nfse?.status === "ok" && <div className="tline-it">
                <small>{v.fiscal.nfse.date.split("T")[0].split("-").reverse().join("/")} {v.fiscal.nfse.date.split("T")[1]?.slice(0,5)}</small>
                <b>NFS-e {v.fiscal.nfse.numero} autorizada Prefeitura</b>
              </div>}
              {v.fsm >= 3 && <div className="tline-it">
                <small>—</small>
                <b>Produção concluída · entregue</b>
              </div>}
              {v.fsm === 4 && <div className="tline-it">
                <small>—</small>
                <b>Pagamento confirmado</b>
              </div>}
            </div>
          </div>
        )}

        {tab === "cliente" && (
          <div className="sec">
            <div className="sec-h"><h3>Cliente</h3></div>
            <div style={{background:"var(--surface)",border:"1px solid var(--border)",borderRadius:8,padding:"14px 16px"}}>
              <b style={{fontSize:14}}>{v.client}</b>
              <p style={{margin:"4px 0 12px",fontSize:11.5,color:"var(--text-mute)"}}>{v.clientNote}</p>
              <div style={{fontSize:11.5,color:"var(--text-dim)"}}>
                Histórico, contato e dados fiscais do cliente aparecem aqui.<br/>
                Em produção: lookup em <code style={{fontFamily:"var(--font-mono)",fontSize:10.5,background:"var(--bg-2)",padding:"1px 4px",borderRadius:3}}>Modules/Clientes</code>.
              </div>
            </div>
          </div>
        )}

      </div>
    </React.Fragment>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<VendasAPP/>);

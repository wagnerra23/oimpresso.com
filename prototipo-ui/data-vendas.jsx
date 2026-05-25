// data-vendas.jsx — Mock vendas (Fase 3 / P0 Sells)
// 2026-05-14: refator A+ — adicionado fsm, itemsList (produto/servico) e fiscal {nfe, nfse}
// 2026-05-14b: F1.5 iter1 — vertical-aware (cv|mec|vest), placa Mercosul, mecânicos
//             campos legados mantidos pra compat com Create drawer e vendas-extras.

// FSM por vertical — cada um tem sua jornada de 5 etapas
const FSM_BY_VERTICAL = {
  cv:   { steps: ["Orçamento","Pedido","Faturada","Entregue","Paga"],     lbls: ["orç","ped","fat","ent","pag"] },
  mec:  { steps: ["Recepção","Diagnóstico","Peças","Execução","Pronto"], lbls: ["rec","dig","pec","exe","prn"] },
  vest: { steps: ["Orçamento","Pedido","Produção","Entregue","Pago"],     lbls: ["orç","ped","prd","ent","pag"] },
  repair:{steps: ["Recepção","Diagnóstico","Orçamento","Execução","Pronto"],lbls:["rec","dig","orç","exe","prn"] },
};

// FSM legado (CV) — mantido pra compat
const VENDAS_FSM_STEPS = FSM_BY_VERTICAL.cv.steps;
const VENDAS_FSM_LBLS  = FSM_BY_VERTICAL.cv.lbls;

const VERTICAL_LABEL = {
  cv:   { label: "CV",       full: "Comunicação Visual" },
  mec:  { label: "Oficina",  full: "Oficina Mecânica" },
  vest: { label: "Vestuário",full: "Vestuário" },
  repair:{label: "Repair",   full: "Repair Eletrônicos" },
};

const VENDEDORES_MAP = {
  bruna:   { id: "bruna",   name: "Bruna Vendas",   abbr: "BR", av: 1, meta: 18000, commissionPct: 0.035 },
  carlos:  { id: "carlos",  name: "Carlos Vendas",  abbr: "CR", av: 2, meta: 22000, commissionPct: 0.035 },
  larissa: { id: "larissa", name: "Larissa Atend.", abbr: "LA", av: 3, meta: 15000, commissionPct: 0.030 },
  patrick: { id: "patrick", name: "Patrick Vendas", abbr: "PT", av: 4, meta: 12000, commissionPct: 0.030 },
};

// Mecânicos da oficina (papel distinto de vendedor)
const MECANICOS_MAP = {
  m1: { id: "m1", name: "João Lima",    abbr: "JL", av: 5, esp: "Suspensão + freios" },
  m2: { id: "m2", name: "Pedro Souza",  abbr: "PS", av: 6, esp: "Injeção + elétrica" },
  m3: { id: "m3", name: "Carlos Rocha", abbr: "CR", av: 7, esp: "Motor + transmissão" },
  m4: { id: "m4", name: "Diego Alves",  abbr: "DA", av: 8, esp: "Alinhamento + pneus" },
};

// helpers internos
const fmtBR_ = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// estrutura "rica" de cada venda — vendas-page.jsx deriva os campos legados a partir disto
const VENDAS_RAW = [
  {
    id: "V-7821", date: "2026-05-14", time: "14:32",
    client: "Padaria Estrela do Vale", clientNote: "Banner 3×2m fachada — cliente recorrente",
    sellerId: "bruna",
    itemsList: [
      { sku: "BAN-001", name: "Banner lona 3×2m fachada", qty: 1, unit: 1640, type: "produto" },
      { sku: "INS-002", name: "Instalação no local",       qty: 1, unit: 200,  type: "servico" },
    ],
    payment: "PIX", installments: 1, payTerm: 1,
    fsm: 4,
    fiscal: {
      nfe:  { status: "ok",  numero: "001234", serie: "1", date: "2026-05-14T14:35",
              chave: "35260534567890000112550010000012341000123412" },
      nfse: { status: "ok",  numero: "00087",  serie: "—", date: "2026-05-14T14:38",
              chave: "21250014038720250000087001234561" },
    },
    osIds: ["4831"], origin: "balcão", urgent: false,
  },
  {
    id: "V-7822", date: "2026-05-14", time: "15:10",
    client: "Mercado União Atacarejo", clientNote: "Folder A4 4×4 cores · 2000un",
    sellerId: "carlos",
    itemsList: [
      { sku: "FLD-A4", name: "Folder A4 4×4 — 2.000 un", qty: 1, unit: 3420, type: "produto" },
    ],
    payment: "Boleto 30d", installments: 1, payTerm: 30,
    fsm: 2,
    fiscal: {
      nfe:  { status: "ok",  numero: "001235", serie: "1", date: "2026-05-14T15:12",
              chave: "35260534567890000112550010000012351000123512" },
      nfse: null,
    },
    osIds: ["4832"], origin: "orçamento", urgent: false,
  },
  {
    id: "V-7823", date: "2026-05-14", time: "16:45",
    client: "Auto Posto Águia I-95", clientNote: "Adesivo bombas + placa LED",
    sellerId: "bruna",
    itemsList: [
      { sku: "ADS-BMB", name: "Adesivo bombas (4 un)",   qty: 4, unit: 220,  type: "produto" },
      { sku: "PLC-LED", name: "Placa LED 80×40cm",       qty: 1, unit: 1300, type: "produto" },
    ],
    payment: "Cartão", installments: 2, payTerm: 30,
    fsm: 3,
    fiscal: {
      nfe:  { status: "wait", numero: "001236", serie: "1", date: "2026-05-14T16:48",
              chave: "35260534567890000112550010000012361000123612" },
      nfse: null,
    },
    osIds: ["4833"], origin: "balcão", urgent: true,
  },
  {
    id: "V-7824", date: "2026-05-14", time: "17:20",
    client: "Consumidor Final", clientNote: "Cartão visita pronta-entrega",
    sellerId: "larissa",
    itemsList: [
      { sku: "CRT-100", name: "Cartão visita 100un", qty: 1, unit: 380, type: "produto" },
    ],
    payment: "Dinheiro", installments: 1, payTerm: 0,
    fsm: 4,
    fiscal: { nfe: null, nfse: null }, // não emite (consumidor final, dinheiro)
    osIds: [], origin: "balcão", urgent: false,
  },
  {
    id: "V-7825", date: "2026-05-13", time: "11:05",
    client: "Farmácia Vida Plena", clientNote: "Sacolas 5.000un · parcelado 3×",
    sellerId: "carlos",
    itemsList: [
      { sku: "SAC-5K", name: "Sacola kraft 5.000un", qty: 1, unit: 4860, type: "produto" },
    ],
    payment: "Boleto 30/60/90", installments: 3, payTerm: 90,
    fsm: 2,
    fiscal: {
      nfe:  { status: "ok",  numero: "001233", serie: "1", date: "2026-05-13T11:08",
              chave: "35260534567890000112550010000012331000123312" },
      nfse: null,
    },
    osIds: ["4830"], origin: "orçamento", urgent: false,
  },
  {
    id: "V-7826", date: "2026-05-13", time: "13:48",
    client: "Pet Shop Latido Feliz", clientNote: "Cartão fidelidade + folder A5",
    sellerId: "bruna",
    itemsList: [
      { sku: "CRT-FID", name: "Cartão fidelidade 500un", qty: 1, unit: 380, type: "produto" },
      { sku: "FLD-A5",  name: "Folder A5 200un",         qty: 1, unit: 600, type: "produto" },
    ],
    payment: "PIX", installments: 1, payTerm: 1,
    fsm: 4,
    fiscal: {
      nfe:  { status: "ok",  numero: "001232", serie: "1", date: "2026-05-13T13:50",
              chave: "35260534567890000112550010000012321000123212" },
      nfse: null,
    },
    osIds: ["4829"], origin: "balcão", urgent: false,
  },
  {
    id: "V-7827", date: "2026-05-13", time: "16:30",
    client: "Construtora Horizonte LTDA", clientNote: "Banner obra 6×3m + tapume 18m + instalação",
    sellerId: "carlos",
    itemsList: [
      { sku: "BAN-OBR", name: "Banner obra 6×3m",       qty: 1, unit: 4200, type: "produto" },
      { sku: "TAP-18",  name: "Tapume impressão 18m",   qty: 1, unit: 3200, type: "produto" },
      { sku: "INS-OBR", name: "Instalação obra",        qty: 1, unit: 1540, type: "servico" },
    ],
    payment: "Boleto 60d", installments: 2, payTerm: 60,
    fsm: 1,
    fiscal: { nfe: null, nfse: null },
    osIds: ["4827"], origin: "orçamento", urgent: true,
  },
  {
    id: "V-7828", date: "2026-05-13", time: "10:15",
    client: "Salão Beleza Pura", clientNote: "Diagramação social media (15 posts)",
    sellerId: "patrick",
    itemsList: [
      { sku: "SOC-15", name: "Pacote social media 15 posts", qty: 1, unit: 420, type: "servico" },
    ],
    payment: "PIX", installments: 1, payTerm: 1,
    fsm: 4,
    fiscal: {
      nfe:  null,
      nfse: { status: "ok",  numero: "00086", serie: "—", date: "2026-05-13T10:18",
              chave: "21250013510860010000086001234560" },
    },
    osIds: ["4824"], origin: "balcão", urgent: false,
  },
  {
    id: "V-7829", date: "2026-05-12", time: "14:50",
    client: "Distribuidora Brasil Foods", clientNote: "Catálogo A4 100p · 200un + diagramação · parcelado 6×",
    sellerId: "carlos",
    itemsList: [
      { sku: "CAT-A4",  name: "Catálogo A4 100p · 200un", qty: 1, unit: 11200, type: "produto" },
      { sku: "DGR-CAT", name: "Diagramação catálogo",     qty: 1, unit: 1200,  type: "servico" },
    ],
    payment: "Boleto 6×", installments: 6, payTerm: 180,
    fsm: 2,
    fiscal: {
      nfe:  { status: "ok",  numero: "001231", serie: "1", date: "2026-05-12T14:52",
              chave: "35260534567890000112550010000012311000123112" },
      nfse: { status: "ok",  numero: "00085", serie: "—", date: "2026-05-12T14:55",
              chave: "21250013510850010000085001234560" },
    },
    osIds: ["4825","4826"], origin: "orçamento", urgent: false,
  },
  {
    id: "V-7830", date: "2026-05-12", time: "11:22",
    client: "Restaurante Sabor de Casa", clientNote: "Cardápio plastificado 50un",
    sellerId: "bruna",
    itemsList: [
      { sku: "CRD-50", name: "Cardápio plastificado 50un", qty: 1, unit: 1320, type: "produto" },
    ],
    payment: "Cartão", installments: 1, payTerm: 30,
    fsm: 2,
    fiscal: {
      nfe:  { status: "bad", numero: "001230", serie: "1", date: "2026-05-12T11:25",
              chave: "35260534567890000112550010000012301000123012",
              failReason: "Rejeição 539: NCM divergente do cadastro SEFAZ" },
      nfse: null,
    },
    osIds: ["4822"], origin: "balcão", urgent: false,
  },
  {
    id: "V-7831", date: "2026-05-12", time: "09:48",
    client: "Studio Alfa Design", clientNote: "Adesivo recorte carro",
    sellerId: "larissa",
    itemsList: [
      { sku: "ADS-CAR", name: "Adesivo recorte carro (4 lados)", qty: 1, unit: 680, type: "produto" },
    ],
    payment: "PIX", installments: 1, payTerm: 1,
    fsm: 4,
    fiscal: {
      nfe: { status: "canc", numero: "001229", serie: "1", date: "2026-05-12T09:51",
             chave: "35260534567890000112550010000012291000122912",
             cancelReason: "Cliente alterou medidas — re-emitida" },
      nfse: null,
    },
    osIds: ["4821"], origin: "balcão", urgent: false,
  },
  {
    id: "V-7832", date: "2026-05-12", time: "15:30",
    client: "Padaria Pão Quente", clientNote: "Etiquetas adesivas 1.000un",
    sellerId: "patrick",
    itemsList: [
      { sku: "ETQ-1K", name: "Etiqueta adesiva 1.000un", qty: 1, unit: 290, type: "produto" },
    ],
    payment: "Boleto 30d", installments: 1, payTerm: 30,
    fsm: 1,
    fiscal: { nfe: null, nfse: null },
    osIds: [], origin: "orçamento", urgent: false,
  },

  // ─── Oficina Mecânica (vertical=mec) ─────────────────────────────
  {
    id: "V-7833", date: "2026-05-14", time: "10:42",
    vertical: "mec",
    plate: "RBA-2H78", vehicle: "Honda Civic 2019", km: "84.220",
    client: "Marcos Aleixo", clientNote: "Pastilhas + disco dianteiro",
    sellerId: "larissa", mechanicId: "m1",
    itemsList: [
      { sku: "BRP-001", name: "Jogo pastilhas dianteiras Bosch",    qty: 1, unit: 280, type: "produto" },
      { sku: "BRD-002", name: "Par discos dianteiros ventilados",   qty: 1, unit: 460, type: "produto" },
      { sku: "MAO-FRE", name: "Mão de obra: troca freios completa", qty: 1, unit: 280, type: "servico" },
    ],
    payment: "PIX", installments: 1, payTerm: 1,
    fsm: 4,
    fiscal: {
      nfe:  { status: "ok", numero: "001237", serie: "1", date: "2026-05-14T11:30",
              chave: "35260534567890000112550010000012371000123712" },
      nfse: { status: "ok", numero: "00088",  serie: "—", date: "2026-05-14T11:32",
              chave: "21250014038880250000088001234561" },
    },
    osIds: ["8810"], origin: "balcão", urgent: false,
  },
  {
    id: "V-7834", date: "2026-05-14", time: "08:50",
    vertical: "mec",
    plate: "FZJ-4F12", vehicle: "VW Saveiro 2021", km: "62.140",
    client: "Frota Boa Esperança", clientNote: "Revisão 60.000 km + óleo",
    sellerId: "carlos", mechanicId: "m4",
    itemsList: [
      { sku: "OLE-5W30", name: "Óleo 5W30 sintético (4L)",          qty: 1, unit: 180, type: "produto" },
      { sku: "FLT-OLE",  name: "Filtro de óleo",                    qty: 1, unit: 45,  type: "produto" },
      { sku: "FLT-AR",   name: "Filtro de ar",                      qty: 1, unit: 65,  type: "produto" },
      { sku: "MAO-REV60",name: "Revisão 60.000 km (mão de obra)",   qty: 1, unit: 320, type: "servico" },
    ],
    payment: "Faturado 30d", installments: 1, payTerm: 30,
    fsm: 1, // diagnóstico
    fiscal: { nfe: null, nfse: null },
    osIds: ["8822"], origin: "frota", urgent: false,
  },
  {
    id: "V-7835", date: "2026-05-13", time: "16:18",
    vertical: "mec",
    plate: "VRP-5K27", vehicle: "Nissan Versa 2020", km: "55.300",
    client: "Helena Bastos", clientNote: "Revisão 50.000 km · entregue ontem · aguarda PIX",
    sellerId: "larissa", mechanicId: "m2",
    itemsList: [
      { sku: "OLE-5W30", name: "Óleo 5W30 sintético (4L)",          qty: 1, unit: 180, type: "produto" },
      { sku: "VEL-NGK",  name: "Velas NGK Iridium (4 un)",          qty: 4, unit: 55,  type: "produto" },
      { sku: "MAO-REV50",name: "Revisão 50.000 km (mão de obra)",   qty: 1, unit: 380, type: "servico" },
    ],
    payment: "PIX", installments: 1, payTerm: 1,
    fsm: 3, // pronto pra retirar
    fiscal: {
      nfe:  { status: "ok",   numero: "001238", serie: "1", date: "2026-05-13T16:25",
              chave: "35260534567890000112550010000012381000123812" },
      nfse: { status: "wait", numero: "—",      serie: "—", date: "2026-05-13T16:26",
              chave: "21250014038890250000089001234561" },
    },
    osIds: ["8788"], origin: "balcão", urgent: true, // urgente: aguarda pagamento
  },
];

// status legado derivado do fsm (consumido pelo render existente)
// nota: depende do vertical (em mec a última etapa é "Pronto" = entregue)
const FSM_TO_STATUS = (fsm, vertical) => {
  if (vertical === "mec" || vertical === "repair") {
    if (fsm === 4) return "paga";       // pronto + retirado
    if (fsm === 3) return "faturada";   // execução avançada
    if (fsm === 2) return "faturada";
    if (fsm >= 1) return "pendente";
    return "orcamento";
  }
  if (fsm === 4) return "paga";
  if (fsm === 2 || fsm === 3) return "faturada";
  if (fsm === 1) return "pendente";
  return "orcamento";
};

// SOURCE — módulo de origem operacional (Integração Vendas × Oficina, 2026-05-25)
//   balcao  · venda direta atendida no balcão (Larissa/Bruna)
//   oficina · derivada de OS do módulo Oficina Auto (Felipe/mecânicos) — carrega osRef
//   online  · canal digital (site, WhatsApp pedido fechado)
// Não confundir com v.origin LEGACY (canal de captação: "balcão"/"orçamento"/"frota").
const VENDAS_SOURCE_META = {
  balcao:  { id:"balcao",  label:"Balcão",  hue:155, abbr:"B" },
  oficina: { id:"oficina", label:"Oficina", hue:230, abbr:"O" },
  online:  { id:"online",  label:"Online",  hue: 50, abbr:"@" },
};

// Override explícito por ID — pra variar a amostra (2 vendas online)
const _SOURCE_OVERRIDES = {
  "V-7826": "online",  // cliente final via WhatsApp
  "V-7832": "online",  // pedido fechado pelo site
};

function _deriveSource(v) {
  if (_SOURCE_OVERRIDES[v.id]) return _SOURCE_OVERRIDES[v.id];
  if (v.vertical === "mec" || v.vertical === "repair") return "oficina";
  return "balcao";
}

// Lista final com campos legados injetados pra compat
const VENDAS_LIST = VENDAS_RAW.map(v => {
  const totalNum = v.itemsList.reduce((s, i) => s + i.qty * i.unit, 0);
  const source = _deriveSource(v);
  const osRef = source === "oficina" && v.osIds?.[0] ? `OS-${v.osIds[0]}` : null;
  return {
    ...v,
    vertical: v.vertical || "cv",                          // default CV
    items: v.itemsList.length,                             // legacy: count
    total: fmtBR_(totalNum),                               // legacy: string
    totalNum,                                              // novo
    seller: VENDEDORES_MAP[v.sellerId]?.name || v.sellerId,// legacy: string nome completo
    status: FSM_TO_STATUS(v.fsm, v.vertical || "cv"),      // legacy
    notes: v.clientNote || "",                             // legacy
    source,                                                // novo · módulo operacional
    osRef,                                                 // novo · #OS-NNNN quando oficina
  };
});

const VENDAS_PAYMENTS = [
  { id: "pix",       label: "PIX",            icon: "⚡", clearing: "imediato" },
  { id: "dinheiro",  label: "Dinheiro",       icon: "💵", clearing: "imediato" },
  { id: "cartao",    label: "Cartão",         icon: "💳", clearing: "D+1 a D+30" },
  { id: "boleto30",  label: "Boleto 30d",     icon: "📄", clearing: "30 dias" },
  { id: "boleto60",  label: "Boleto 60d",     icon: "📄", clearing: "60 dias" },
  { id: "transf",    label: "Transferência",  icon: "🏦", clearing: "D+0 a D+1" },
];

const VENDAS_STATUS = {
  paga:       { label: "Paga",       color: "oklch(0.50 0.14 145)" },
  pendente:   { label: "Pendente",   color: "oklch(0.60 0.14 70)"  },
  faturada:   { label: "Faturada",   color: "oklch(0.55 0.14 240)" },
  orcamento:  { label: "Orçamento",  color: "oklch(0.55 0.04 250)" },
  cancelada:  { label: "Cancelada",  color: "oklch(0.55 0.04 250)" },
};

// Saved views — filtros pré-definidos
const VENDAS_SAVED_VIEWS = [
  { id: "todas",      label: "Todas",              filter: () => true },
  { id: "hoje",       label: "Hoje",               filter: (v) => v.date === "2026-05-14" },
  { id: "pendentes",  label: "Pendentes pgto.",    filter: (v) => v.fsm < 4 },
  { id: "faturadas",  label: "Faturadas semana",   filter: (v) => v.fsm === 2 },
  { id: "atrasadas",  label: "Atrasadas",          filter: (v) => v.urgent && v.fsm < 4 },
  { id: "rejeitadas", label: "Rejeitadas SEFAZ",   filter: (v) => v.fiscal?.nfe?.status === "bad" || v.fiscal?.nfse?.status === "bad" },
  // Integração Vendas × Oficina (2026-05-25) — expansível com filhos balcao/oficina/online
  { id: "origem",     label: "Por origem",          filter: () => true, expandable: "source" },
];

window.VENDAS_DATA = {
  VENDAS_LIST,
  VENDAS_PAYMENTS,
  VENDAS_STATUS,
  VENDAS_FSM_STEPS,
  VENDAS_FSM_LBLS,
  FSM_BY_VERTICAL,
  VERTICAL_LABEL,
  VENDEDORES_MAP,
  MECANICOS_MAP,
  VENDAS_SAVED_VIEWS,
  VENDAS_SOURCE_META,   // Integração Vendas × Oficina (2026-05-25)
};

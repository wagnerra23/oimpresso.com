// Cobrança Recorrente — mocks.
// 18 assinaturas, 5 planos, retentativas, motivos churn, próximas cobranças.
// Exposto via window.REC_DATA.
(() => {

const PLANS = [
  { id: "p1", name: "Cardápios Mensais",      cycle: "mensal", price: 480,  items: "Cardápio A4 4x0 · 50un · entrega 3d", fiscalType: "nfe",  cfop: "5102" },
  { id: "p2", name: "Banner Promo · 4 trocas",cycle: "mensal", price: 1890, items: "4 banners 3x2m lona 380g + troca semanal", fiscalType: "nfe",  cfop: "5102" },
  { id: "p3", name: "Wind Banner Multi-loja", cycle: "mensal", price: 2800, items: "5 wind banners + base + bolsa transporte", fiscalType: "nfe",  cfop: "5102" },
  { id: "p4", name: "Fachada + Faixa",        cycle: "mensal", price: 1620, items: "Fachada ACM + faixa promocional 30 dias", fiscalType: "nfse", servico: "01.07" },
  { id: "p5", name: "Rótulos Perolados",      cycle: "trimestral", price: 1640, items: "2000un rótulo perolado · lote trimestral", fiscalType: "nfe", cfop: "5102" },
];

const SUBS = [
  // ATIVAS EM-DIA (8)
  { id:"s001", client:"Padaria Estrela",         cnpj:"12.345.678/0001-90", plan:"p1", since:"2025-02-05", method:"pix",    status:"em_dia",      nextAt:"2026-06-05", nextValue:480,  os:"#4819", notes:"Cliente prefere boleto pré-agendado", paid:14, missed:0, ltv:6720,  contact:{nome:"Renato",  fone:"19 99876-1234"},   fiscal:{type:"nfe",  channels:["whatsapp"],         lastNf:"NFe 8412"} },
  { id:"s002", client:"Acme Comércio Ltda",      cnpj:"45.678.901/0001-23", plan:"p2", since:"2025-08-10", method:"boleto", status:"em_dia",      nextAt:"2026-06-10", nextValue:1890, os:"#4821", notes:"4 trocas/mês — cuidado com SLA arte",       paid:9,  missed:0, ltv:17010, contact:{nome:"Camila",  fone:"19 98777-2200"},   fiscal:{type:"nfe",  channels:["email","whatsapp"], lastNf:"NFe 8418"} },
  { id:"s003", client:"Imobiliária Horizonte",   cnpj:"78.901.234/0001-56", plan:"p3", since:"2024-09-20", method:"pix",    status:"em_dia",      nextAt:"2026-06-20", nextValue:2800, os:"#4790", notes:"5 lojas · cada uma com KV diferente",       paid:20, missed:1, ltv:55400, contact:{nome:"Patrícia",fone:"19 99110-4400"},   fiscal:{type:"nfe",  channels:["email"],            lastNf:"NFe 8427"} },
  { id:"s004", client:"Mercado União",           cnpj:"23.456.789/0001-12", plan:"p4", since:"2025-11-24", method:"boleto", status:"em_dia",      nextAt:"2026-06-24", nextValue:1620, os:"#4830", notes:"Trocar faixa toda primeira quinzena",       paid:6,  missed:0, ltv:9720,  contact:{nome:"José Ant.", fone:"19 99220-5500"}, fiscal:{type:"nfse", channels:["whatsapp"],         lastNf:"NFS-e 1184"} },
  { id:"s005", client:"Cervejaria Lupulada",     cnpj:"56.789.012/0001-34", plan:"p5", since:"2024-11-30", method:"pix",    status:"em_dia",      nextAt:"2026-08-30", nextValue:1640, os:"#4750", notes:"Lote trimestral · próximo ago/26",          paid:5,  missed:0, ltv:8200,  contact:{nome:"Bruno",   fone:"19 99330-6600"},   fiscal:{type:"nfe",  channels:["email"],            lastNf:"NFe 8350"} },
  { id:"s006", client:"Restaurante Sabor da Vó", cnpj:"89.012.345/0001-67", plan:"p1", since:"2025-04-12", method:"pix",    status:"em_dia",      nextAt:"2026-06-12", nextValue:480,  os:"#4805", notes:"",                                          paid:10, missed:0, ltv:4800,  contact:{nome:"Dona Lu", fone:"19 99440-7700"},   fiscal:{type:"nfe",  channels:["whatsapp"],         lastNf:"NFe 8421"} },
  { id:"s007", client:"Clínica Vida Plena",      cnpj:"34.567.890/0001-89", plan:"p2", since:"2024-06-08", method:"boleto", status:"em_dia",      nextAt:"2026-06-08", nextValue:1890, os:"#4640", notes:"Indicação de cliente atual",                paid:24, missed:2, ltv:45360, contact:{nome:"Dr. Marcos", fone:"19 99550-8800"},fiscal:{type:"nfse", channels:["email","whatsapp"], lastNf:"NFS-e 1156"} },
  { id:"s008", client:"Auto Posto Caminho",      cnpj:"67.890.123/0001-01", plan:"p3", since:"2025-06-15", method:"boleto", status:"em_dia",      nextAt:"2026-06-15", nextValue:2800, os:"#4815", notes:"5 unidades de posto",                       paid:11, missed:0, ltv:30800, contact:{nome:"Sr. Edu",  fone:"19 99660-9900"},  fiscal:{type:"nfe",  channels:["email"],            lastNf:"NFe 8404"} },

  // RETENTANDO (2)
  { id:"s009", client:"Pet Shop Cãopanhia",      cnpj:"12.987.654/0001-45", plan:"p1", since:"2025-05-22", method:"boleto", status:"retentando",  retry:2, retryMax:3, nextAt:"hoje 14:00", nextValue:480, os:"#4798", notes:"Boleto 1ª retentativa falhou — banco fora", paid:11, missed:1, ltv:5280,  contact:{nome:"Marina",  fone:"19 99771-0100"}, fiscal:{type:"nfe",  channels:["whatsapp"], lastNf:"NFe 8398"} },
  { id:"s010", client:"Escola Caminhos",         cnpj:"45.321.876/0001-12", plan:"p4", since:"2025-09-03", method:"card",   status:"retentando",  retry:1, retryMax:3, nextAt:"amanhã 08:00", nextValue:1620, os:"#4825", notes:"Cartão recusou · trocar bandeira",        paid:7,  missed:1, ltv:11340, contact:{nome:"Diretora", fone:"19 99880-2200"}, fiscal:{type:"nfse", channels:["email"], lastNf:"NFS-e 1172"} },

  // FALHOU 3X (2) — requer ação Eliana
  { id:"s011", client:"Padaria Sol Nascente",    cnpj:"99.111.222/0001-33", plan:"p1", since:"2024-12-10", method:"boleto", status:"falhou",      retry:3, retryMax:3, nextAt:"manual", nextValue:480, os:"#4710", notes:"Cliente não responde WhatsApp há 8d",     paid:14, missed:3, ltv:6720,  contact:{nome:"Sr. Antônio", fone:"19 99001-3300"}, fiscal:{type:"none",  channels:[],                          lastNf:null} },
  { id:"s012", client:"Loja BellaModa",          cnpj:"55.666.777/0001-44", plan:"p2", since:"2025-01-18", method:"boleto", status:"falhou",      retry:3, retryMax:3, nextAt:"manual", nextValue:1890, os:"#4730", notes:"3 boletos pulados · suspensão sugerida",   paid:13, missed:3, ltv:24570, contact:{nome:"Sandra",  fone:"19 99112-4400"},      fiscal:{type:"nfe",   channels:["whatsapp"], lastNf:"NFe 8290"} },

  // PAUSADAS (1)
  { id:"s013", client:"Estúdio Foto&Cia",        cnpj:"33.444.555/0001-66", plan:"p1", since:"2025-03-14", method:"pix",    status:"pausada",     pausedUntil:"2026-07-01", nextAt:"01/07", nextValue:480, os:"#4760", notes:"Cliente em férias até jun · pausou voluntário", paid:11, missed:0, ltv:5280,  contact:{nome:"Fred",   fone:"19 99223-5500"}, fiscal:{type:"nfe", channels:["email"], lastNf:"NFe 8400"} },

  // CANCELADAS recentes (4)
  { id:"s014", client:"Sushi Konichiwa",         cnpj:"22.333.444/0001-77", plan:"p2", since:"2024-10-05", method:"boleto", status:"cancelada",   churn:"preço",            cancelAt:"2026-05-08", paid:18, missed:1, ltv:34020, contact:{nome:"Yuki",     fone:"19 99334-6600"}, fiscal:{type:"nfe",  channels:["email"], lastNf:"NFe 8385"} },
  { id:"s015", client:"Loja Vivenda",            cnpj:"77.888.999/0001-22", plan:"p4", since:"2025-02-28", method:"boleto", status:"cancelada",   churn:"loja fechou",      cancelAt:"2026-04-30", paid:13, missed:0, ltv:21060, contact:{nome:"Marcelo",  fone:"19 99445-7700"}, fiscal:{type:"nfse", channels:["whatsapp"], lastNf:"NFS-e 1099"} },
  { id:"s016", client:"Pizzaria Forno Lenha",    cnpj:"11.222.333/0001-88", plan:"p1", since:"2024-08-15", method:"pix",    status:"cancelada",   churn:"inadimplência",    cancelAt:"2026-03-15", paid:18, missed:3, ltv:8640,  contact:{nome:"Sr. Júlio",fone:"19 99556-8800"}, fiscal:{type:"nfe",  channels:["whatsapp"], lastNf:"NFe 8210"} },
  { id:"s017", client:"Studio Yoga Equilíbrio",  cnpj:"44.555.666/0001-99", plan:"p1", since:"2025-07-08", method:"card",   status:"cancelada",   churn:"trocou fornecedor",cancelAt:"2026-05-02", paid:9,  missed:0, ltv:4320,  contact:{nome:"Letícia",  fone:"19 99667-9900"}, fiscal:{type:"none", channels:[], lastNf:null} },

  // NOVA · ativada essa semana
  { id:"s018", client:"Cafeteria Grão & Arte",   cnpj:"66.777.888/0001-55", plan:"p1", since:"2026-05-10", method:"pix",    status:"em_dia",      nextAt:"2026-06-10", nextValue:480, os:"#4860", notes:"Nova · 1ª cobrança ainda não rodou",       paid:0,  missed:0, ltv:0,     contact:{nome:"Vera",    fone:"19 99778-1100"},       fiscal:{type:"nfe", channels:["whatsapp"], lastNf:null} },
];

// Próximas cobranças do mês (consolidado da view do calendário)
const UPCOMING = [
  { day: 5,  date:"05/jun", subs: ["s001"],          total: 480  },
  { day: 8,  date:"08/jun", subs: ["s007"],          total: 1890 },
  { day: 10, date:"10/jun", subs: ["s002","s006","s018"], total: 2850 },
  { day: 12, date:"12/jun", subs: ["s006"],          total: 480  },
  { day: 15, date:"15/jun", subs: ["s008"],          total: 2800 },
  { day: 20, date:"20/jun", subs: ["s003"],          total: 2800 },
  { day: 24, date:"24/jun", subs: ["s004"],          total: 1620 },
];

const KPIS = {
  mrr: 8420,
  mrrDelta: 1200,        // vs abril
  churnCount: 1,         // maio
  churnRate: 8.3,
  nextChargeWhen: "amanhã",
  nextChargeValue: 2140,
  nextChargeCount: 4,
  failedCount: 2,        // requer ação
  retryingCount: 2,
  pausedCount: 1,
  activeCount: 13,
  totalLtv: 318660,
};

// Motivos de churn (para distribuição)
const CHURN_REASONS = [
  { key: "preço",              count: 3, color: "oklch(0.55 0.13 60)" },
  { key: "loja fechou",        count: 1, color: "oklch(0.50 0.04 80)" },
  { key: "inadimplência",      count: 2, color: "oklch(0.55 0.18 25)" },
  { key: "trocou fornecedor",  count: 2, color: "oklch(0.55 0.13 295)" },
];

// Timeline de eventos por assinatura (notas humanas + eventos automáticos)
// kind: "note" | "event-create" | "event-status" | "event-plan" | "event-charge" | "event-retry" | "event-nf"
const TIMELINES = {
  s001: [
    { kind: "event-create", at: "2025-02-05T10:00", by: "sistema", text: "Assinatura criada · plano Cardápios Mensais" },
    { kind: "event-charge", at: "2025-02-05T10:01", by: "sistema", text: "Primeira cobrança paga · R$ 480 (pix)" },
    { kind: "note",         at: "2025-03-10T14:22", by: "Eliana", text: "Cliente prefere boleto pré-agendado. Vincular #os4819 nos próximos ciclos." },
    { kind: "event-charge", at: "2026-04-05T08:30", by: "sistema", text: "Cobrança paga · R$ 480 (boleto pré-agendado)" },
    { kind: "event-nf",     at: "2026-04-05T08:32", by: "SEFAZ",   text: "NFe 8412 emitida · R$ 480 · CFOP 5102 · enviada via WhatsApp ✓" },
    { kind: "event-charge", at: "2026-05-05T08:30", by: "sistema", text: "Cobrança paga · R$ 480 (boleto pré-agendado)" },
    { kind: "event-nf",     at: "2026-05-05T08:32", by: "SEFAZ",   text: "NFe 8418 emitida · enviada via WhatsApp para 19 99876-1234 ✓" },
    { kind: "note",         at: "2026-05-14T11:08", by: "Wagner", text: "OK manter no plano. LTV ótimo." },
  ],
  s002: [
    { kind: "event-create", at: "2025-08-10T09:00", by: "sistema", text: "Assinatura criada · plano Banner Promo · 4 trocas" },
    { kind: "note",         at: "2025-08-12T15:10", by: "Eliana", text: "Cuidado com SLA de arte. Camila exige 4 trocas/mês reais. Ver #os4821." },
    { kind: "event-charge", at: "2026-04-10T09:30", by: "sistema", text: "Cobrança paga · R$ 1.890 (boleto)" },
    { kind: "event-nf",     at: "2026-04-10T09:32", by: "SEFAZ",   text: "NFe 8415 emitida · enviada via e-mail + WhatsApp ✓" },
    { kind: "event-charge", at: "2026-05-10T09:30", by: "sistema", text: "Cobrança paga · R$ 1.890 (boleto)" },
    { kind: "event-nf",     at: "2026-05-10T09:32", by: "SEFAZ",   text: "NFe 8418 emitida · enviada via e-mail + WhatsApp ✓" },
  ],
  s004: [
    { kind: "event-create", at: "2025-11-24T10:00", by: "sistema", text: "Assinatura criada · Fachada + Faixa" },
    { kind: "event-charge", at: "2026-04-24T08:00", by: "sistema", text: "Cobrança paga · R$ 1.620 (boleto)" },
    { kind: "event-nf",     at: "2026-04-24T08:02", by: "Prefeitura", text: "NFS-e 1184 emitida · serviço 01.07 · enviada via WhatsApp para 19 99220-5500 ✓" },
    { kind: "note",         at: "2026-04-24T11:30", by: "José Ant.", text: "Recebi a nota pelo WhatsApp, valeu!" },
    { kind: "event-charge", at: "2026-05-24T08:00", by: "sistema", text: "Cobrança paga · R$ 1.620 (boleto)" },
    { kind: "event-nf",     at: "2026-05-24T08:02", by: "Prefeitura", text: "NFS-e 1196 emitida · enviada via WhatsApp ✓" },
  ],
  s007: [
    { kind: "event-create", at: "2024-06-08T10:00", by: "sistema", text: "Assinatura criada · serviço continuado" },
    { kind: "event-charge", at: "2026-04-08T09:00", by: "sistema", text: "Cobrança paga · R$ 1.890 (boleto)" },
    { kind: "event-nf",     at: "2026-04-08T09:02", by: "Prefeitura", text: "NFS-e 1150 emitida · enviada via e-mail + WhatsApp ✓" },
    { kind: "note",         at: "2026-05-02T14:00", by: "Dr. Marcos", text: "Pode reenviar a NFS-e pelo WhatsApp? Não achei o e-mail." },
    { kind: "event-nf",     at: "2026-05-02T14:18", by: "Eliana",    text: "NFS-e 1150 reenviada via WhatsApp manualmente" },
    { kind: "event-charge", at: "2026-05-08T09:00", by: "sistema", text: "Cobrança paga · R$ 1.890 (boleto)" },
    { kind: "event-nf",     at: "2026-05-08T09:02", by: "Prefeitura", text: "NFS-e 1156 emitida · enviada via e-mail + WhatsApp ✓" },
  ],
  s009: [
    { kind: "event-create", at: "2025-05-22T11:00", by: "sistema", text: "Assinatura criada" },
    { kind: "event-charge", at: "2026-04-22T08:00", by: "sistema", text: "Cobrança paga · R$ 480 (boleto)" },
    { kind: "event-nf",     at: "2026-04-22T08:02", by: "SEFAZ",   text: "NFe 8398 emitida · enviada via WhatsApp ✓" },
    { kind: "event-retry",  at: "2026-05-22T08:00", by: "sistema", text: "Boleto vencido · iniciando retentativa 1/3" },
    { kind: "note",         at: "2026-05-23T10:15", by: "Eliana", text: "Liguei pra Marina. Banco fora ontem. Vai pagar hoje 14h." },
    { kind: "event-retry",  at: "2026-05-24T14:00", by: "sistema", text: "Retentativa 2/3 agendada · hoje 14:00" },
  ],
  s010: [
    { kind: "event-create", at: "2025-09-03T16:00", by: "sistema", text: "Assinatura criada · método cartão" },
    { kind: "event-retry",  at: "2026-05-15T08:00", by: "sistema", text: "Cartão recusou · code 51 · saldo insuficiente · retentativa 1/3" },
    { kind: "note",         at: "2026-05-15T16:42", by: "Eliana", text: "WhatsApp pra Diretora. Vai mandar outro cartão. Considerar trocar pra boleto." },
  ],
  s011: [
    { kind: "event-create", at: "2024-12-10T14:00", by: "sistema", text: "Assinatura criada" },
    { kind: "event-charge", at: "2026-02-10T08:00", by: "sistema", text: "Cobrança paga · R$ 480 (boleto)" },
    { kind: "event-retry",  at: "2026-05-10T08:00", by: "sistema", text: "Boleto vencido · retentativa 1/3" },
    { kind: "event-retry",  at: "2026-05-13T14:00", by: "sistema", text: "Retentativa 2/3 falhou" },
    { kind: "event-retry",  at: "2026-05-15T14:00", by: "sistema", text: "Retentativa 3/3 falhou · status → falhou" },
    { kind: "note",         at: "2026-05-15T15:20", by: "Eliana", text: "Mandei mensagem dia 15. Sem resposta. Tentar de novo dia 18 ou suspender." },
    { kind: "note",         at: "2026-05-18T09:00", by: "Eliana", text: "Sem resposta há 8d. Vou pedir aprovação do Wagner pra suspender." },
  ],
  s014: [
    { kind: "event-create", at: "2024-10-05T11:00", by: "sistema", text: "Assinatura criada" },
    { kind: "event-charge", at: "2026-04-05T09:00", by: "sistema", text: "Cobrança paga · R$ 1.890 (boleto)" },
    { kind: "event-nf",     at: "2026-04-05T09:02", by: "SEFAZ",   text: "NFe 8385 emitida · enviada via e-mail" },
    { kind: "note",         at: "2026-05-05T16:00", by: "Yuki",   text: "Pedi pra cancelar. Achei caro." },
    { kind: "event-status", at: "2026-05-08T10:00", by: "Eliana", text: "Status → cancelada · motivo: preço" },
  ],
};

// Troubleshooters · árvores de decisão por problema
const TROUBLESHOOTERS = [
  {
    id: "boleto-recusado",
    title: "Boleto recusou / não pago",
    icon: "boleto",
    hue: 60,
    steps: [
      { q: "Cliente já recebeu o boleto?", opts: [
        { label: "Sim", next: 1 },
        { label: "Não / não sei", next: 2 },
      ]},
      { q: "Conseguiu confirmar o motivo da não-pagamento?", opts: [
        { label: "Sem saldo / problema financeiro", next: 3 },
        { label: "Esqueceu / vai pagar", next: 4 },
        { label: "Não conseguiu falar", next: 5 },
      ]},
      { q: "Verificar e re-enviar boleto", final: "Reenviar boleto via WhatsApp + e-mail. Confirmar recebimento. Reagendar retentativa em 3 dias." },
      { q: "Negociar parcelamento ou pausa", final: "Oferecer pausa de 1 ciclo ou parcelamento em 2x. Documentar acordo em nota interna." },
      { q: "Aguardar com retentativa programada", final: "Manter retentativa 2/3 agendada. Após pagamento, registrar em nota." },
      { q: "Escalar para Wagner", final: "Tentar mais 1× por telefone. Se não responder em 48h, escalar para Wagner decidir suspensão." },
    ]
  },
  {
    id: "cartao-expirado",
    title: "Cartão recusou / expirado",
    icon: "card",
    hue: 250,
    steps: [
      { q: "Cliente já forneceu novo cartão?", opts: [
        { label: "Sim", next: 1 },
        { label: "Não", next: 2 },
      ]},
      { q: "Atualizar e re-cobrar", final: "Atualizar dados no gateway. Re-cobrar manualmente. Confirmar sucesso antes de fechar." },
      { q: "Solicitar pelo WhatsApp", final: "Enviar template HSM 'atualizar-cartao' pelo WhatsApp + e-mail. Aguardar até 72h. Sugerir migração para boleto/pix se persistir." },
    ]
  },
  {
    id: "cliente-sumiu",
    title: "Cliente sumiu há 7+ dias",
    icon: "user",
    hue: 25,
    steps: [
      { q: "Quantos canais já testou?", opts: [
        { label: "Só WhatsApp", next: 1 },
        { label: "WhatsApp + e-mail", next: 2 },
        { label: "WhatsApp + e-mail + telefone", next: 3 },
      ]},
      { q: "Tentar canais adicionais", final: "Enviar e-mail formal + ligar no telefone cadastrado. Documentar tentativa em nota interna." },
      { q: "Última tentativa antes de suspender", final: "Ligar no telefone cadastrado + tentar contato alternativo (CNPJ → procurar sócios em junta comercial)." },
      { q: "Suspender com prazo de retomada", final: "Pausar assinatura por 30 dias com 'cliente sem resposta'. Após 30 dias, cancelar com motivo 'inadimplência'. Notificar Wagner." },
    ]
  },
  {
    id: "suspensao",
    title: "Suspensão por inadimplência",
    icon: "pause",
    hue: 25,
    steps: [
      { q: "Quantas falhas consecutivas?", opts: [
        { label: "3 (mínimo)", next: 1 },
        { label: "4 ou mais", next: 2 },
      ]},
      { q: "Suspensão sugerida", final: "Pausar assinatura. Enviar e-mail formal informando suspensão. Manter histórico ativo para retomada. Notificar Wagner." },
      { q: "Cancelamento sugerido", final: "Cancelar com motivo 'inadimplência'. Bloquear nova contratação por 90d. Registrar em auditoria." },
    ]
  },
];

window.REC_DATA = { SUBS, PLANS, UPCOMING, KPIS, CHURN_REASONS, TIMELINES, TROUBLESHOOTERS };

})();

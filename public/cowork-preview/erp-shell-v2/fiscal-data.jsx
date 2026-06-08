// fiscal-data.jsx — Mocks do módulo Fiscal (R#1 enriched)
// Persona: contador / fiscal ops. Dados ancorados em:
//   - Modules/NfeBrasil  → NF-e (55) e NFC-e (65), DANFE, status SEFAZ
//   - Modules/NFSe       → NFS-e via Sistema Nacional (LC 214/2025)
// Códigos SEFAZ canônicos: 100/101/102/110/135/204/220/539/691/778
// R#1 enrich:
//   - emittedAtIso por nota → cálculo de prazos legais
//   - MOCK_NOW constante de referência
//   - SEFAZ_ACTIONS — receitas guiadas por código (R#2 sem precisar de IA real)
//   - SPARKLINES — séries pra mini-charts nos KPIs do cockpit
(() => {

// ─── "Agora" do sistema mock (08-09 mai, fim de tarde) ──────────────
//     Tudo o que é "prazo restante" se computa contra esta data.
const MOCK_NOW = "2026-05-09T19:00:00-03:00";

// ─── KPIs do cockpit (maio 2026) ─────────────────────────────────────
const FISCAL_KPIS = {
  emitidas:      { value: 184, deltaPct: +7.0, deltaLabel: "↑ 12 vs abr" },
  autorizadas:   { value: 181, pct: 98.4 },
  rejeitadas:    { value: 3,   label: "requer ação" },
  contingencia:  { value: 0,   label: "SEFAZ-SP ok" },
  dfeAguardando: { value: 5,   label: "manifestar até 28/05" },
  cancelHoje:    { value: 1,   label: "janela 24h" },
  ccePendente:   { value: 0,   label: "—" },
  certificadoValidadeDias: 47,
  faturamentoFiscal: 84210.50,
};

// ─── Sparklines pros KPIs (últimos 14 dias) ──────────────────────────
const SPARKLINES = {
  emitidas:    [6,  8, 12, 10,  7,  9, 14, 11, 13, 12, 15, 14, 18, 17],
  autorizadas: [6,  8, 12, 10,  7,  9, 14, 10, 12, 12, 15, 13, 18, 17],
  rejeitadas:  [0,  0,  0,  1,  0,  0,  0,  1,  1,  0,  0,  1,  0,  0],
  // Valor faturado fiscal por dia (em milhares)
  faturamento: [2.1, 3.4, 4.0, 3.8, 2.6, 3.0, 5.2, 4.0, 4.8, 4.5, 5.6, 5.0, 6.4, 6.0],
};

// ─── Alertas do cockpit (3 níveis: crit, warn, info) ─────────────────
const FISCAL_ALERTS = [
  { level: "crit", icon: "audit",
    title: "NFe 8425 rejeitada (220 · duplicidade)",
    sub: "Construtora Vértice · R$ 3.200,00 · 08/05 17:30",
    action: "Abrir mapa SEFAZ", goto: "nfe", focus: "n8425" },
  { level: "crit", icon: "audit",
    title: "NFe 8421 rejeitada (539 · chave duplicada)",
    sub: "Marmoraria Pedra Real · 07/05 11:15",
    action: "Inutilizar e refazer", goto: "nfe", focus: "n8421" },
  { level: "warn", icon: "shield",
    title: "Certificado A1 vence em 47 dias",
    sub: "wagner-04.pfx · senha em MemCofre · auto-renova: não",
    action: "Agendar renovação", goto: "fiscal_config" },
  { level: "warn", icon: "receipt",
    title: "5 DF-e aguardam manifestação",
    sub: "Notas emitidas contra o CNPJ Oimpresso · prazo legal 90d",
    action: "Manifestar lote", goto: "dfe" },
  { level: "info", icon: "refresh",
    title: "NFe 8428 dentro da janela de cancelamento",
    sub: "Restam 19h20 · Imobiliária Horizonte · R$ 540",
    action: "Ver janela", goto: "nfe", focus: "n8428" },
];

// ─── Códigos SEFAZ ───────────────────────────────────────────────────
const SEFAZ_CODES = {
  100: { tone:"ok",   label:"Autorizada",                hint:"Nota válida na SEFAZ." },
  101: { tone:"ok",   label:"Cancelamento homologado",   hint:"Cancelamento aceito." },
  102: { tone:"ok",   label:"Inutilização homologada",   hint:"Faixa de numeração inutilizada." },
  110: { tone:"bad",  label:"Uso denegado",              hint:"Destinatário irregular na SEFAZ." },
  135: { tone:"ok",   label:"Evento registrado",         hint:"CC-e ou manifestação aceita." },
  204: { tone:"bad",  label:"Duplicidade de NF-e",       hint:"Já existe nota com a mesma chave." },
  220: { tone:"bad",  label:"Duplicidade",               hint:"Numeração já usada. Inutilize ou pule a numeração." },
  539: { tone:"bad",  label:"Duplicidade de chave",      hint:"Chave de acesso já existe. Verifique o cNF aleatório." },
  691: { tone:"warn", label:"NCM divergente",            hint:"NCM informado não bate com o cadastro." },
  778: { tone:"bad",  label:"CST/CFOP inválido",         hint:"Combinação CST+CFOP rejeitada pela UF destino." },
  999: { tone:"warn", label:"Processando",               hint:"SEFAZ não respondeu ainda. Reenvio automático em 30s." },
};

// ─── SEFAZ_ACTIONS — receitas guiadas por código rejeitado ───────────
//     Cada item tem passos numerados + botão sugerido.
//     Substitui a IA real no R#1; vira "Jana sugere" sem precisar de LLM.
const SEFAZ_ACTIONS = {
  110: {
    headline: "Operação irregular — NÃO retransmitir",
    steps: [
      "Confira o CNPJ do destinatário na Receita Federal.",
      "Se inscrição estiver baixada ou suspensa, contate o cliente.",
      "Esta nota não pode ser reemitida com este destinatário.",
    ],
    primary: { label: "Marcar como bloqueada", kind: "danger" },
    secondary: { label: "Abrir cadastro do cliente", kind: "ghost" },
  },
  220: {
    headline: "Numeração colidiu — inutilize e retransmita",
    steps: [
      "Inutilize a faixa atual no SEFAZ (mantém o histórico legal).",
      "Revise o gerador de cNF (8 dígitos aleatórios não-sequenciais).",
      "Retransmita usando o próximo número da série.",
    ],
    primary: { label: "Inutilizar e retransmitir", kind: "warn" },
    secondary: { label: "Ver detalhes técnicos", kind: "ghost" },
  },
  539: {
    headline: "Chave de acesso duplicada",
    steps: [
      "A combinação CNPJ + nº + cNF + modelo + série já existe.",
      "Inutilize esta faixa de numeração.",
      "Verifique se há reenvio paralelo (queue duplicada) — checar logs.",
      "Retransmita com cNF regenerado.",
    ],
    primary: { label: "Inutilizar faixa", kind: "warn" },
    secondary: { label: "Investigar fila SEFAZ", kind: "ghost" },
  },
  691: {
    headline: "NCM divergente — revisar cadastro",
    steps: [
      "O NCM enviado não bate com o cadastro do produto.",
      "Abra o produto e confira o código NCM correto.",
      "Ajuste o item da nota e retransmita.",
    ],
    primary: { label: "Abrir produto e corrigir", kind: "primary" },
  },
  778: {
    headline: "CST/CFOP inválido para a UF destino",
    steps: [
      "Confira a UF do destinatário (regra muda por estado).",
      "Verifique tabela CST × CFOP do regime tributário atual.",
      "Ajuste no cadastro do produto ou direto na nota e retransmita.",
    ],
    primary: { label: "Ajustar tributação", kind: "primary" },
    secondary: { label: "Ver matriz CST/CFOP", kind: "ghost" },
  },
};

// ─── Notas: saída (NF-e modelo 55 + NFC-e 65) ────────────────────────
//     emittedAtIso permite cálculo de prazos legais (cancel 24h, CC-e 30d)
const NOTAS_SAIDA = [
  { id:"n8428", num:"8428", serie:1, modelo:55, key:"35260534701234000150550010000084281098765432", dest:"Imobiliária Horizonte", cnpj:"12.345.678/0001-90", uf:"SP", status:100, emittedAtIso:"2026-05-09T14:20:00-03:00", when:"09/05 14:20", value: 540.00, items:3, venda:"V-4821", cliente:"cli-imob-horizonte" },
  { id:"n8427", num:"8427", serie:1, modelo:55, key:"35260534701234000150550010000084271087654321", dest:"Imobiliária Horizonte", cnpj:"12.345.678/0001-90", uf:"SP", status:100, emittedAtIso:"2026-05-09T11:05:00-03:00", when:"09/05 11:05", value: 560.00, items:2, venda:"V-4820", cliente:"cli-imob-horizonte" },
  { id:"n8426", num:"8426", serie:1, modelo:55, key:"35260534701234000150550010000084261076543210", dest:"Restaurante Sabor & Cia", cnpj:"22.333.444/0001-55", uf:"SP", status:100, emittedAtIso:"2026-05-09T09:42:00-03:00", when:"09/05 09:42", value: 340.00, items:1, venda:"V-4819", cliente:"cli-sabor-cia" },
  { id:"n8425", num:"8425", serie:1, modelo:55, key:"35260534701234000150550010000084251065432109", dest:"Construtora Vértice", cnpj:"44.555.666/0001-77", uf:"SP", status:220, emittedAtIso:"2026-05-08T17:30:00-03:00", when:"08/05 17:30", value: 3200.00, items:6, venda:"V-4818", cliente:"cli-vertice", rejMsg:"Duplicidade de NF-e (cNF colisão)" },
  { id:"n8424", num:"8424", serie:1, modelo:55, key:"35260534701234000150550010000084241054321098", dest:"Maria Aparecida", cpf:"123.456.789-00", uf:"SP", status:100, emittedAtIso:"2026-05-08T16:18:00-03:00", when:"08/05 16:18", value: 120.00, items:1, venda:"V-4817" },
  { id:"n8423", num:"8423", serie:1, modelo:55, key:"35260534701234000150550010000084231043210987", dest:"Academia Movimento", cnpj:"33.222.111/0001-44", uf:"SP", status:999, emittedAtIso:"2026-05-08T15:00:00-03:00", when:"08/05 15:00", value: 290.00, items:2, venda:"V-4816", cliente:"cli-academia-mov" },
  { id:"n8422", num:"8422", serie:1, modelo:55, key:"35260534701234000150550010000084221032109876", dest:"Studio Foco Fotografia", cnpj:"55.444.333/0001-22", uf:"SP", status:100, emittedAtIso:"2026-05-08T11:42:00-03:00", when:"08/05 11:42", value: 1840.00, items:4, venda:"V-4815", cliente:"cli-studio-foco" },
  { id:"n8421", num:"8421", serie:1, modelo:55, key:"35260534701234000150550010000084211021098765", dest:"Marmoraria Pedra Real", cnpj:"66.777.888/0001-11", uf:"MG", status:539, emittedAtIso:"2026-05-07T11:15:00-03:00", when:"07/05 11:15", value: 2410.00, items:5, venda:"V-4814", cliente:"cli-pedra-real", rejMsg:"Chave já registrada em outro tenant" },
  { id:"n8420", num:"8420", serie:1, modelo:55, key:"35260534701234000150550010000084201010987654", dest:"Farmácia Saúde Total", cnpj:"77.888.999/0001-22", uf:"SP", status:100, emittedAtIso:"2026-05-07T09:30:00-03:00", when:"07/05 09:30", value: 680.00, items:2, venda:"V-4813", cliente:"cli-farma-saude" },
  // NFC-e (65)
  { id:"f9012", num:"9012", serie:9, modelo:65, key:"35260534701234000150650090000090121009876543", dest:"Consumidor",            cpf:"—",              uf:"SP", status:100, emittedAtIso:"2026-05-09T13:50:00-03:00", when:"09/05 13:50", value: 84.00,  items:1, venda:"V-4825" },
  { id:"f9011", num:"9011", serie:9, modelo:65, key:"35260534701234000150650090000090111008765432", dest:"Consumidor (CPF nota)", cpf:"987.654.321-00", uf:"SP", status:100, emittedAtIso:"2026-05-09T12:18:00-03:00", when:"09/05 12:18", value: 142.00, items:2, venda:"V-4824" },
  { id:"f9010", num:"9010", serie:9, modelo:65, key:"35260534701234000150650090000090101007654321", dest:"Consumidor",            cpf:"—",              uf:"SP", status:100, emittedAtIso:"2026-05-09T10:02:00-03:00", when:"09/05 10:02", value: 38.00,  items:1, venda:"V-4823" },
];

// ─── NFS-e (modelo único · Sistema Nacional LC 214/2025) ─────────────
const NOTAS_NFSE = [
  { id:"s2104", num:"2104", competencia:"05/2026", tomador:"TechPro Equipamentos",  cnpj:"55.666.777/0001-88", municipio:"São Paulo/SP", codServ:"14.05", iss: 5.0, status:"autorizada", emittedAtIso:"2026-05-09T14:55:00-03:00", when:"09/05 14:55", value: 2840.00, ref:"OS #4807 · adesivos recortados" },
  { id:"s2103", num:"2103", competencia:"05/2026", tomador:"Clínica Vida Plena",     cnpj:"33.444.555/0001-66", municipio:"São Paulo/SP", codServ:"14.05", iss: 5.0, status:"autorizada", emittedAtIso:"2026-05-09T10:12:00-03:00", when:"09/05 10:12", value: 1890.00, ref:"OS #4812 · sinalização interna" },
  { id:"s2102", num:"2102", competencia:"05/2026", tomador:"Posto BR Centro",        cnpj:"11.222.333/0001-44", municipio:"São Paulo/SP", codServ:"23.01", iss: 5.0, status:"processando", emittedAtIso:"2026-05-08T16:30:00-03:00", when:"08/05 16:30", value: 3120.00, ref:"OS #4790 · lona front-light + instalação" },
  { id:"s2101", num:"2101", competencia:"05/2026", tomador:"Padaria Estrela",        cnpj:"22.333.444/0001-55", municipio:"São Paulo/SP", codServ:"14.05", iss: 5.0, status:"autorizada", emittedAtIso:"2026-05-08T14:18:00-03:00", when:"08/05 14:18", value: 480.00,  ref:"Recorrente mensal · cardápios" },
  { id:"s2100", num:"2100", competencia:"05/2026", tomador:"Academia Movimento",     cnpj:"33.222.111/0001-44", municipio:"Guarulhos/SP", codServ:"23.01", iss: 4.0, status:"rejeitada",  emittedAtIso:"2026-05-07T17:00:00-03:00", when:"07/05 17:00", value: 1240.00, ref:"Wind banners · 3un", rejMsg:"Tomador sem inscrição municipal em Guarulhos" },
  { id:"s2099", num:"2099", competencia:"05/2026", tomador:"Maria Aparecida (autônoma)", cpf:"123.456.789-00", municipio:"São Paulo/SP", codServ:"14.05", iss: 2.0, status:"autorizada", emittedAtIso:"2026-05-07T09:42:00-03:00", when:"07/05 09:42", value: 320.00,  ref:"Adesivo veicular" },
];

// ─── DF-e: notas emitidas CONTRA o CNPJ Oimpresso (manifestação) ──────
const DFE_PENDENTE = [
  { id:"d-9871", emitente:"Avery Brasil Ltda",       cnpj:"01.234.567/0001-88", key:"35260512345678000188550010001098711098765432", value:12480.00, when:"06/05 14:00", ddays:18, type:"compra", desc:"NF entrada · bobina vinil"},
  { id:"d-9870", emitente:"Distribuidora 3M",        cnpj:"02.345.678/0001-99", key:"35260523456789000199550010001098701087654321", value: 4320.00, when:"05/05 09:30", ddays:19, type:"compra", desc:"Tintas solvente CMYK"},
  { id:"d-9869", emitente:"Eucatex Comercial",       cnpj:"03.456.789/0001-00", key:"35260534567890000100550010001098691076543210", value: 8150.00, when:"04/05 17:42", ddays:20, type:"compra", desc:"Chapas ACM 3mm · pallet"},
  { id:"d-9868", emitente:"Roland DG Suprimentos",   cnpj:"04.567.890/0001-11", key:"35260545678901000111550010001098681065432109", value: 1840.00, when:"03/05 11:18", ddays:21, type:"compra", desc:"Lâmina recorte + faca"},
  { id:"d-9867", emitente:"PWR Energia Ltda",        cnpj:"05.678.901/0001-22", key:"35260556789012000122550010001098671054321098", value:  315.00, when:"02/05 08:00", ddays:22, type:"servico", desc:"Conta luz · matriz"},
];

const DFE_HISTORICO = [
  { id:"d-9866", emitente:"Avery Brasil Ltda",    when:"abr 28", value:8420.00, ack:"confirmada", actor:"Wagner" },
  { id:"d-9865", emitente:"Distribuidora 3M",     when:"abr 25", value:3120.00, ack:"ciencia",    actor:"auto" },
  { id:"d-9864", emitente:"Companhia Brasil",     when:"abr 22", value: 540.00, ack:"desconhecimento", actor:"Wagner", obs:"não reconhecemos a compra" },
];

// ─── Eventos: CC-e, cancelamento, inutilização ───────────────────────
const EVENTOS = [
  { id:"e9421", tipo:"CCe",  nota:"NFe 8420", emit:"09/05 11:05", autor:"Wagner",
    descricao:"Correção de natureza da operação · 5102 → 5403",
    sefaz:135, sequencia:1 },
  { id:"e9420", tipo:"Cancelamento", nota:"NFe 8419", emit:"09/05 09:42", autor:"Wagner",
    descricao:"Cliente desistiu pós-emissão · estorno via venda V-4816",
    sefaz:101 },
  { id:"e9419", tipo:"Inutilização", nota:"Faixa 8418–8418", emit:"08/05 16:10", autor:"Wagner",
    descricao:"Numeração quebrada após rejeição 539 (chave duplicada)",
    sefaz:102 },
  { id:"e9418", tipo:"CCe",  nota:"NFe 8417", emit:"08/05 11:18", autor:"Eliana",
    descricao:"Corrigido endereço de entrega · campo xMunFG",
    sefaz:135, sequencia:1 },
  { id:"e9417", tipo:"Cancelamento", nota:"NFSe 2095", emit:"07/05 17:50", autor:"Wagner",
    descricao:"Serviço refaturado para o tomador correto",
    sefaz:101 },
];

// ─── Configuração: certificado, séries, ambiente ─────────────────────
const CONFIG = {
  certificado: {
    arquivo: "wagner-04.pfx",
    tipo:    "A1",
    emissor: "AC SERASA RFB v5",
    titular: "OIMPRESSO COMERCIO LTDA",
    cnpj:    "34.701.234/0001-50",
    emitidoEm: "26/06/2025",
    validade:  "26/06/2026",
    diasRestantes: 47,
    senhaCofre: "MemCofre · ref: cert-fiscal-2025",
    autoRenovar: false,
  },
  ambiente: {
    nfe:  { atual:"producao", homologUltimoTeste:"21/04/2026 10:14" },
    nfse: { atual:"producao", homologUltimoTeste:"19/04/2026 16:33" },
  },
  series: [
    { modelo:55, serie:1, proxima:8429, filial:"Matriz",  ativo:true },
    { modelo:65, serie:9, proxima:9013, filial:"Matriz",  ativo:true },
    { modelo:55, serie:2, proxima:1,    filial:"WR-Filial", ativo:false, obs:"não usada desde 2024" },
  ],
  regime:  "Lucro Presumido · trimestral",
  regimeAcoes:["ICMS-ST (substituição) ativo","ISS 5% São Paulo · 4% Guarulhos","PIS/COFINS cumulativo"],
  nfse: { tomadorMunicipal: "São Paulo/SP — inscrição 3.211.234-5 · senha cofre" },
};

// ─── SPED & Livros ───────────────────────────────────────────────────
const SPED_PERIODOS = [
  { mes:"05/2026", status:"aberto",   icms:null,            pis:null,          ecf:null,         obs:"competência em curso" },
  { mes:"04/2026", status:"pronto",   icms:"34.250 saídas / 22.180 entradas", pis:"6.420 PIS / 29.500 COFINS", ecf:"—", obs:"prazo 15/06" },
  { mes:"03/2026", status:"entregue", icms:"31.940 / 19.870", pis:"5.910 / 27.230", ecf:"—",      obs:"protocolo 2026.0341.8821" },
  { mes:"02/2026", status:"entregue", icms:"28.620 / 18.210", pis:"5.420 / 24.890", ecf:"—",      obs:"protocolo 2026.0241.7610" },
  { mes:"01/2026", status:"entregue", icms:"30.140 / 19.420", pis:"5.680 / 26.310", ecf:"—",      obs:"protocolo 2026.0141.6411" },
];

const LIVROS = [
  { id:"l1", nome:"Apuração ICMS · maio (parcial)", periodo:"01–09/05", saidas: 28420.00, entradas: 17120.00, saldo: +1840.00, status:"parcial" },
  { id:"l2", nome:"Apuração ISS · maio (parcial)",  periodo:"01–09/05", base:    6890.00, iss:        320.00, saldo:     0.00, status:"parcial" },
  { id:"l3", nome:"Conciliação SEFAZ × ERP",         periodo:"abril",    divergencias: 0,  notasErp: 178,    notasSefaz: 178, status:"ok" },
];

// ─── Expor ───────────────────────────────────────────────────────────
window.FISCAL_DATA = {
  MOCK_NOW,
  KPIS: FISCAL_KPIS,
  SPARKLINES,
  ALERTS: FISCAL_ALERTS,
  SEFAZ_CODES,
  SEFAZ_ACTIONS,
  NOTAS_SAIDA,
  NOTAS_NFSE,
  DFE_PENDENTE,
  DFE_HISTORICO,
  EVENTOS,
  CONFIG,
  SPED_PERIODOS,
  LIVROS,
};

})();

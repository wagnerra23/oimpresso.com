// ────────────────────────────────────────────────
// OFICINA / MANUTENÇÃO DE FROTA — domínio de dados
// Vertical de manutenção de caminhões e veículos pesados.
// "Agora" do protótipo = manhã do dia 16/05; dueMin = min até o prazo (neg = atrasado).
// ────────────────────────────────────────────────

// Pipeline de status (ordem importa p/ stepper e p/ avançar a OS)
const MANUT_PIPE = ["Triagem", "Diagnóstico", "Aprovação", "Execução", "Qualidade", "Pronto"];

// Status reais que uma OS pode assumir (mapeados ao pipeline acima)
const MANUT_STATUS = {
  "Triagem":               { pipe: 0, tone: "" },
  "Diagnóstico":           { pipe: 1, tone: "accent" },
  "Aguardando aprovação":  { pipe: 2, tone: "warn" },
  "Aguardando peça":       { pipe: 3, tone: "danger" },
  "Em execução":           { pipe: 3, tone: "accent" },
  "Qualidade":             { pipe: 4, tone: "accent" },
  "Pronto":                { pipe: 5, tone: "ok" },
};

// ─── LOCAIS / POSIÇÕES DA OFICINA ───
const OFICINA_LOCAIS = [
  { id: "rampa1",    nome: "Rampa 1",          tipo: "rampa",    ic: "ramp",     cap: 1, obs: "Elevadora 4t — leves/médios" },
  { id: "rampa2",    nome: "Rampa 2",          tipo: "rampa",    ic: "ramp",     cap: 1, obs: "Elevadora 4t — leves/médios" },
  { id: "elev1",     nome: "Elevador 1",       tipo: "elevador", ic: "elevator", cap: 1, obs: "Coluna 15t — pesados" },
  { id: "box-alin",  nome: "Box Alinhamento",  tipo: "box",      ic: "gauge",    cap: 1, obs: "Geometria a laser" },
  { id: "box-solda", nome: "Box Solda/Lataria",tipo: "box",      ic: "zap",      cap: 1, obs: "Solda MIG + maçarico" },
  { id: "patio",     nome: "Pátio",            tipo: "patio",    ic: "truck",    cap: 8, obs: "Triagem / aguardando / entrega" },
  { id: "lavagem",   nome: "Lavagem",          tipo: "externo",  ic: "droplet",  cap: 2, obs: "Lavador + desengraxante" },
];
const OFICINA_LOCAL_TIPOS = [
  { id: "rampa",    label: "Rampa",    ic: "ramp" },
  { id: "elevador", label: "Elevador", ic: "elevator" },
  { id: "box",      label: "Box",      ic: "wrench" },
  { id: "patio",    label: "Pátio",    ic: "truck" },
  { id: "externo",  label: "Externo",  ic: "droplet" },
];

// ─── MECÂNICOS / EQUIPE ───
const OFICINA_MECANICOS = [
  { id: "and", nome: "Anderson",  esp: "Motor / Diesel",     av: "av-2" },
  { id: "bru", nome: "Bruna",     esp: "Suspensão / Freios", av: "av-1" },
  { id: "car", nome: "Carlos",    esp: "Elétrica / Injeção", av: "av-4" },
  { id: "die", nome: "Diego",     esp: "Alinhamento / Pneus",av: "av-3" },
];

// ─── CATÁLOGO (peças + serviços p/ adicionar à OS de forma orgânica) ───
const OFICINA_CATALOGO = [
  // Serviços (mão de obra)
  { id: "sv-diag",   tipo: "servico", nome: "Diagnóstico eletrônico (scanner)", un: "serv",  preco: 150,  horas: 1.0 },
  { id: "sv-oleo",   tipo: "servico", nome: "Troca de óleo e filtros",          un: "serv",  preco: 140,  horas: 1.0 },
  { id: "sv-freio",  tipo: "servico", nome: "Revisão sistema de freios",        un: "serv",  preco: 320,  horas: 2.5 },
  { id: "sv-susp",   tipo: "servico", nome: "Mão de obra suspensão",            un: "h",     preco: 110,  horas: 1.0 },
  { id: "sv-alin",   tipo: "servico", nome: "Alinhamento e balanceamento",      un: "serv",  preco: 190,  horas: 1.5 },
  { id: "sv-inj",    tipo: "servico", nome: "Limpeza/teste de bicos injetores", un: "serv",  preco: 380,  horas: 3.0 },
  { id: "sv-revis",  tipo: "servico", nome: "Revisão programada (km)",          un: "serv",  preco: 620,  horas: 4.0 },
  // Peças
  { id: "pc-oleo",   tipo: "peca", nome: "Óleo motor 15W40 — balde 20L", un: "balde", preco: 690, sku: "OL-1540", estoque: 6 },
  { id: "pc-fol",    tipo: "peca", nome: "Filtro de óleo",               un: "un",    preco: 52,  sku: "FO-228",  estoque: 14 },
  { id: "pc-fcomb",  tipo: "peca", nome: "Filtro de combustível (Racor)",un: "un",    preco: 96,  sku: "FC-Racor",estoque: 9 },
  { id: "pc-far",    tipo: "peca", nome: "Filtro de ar",                 un: "un",    preco: 134, sku: "FA-901",  estoque: 7 },
  { id: "pc-bico",   tipo: "peca", nome: "Bico injetor (unitário)",      un: "un",    preco: 1280,sku: "BI-D13",  estoque: 0 },
  { id: "pc-past",   tipo: "peca", nome: "Pastilha de freio — jogo",     un: "jogo",  preco: 410, sku: "PF-J20",  estoque: 5 },
  { id: "pc-lona",   tipo: "peca", nome: "Lona de freio — eixo",         un: "eixo",  preco: 620, sku: "LF-E2",   estoque: 3 },
  { id: "pc-amort",  tipo: "peca", nome: "Amortecedor dianteiro",        un: "un",    preco: 540, sku: "AM-D1",   estoque: 4 },
  { id: "pc-bucha",  tipo: "peca", nome: "Bucha barra estabilizadora",   un: "un",    preco: 78,  sku: "BB-12",   estoque: 22 },
  { id: "pc-corr",   tipo: "peca", nome: "Correia poly-V",               un: "un",    preco: 188, sku: "CR-8PK",  estoque: 6 },
  { id: "pc-turbo",  tipo: "peca", nome: "Turbina (recondicionada)",     un: "un",    preco: 4200,sku: "TB-RX",   estoque: 0 },
];

// status de item: aplicado | aprovado | aguardando | comprar
const OFICINA_OS = [
  {
    id: "MAN-2039", abertura: "Ontem 15:40",
    placa: "PQR-3J45", chassi: "9BM958074MB612207", renavam: "01188224590",
    veiculo: "Mercedes-Benz Actros 2651", tipoVeic: "Cavalo mecânico 6x4", ano: 2020, cor: "Branco", km: 712450,
    frota: "FR-04", cliente: "Frota própria", motorista: "Edson Ramos",
    tipoManut: "Corretiva", status: "Aguardando peça", local: "elev1", mecanico: "and",
    prio: "alta", prazo: "Ontem 17:00", dueMin: -240,
    sintomas: "Apito agudo na aceleração e perda de pressão do turbo.",
    diagnostico: "Turbina com folga axial e eixo riscado — necessária substituição.",
    itens: [
      { id: "i1", ref: "sv-diag",  nome: "Diagnóstico eletrônico (scanner)", tipo: "servico", qty: 1, un: "serv", preco: 150,  status: "aplicado" },
      { id: "i2", ref: "pc-turbo", nome: "Turbina (recondicionada)",         tipo: "peca",    qty: 1, un: "un",   preco: 4200, status: "comprar" },
      { id: "i3", ref: "pc-oleo",  nome: "Óleo motor 15W40 — balde 20L",     tipo: "peca",    qty: 1, un: "balde",preco: 690,  status: "aprovado" },
    ],
  },
  {
    id: "MAN-2035", abertura: "Ontem 16:20",
    placa: "VWX-5L23", chassi: "9BFYCEF40NBS22841", renavam: "00997120044",
    veiculo: "Ford Cargo 1719", tipoVeic: "Caminhão 4x2 toco", ano: 2019, cor: "Azul", km: 388110,
    frota: "—", cliente: "Distribuidora Vale Verde", motorista: "Marcos Lopes",
    tipoManut: "Corretiva", status: "Aguardando aprovação", local: "patio", mecanico: "bru",
    prio: "alta", prazo: "Hoje 09:00", dueMin: -30,
    sintomas: "Freio do eixo traseiro fraco e ruído de raspagem.",
    diagnostico: "Lona vencida e tambor com sulco — recomendado jogo de lonas + retífica.",
    itens: [
      { id: "i1", ref: "sv-freio", nome: "Revisão sistema de freios", tipo: "servico", qty: 1, un: "serv", preco: 320, status: "aguardando" },
      { id: "i2", ref: "pc-lona",  nome: "Lona de freio — eixo",      tipo: "peca",    qty: 1, un: "eixo", preco: 620, status: "aguardando" },
    ],
  },
  {
    id: "MAN-2040", abertura: "Hoje 07:30",
    placa: "OQK-1B88", chassi: "9BSR4X200N3891044", renavam: "01277540918",
    veiculo: "Scania R 450", tipoVeic: "Cavalo mecânico 6x2", ano: 2021, cor: "Vermelho", km: 622140,
    frota: "FR-11", cliente: "Sul Cargas Transportes", motorista: "João Vitor",
    tipoManut: "Preventiva", status: "Aguardando aprovação", local: "rampa2", mecanico: "and",
    prio: "media", prazo: "Hoje 15:30", dueMin: 50,
    sintomas: "Revisão programada de 600 mil km.",
    diagnostico: "Revisão completa — troca de óleo, filtros e correia poly-V com desgaste.",
    itens: [
      { id: "i1", ref: "sv-revis", nome: "Revisão programada (km)",       tipo: "servico", qty: 1, un: "serv", preco: 620, status: "aguardando" },
      { id: "i2", ref: "pc-oleo",  nome: "Óleo motor 15W40 — balde 20L",  tipo: "peca",    qty: 2, un: "balde",preco: 690, status: "aguardando" },
      { id: "i3", ref: "pc-fol",   nome: "Filtro de óleo",                tipo: "peca",    qty: 1, un: "un",   preco: 52,  status: "aguardando" },
      { id: "i4", ref: "pc-fcomb", nome: "Filtro de combustível (Racor)", tipo: "peca",    qty: 2, un: "un",   preco: 96,  status: "aguardando" },
      { id: "i5", ref: "pc-corr",  nome: "Correia poly-V",                tipo: "peca",    qty: 1, un: "un",   preco: 188, status: "aguardando" },
    ],
  },
  {
    id: "MAN-2041", abertura: "Hoje 08:10",
    placa: "RTA-7G21", chassi: "9BWZZZ377VT004251", renavam: "01554120880",
    veiculo: "Volvo FH 540", tipoVeic: "Cavalo mecânico 6x4", ano: 2022, cor: "Branco", km: 418320,
    frota: "FR-12", cliente: "Transportes Andorinha", motorista: "José Carlos",
    tipoManut: "Corretiva", status: "Em execução", local: "rampa1", mecanico: "car",
    prio: "alta", prazo: "Hoje 17:00", dueMin: 80,
    sintomas: "Perda de força e fumaça preta sob carga.",
    diagnostico: "Filtro de combustível saturado; bico injetor nº3 com vazamento detectado no teste.",
    itens: [
      { id: "i1", ref: "sv-diag",  nome: "Diagnóstico eletrônico (scanner)", tipo: "servico", qty: 1, un: "serv", preco: 150,  status: "aplicado" },
      { id: "i2", ref: "pc-fcomb", nome: "Filtro de combustível (Racor)",    tipo: "peca",    qty: 2, un: "un",   preco: 96,   status: "aplicado" },
      { id: "i3", ref: "sv-inj",   nome: "Limpeza/teste de bicos injetores",  tipo: "servico", qty: 1, un: "serv", preco: 380,  status: "aprovado" },
      { id: "i4", ref: "pc-bico",  nome: "Bico injetor (unitário)",          tipo: "peca",    qty: 1, un: "un",   preco: 1280, status: "aguardando" },
    ],
  },
  {
    id: "MAN-2037", abertura: "Hoje 08:45",
    placa: "GHT-2D77", chassi: "93ZL68B01M8451220", renavam: "01099887120",
    veiculo: "Iveco Daily 35-150", tipoVeic: "Utilitário / van", ano: 2021, cor: "Branco", km: 142980,
    frota: "—", cliente: "Express Log Entregas", motorista: "Patrícia Nunes",
    tipoManut: "Corretiva", status: "Qualidade", local: "box-alin", mecanico: "die",
    prio: "media", prazo: "Hoje 14:00", dueMin: 200,
    sintomas: "Puxando para a direita e desgaste irregular dos pneus.",
    diagnostico: "Amortecedores dianteiros vencidos + alinhamento fora de geometria.",
    itens: [
      { id: "i1", ref: "pc-amort", nome: "Amortecedor dianteiro",       tipo: "peca",    qty: 2, un: "un",   preco: 540, status: "aplicado" },
      { id: "i2", ref: "sv-susp",  nome: "Mão de obra suspensão",        tipo: "servico", qty: 3, un: "h",    preco: 110, status: "aplicado" },
      { id: "i3", ref: "sv-alin",  nome: "Alinhamento e balanceamento",  tipo: "servico", qty: 1, un: "serv", preco: 190, status: "aplicado" },
    ],
  },
  {
    id: "MAN-2038", abertura: "Hoje 09:05",
    placa: "LMN-9F12", chassi: "9532E82P3MR118840", renavam: "01320998410",
    veiculo: "VW Constellation 24.280", tipoVeic: "Caminhão 6x2 truck", ano: 2018, cor: "Prata", km: 540770,
    frota: "FR-02", cliente: "Distribuidora Vale Verde", motorista: "Renato Dias",
    tipoManut: "Corretiva", status: "Triagem", local: "patio", mecanico: null,
    prio: "baixa", prazo: "Amanhã 10:00", dueMin: 1380,
    sintomas: "Ar-condicionado não gela e luz de injeção acesa.",
    diagnostico: "",
    itens: [],
  },
  {
    id: "MAN-2036", abertura: "Ontem 11:00",
    placa: "STU-7K90", chassi: "9A9SR62T0KSPN1207", renavam: "00887541290",
    veiculo: "Randon SR Graneleiro", tipoVeic: "Semirreboque 3 eixos", ano: 2017, cor: "Cinza", km: 0,
    frota: "FR-09", cliente: "Sul Cargas Transportes", motorista: "—",
    tipoManut: "Preventiva", status: "Pronto", local: "patio", mecanico: "bru",
    prio: "baixa", prazo: "Hoje 16:00", dueMin: 260,
    sintomas: "Manutenção de freios e revisão de pneus dos eixos.",
    diagnostico: "Pastilhas e buchas substituídas; sistema testado e aprovado.",
    itens: [
      { id: "i1", ref: "pc-past",  nome: "Pastilha de freio — jogo",   tipo: "peca",    qty: 3, un: "jogo", preco: 410, status: "aplicado" },
      { id: "i2", ref: "pc-bucha", nome: "Bucha barra estabilizadora", tipo: "peca",    qty: 4, un: "un",   preco: 78,  status: "aplicado" },
      { id: "i3", ref: "sv-freio", nome: "Revisão sistema de freios",  tipo: "servico", qty: 1, un: "serv", preco: 320, status: "aplicado" },
    ],
  },
];

// ─── EQUIPAMENTOS (frota / máquinas vinculados a uma pessoa cadastrada) ───
const EQUIPAMENTOS = [
  { id: "EQ-1012", cat: "veiculo", tipo: "Cavalo mecânico 6x4", marca: "Volvo", modelo: "FH 540", placa: "RTA-7G21", chassi: "9BWZZZ377VT004251", renavam: "01554120880", ano: 2022, cor: "Branco", km: 418320, apelido: "Frota 12", donoId: "c11" },
  { id: "EQ-1008", cat: "veiculo", tipo: "Cavalo mecânico 6x2", marca: "Scania", modelo: "R 450", placa: "OQK-1B88", chassi: "9BSR4X200N3891044", renavam: "01277540918", ano: 2021, cor: "Vermelho", km: 622140, apelido: "Frota 11", donoId: "c12" },
  { id: "EQ-1003", cat: "veiculo", tipo: "Cavalo mecânico 6x4", marca: "Mercedes-Benz", modelo: "Actros 2651", placa: "PQR-3J45", chassi: "9BM958074MB612207", renavam: "01188224590", ano: 2020, cor: "Branco", km: 712450, apelido: "Frota 04", donoId: "c13" },
  { id: "EQ-1019", cat: "veiculo", tipo: "Caminhão 4x2 toco", marca: "Ford", modelo: "Cargo 1719", placa: "VWX-5L23", chassi: "9BFYCEF40NBS22841", renavam: "00997120044", placa2: "VWX-5L24", chassi2: "9BFCARR0CER001920", ano: 2019, cor: "Azul", km: 388110, apelido: "", donoId: "c13" },
  { id: "EQ-1021", cat: "veiculo", tipo: "Utilitário / van", marca: "Iveco", modelo: "Daily 35-150", placa: "GHT-2D77", chassi: "93ZL68B01M8451220", renavam: "01099887120", ano: 2021, cor: "Branco", km: 142980, apelido: "", donoId: "c14" },
  { id: "EQ-1002", cat: "veiculo", tipo: "Caminhão 6x2 truck", marca: "VW", modelo: "Constellation 24.280", placa: "LMN-9F12", chassi: "9532E82P3MR118840", renavam: "01320998410", placa2: "LMN-9F13", chassi2: "9BWCARR0CER044120", ano: 2018, cor: "Prata", km: 540770, apelido: "Frota 02", donoId: "c13" },
  { id: "EQ-1009", cat: "veiculo", tipo: "Semirreboque 3 eixos", marca: "Randon", modelo: "SR Graneleiro", placa: "STU-7K90", chassi: "9A9SR62T0KSPN1207", renavam: "00887541290", ano: 2017, cor: "Cinza", km: 0, apelido: "Frota 09", donoId: "c12" },
  { id: "EQ-2207", cat: "equipamento", tipo: "Gerador diesel", marca: "Stemac", modelo: "ST 80 kVA", serie: "STC-80-44120", ano: 2020, horas: 3120, apelido: "Gerador da loja", donoId: "c6" },
  // Impressoras próprias — entram na PRODUÇÃO como estação (analogia aos locais da oficina)
  { id: "EQ-3301", cat: "equipamento", tipo: "Impressora", marca: "Roland", modelo: "VersaCAMM VS-640", serie: "RLD-640-1182", ano: 2021, horas: 4120, apelido: "Plotter Vinil", donoId: null,
    producao: true, estacaoId: "imp-vs640", impTipo: "Plotter eco-solvente", impFormato: "1,60 m", impTintas: "CMYK + Branco", impDpi: "1440 dpi", impCap: "12 m²/h", carga: 0.40 },
  { id: "EQ-3302", cat: "equipamento", tipo: "Impressora", marca: "Konica Minolta", modelo: "AccurioPrint C4065", serie: "KM-C4065-0907", ano: 2022, horas: 6890, apelido: "Digital CMYK", donoId: null,
    producao: true, estacaoId: "imp-c4065", impTipo: "Impressão digital (toner)", impFormato: "SRA3", impTintas: "Toner CMYK", impDpi: "1200 dpi", impCap: "65 ppm", carga: 0.66 },
];
const EQUIP_TIPOS_VEIC = ["Cavalo mecânico 6x4", "Cavalo mecânico 6x2", "Caminhão 4x2 toco", "Caminhão 6x2 truck", "Caminhão 8x2 bitruck", "Semirreboque 3 eixos", "Bitrem / rodotrem", "Utilitário / van", "VUC / 3-4", "Ônibus / micro-ônibus", "Carro de passeio", "Motocicleta"];
const EQUIP_TIPOS_EQUIP = ["Impressora", "Plotter de recorte", "Laminadora", "Guilhotina / refile", "Máquina / implemento", "Gerador diesel", "Compressor", "Empilhadeira"];
const EQUIP_TIPOS = [...EQUIP_TIPOS_VEIC, ...EQUIP_TIPOS_EQUIP]; // compat

// Impressoras: campos exclusivos p/ produção (a máquina vira estação na Produção)
const IMP_TIPOS = ["Impressão digital (toner)", "Offset", "Plotter eco-solvente", "Plotter solvente", "Plotter UV", "Sublimática", "Látex", "DTF"];
const IMP_FORMATOS = ["A4", "A3", "A3+", "SRA3", "0,90 m", "1,10 m", "1,60 m", "3,20 m"];
const IMP_TINTAS = ["CMYK", "CMYK + Branco", "CMYK + Verniz", "CMYK Lc/Lm", "Toner CMYK", "Pigmentada", "Sublimática"];

// ─── CHECKLIST DE SERVIÇO (passos de execução, marcáveis — X/Y) ───
// Independente dos itens faturáveis: é o roteiro de trabalho do mecânico.
const OFICINA_CHECKLISTS = {
  "MAN-2039": [
    { id: "c1", label: "Diagnóstico do turbo (folga axial)", done: true },
    { id: "c2", label: "Drenar óleo e desconectar dutos", done: true },
    { id: "c3", label: "Remover turbina antiga", done: true },
    { id: "c4", label: "Instalar turbina recondicionada", done: false },
    { id: "c5", label: "Teste de pressão e vazamento", done: false },
  ],
  "MAN-2035": [
    { id: "c1", label: "Elevar veículo e remover rodas traseiras", done: true },
    { id: "c2", label: "Medir lona e tambor", done: true },
    { id: "c3", label: "Substituir jogo de lonas", done: false },
    { id: "c4", label: "Retífica do tambor", done: false },
    { id: "c5", label: "Regulagem e teste de freio", done: false },
  ],
  "MAN-2040": [
    { id: "c1", label: "Inspeção geral de revisão (600 mil km)", done: true },
    { id: "c2", label: "Orçar peças e mão de obra", done: true },
    { id: "c3", label: "Drenar e trocar óleo do motor", done: false },
    { id: "c4", label: "Substituir filtros (óleo, ar, combustível)", done: false },
    { id: "c5", label: "Trocar correia poly-V", done: false },
    { id: "c6", label: "Zerar avisos e teste final", done: false },
  ],
  "MAN-2041": [
    { id: "c1", label: "Scanner de falhas (códigos de injeção)", done: true },
    { id: "c2", label: "Substituir filtro de combustível", done: true },
    { id: "c3", label: "Teste de vazão dos bicos injetores", done: true },
    { id: "c4", label: "Substituir bico injetor nº3", done: false },
    { id: "c5", label: "Teste de potência sob carga", done: false },
  ],
  "MAN-2037": [
    { id: "c1", label: "Substituir amortecedores dianteiros", done: true },
    { id: "c2", label: "Conferir buchas e batentes", done: true },
    { id: "c3", label: "Alinhamento de geometria a laser", done: true },
    { id: "c4", label: "Balanceamento das rodas", done: true },
    { id: "c5", label: "Teste de pista e validação final", done: false },
  ],
  "MAN-2038": [
    { id: "c1", label: "Ler códigos de falha (injeção + A/C)", done: false },
    { id: "c2", label: "Verificar carga do sistema de A/C", done: false },
  ],
  "MAN-2036": [
    { id: "c1", label: "Substituir pastilhas e buchas", done: true },
    { id: "c2", label: "Conferir pneus dos 3 eixos", done: true },
    { id: "c3", label: "Teste de freio e inspeção final", done: true },
  ],
};

// helpers
window.MOCK.OFICINA_CHECKLISTS = OFICINA_CHECKLISTS;
window.MOCK.OFICINA_LOCAIS = OFICINA_LOCAIS;
window.MOCK.OFICINA_LOCAL_TIPOS = OFICINA_LOCAL_TIPOS;
window.MOCK.OFICINA_MECANICOS = OFICINA_MECANICOS;
window.MOCK.OFICINA_CATALOGO = OFICINA_CATALOGO;
window.MOCK.OFICINA_OS = OFICINA_OS;
window.MOCK.MANUT_PIPE = MANUT_PIPE;
window.MOCK.MANUT_STATUS = MANUT_STATUS;
window.MOCK.EQUIPAMENTOS = EQUIPAMENTOS;
window.MOCK.EQUIP_TIPOS = EQUIP_TIPOS;
window.MOCK.EQUIP_TIPOS_VEIC = EQUIP_TIPOS_VEIC;
window.MOCK.EQUIP_TIPOS_EQUIP = EQUIP_TIPOS_EQUIP;
window.MOCK.IMP_TIPOS = IMP_TIPOS;
window.MOCK.IMP_FORMATOS = IMP_FORMATOS;
window.MOCK.IMP_TINTAS = IMP_TINTAS;
// Tipo de equipamento considerado impressora (campos de produção)
window.MOCK.isImpressora = (tipo) => /impressora|plotter|offset|látex|latex|sublim/i.test(tipo || "");
// Estações de produção = estações-base + impressoras cadastradas em Equipamentos
window.MOCK.estacoesProducao = () => {
  const base = window.MOCK.PRODUCAO_ESTACOES || [];
  const printers = EQUIPAMENTOS.filter(e => e.producao).map(e => ({
    id: e.estacaoId || e.id,
    nome: e.apelido ? (e.apelido + " · " + e.modelo) : (e.marca + " " + e.modelo),
    carga: typeof e.carga === "number" ? e.carga : 0.3,
    jobsHoje: 0, status: e.carga > 0.85 ? "warn" : "ok",
    fromEquip: true, equipId: e.id,
  }));
  return [...base, ...printers];
};
window.MOCK.equipDono = (eq) => (window.MOCK.CLIENTES || []).find(c => c.id === (eq && eq.donoId));
window.MOCK.equipHist = (eq) => eq && eq.placa ? window.MOCK.OFICINA_OS.filter(o => o.placa === eq.placa) : [];

window.MOCK.mecNome = (id) => (OFICINA_MECANICOS.find(m => m.id === id) || {}).nome || "—";
window.MOCK.localNome = (id) => (OFICINA_LOCAIS.find(l => l.id === id) || {}).nome || "—";
window.MOCK.osTotal = (os) => (os.itens || []).reduce((s, i) => s + i.preco * i.qty, 0);
window.MOCK.osTotalAprovado = (os) => (os.itens || [])
  .filter(i => i.status === "aplicado" || i.status === "aprovado")
  .reduce((s, i) => s + i.preco * i.qty, 0);
window.MOCK.osPendentes = (os) => (os.itens || []).filter(i => i.status === "aguardando").length;

// Mock data refletindo o domínio do mini-erp:
// - tenants (empresa ativa)
// - tarefas (origem: OS/CRM/FIN/PNT/MFG)
// - pedidos (com etapas)
// - produtos (estoque)
// - transações (financeiro)
// - notificações

const TENANTS = [
  { id: "oi",   short: "OI",  name: "Oimpresso",            cnpj: "21.998.310/0001-21", color: "av-2" },
  { id: "wr2",  short: "WR",  name: "WR2 Sistemas",         cnpj: "18.554.012/0001-08", color: "av-3" },
  { id: "tcp",  short: "TC",  name: "Toca da Pedra Studio", cnpj: "44.221.665/0001-77", color: "av-1" },
];

const USER = {
  nome: "Wagner Rocha",
  email: "wagner@oimpresso.com.br",
  role: "Admin · Vendas",
  initials: "WR",
};

// ─── TAREFAS (inbox unificada estilo web) ───
const TASKS_FULL = [
  {
    id: "t-os-3041", origin: "OS",  color: "amber",
    title: "Aprovar arte: 1.000 cartões — Marília Costa",
    sub: "OS-3041 · enviado pelo cliente há 12min",
    when: "agora",
    urgent: true,
    bucket: "hoje",
    cliente: "Marília Costa",
    prazo: "Hoje 18:00",
    estagio: "Aguardando aprovação",
    valor: 248.0,
    arte: "cartoes_marilia_v3.pdf",
    viewer: "OsAprovarArte",
  },
  {
    id: "t-fin-902", origin: "FIN", color: "emerald",
    title: "Boleto vencendo: Marília Costa — R$ 248,00",
    sub: "FIN-902 · vence hoje",
    when: "Hoje",
    urgent: true,
    bucket: "hoje",
    cliente: "Marília Costa",
    valor: 248.0,
    venc: "Hoje",
    viewer: "FinBoleto",
  },
  {
    id: "t-crm-77", origin: "CRM", color: "blue",
    title: "Retornar contato: Studio Verde Lima",
    sub: "CRM-77 · último contato há 4 dias",
    when: "Hoje",
    bucket: "hoje",
    cliente: "Studio Verde Lima",
    fone: "(11) 95412-8821",
    viewer: "CrmContato",
  },
  {
    id: "t-mfg-12", origin: "MFG", color: "orange",
    title: "Liberar produção: Banner 3x1m — Restaurante 88",
    sub: "MFG-12 · arte já aprovada",
    when: "Hoje 14:00",
    bucket: "hoje",
    cliente: "Restaurante 88",
    valor: 480.0,
    viewer: "MfgLiberar",
  },
  {
    id: "t-os-3038", origin: "OS",  color: "amber",
    title: "Confirmar entrega: OS-3038 — Clínica Vita",
    sub: "OS-3038 · saiu para entrega",
    when: "Amanhã 09:00",
    bucket: "amanha",
    cliente: "Clínica Vita",
    viewer: "OsEntrega",
  },
  {
    id: "t-pnt-5", origin: "PNT", color: "violet",
    title: "Justificar marcação faltante de ontem",
    sub: "PNT-5 · saída registrada às 17:42",
    when: "Amanhã",
    bucket: "amanha",
    viewer: "PntJustificar",
  },
  {
    id: "t-crm-78", origin: "CRM", color: "blue",
    title: "Enviar orçamento: Bistrô do Forno",
    sub: "CRM-78 · pediu retorno até quinta",
    when: "Quinta",
    bucket: "semana",
    cliente: "Bistrô do Forno",
    viewer: "CrmOrcamento",
  },
  {
    id: "t-fin-905", origin: "FIN", color: "emerald",
    title: "Conciliar PIX recebido: R$ 1.420,00",
    sub: "FIN-905 · ainda sem cliente vinculado",
    when: "Quinta",
    bucket: "semana",
    viewer: "FinConciliar",
  },
];

const TASKS_EMPTY = [];
const TASKS_URGENT = TASKS_FULL.filter(t => t.urgent || t.origin === "FIN");

// ─── PEDIDOS ───
// nome = título curto · specs = ficha técnica · thumb = ícone do tipo de produto
const PEDIDOS = [
  {
    id: "OS-3041", cliente: "Marília Costa",
    nome: "Cartão de Visita", produto: "1.000 cartões 9x5 4/4",
    specs: "1.000 un · 9x5cm · Couché 300g · 4x4", thumb: "tag",
    valor: 248.0, etapa: "Aguardando aprovação", etapaKey: "aprov",
    prazo: "Hoje 18:00", urgent: true,
    atualizado: "12min", criado: "Hoje 09:14",
    progresso: 0.3,
  },
  {
    id: "OS-3038", cliente: "Clínica Vita",
    nome: "Folder A4 4/4", produto: "Folder A4 4/4 — 500 un",
    specs: "500 un · A4 · Couché 150g · 4/4", thumb: "file",
    valor: 612.0, etapa: "Saiu para entrega", etapaKey: "entrega",
    prazo: "Amanhã 09:00",
    atualizado: "2h", criado: "Ontem 11:02",
    progresso: 0.85,
  },
  {
    id: "OS-3035", cliente: "Restaurante 88",
    nome: "Banner 3x1m vinil", produto: "Banner 3x1m vinil",
    specs: "1 un · 3x1m · Lona 440g · 4/0", thumb: "image",
    valor: 480.0, etapa: "Em produção", etapaKey: "prod",
    prazo: "Sex 16:00",
    atualizado: "ontem", criado: "Seg 14:30",
    progresso: 0.55,
  },
  {
    id: "OS-3029", cliente: "Studio Verde Lima",
    nome: "Adesivos vinil 5x5", produto: "Adesivos vinil 5x5 — 200 un",
    specs: "200 un · 5x5cm · Vinil branco · 4/0", thumb: "image",
    valor: 180.0, etapa: "Concluído", etapaKey: "done",
    prazo: "Entregue",
    atualizado: "Sex", criado: "23/04",
    progresso: 1,
  },
  {
    id: "OS-3028", cliente: "Bistrô do Forno",
    nome: "Cardápio A3 dobrado", produto: "Cardápio A3 dobrado",
    specs: "50 un · A3 · Couché 250g · 4/4", thumb: "file",
    valor: 320.0, etapa: "Orçamento", etapaKey: "orc",
    prazo: "—",
    atualizado: "Sex", criado: "22/04",
    progresso: 0.1,
  },
  {
    id: "OS-3025", cliente: "Açougue Premium",
    nome: "Etiqueta térmica 5cm", produto: "Etiqueta térmica 5cm",
    specs: "8 rolos · 5cm · Térmica · 1/0", thumb: "printer",
    valor: 92.0, etapa: "Concluído", etapaKey: "done",
    prazo: "Entregue",
    atualizado: "Qui", criado: "20/04",
    progresso: 1,
  },
];

// ─── DASHBOARD HOME (widgets do modelo aprovado) ───
const HOME_DASH = {
  tiles: [
    { label: "Pedidos em produção", value: "6", sub: "+2 vs ontem", subTone: "accent", thumb: "box", tone: "accent" },
    { label: "Receber hoje",        value: "R$ 4.580,00", money: true, sub: "R$ 2.480 em aberto", subTone: "mute", thumb: "box", tone: "ok" },
    { label: "Entregas do dia",     value: "5", sub: "2 atrasadas", subTone: "danger", thumb: "box", tone: "danger" },
  ],
  spark: [
    { label: "Recebido hoje", value: "R$ 2.100,00", sub: "46% do previsto", tone: "ok",     data: [3,4,3,5,4,6,5,7,6,8,7,9] },
    { label: "Falta receber", value: "R$ 2.480,00", sub: "54% · 2 boletos", tone: "danger", data: [6,5,7,6,8,5,7,6,8,7,9,8] },
  ],
  weekly: [
    { label: "Faturamento",  value: "R$ 12.450,00", delta: "12%", data: [4,5,4,6,5,7,6,8,7,9,8,11] },
    { label: "Pedidos",      value: "28",          delta: "7%",  data: [3,4,5,4,6,5,7,6,7,8,7,9] },
    { label: "Ticket médio", value: "R$ 445,00",   delta: "9%",  data: [5,4,6,5,7,6,5,7,6,8,7,8] },
  ],
  assist: [
    { text: "3 pedidos estão atrasados", tab: "pedidos" },
    { text: "2 boletos vencem hoje", time: "08:30", tab: "financas" },
  ],
};

// ─── PRODUTOS ───
const PRODUTOS = [
  { id: "P-001", nome: "Cartão visita 9x5 4/4",   cat: "Impressos", preco: 248.0, estoque: 38, min: 50, sku: "CV-9X5-44",   un: "milheiro", img: "tag" },
  { id: "P-002", nome: "Folder A4 4/4",            cat: "Impressos", preco: 612.0, estoque: 12, min: 20, sku: "FD-A4-44",    un: "milheiro", img: "file" },
  { id: "P-003", nome: "Banner vinil 3x1m",        cat: "Sinalização", preco: 160.0, estoque: 7,  min: 10, sku: "BV-3X1",     un: "unid",     img: "image" },
  { id: "P-004", nome: "Adesivo vinil 5x5",        cat: "Adesivos",  preco: 0.90, estoque: 1240, min: 500, sku: "AV-5X5",     un: "unid",     img: "tag" },
  { id: "P-005", nome: "Etiqueta térmica 5cm",     cat: "Etiquetas", preco: 28.0, estoque: 64, min: 30, sku: "ET-T-5",     un: "rolo",     img: "printer" },
  { id: "P-006", nome: "Lona front 3x2m",          cat: "Sinalização", preco: 220.0, estoque: 4,  min: 6,  sku: "LF-3X2",     un: "unid",     img: "image" },
  { id: "P-007", nome: "Papel couchê 250g A3",     cat: "Insumos",   preco: 0.46, estoque: 3200, min: 1000, sku: "PC-25-A3",  un: "folha",    img: "file" },
  { id: "P-008", nome: "Cartão visita 9x5 laminado", cat: "Impressos", preco: 320.0, estoque: 24, min: 30, sku: "CV-9X5-LM",  un: "milheiro", img: "tag" },
];

// ─── TRANSAÇÕES ───
const TRANSACOES = [
  { id: "T-1042", tipo: "in",  desc: "PIX — Restaurante 88",         categoria: "Venda OS-3035", valor: 480.0,  data: "Hoje 09:32",   conta: "PIX",   status: "ok",   meio: "PIX" },
  { id: "T-1041", tipo: "out", desc: "Insumos gráficos — Suzano",    categoria: "Compra fornecedor", valor: 1290.0, data: "Hoje 08:14",   conta: "Conta Corrente", status: "ok", meio: "Boleto" },
  { id: "T-1040", tipo: "in",  desc: "Cartão — Açougue Premium",      categoria: "Venda OS-3025", valor: 92.0,   data: "Ontem 16:50",  conta: "Cielo", status: "ok",   meio: "Crédito" },
  { id: "T-1039", tipo: "in",  desc: "Boleto — Marília Costa",        categoria: "OS-3041", valor: 248.0,  data: "Vence Hoje", conta: "Boleto", status: "warn", meio: "Boleto" },
  { id: "T-1038", tipo: "out", desc: "Combustível — frota",           categoria: "Despesa op.",  valor: 320.0,  data: "Ontem 12:10",  conta: "Conta Corrente", status: "ok", meio: "Débito" },
  { id: "T-1037", tipo: "in",  desc: "PIX — Studio Verde Lima",       categoria: "OS-3029", valor: 180.0,  data: "Sex 11:22",    conta: "PIX",   status: "ok",   meio: "PIX" },
  { id: "T-1036", tipo: "out", desc: "Folha — quinzena",              categoria: "Pessoal",      valor: 4280.0, data: "Sex 09:00",    conta: "Conta Corrente", status: "ok", meio: "TED" },
];

// ─── CLIENTES ───
// ─── PAPÉIS (classificações de uma pessoa — múltiplas por cadastro) ───
const PAPEIS = [
  { id: "cliente",       label: "Cliente",       short: "CLI",    color: "oklch(0.58 0.12 220)" },
  { id: "fornecedor",    label: "Fornecedor",    short: "FORN",   color: "oklch(0.58 0.13 145)" },
  { id: "funcionario",   label: "Funcionário",   short: "FUNC",   color: "oklch(0.55 0.13 295)" },
  { id: "transportadora",label: "Transportadora",short: "TRANSP", color: "oklch(0.60 0.14 70)" },
];

// ─── PESSOAS (clientes / fornecedores / funcionários…) ───
const CLIENTES = [
  { id: "c1", nome: "Marília Costa",        papeis: ["cliente"],                tipo: "PF", doc: "412.998.220-15", fone: "(11) 95412-8821", email: "marilia@exemplo.com", cidade: "São Paulo", pedidos: 6,  ticket: 412.0,  ultimo: "Hoje",     ativo: true,  tags: ["VIP", "Boleto 7d"], saldo: -248.0,  av: "av-1" },
  { id: "c2", nome: "Restaurante 88",       papeis: ["cliente"],                tipo: "PJ", doc: "12.881.220/0001-30", fone: "(11) 4002-8800", email: "compras@rest88.com", cidade: "São Paulo", pedidos: 14, ticket: 380.0,  ultimo: "Ontem",    ativo: true,  tags: ["Mensalista"], saldo: 0,        av: "av-2" },
  { id: "c3", nome: "Clínica Vita",         papeis: ["cliente"],                tipo: "PJ", doc: "33.110.005/0001-12", fone: "(11) 3221-9090", email: "ana@clinicavita.com.br", cidade: "Osasco", pedidos: 9,  ticket: 612.0,  ultimo: "Sex",      ativo: true,  tags: ["NF-e"], saldo: 0,            av: "av-3" },
  { id: "c4", nome: "Studio Verde Lima",    papeis: ["cliente", "fornecedor"],  tipo: "PJ", doc: "44.221.665/0001-77", fone: "(11) 4007-2299", email: "ola@verdelima.com", cidade: "São Paulo", pedidos: 3,  ticket: 220.0,  ultimo: "4 dias",   ativo: true,  tags: ["Em retorno"], saldo: 0,        av: "av-4" },
  { id: "c5", nome: "Bistrô do Forno",      papeis: ["cliente"],                tipo: "PJ", doc: "55.881.299/0001-04", fone: "(11) 91020-4471", email: "rogerio@bistrodoforno.com.br", cidade: "Guarulhos", pedidos: 1, ticket: 320.0, ultimo: "Sex",  ativo: true,  tags: ["Aguardando"], saldo: 0,        av: "av-5" },
  { id: "c6", nome: "Açougue Premium",      papeis: ["cliente"],                tipo: "PJ", doc: "61.227.118/0001-50", fone: "(11) 4111-5566", email: "matheus@premium.com.br", cidade: "São Paulo", pedidos: 22, ticket: 95.0, ultimo: "Qui", ativo: true,  tags: ["Recorrente"], saldo: 0,         av: "av-6" },
  { id: "c7", nome: "André Santiago",       papeis: ["funcionario", "cliente"], tipo: "PF", doc: "182.441.998-02", fone: "(11) 99820-1011", email: "andre@email.com", cidade: "São Paulo", pedidos: 2, ticket: 180.0, ultimo: "10 dias", ativo: true, tags: ["Vendas"], saldo: 0,          av: "av-3" },
  { id: "c8", nome: "Suzano Papéis S.A.",   papeis: ["fornecedor"],             tipo: "PJ", doc: "[REDACTED-CNPJ]", fone: "(11) 3503-2000", email: "vendas@suzano.com.br", cidade: "São Paulo", pedidos: 0, ticket: 0, ultimo: "Seg", ativo: true, tags: ["Insumos"], saldo: 0,        av: "av-2" },
  { id: "c9", nome: "Transportadora Veloz", papeis: ["fornecedor", "transportadora"], tipo: "PJ", doc: "29.881.110/0001-44", fone: "(11) 4444-7788", email: "operacao@veloz.com.br", cidade: "Guarulhos", pedidos: 0, ticket: 0, ultimo: "Qua", ativo: true, tags: ["Frete"], saldo: 0,   av: "av-4" },
  { id: "c10", nome: "Daniela Souza",       papeis: ["funcionario"],            tipo: "PF", doc: "335.220.118-90", fone: "(11) 98140-2231", email: "daniela@oimpresso.com.br", cidade: "São Paulo", pedidos: 0, ticket: 0, ultimo: "Hoje", ativo: true, tags: ["Acabamento"], saldo: 0,  av: "av-5" },
  { id: "c11", nome: "Transportes Andorinha", papeis: ["cliente"],              tipo: "PJ", doc: "27.554.120/0001-88", fone: "(11) 4055-1200", email: "frota@andorinha.com.br", cidade: "Guarulhos", pedidos: 18, ticket: 1240.0, ultimo: "Hoje", ativo: true, tags: ["Frota", "Contrato"], saldo: 0, av: "av-2" },
  { id: "c12", nome: "Sul Cargas Transportes", papeis: ["cliente"],             tipo: "PJ", doc: "12.775.409/0001-18", fone: "(11) 4112-7700", email: "manutencao@sulcargas.com.br", cidade: "São Bernardo", pedidos: 24, ticket: 1680.0, ultimo: "Ontem", ativo: true, tags: ["Frota", "Mensalista"], saldo: -1840.0, av: "av-3" },
  { id: "c13", nome: "Distribuidora Vale Verde", papeis: ["cliente"],           tipo: "PJ", doc: "13.209.984/0001-10", fone: "(11) 4330-2210", email: "logistica@valeverde.com.br", cidade: "Osasco", pedidos: 11, ticket: 920.0, ultimo: "Hoje", ativo: true, tags: ["Frota"], saldo: 0, av: "av-4" },
  { id: "c14", nome: "Express Log Entregas", papeis: ["cliente"],              tipo: "PJ", doc: "10.998.871/0001-20", fone: "(11) 91200-4471", email: "contato@expresslog.com.br", cidade: "São Paulo", pedidos: 7, ticket: 540.0, ultimo: "3 dias", ativo: true, tags: ["Frota leve"], saldo: 0, av: "av-1" },
];

// ─── PRODUÇÃO ───
// Pipeline canônico de etapas (ordem importa p/ avançar job e p/ o stepper)
const PRODUCAO_ETAPAS = ["Em fila", "Imprimindo", "Acabamento", "Pronto"];

const PRODUCAO_ESTACOES = [
  { id: "imp4c", nome: "Impressora 4 cores", carga: 0.74, jobsHoje: 9, status: "ok" },
  { id: "plot1", nome: "Plotter 1 — Lona",   carga: 0.45, jobsHoje: 4, status: "ok" },
  { id: "plot2", nome: "Plotter 2 — Vinil",  carga: 0.82, jobsHoje: 6, status: "warn" },
  { id: "corte", nome: "Mesa de corte",      carga: 0.58, jobsHoje: 5, status: "ok" },
  { id: "acaba", nome: "Bancada acabamento", carga: 0.93, jobsHoje: 7, status: "warn" },
  { id: "exped", nome: "Expedição",          carga: 0.38, jobsHoje: 5, status: "ok" },
];

// dueMin = minutos até o prazo a partir do "agora" do protótipo (negativo = atrasado).
// Alimenta a triagem por urgência e os selos de prazo na consulta.
const PRODUCAO_JOBS = [
  { id: "MFG-07", os: "OS-3027", cliente: "Studio Verde Lima", produto: "Adesivos vinil 5x5 — 200 un", qtd: "200 un", estacao: "corte", etapa: "Acabamento", prazo: "Hoje 07:40", dueMin: -120, duracao: "40min", prio: "alta",  op: "André",   prog: 0.7 },
  { id: "MFG-12", os: "OS-3035", cliente: "Restaurante 88",    produto: "Banner 3x1m vinil",          qtd: "1 un",   estacao: "plot2", etapa: "Imprimindo", prazo: "Hoje 09:00", dueMin: -40,  duracao: "45min", prio: "alta",  op: "Lucas",   prog: 0.55 },
  { id: "MFG-10", os: "OS-3033", cliente: "Clínica Vita",      produto: "200 receituários A5",        qtd: "200 un", estacao: "imp4c", etapa: "Imprimindo", prazo: "Hoje 10:15", dueMin: 35,   duracao: "25min", prio: "media", op: "Lucas",   prog: 0.62 },
  { id: "MFG-11", os: "OS-3041", cliente: "Marília Costa",     produto: "1.000 cartões 9x5 4/4",      qtd: "1.000 un",estacao: "imp4c", etapa: "Em fila",    prazo: "Hoje 10:50", dueMin: 70,   duracao: "1h10",  prio: "alta",  op: null,      nota: "Arte aprovada · pronta p/ rodar" },
  { id: "MFG-14", os: "OS-3043", cliente: "Restaurante 88",    produto: "Cardápio QR de mesa",        qtd: "30 un",  estacao: "corte", etapa: "Imprimindo", prazo: "Hoje 11:10", dueMin: 90,   duracao: "35min", prio: "media", op: "André",   prog: 0.25 },
  { id: "MFG-09", os: "OS-3032", cliente: "Bistrô do Forno",   produto: "Cardápio A3 laminado",       qtd: "50 un",  estacao: "acaba", etapa: "Acabamento", prazo: "Hoje 12:10", dueMin: 150,  duracao: "55min", prio: "media", op: "Daniela", prog: 0.35 },
  { id: "MFG-04", os: "OS-3019", cliente: "Bar do Zé",         produto: "Cardápio plastificado",      qtd: "20 un",  estacao: "acaba", etapa: "Acabamento", prazo: "Hoje 13:00", dueMin: 200,  duracao: "30min", prio: "media", op: "Daniela", prog: 0.5 },
  { id: "MFG-08", os: "OS-3030", cliente: "Açougue Premium",   produto: "Etiqueta térmica 5cm",       qtd: "8 rolos",estacao: "exped", etapa: "Pronto",     prazo: "Hoje 13:40", dueMin: 240,  duracao: "—",     prio: "baixa", op: "Carla",   prog: 1 },
  { id: "MFG-13", os: "OS-3042", cliente: "Padaria Pão Dourado",produto: "Banner promo 2x1m",         qtd: "1 un",   estacao: "plot1", etapa: "Em fila",    prazo: "Hoje 14:40", dueMin: 300,  duracao: "40min", prio: "media", op: null,      nota: "Aguardando liberação de arte" },
  { id: "MFG-06", os: "OS-3024", cliente: "Clínica Vita",      produto: "Folder A4 4/4 — 500 un",     qtd: "500 un", estacao: "exped", etapa: "Pronto",     prazo: "Ontem 16:40",dueMin: -300, duracao: "—",     prio: "media", op: "Lucas",   prog: 1 },
  { id: "MFG-05", os: "OS-3022", cliente: "Mercado Bom Preço", produto: "Faixa de lona 5x1m",         qtd: "2 un",   estacao: "plot1", etapa: "Em fila",    prazo: "Hoje 17:40", dueMin: 480,  duracao: "50min", prio: "baixa", op: null },
  { id: "MFG-15", os: "OS-3045", cliente: "Academia Corpo",    produto: "Adesivo de vitrine",         qtd: "1 un",   estacao: "plot2", etapa: "Em fila",    prazo: "Amanhã 07:40",dueMin: 1320, duracao: "30min", prio: "baixa", op: null },
  { id: "MFG-16", os: "OS-3046", cliente: "Escola Saber",      produto: "Crachás PVC",                qtd: "120 un", estacao: "imp4c", etapa: "Em fila",    prazo: "Amanhã 10:40",dueMin: 1500, duracao: "1h",    prio: "baixa", op: null },
];
const NOTIFS = [
  { id: "n1", origin: "OS",  title: "Marília Costa aprovou parcialmente a arte",  sub: "OS-3041 · pediu ajuste no telefone", when: "agora", unread: true },
  { id: "n2", origin: "FIN", title: "PIX recebido R$ 480,00 — Restaurante 88",     sub: "T-1042 · vinculado à OS-3035", when: "1h", unread: true },
  { id: "n3", origin: "MFG", title: "Arte do banner 3x1m liberada para produção",  sub: "MFG-12 · fila Plotter 1", when: "2h", unread: false },
  { id: "n4", origin: "CRM", title: "Studio Verde Lima abriu seu orçamento",       sub: "CRM-77 · 3ª vez hoje", when: "Ontem", unread: false },
  { id: "n5", origin: "PNT", title: "Lembrete: marcação faltante 22/abr",           sub: "PNT-5 · saída sem registro", when: "Ontem", unread: false },
];

// ─── KPIs HOME ───
const OPS_KPIS = {
  faturamento_hoje: 1840.00,
  faturamento_meta: 2500.00,
  pedidos_hoje: 7,
  os_em_aberto: 14,
  os_urgentes: 3,
  estoque_baixo: 4,
  contas_a_receber: 6420.00,
  contas_a_pagar: 4870.00,
};

window.MOCK = { TENANTS, USER, TASKS_FULL, TASKS_EMPTY, TASKS_URGENT, PEDIDOS, PRODUTOS, TRANSACOES, NOTIFS, OPS_KPIS, HOME_DASH, CLIENTES, PRODUCAO_ESTACOES, PRODUCAO_JOBS, PRODUCAO_ETAPAS, PAPEIS };

// ─── Coerência Início ↔ Produção: tiles DERIVADOS dos jobs reais ───
// Antes eram literais ("6", "Entregas do dia 5") que divergiam quando os
// jobs avançavam. Agora contam o estado real — fonte única de verdade.
(() => {
  const J = PRODUCAO_JOBS;
  const emProducao = J.filter(j => !j.expedido && (j.etapa === "Imprimindo" || j.etapa === "Acabamento")).length;
  const ativos     = J.filter(j => !j.expedido && j.etapa !== "Pronto").length;
  const prontos    = J.filter(j => !j.expedido && j.etapa === "Pronto").length;
  const atrasados  = J.filter(j => !j.expedido && j.etapa !== "Pronto" && j.dueMin < 0).length;

  const tProd = HOME_DASH.tiles[0];
  if (tProd) {
    tProd.value = String(emProducao);
    tProd.sub = atrasados > 0 ? atrasados + " atrasados" : ativos + " ativos";
    tProd.subTone = atrasados > 0 ? "danger" : "accent";
  }
  const tExp = HOME_DASH.tiles[2];
  if (tExp) {
    tExp.label = "Prontos p/ expedir";
    tExp.value = String(prontos);
    tExp.sub = prontos > 0 ? "para entrega hoje" : "nada na fila";
    tExp.subTone = "ok";
    tExp.tone = "ok";
  }
})();

// Expande um cliente da lista para a ficha cadastral completa (preenche defaults
// plausíveis para os campos que a listagem não carrega).
window.MOCK.fullCliente = (c) => {
  if (!c) return null;
  const isPJ = c.tipo === "PJ";
  const first = (c.nome || "").split(" ")[0].toLowerCase();
  return {
    id: c.id,
    papeis: c.papeis || ["cliente"],
    tipo: isPJ ? "pj" : "pf",
    nome: c.nome,
    fantasia: isPJ ? (c.nome.replace(/\s+(LTDA|ME|EIRELI|S\.?A\.?).*$/i, "").split(" ").slice(0, 2).join(" ")) : "",
    doc: c.doc,
    contribuinte: isPJ ? "Contribuinte ICMS" : "Não contribuinte",
    produtorRural: false,
    ie: isPJ ? "251.998.220.114" : "",
    whats: c.fone,
    tel2: "",
    email: c.email,
    emailNfe: isPJ ? ("financeiro@" + first + ".com.br") : c.email,
    cep: "01452-000",
    logradouro: "Av. Brigadeiro Faria Lima",
    numero: "2391",
    compl: isPJ ? "Conj. 142" : "",
    bairro: "Jardim Paulistano",
    cidade: c.cidade,
    uf: "SP",
    vendedor: "Wagner Rocha",
    pagamento: c.tags.includes("Boleto 7d") ? "Boleto 7 dias" : "PIX",
    limite: c.saldo < 0 ? "R$ 1.000,00" : "R$ 2.500,00",
    origem: c.tags.includes("Recorrente") || c.tags.includes("Mensalista") ? "Cliente antigo" : "Indicação",
    lgpdWhats: true,
    lgpdNfe: isPJ,
    lgpdMkt: c.tags.includes("VIP"),
    desde: "mar/2024",
  };
};

// Categorias / unidades para o cadastro de produto
window.MOCK.CATEGORIAS = ["Impressos", "Sinalização", "Adesivos", "Etiquetas", "Insumos", "Serviços"];
window.MOCK.UNIDADES = ["unid", "milheiro", "cento", "rolo", "folha", "m²", "kg"];

function _hashStr(s) { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return h; }

// Expande um produto para a ficha completa de cadastro (defaults plausíveis).
window.MOCK.fullProduto = (p) => {
  if (!p) return null;
  return {
    id: p.id,
    nome: p.nome,
    sku: p.sku,
    barras: "789" + String(Math.abs(_hashStr(p.id))).padStart(10, "0").slice(0, 10),
    cat: p.cat,
    tipo: p.cat === "Serviços" ? "servico" : p.cat === "Insumos" ? "insumo" : "produto",
    un: p.un,
    preco: p.preco,
    precoStr: window.BRL(p.preco),
    custo: +(p.preco * 0.62).toFixed(2),
    custoStr: window.BRL(+(p.preco * 0.62).toFixed(2)),
    promo: "",
    controlaEstoque: p.cat !== "Serviços",
    estoque: String(p.estoque),
    min: String(p.min),
    local: "Prateleira " + (p.sku || "A1").slice(0, 2).toUpperCase(),
    fornecedor: "Suzano Papéis",
    ncm: "4911.10.90",
    cfop: "5101",
    origem: "0 - Nacional",
    cest: "",
    descricao: "",
    gramatura: p.cat === "Impressos" ? "250g couchê" : "",
    acabamento: p.cat === "Impressos" ? "Laminação fosca" : "",
    img: p.img,
  };
};
window.BRL = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
window.BRLcompact = (n) => {
  if (n >= 1000) return "R$ " + (n / 1000).toFixed(n >= 10000 ? 0 : 1) + "k";
  return window.BRL(n);
};

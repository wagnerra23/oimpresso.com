// ────────────────────────────────────────────────────────────────────
// MOCK DATA — Empresas, Menu (espelho AppShell), Conversas, Tarefas
// ────────────────────────────────────────────────────────────────────

const COMPANIES = [
  { id: "oi",  name: "Oimpresso Matriz", initials: "OI", grad: "av-2" },
  { id: "wr",  name: "WR Comunicação",    initials: "WR", grad: "av-1" },
  { id: "gv",  name: "Gráfica Vértice",   initials: "GV", grad: "av-3" },
];

// ─── MENU (espelho fiel do AppShell.tsx atual) ───
// Mesma ordem, labels e ícones que o sidebar.blade.php do sistema atual.
// Backend devolve isso via shell.menu (LegacyMenuAdapter).
// ─── MENU completo — 36 módulos do repo wagnerra23/oimpresso.com@main ───
// Auditoria: AUDITORIA_MODULOS.md. Cada item.id casa com a chave em MIGRATION_INFO (app.jsx).
const MENU = [
  // Núcleo — sem agrupamento, no topo
  { id: "chat",       icon: "chat",    label: "Jana · Analista",  badge: 3 },
  { id: "tarefas",    icon: "inbox",   label: "Tarefas",          badge: 6 },

  // Officeimpresso (módulo principal — núcleo do ERP)
  { group: "OFFICEIMPRESSO", items: [
    { id: "os",         icon: "orders",  label: "Ordens de Serviço" },
    { id: "clientes",   icon: "clients", label: "Clientes" },
    { id: "produtos",   icon: "product", label: "Produtos" },
    { id: "orcamentos", icon: "quote",   label: "Orçamentos" },
    { id: "vendas",     icon: "cash",    label: "Vendas" },
    { id: "cv",         icon: "layers",  label: "Comunicação Visual" },
    { id: "catalogue",  icon: "book",    label: "Catálogo de Produtos" },
    { id: "portalos",   icon: "globe",   label: "Portal Consulta OS" },
  ]},

  // Comercial / Marketing
  { group: "COMERCIAL", items: [
    { id: "crm",        icon: "clients",   label: "CRM" },
    { id: "ads",        icon: "megaphone", label: "ADS" },
    { id: "grow",       icon: "rocket",    label: "Grow" },
    { id: "whatsapp",   icon: "wa",        label: "WhatsApp" },
  ]},

  // Produção
  { group: "PRODUÇÃO", items: [
    { id: "fila",         icon: "print",   label: "Fila de impressão" },
    { id: "acabamento",   icon: "scissor", label: "Acabamento" },
    { id: "expedicao",    icon: "truck",   label: "Expedição" },
    { id: "manufacturing",icon: "factory", label: "Manufacturing" },
    { id: "iproduction",  icon: "factory", label: "IProduction" },
    { id: "brief",        icon: "doc",     label: "Briefings" },
  ]},

  // Verticais (outras linhas de negócio)
  { group: "VERTICAIS", items: [
    { id: "repair",      icon: "wrench", label: "Repair" },
    { id: "oficinaauto", icon: "car",    label: "Oficina Auto" },
    { id: "vestuario",   icon: "shirt",  label: "Vestuário" },
  ]},

  // Pessoas
  { group: "PESSOAS", items: [
    { id: "ponto",    icon: "user",    label: "Ponto WR2" },
    { id: "equipes",  icon: "clients", label: "Equipes" },
  ]},

  // Fiscal & Financeiro
  { group: "FINANCEIRO", items: [
    { id: "financeiro", icon: "cash",    label: "Financeiro" },
    { id: "boletos",    icon: "receipt", label: "Boleto · Inter" },
    { id: "compras",    icon: "archive", label: "Compras" },
    { id: "relatorios", icon: "chart",   label: "Relatórios" },
    { id: "nfse",       icon: "receipt", label: "NFS-e" },
    { id: "nfe",        icon: "receipt", label: "NF-e Brasil" },
    { id: "accounting", icon: "calc",    label: "Contabilidade" },
    { id: "recurring",  icon: "refresh", label: "Cobrança recorrente" },
  ]},

  // Projetos & Gestão
  { group: "PROJETOS & GESTÃO", items: [
    { id: "projects",    icon: "briefcase", label: "Projetos" },
    { id: "assets",      icon: "archive",   label: "Patrimônio" },
    { id: "auditoria",   icon: "audit",     label: "Auditoria" },
    { id: "governance",  icon: "scale",     label: "Governança" },
    { id: "kb",          icon: "book",      label: "Base de Conhecimento" },
    { id: "spreadsheet", icon: "sheet",     label: "Planilhas" },
  ]},

  // Outros / Internos
  { group: "OUTROS", items: [
    { id: "memcofre", icon: "shield",  label: "MemCofre" },
    { id: "copiloto", icon: "bot",     label: "Copiloto" },
    { id: "site",     icon: "globe",   label: "Site (CMS)" },
    { id: "arquivos", icon: "folder",  label: "Arquivos" },
  ]},

  // Integrações
  { group: "INTEGRAÇÕES", items: [
    { id: "connector",  icon: "plug",   label: "Connector" },
    { id: "woocommerce",icon: "plug",   label: "WooCommerce" },
    { id: "teammcp",    icon: "bot",    label: "Team MCP" },
    { id: "srs",        icon: "map",    label: "SRS" },
  ]},

  // Configurações
  { group: "CONFIGURAÇÕES", items: [
    { id: "prefs",      icon: "cog",    label: "Preferências" },
    { id: "users",      icon: "shield", label: "Usuários & Permissões" },
    { id: "admin",      icon: "cog",    label: "Admin (UltimatePOS)" },
    { id: "superadmin", icon: "shield", label: "Superadmin" },
    { id: "jana",       icon: "layers", label: "Jana (módulo ref.)" },
  ]},
];

// ─── CONVERSAS (mantido, levemente enxuto) ───
const CONV = {
  oi: [
    { id:"c1", kind:"os", tag:"OS #4821", title:"Banner Loja Acme 3×2m", av:"AC", grad:"av-2",
      preview:"Mateus: Arquivo final aprovado, liberando p/ impressão.",
      time:"14:32", unread:2, pinned:true,
      stage:"Em produção", os:"#4821", client:"Acme Comércio Ltda",
      online:true,
      msgs: [
        { d:"Hoje", who:"Joana Lima", side:"them", grad:"av-1", t:"Bom dia! Acabei de subir a arte revisada do banner 3×2m no drive da OS.", time:"09:14" },
        { d:"Hoje", who:"Joana Lima", side:"them", grad:"av-1", t:"Ajustamos o sangramento e aumentei a logo em 6%, conforme pedido pelo cliente ontem.", time:"09:14", continued:true },
        { d:"Hoje", who:"você", side:"me", t:"Perfeito, recebi. Vou conferir o perfil ICC e te respondo em 10min.", time:"09:21", read:true },
        { d:"Hoje", who:"você", side:"me", t:"Cor calibrada, sangria ok. Liberado.", time:"09:34", read:true },
        { d:"Hoje", who:"Mateus PCP", side:"them", grad:"av-3", t:"Nota interna: Encaixei na próxima carga da Roland 540, sai hoje 16h.", time:"10:02", note:true },
        { d:"Hoje", who:"Joana Lima", side:"them", grad:"av-1", file:{name:"banner-acme-final-v3.pdf", size:"24.7 MB"}, time:"13:55" },
        { d:"Hoje", who:"Mateus PCP", side:"them", grad:"av-3", t:"Arquivo final aprovado, liberando p/ impressão.", time:"14:32" },
      ]},
    { id:"c2", kind:"team", tag:"#equipe", title:"Produção — Turno A", av:"PA", grad:"av-3",
      preview:"você: Pessoal, lembrete da reunião 17h.",
      time:"13:10", unread:0, pinned:true, online:true,
      msgs: [
        { d:"Hoje", who:"Carla Souza", side:"them", grad:"av-5", t:"Bom dia turma! Bobina nova da Avery chegou, já estoquei.", time:"08:02" },
        { d:"Hoje", who:"você", side:"me", t:"Pessoal, lembrete da reunião 17h.", time:"13:10", read:true },
      ]},
    { id:"c3", kind:"client", tag:"cliente", title:"Padaria Estrela — Renato", av:"RE", grad:"av-4",
      preview:"Renato: Posso passar pra retirar amanhã 9h?",
      time:"11:48", unread:1,
      stage:"Aguardando retirada", client:"Padaria Estrela",
      msgs: [
        { d:"Ontem", who:"você", side:"me", t:"Boa tarde Renato! Seu pedido de cardápios está pronto.", time:"16:20", read:true },
        { d:"Hoje", who:"Renato Lopes", side:"them", grad:"av-4", t:"Posso passar pra retirar amanhã 9h?", time:"11:48" },
      ]},
    { id:"c4", kind:"os", tag:"OS #4807", title:"Adesivos Recortados — TechPro", av:"TP", grad:"av-6",
      preview:"Felipe: Recorte testado, aprovado pelo cliente.",
      time:"Ontem", unread:0,
      stage:"Acabamento", os:"#4807", client:"TechPro Equipamentos",
      msgs: [
        { d:"Ontem", who:"Felipe Acab.", side:"them", grad:"av-6", t:"Recorte testado, aprovado pelo cliente.", time:"17:20" },
      ]},
    { id:"c5", kind:"team", tag:"#equipe", title:"Comercial", av:"CM", grad:"av-5",
      preview:"Bruna: Orçamento da Acme renovado.",
      time:"Ontem", unread:0,
      msgs: [
        { d:"Ontem", who:"Bruna Vendas", side:"them", grad:"av-5", t:"Orçamento da Acme renovado.", time:"15:00" },
      ]},
    { id:"c6", kind:"client", tag:"cliente", title:"Clínica Vida — Marcos", av:"MV", grad:"av-2",
      preview:"você: Vou te enviar o mockup hoje.", time:"Seg", unread:0,
      msgs: [{ d:"Seg", who:"você", side:"me", t:"Vou te enviar o mockup hoje.", time:"10:00", read:true }]},
  ],
  wr: [
    { id:"w1", kind:"os", tag:"OS #112", title:"Fachada Mercado União", av:"MU", grad:"av-3",
      preview:"João: Vou medir hoje à tarde.", time:"15:02", unread:1,
      stage:"Medição", os:"#112", client:"Mercado União",
      msgs: [{ d:"Hoje", who:"João Inst.", side:"them", grad:"av-3", t:"Vou medir hoje à tarde.", time:"15:02" }]},
    { id:"w2", kind:"team", tag:"#equipe", title:"Atendimento", av:"AT", grad:"av-2",
      preview:"Lia: Cliente novo na linha 1.", time:"14:10", unread:0,
      msgs: [{ d:"Hoje", who:"Lia", side:"them", grad:"av-2", t:"Cliente novo na linha 1.", time:"14:10" }]},
  ],
  gv: [
    { id:"g1", kind:"team", tag:"#equipe", title:"Produção", av:"PR", grad:"av-3",
      preview:"Sem mensagens novas.", time:"Sex", unread:0,
      msgs: [{ d:"Sex", who:"Pedro", side:"them", grad:"av-3", t:"Tudo certo por aqui.", time:"17:00" }]},
  ],
};

// ─── ROTINAS (atalhos pinados estilo "Routines" do print) ───
const ROUTINES = [
  { id:"r1", title:"Banner Acme — aprovação diária", freq:"Diário" },
  { id:"r2", title:"Cobrança Padaria Estrela",       freq:"Uma vez" },
  { id:"r3", title:"Reunião PCP — 8h30",             freq:"Diário" },
  { id:"r4", title:"Fechamento Caixa",                freq:"Diário" },
];

// ─── TASKS (inbox unificada — todas as tarefas atribuídas ao usuário) ───
// Cada task tem: origem (módulo), tipo de viewer, dados específicos.
// Em produção: TaskRegistry agrega de cada módulo via TaskProvider interface.
const TASKS = [
  // ── HOJE
  {
    id:"t1", origin:"OS",  color:"amber",  viewer:"os_aprovar_arte",
    title:"Aprovar arte final — Banner Acme 3×2m",
    subtitle:"OS #4821 · Acme Comércio",
    when:"hoje 14:00", group:"hoje", urgent:true, unread:true,
    assigned:"você", from:"Joana Lima",
    data: {
      os:"#4821", client:"Acme Comércio Ltda", contact:"Camila Diniz",
      product:"Banner Lona 440g — 3×2m", quantity:1,
      stage:"Aprovar arte final", deadline:"Hoje, 14:00",
      art:{ filename:"banner-acme-final-v3.pdf", size:"24.7 MB", version:3 },
      history:[
        {who:"Joana Lima",  when:"13:55", what:"subiu versão v3"},
        {who:"Mateus PCP",  when:"10:02", what:"alocou na Roland 540 (16h)"},
        {who:"Camila (cli)",when:"ontem 17:30", what:"pediu logo +6%"},
      ],
      thread:"c1",
    },
  },
  {
    id:"t2", origin:"CRM", color:"blue", viewer:"crm_ligar",
    title:"Ligar para Renato — Padaria Estrela",
    subtitle:"Retirada agendada 9h amanhã",
    when:"hoje 16:30", group:"hoje", urgent:false, unread:false,
    assigned:"você", from:"workflow",
    data: {
      lead:"Renato Lopes", company:"Padaria Estrela",
      phone:"+55 11 98712-3344", whatsapp:"+55 11 98712-3344",
      lastTouch:"hoje 11:48 — perguntou se pode retirar 9h amanhã",
      notes:[
        "Cliente recorrente — 4ª compra.",
        "Pedido pronto desde ontem 16:20.",
      ],
      thread:"c3",
    },
  },
  {
    id:"t3", origin:"FIN", color:"emerald", viewer:"fin_aprovar_boleto",
    title:"Aprovar boleto NF 1240 — Fornecedor Avery",
    subtitle:"R$ 12.480,00 · vence amanhã",
    when:"hoje", group:"hoje", urgent:false, unread:true,
    assigned:"você", from:"workflow",
    data: {
      nf:"1240", supplier:"Avery Brasil", amount:"R$ 12.480,00",
      due:"amanhã, 24/04", category:"Insumos · Bobina vinil",
      ref:"Reposição estoque turno A — solicitado por Carla S.",
      account:"Banco Itaú · CC 12345-6",
      attached:"NF-1240.pdf · 312 KB",
    },
  },
  {
    id:"t4", origin:"PNT", color:"violet", viewer:"pnt_justificar",
    title:"Justificar marcação faltante — 22/04",
    subtitle:"Saída do almoço não registrada",
    when:"hoje", group:"hoje", urgent:false, unread:false,
    assigned:"você", from:"sistema",
    data: {
      date:"22/04/2025 (terça)",
      missing:"Saída — almoço",
      recorded:[
        {label:"Entrada",        time:"08:02"},
        {label:"Saída almoço",   time:"—",     missing:true},
        {label:"Retorno almoço", time:"13:05"},
        {label:"Saída",          time:"18:00"},
      ],
      suggestions:["Esqueci de bater","Almoço fora do escritório","Reunião externa"],
    },
  },

  // ── ATRASADAS
  {
    id:"t5", origin:"OS", color:"amber", viewer:"os_aprovar_arte",
    title:"Revisar prova — Adesivos TechPro",
    subtitle:"OS #4807 · prova enviada há 2 dias",
    when:"atrasada 2d", group:"atrasadas", urgent:true, unread:true,
    assigned:"você", from:"Felipe Acab.",
    data: {
      os:"#4807", client:"TechPro Equipamentos", contact:"Diego Vasconcellos",
      product:"Adesivos recortados — 200un · 8×8cm",
      stage:"Revisar prova de recorte", deadline:"21/04 (atrasada 2d)",
      art:{ filename:"techpro-adesivo-prova.pdf", size:"4.1 MB", version:1 },
      history:[
        {who:"Felipe Acab.", when:"21/04 17:20", what:"recorte testado, aguarda aprovação"},
      ],
      thread:"c4",
    },
  },
  {
    id:"t6", origin:"FIN", color:"emerald", viewer:"fin_aprovar_boleto",
    title:"Aprovar boleto luz — abril",
    subtitle:"R$ 3.245,00 · venceu ontem",
    when:"atrasada 1d", group:"atrasadas", urgent:true, unread:true,
    assigned:"você", from:"workflow",
    data: {
      nf:"—", supplier:"CPFL Energia", amount:"R$ 3.245,00",
      due:"22/04 (atrasada 1d)", category:"Despesa fixa · Energia",
      ref:"Conta de luz matriz — abril/25",
      account:"Banco Itaú · CC 12345-6",
      attached:"conta-luz-abril.pdf · 89 KB",
    },
  },

  // ── ESTA SEMANA
  {
    id:"t7", origin:"CRM", color:"blue", viewer:"crm_ligar",
    title:"Follow-up Mercado União — orçamento",
    subtitle:"Orçamento enviado segunda",
    when:"sex 10:00", group:"semana", urgent:false, unread:false,
    assigned:"você", from:"workflow",
    data: {
      lead:"João Inst.", company:"Mercado União",
      phone:"+55 11 99812-7700", whatsapp:"+55 11 99812-7700",
      lastTouch:"seg 14:00 — orçamento enviado por e-mail",
      notes:["Aguardando aprovação da diretoria."],
      thread:"w1",
    },
  },
  {
    id:"t8", origin:"OS", color:"amber", viewer:"os_aprovar_arte",
    title:"Aprovar arte — Lona Posto BR",
    subtitle:"OS #4790 · revisão final",
    when:"qui 11:00", group:"semana", urgent:false, unread:false,
    assigned:"você", from:"Joana Lima",
    data: {
      os:"#4790", client:"Posto BR Centro", contact:"Marcos Vinícius",
      product:"Lona Front-Light — 5×3m", quantity:1,
      stage:"Aprovar arte final", deadline:"Quinta, 11:00",
      art:{ filename:"posto-br-lona-v2.pdf", size:"31.2 MB", version:2 },
      history:[],
    },
  },
];

// Cores dos badges de origem (que módulo gerou a tarefa)
const ORIGIN_COLORS = {
  OS:  { bg:"oklch(0.93 0.07 70)",  fg:"oklch(0.40 0.10 60)",  bgD:"oklch(0.30 0.07 60)",  fgD:"oklch(0.85 0.07 60)" },
  CRM: { bg:"oklch(0.92 0.06 220)", fg:"oklch(0.40 0.10 220)", bgD:"oklch(0.30 0.07 220)", fgD:"oklch(0.85 0.07 220)" },
  FIN: { bg:"oklch(0.93 0.07 145)", fg:"oklch(0.36 0.10 145)", bgD:"oklch(0.30 0.07 145)", fgD:"oklch(0.85 0.07 145)" },
  PNT: { bg:"oklch(0.93 0.06 295)", fg:"oklch(0.40 0.10 295)", bgD:"oklch(0.30 0.07 295)", fgD:"oklch(0.85 0.07 295)" },
  MFG: { bg:"oklch(0.93 0.05 30)",  fg:"oklch(0.40 0.10 30)",  bgD:"oklch(0.30 0.07 30)",  fgD:"oklch(0.85 0.07 30)" },
};

// ─── GROUP_META: ícone/label/descrição/cor de cada grupo ───
const GROUP_META = {
  "OFFICEIMPRESSO":    { icon:"orders",    label:"Operação",    hue: 60,  desc:"OS, clientes, catálogo, orçamentos, vendas, CV" },
  "COMERCIAL":         { icon:"megaphone", label:"Comercial",   hue: 220, desc:"CRM, ADS, Grow, WhatsApp" },
  "PRODUÇÃO":          { icon:"factory",   label:"Produção",    hue: 30,  desc:"Fila, acabamento, expedição, manufatura, briefs" },
  "VERTICAIS":         { icon:"layers",    label:"Verticais",   hue: 350, desc:"Repair, oficina auto, vestuário" },
  "PESSOAS":           { icon:"clients",   label:"Pessoas",     hue: 295, desc:"Ponto WR2, equipes" },
  "FINANCEIRO":        { icon:"cash",      label:"Financeiro",  hue: 145, desc:"Caixa, boletos, compras, NF, contabilidade" },
  "PROJETOS & GESTÃO": { icon:"briefcase", label:"Gestão",      hue: 240, desc:"Projetos, patrimônio, auditoria, governança, KB" },
  "OUTROS":            { icon:"folder",    label:"Outros",      hue: 80,  desc:"MemCofre, Copiloto, Site, Arquivos" },
  "INTEGRAÇÕES":       { icon:"plug",      label:"Integrações", hue: 200, desc:"Connector, WooCommerce, Team MCP, SRS" },
  "CONFIGURAÇÕES":     { icon:"cog",       label:"Config.",     hue: 270, desc:"Preferências, usuários, admin, módulo ref." },
};

window.MOCK = { COMPANIES, MENU, CONV, ROUTINES, TASKS, ORIGIN_COLORS, GROUP_META };

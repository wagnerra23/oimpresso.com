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
  // ── Shortcuts de topo (não-grupos) — [W] 2026-08: IA · Forja · Atendimento (Equipe → MAIS) ──
  { id: "chat",     icon: "chat",  label: "IA",          shortcut: true },
  { id: "dash-legacy", icon: "chart", label: "Visão geral", shortcut: true },
  { id: "inbox",    icon: "inbox", label: "Atendimento", shortcut: true },

  // ── 8 grupos canon (ADR 0180 / Sidebar.tsx v3) em ordem fixa ──
  { group: "CADASTRO", items: [
    { id: "clientes",      icon: "clients", label: "Clientes" },
    { id: "produtos",      icon: "product", label: "Produtos", ghosts: [
      { id: "prod-lista",     icon: "list",    label: "Todos os produtos" },
      { id: "prod-novo",      icon: "plus",    label: "Novo produto" },
      { id: "prod-estoque",   icon: "chart",   label: "Relatório de estoque" },
      { id: "prod-historico", icon: "clock",   label: "Histórico de estoque" },
      { id: "prod-precos",    icon: "cash",    label: "Preços por grupo" },
      { id: "prod-massa",     icon: "pencil",  label: "Edição em massa" },
      { id: "prod-analises",  icon: "chart",   label: "Análises do catálogo" },
      { id: "prod-etiquetas", icon: "print",   label: "Imprimir etiquetas" },
      { id: "prod-atualizar-preco", icon: "cash", label: "Atualizar preço" },
      { id: "prod-importar",  icon: "upload",  label: "Importar produtos" },
      { id: "prod-importar-estoque", icon: "archive", label: "Importar estoque inicial" },
      { id: "prod-cadastros", icon: "grid",    label: "Cadastros de apoio" },
    ]},
    { id: "manufacturing", icon: "factory", label: "Fabricação", ghosts: [
      { id: "mfg-producao",  icon: "orders", label: "Ordens de produção" },
      { id: "mfg-relatorio", icon: "chart",  label: "Relatório de produção" },
      { id: "mfg-config",    icon: "cog",    label: "Configurações" },
    ]},
  ]},
  { group: "COMERCIAL", items: [
    { id: "crm",         icon: "clients", label: "CRM", ghosts: [
      { id: "crm-leads",       icon: "clients", label: "Leads" },
      { id: "crm-followups",   icon: "clock",   label: "Acompanhamentos" },
      { id: "crm-campanhas",   icon: "send",    label: "Campanhas" },
      { id: "crm-logins",      icon: "users",   label: "Login de contatos" },
      { id: "crm-comissoes",   icon: "cash",    label: "Comissões" },
      { id: "crm-chamadas",    icon: "phone",   label: "Registro de chamadas" },
      { id: "crm-relatorios",  icon: "chart",   label: "Relatórios do CRM" },
      { id: "crm-modelo",      icon: "quote",   label: "Modelo de proposta" },
      { id: "crm-propostas",   icon: "quote",   label: "Propostas" },
      { id: "crm-marketplace", icon: "plug",    label: "Marketplace B2B" },
      { id: "crm-pedidos",     icon: "orders",  label: "Pedido de ordem" },
      { id: "crm-taxonomias",  icon: "folder",  label: "Fontes e estágios" },
      { id: "crm-config",      icon: "cog",     label: "Configurações do CRM" },
      { id: "crm-portal",      icon: "globe",   label: "Portal do contato" },
    ]},
    { id: "vendas",      icon: "cash",    label: "Vendas", ghosts: [
      { id: "venda-pedidos",     icon: "orders", label: "Pedido de venda" },
      { id: "venda-todas",       icon: "list",   label: "Todas as vendas" },
      { id: "venda-nova",        icon: "plus",   label: "Adicionar venda" },
      { id: "venda-pos",         icon: "cash",   label: "lista de POS" },
      { id: "venda-pdv",         icon: "cash",   label: "POS" },
      { id: "venda-nova-rascunho", icon: "plus", label: "Adicionar rascunho" },
      { id: "venda-rascunhos",   icon: "quote",  label: "Lista de rascunhos" },
      { id: "venda-nova-cotacao", icon: "plus",  label: "Adicionar cotação" },
      { id: "venda-cotacoes",    icon: "quote",  label: "Lista de compromissos" },
      { id: "venda-devolucoes",  icon: "list",   label: "lista de devolução" },
      { id: "venda-remessas",    icon: "truck",  label: "Remessas" },
      { id: "venda-descontos",   icon: "cash",   label: "Descontos" },
      { id: "venda-assinaturas", icon: "clock",  label: "Assinaturas" },
      { id: "venda-devolver",    icon: "list",   label: "Devolver venda" },
      { id: "venda-importar",    icon: "upload", label: "Importação de vendas" },
      { id: "orcamentos",  icon: "quote", label: "Orçamentos" },
      { id: "woocommerce", icon: "plug",  label: "WooCommerce" },
      { id: "portalos",    icon: "globe", label: "Portal Consulta OS" },
    ]},
    { id: "oficinaauto", icon: "car",     label: "Oficina Auto", ghosts: [
      { id: "oficina-os", icon: "orders", label: "Nova OS" },
    ]},
  ]},
  { group: "FINANÇAS", items: [
    { id: "venda-caixa", icon: "cash", label: "Caixa" },
    { id: "financeiro", icon: "cash",    label: "Financeiro", ghosts: [
      { id: "fin-fluxo",   icon: "chart",  label: "Fluxo de caixa" },
      { id: "fin-concil",  icon: "doc",    label: "Conciliação" },
      { id: "fin-dre",     icon: "doc",    label: "DRE / Relatórios" },
      { id: "fin-pcontas", icon: "folder", label: "Plano de contas" },
      { id: "fin-impostos", icon: "receipt", label: "Impostos e obrigações" },
      { id: "fin-receber",  icon: "cash",    label: "Contas a receber" },
      { id: "fin-pagar",    icon: "cash",    label: "Contas a pagar" },
      { id: "fin-despesas", icon: "receipt", label: "Despesas" },
      { id: "fin-categorias", icon: "folder", label: "Categorias de despesa" },
      { id: "fin-bancos",   icon: "cash",    label: "Contas bancárias" },
      { id: "fin-extrato",  icon: "list",    label: "Extrato" },
    ]},
    { id: "cobranca",   icon: "receipt", label: "Cobrança", ghosts: [
      { id: "payment-gateways", icon: "shield", label: "Gateways de Pagamento" },
    ]},
    // Cobrança Recorrente — hub JÁ é a lista de assinaturas; ghosts = só Planos/Faturas/Config.
    { id: "recurring",  icon: "refresh", label: "Cobrança Recorrente", ghosts: [
      { id: "rb-planos",  icon: "folder",  label: "Planos" },
      { id: "rb-faturas", icon: "receipt", label: "Faturas" },
      { id: "rb-config",  icon: "cog",     label: "Configurações" },
    ]},
  ]},
  { group: "FISCAL", items: [
    // Cockpit unificado (Modules/Fiscal @main): hub /fiscal + 6 sub-páginas
    { id: "fiscal", icon: "receipt", label: "Fiscal", ghosts: [
      { id: "fiscal-nfe",     icon: "receipt", label: "NF-e · NFC-e" },
      { id: "fiscal-nfse",    icon: "doc",     label: "NFS-e" },
      { id: "fiscal-eventos", icon: "refresh", label: "Eventos" },
      { id: "fiscal-dfe",     icon: "archive", label: "Manifesto DF-e" },
      { id: "fiscal-config",  icon: "shield",  label: "Certificado e configuração" },
      { id: "fiscal-sped",    icon: "grid",    label: "SPED e livros" },
    ]},
  ]},
  { group: "PRODUÇÃO", items: [
    { id: "os",     icon: "orders", label: "Ordens de Serviço" },
    { id: "repair", icon: "wrench", label: "Assistência técnica", ghosts: [
      { id: "rep-producao", icon: "grid",    label: "Produção · oficina" },
      { id: "rep-folhas",   icon: "orders",  label: "Folhas de OS" },
      { id: "rep-reparos",  icon: "cash",    label: "Reparos faturados" },
      { id: "rep-status",   icon: "list",    label: "Status do reparo" },
      { id: "rep-modelos",  icon: "product", label: "Modelos de equipamento" },
      { id: "rep-portal",   icon: "globe",   label: "Portal do cliente" },
      { id: "rep-config",   icon: "cog",     label: "Configurações" },
    ]},
  ]},
  { group: "ESTOQUE", items: [
    { id: "compras", icon: "archive", label: "Compras", ghosts: [
      { id: "cmp-pedidos",     icon: "orders", label: "Pedidos de compra" },
      { id: "cmp-requisicoes", icon: "doc",    label: "Requisições de compra" },
      { id: "cmp-devolucoes",  icon: "truck",  label: "Devoluções de compra" },
      { id: "cmp-grade",       icon: "grid",   label: "Grade matrix (tam × cor)" },
    ]},
    { id: "estoque", icon: "refresh", label: "Movimentações", ghosts: [
      { id: "est-ajustes",            icon: "list",  label: "Ajustes de estoque" },
      { id: "est-ajuste-novo",        icon: "plus",  label: "Novo ajuste" },
      { id: "est-transferencias",     icon: "truck", label: "Transferências" },
      { id: "est-transferencia-nova", icon: "plus",  label: "Nova transferência" },
      { id: "est-vencimentos",        icon: "clock", label: "Vencimentos" },
      { id: "est-contagem",           icon: "check", label: "Contagem cíclica" },
    ]},
    { id: "assets",  icon: "archive", label: "Patrimônio", ghosts: [
      { id: "pat-bens",       icon: "grid",    label: "Bens" },
      { id: "pat-alocacoes",  icon: "user",    label: "Alocações" },
      { id: "pat-manutencao", icon: "wrench",  label: "Manutenções" },
      { id: "pat-config",     icon: "cog",     label: "Configurações" },
    ]},
  ]},
  { group: "RH", items: [
    { id: "ponto",   icon: "user",    label: "Ponto" },
    { id: "hrm",     icon: "users",   label: "HRM", ghosts: [
      { id: "hrm-licencas", icon: "doc",     label: "Licenças" },
      { id: "hrm-presenca", icon: "clock",   label: "Presença" },
      { id: "hrm-turnos",   icon: "refresh", label: "Turnos" },
      { id: "hrm-folha",    icon: "cash",    label: "Folha de pagamento" },
      { id: "hrm-feriados", icon: "clock",   label: "Feriados" },
      { id: "hrm-metas",    icon: "chart",   label: "Metas de venda" },
      { id: "hrm-config",   icon: "cog",     label: "Configurações" },
    ]},
    { id: "essenciais", icon: "check", label: "Essenciais", ghosts: [
      { id: "ess-documentos", icon: "folder", label: "Documentos" },
      { id: "ess-memorandos", icon: "doc",    label: "Memorandos" },
      { id: "ess-lembretes",  icon: "clock",  label: "Lembretes" },
      { id: "ess-mensagens",  icon: "chat",   label: "Mensagens" },
      { id: "ess-kb",         icon: "book",   label: "Base de conhecimento" },
      { id: "ess-config",     icon: "cog",    label: "Configurações" },
    ]},
  ]},
  { group: "SISTEMA", items: [
    { id: "auditoria",   icon: "audit", label: "Auditoria" },
    { id: "usuarios",    icon: "user",  label: "Usuários", ghosts: [
      { id: "funcoes",       icon: "shield", label: "Funções e permissões" },
      { id: "comissionados", icon: "cash",   label: "Comissionados" },
      { id: "comissoes",     icon: "chart",  label: "Apuração de comissão" },
    ]},
    { id: "prefs",       icon: "cog",   label: "Preferências" },
    // Grupo "Configurações" do menu legado (AdminLTE) — importado do blade business/settings + cadastros
    { id: "cfg-empresa", icon: "cog", label: "Configurações", ghosts: [
      { id: "cfg-locais",        icon: "globe",  label: "Locais comerciais" },
      { id: "cfg-fatura",        icon: "receipt", label: "Configurações da fatura" },
      { id: "cfg-barras",        icon: "grid",   label: "Código de barras" },
      { id: "notificacoes",      icon: "bell",   label: "Modelos de notificação" },
      { id: "cfg-impressoras",   icon: "print",  label: "Impressoras de recibos" },
      { id: "cfg-impostos",      icon: "cash",   label: "Taxas de imposto" },
      { id: "cfg-modificadores", icon: "wrench", label: "Modificadores" },
      { id: "cfg-servicos",      icon: "check",  label: "Tipos de serviço" },
      { id: "cfg-mesas",         icon: "grid",   label: "Mesas" },
      { id: "cfg-atendentes",    icon: "users",  label: "Atendentes" },
      { id: "cfg-pacote",        icon: "refresh", label: "Assinatura de pacote" },
    ]},
    { id: "relatorios",  icon: "chart", label: "Relatórios", ghosts: [
      { id: "rel-financeiro", icon: "cash",    label: "Financeiro" },
      { id: "rel-comercial",  icon: "chart",   label: "Comercial" },
      { id: "rel-estoque",    icon: "archive", label: "Estoque" },
      { id: "rel-fiscal",     icon: "receipt", label: "Fiscal" },
    ]},
    { id: "kb",          icon: "book",  label: "Base de Conhecimento" },
    { id: "planilhas",   icon: "grid",  label: "Planilhas", ghosts: [
      { id: "planilha-nova", icon: "plus", label: "Criar planilha" },
    ]},
  ]},

  // ── PLATAFORMA ([W] 2026-08: era "MAIS") — fechado por default ──
  { group: "PLATAFORMA", items: [
    { id: "tarefas",    icon: "inbox",     label: "Tarefas" },
    { id: "equipe",     icon: "users",     label: "Equipe" },
    { id: "governance", icon: "scale",     label: "Governança", ghosts: [
      { id: "gov-politicas",  icon: "shield", label: "Políticas" },
      { id: "gov-auditoria",  icon: "audit",  label: "Auditoria" },
      { id: "gov-drift",      icon: "search", label: "Drift de escopo" },
      { id: "gov-notas",      icon: "chart",  label: "Notas dos módulos" },
    ]},
    // [W] 2026-08: Forja saiu de SISTEMA → PLATAFORMA (uso só p/ programação)
    { id: "projects",   icon: "bot",       label: "Forja" },
    // [W] 2026-06-16: removidos da nav (Copiloto · MemCofre · Arquivos · Connector · Team MCP · SRS)
    // [W] 2026-08: removidos da nav também — Briefings · Frotas 360 (não serão usados)
  ]},
];

// ── Contadores do sidebar — no vivo são 3 e vêm de shell.sidebar_counts ──
// (HandleInertiaRequests::sidebarCounts). Nada de badge inventado por item.
const SIDEBAR_COUNTS = { chat: 3, atendimento: 6, tarefas: 6 };

// ── Itens de usuário → user dropdown do rodapé (canon repo: fora do corpo) ──
const USER_MENU = [
  { id: "prefs",    icon: "cog",    label: "Preferências" },
  { id: "usuarios", icon: "shield", label: "Usuários & permissões" },
  { id: "funcoes",  icon: "shield", label: "Funções e permissões" },
];

// ── Cascata "Superadmin" do rodapé (SUPERADMIN_LABELS do Sidebar.tsx vivo):
// admin de plataforma sai do menu principal — Módulos · Backup · CMS · Conector ·
// Office Impresso · Superadmin. Os ghosts de cada um seguem alcançáveis por ⌘K.
const SUPERADMIN_MENU = [
  { id: "superadmin",     icon: "shield",  label: "Superadmin", ghosts: [
    { id: "sa-negocios",    icon: "users",   label: "Negócios" },
    { id: "sa-assinaturas", icon: "refresh", label: "Assinaturas SaaS" },
    { id: "sa-pacotes",     icon: "folder",  label: "Pacotes" },
    { id: "sa-comunicador", icon: "inbox",   label: "Comunicador" },
    { id: "sa-config",      icon: "cog",     label: "Configurações" },
  ]},
  { id: "modulos",        icon: "grid",    label: "Módulos" },
  { id: "backup",         icon: "archive", label: "Backup" },
  { id: "site",           icon: "globe",   label: "Site (CMS)", ghosts: [
    { id: "cms-blog",        icon: "doc",   label: "Blog" },
    { id: "cms-depoimentos", icon: "users", label: "Depoimentos" },
    { id: "cms-leads",       icon: "inbox", label: "Leads do site" },
    { id: "cms-detalhes",    icon: "cog",   label: "Detalhes do site" },
    { id: "cms-modulo",      icon: "plug",  label: "Módulo" },
  ]},
  { id: "connector",      icon: "plug",    label: "Conector (API)", ghosts: [
    { id: "conn-docs",   icon: "book",   label: "Documentação da API" },
    { id: "conn-saude",  icon: "shield", label: "Saúde" },
    { id: "conn-modulo", icon: "cog",    label: "Módulo" },
  ]},
  { id: "officeimpresso", icon: "plug",    label: "Office Impresso", ghosts: [
    { id: "oi-licencas", icon: "keyboard", label: "Licenças" },
    { id: "oi-clientes", icon: "shield",   label: "Clientes OAuth" },
    { id: "oi-importar", icon: "archive",  label: "Importar do Firebird" },
    { id: "oi-log",      icon: "audit",    label: "Log de acesso" },
  ]},
];

// Rodapé fixo (fora do shell.menu, como no vivo): Documentação é o único ponto de entrada.
const FOOTER_LINKS = [
  { id: "documentacao", icon: "book", label: "Documentação" },
];

// ── Papel simulado: no vivo o item some quando o can() falha. Lista = ids visíveis;
// null = tudo. Afordância de protótipo — não vai pro F3.
const SIDEBAR_PAPEIS = {
  "wagner (admin)": null,
  "larissa (balcão)": ["chat","inbox","dash-legacy","tarefas","clientes","produtos","orcamentos","crm","vendas","oficinaauto","os","brief"],
  "eliana (financeiro)": ["chat","dash-legacy","clientes","venda-caixa","financeiro","cobranca","recurring","nfe","nfse","compras","relatorios","auditoria"],
  "técnico (produção)": ["chat","tarefas","os","repair","estoque","assets","ponto","kb"],
};

// ─── ATALHOS — padrão "G X" do contrato do vivo. Só os hubs de uso diário levam atalho. ───
const MENU_SHORTCUTS = {
  chat: "I", inbox: "A", tarefas: "T", "dash-legacy": "D",
  clientes: "C", produtos: "P", crm: "R", vendas: "V",
  orcamentos: "O", financeiro: "F", "venda-caixa": "X", estoque: "E",
};
const SHORTCUT_TO_ROUTE = Object.keys(MENU_SHORTCUTS).reduce((a, k) => (a[MENU_SHORTCUTS[k]] = k, a), {});

// ─── ROUTE_STATE — auditoria de destino (validada contra o if/else de app.jsx) ───
// Ausente = tela desenhada. "mock" = MockupPage (esqueleto genérico). "stub" = ModuleStub (só ficha de migração).
const ROUTE_STATE = {
  cv: "mock", nfe: "mock", nfse: "mock", auditoria: "mock",
  ads: "mock", brief: "mock", portalos: "mock",
  governance: "stub",
};
const ROUTE_STATE_LABEL = { mock: "Esqueleto — tela ainda não desenhada", stub: "Sem tela — só ficha de migração" };

// Flatten p/ roteamento: grupos + ghosts + shortcuts topo + user-menu (tudo resolvível)
function flattenMenu() {
  const out = [];
  MENU.forEach(e => {
    if (e.group) {
      e.items.forEach(it => {
        out.push({ ...it, group: e.group });
        (it.ghosts || []).forEach(g => out.push({ ...g, group: e.group, ghostOf: it.id }));
      });
    } else {
      out.push({ ...e, group: null });
    }
  });
  [...USER_MENU, ...FOOTER_LINKS].forEach(it => out.push({ ...it, group: "__user__" }));
  SUPERADMIN_MENU.forEach(it => {
    out.push({ ...it, group: "__super__" });
    (it.ghosts || []).forEach(g => out.push({ ...g, group: "__super__", ghostOf: it.id }));
  });
  return out;
}
const MENU_FLAT = flattenMenu();


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

// ─── FIN_SUBNAV — barra de abas UNIFICADA do Financeiro (puxada do vivo 2026-08-17)
// Fonte: resources/js/Pages/Financeiro/_shared/financeiroMenu.ts → FINANCEIRO_SUBNAV_GHOSTS
// (ADR 0313, supersede parcial da 0180: a subnav NÃO é mais os ghosts da entry ativa —
// toda tela do Financeiro renderiza a MESMA barra; 8 visíveis, resto no overflow ⋯).
// Ícones = os do vivo (banknote/receipt/refresh-cw/chart/file/file/folder/receipt).
const FIN_SUBNAV = [
  { id: "financeiro",  icon: "cash",    label: "Financeiro" },
  { id: "cobranca",    icon: "receipt", label: "Cobrança" },
  { id: "recurring",   icon: "refresh", label: "Assinaturas" },
  { id: "fin-fluxo",   icon: "chart",   label: "Fluxo de caixa" },
  { id: "fin-concil",  icon: "doc",     label: "Conciliação" },
  { id: "fin-dre",     icon: "doc",     label: "DRE / Relatórios" },
  { id: "fin-pcontas", icon: "folder",  label: "Plano de contas" },
  { id: "fin-impostos",icon: "receipt", label: "Impostos e obrigações" },
];
// Destinos legacy do vivo — vão pro ⋯ (nada se perde). `route` só onde o Cowork tem tela.
const FIN_SUBNAV_OVERFLOW = [
  { label: "Contas a Pagar",    path: "/financeiro/contas-pagar" },
  { label: "Contas a Receber",  path: "/financeiro/contas-receber" },
  { label: "Relatórios",        path: "/financeiro/relatorios", route: "relatorios" },
  { label: "Categorias",        path: "/financeiro/categorias" },
  { label: "Caixa",             path: "/financeiro/caixa" },
  { label: "Extrato",           path: "/financeiro/extrato" },
  { label: "Contas Bancárias",  path: "/financeiro/contas-bancarias" },
  { label: "Contador",          path: "/financeiro/configuracoes/contador" },
  { label: "Gateway",           path: "/settings/payment-gateways", route: "payment-gateways" },
];

// ─── GROUP_META: ícone/label/descrição/cor de cada grupo ───
// hue = SIDEBAR_GROUP_HUE do vivo (resources/js/Components/cockpit/shared.ts):
// escala canon espaçada ≥25° no círculo cromático. PLATAFORMA é neutro (sem hue).
const GROUP_META = {
  "CADASTRO":  { icon:"book",    label:"Cadastro",  hue: 202,  key:"cadastro",  desc:"Clientes, produtos, catálogo, fabricação" },
  "COMERCIAL": { icon:"cash",    label:"Comercial", hue: 55,   key:"comercial", desc:"CRM, vendas, oficina auto" },
  "FINANÇAS":  { icon:"cash",    label:"Finanças",  hue: 145,  key:"financas",  desc:"Caixa, cobrança, financeiro, recorrente" },
  "FISCAL":    { icon:"receipt", label:"Fiscal",    hue: 175,  key:"fiscal",    desc:"NF-e, NFS-e, certificado" },
  "PRODUÇÃO":  { icon:"factory", label:"Produção",  hue: 8,    key:"producao",  desc:"OS, assistência técnica" },
  "ESTOQUE":   { icon:"archive", label:"Estoque",   hue: 315,  key:"estoque",   desc:"Compras, transferências, patrimônio" },
  "RH":        { icon:"users",   label:"RH",        hue: 88,   key:"pessoas",   desc:"Ponto, colaboradores" },
  "SISTEMA":   { icon:"cog",     label:"Sistema",   hue: 245,  key:"sistema",   desc:"Auditoria, relatórios, planilhas, KB" },
  "PLATAFORMA":{ icon:"folder",  label:"Plataforma", hue: null, key:"plataforma", desc:"Tarefas, equipe, governança e Forja" },
};

window.MOCK = { COMPANIES, MENU, MENU_FLAT, USER_MENU, MENU_SHORTCUTS, SHORTCUT_TO_ROUTE, SUPERADMIN_MENU, FOOTER_LINKS, SIDEBAR_COUNTS, SIDEBAR_PAPEIS, ROUTE_STATE, ROUTE_STATE_LABEL, CONV, ROUTINES, TASKS, ORIGIN_COLORS, GROUP_META, FIN_SUBNAV, FIN_SUBNAV_OVERFLOW };

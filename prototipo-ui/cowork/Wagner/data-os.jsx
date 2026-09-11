// data-os.jsx — Mock data + helpers para Ordens de Serviço

const OS_STAGES = [
  { id: "rascunho",   label: "Rascunho",   color: "muted" },
  { id: "orcado",     label: "Orçado",     color: "blue" },
  { id: "aprovacao",  label: "Aprovação arte", color: "amber" },
  { id: "producao",   label: "Em produção", color: "violet" },
  { id: "acabamento", label: "Acabamento",  color: "violet" },
  { id: "expedicao",  label: "Expedição",   color: "cyan" },
  { id: "entregue",   label: "Entregue",    color: "green" },
  { id: "cancelado",  label: "Cancelado",   color: "red" },
];

const OS_LIST = [
  { id:"4821", client:"Acme Comércio Ltda", contact:"Camila Diniz", product:"Banner Lona 440g — 3×2m", qty:1, value:"R$ 480,00", stage:"aprovacao", deadline:"hoje 14:00", urgent:true,  responsible:"Joana Lima",  team:"Arte", created:"21/04 09:14", updated:"hoje 13:55" },
  { id:"4820", client:"TechPro Equipamentos", contact:"Diego Vasconcellos", product:"Adesivos recortados 8×8cm — 200un", qty:200, value:"R$ 740,00", stage:"acabamento", deadline:"hoje 17:00", urgent:true, responsible:"Felipe Acab.", team:"Produção", created:"19/04", updated:"hoje 11:02" },
  { id:"4819", client:"Padaria Estrela", contact:"Renato Lopes", product:"Cardápios A4 frente e verso — 50un", qty:50, value:"R$ 215,00", stage:"expedicao", deadline:"amanhã 09:00", urgent:false, responsible:"Mateus PCP", team:"Expedição", created:"18/04", updated:"ontem 16:20" },
  { id:"4818", client:"Posto BR Centro", contact:"Marcos Vinícius", product:"Lona Front-Light — 5×3m", qty:1, value:"R$ 920,00", stage:"aprovacao", deadline:"qui 11:00", urgent:false, responsible:"Joana Lima", team:"Arte", created:"17/04", updated:"hoje 09:30" },
  { id:"4817", client:"Clínica Vida", contact:"Marcos Saraiva", product:"Placas de sinalização — 12un", qty:12, value:"R$ 1.840,00", stage:"producao", deadline:"sex 16:00", urgent:false, responsible:"Carla Souza", team:"Produção", created:"16/04", updated:"hoje 08:42" },
  { id:"4816", client:"Mercado União", contact:"João Inst.", product:"Fachada ACM 6×2m + instalação", qty:1, value:"R$ 4.250,00", stage:"orcado", deadline:"prox semana", urgent:false, responsible:"Bruna Vendas", team:"Comercial", created:"15/04", updated:"15/04" },
  { id:"4815", client:"Escola Aurora", contact:"Pedagógico", product:"Banners faixas 1×3m — 6un", qty:6, value:"R$ 1.080,00", stage:"entregue", deadline:"entregue ontem", urgent:false, responsible:"Mateus PCP", team:"Expedição", created:"10/04", updated:"ontem 17:50" },
  { id:"4814", client:"Auto Posto Vale", contact:"Sandro", product:"Adesivo bomba — 8un", qty:8, value:"R$ 320,00", stage:"entregue", deadline:"entregue 20/04", urgent:false, responsible:"Felipe Acab.", team:"Expedição", created:"08/04", updated:"20/04" },
  { id:"4813", client:"Acme Comércio Ltda", contact:"Camila Diniz", product:"Cartões de visita 4/4 — 1000un", qty:1000, value:"R$ 180,00", stage:"producao", deadline:"qua 10:00", urgent:false, responsible:"Carla Souza", team:"Produção", created:"15/04", updated:"hoje 07:14" },
  { id:"4812", client:"Restaurante Tomate", contact:"Helena", product:"Cardápio plastificado A3 — 20un", qty:20, value:"R$ 410,00", stage:"acabamento", deadline:"sex 12:00", urgent:false, responsible:"Felipe Acab.", team:"Produção", created:"14/04", updated:"hoje 10:18" },
  { id:"4811", client:"Studio Foto Click", contact:"Beto", product:"Backdrop tecido 3×2,5m", qty:1, value:"R$ 680,00", stage:"aprovacao", deadline:"atrasada 1d", urgent:true, responsible:"Joana Lima", team:"Arte", created:"12/04", updated:"21/04 11:00" },
  { id:"4810", client:"Pet Shop Amigo", contact:"Telma", product:"Placa fachada PVC + iluminação", qty:1, value:"R$ 2.150,00", stage:"orcado", deadline:"aprovação cliente", urgent:false, responsible:"Bruna Vendas", team:"Comercial", created:"11/04", updated:"19/04" },
  { id:"4809", client:"Imobiliária Norte", contact:"Diretoria", product:"Fachada ACM 4×1,5m", qty:1, value:"R$ 3.180,00", stage:"producao", deadline:"qui 18:00", urgent:false, responsible:"Carla Souza", team:"Produção", created:"10/04", updated:"hoje 09:00" },
  { id:"4808", client:"Padaria Estrela", contact:"Renato Lopes", product:"Adesivos vitrine — 4 peças", qty:4, value:"R$ 290,00", stage:"rascunho", deadline:"—", urgent:false, responsible:"Bruna Vendas", team:"Comercial", created:"hoje 08:00", updated:"hoje 08:00" },
  { id:"4807", client:"Clínica Vida", contact:"Marcos Saraiva", product:"Adesivos perfurados janela — 3m²", qty:1, value:"R$ 540,00", stage:"cancelado", deadline:"cancelada 18/04", urgent:false, responsible:"Joana Lima", team:"Arte", created:"05/04", updated:"18/04" },
  { id:"4806", client:"Auto Center Júnior", contact:"Junior", product:"Faixa lateral veicular — 2 carros", qty:2, value:"R$ 980,00", stage:"acabamento", deadline:"ter 14:00", urgent:false, responsible:"Felipe Acab.", team:"Produção", created:"09/04", updated:"hoje 13:20" },
  { id:"4805", client:"Buffet Família", contact:"Luana", product:"Painel cenário 2×2m", qty:1, value:"R$ 360,00", stage:"expedicao", deadline:"hoje 18:00", urgent:false, responsible:"Mateus PCP", team:"Expedição", created:"15/04", updated:"hoje 14:00" },
  { id:"4804", client:"Academia Pulse", contact:"Ricardo", product:"Lona perfurada fachada 8×3m", qty:1, value:"R$ 2.940,00", stage:"producao", deadline:"sex 09:00", urgent:false, responsible:"Carla Souza", team:"Produção", created:"08/04", updated:"hoje 11:50" },
  { id:"4803", client:"Mercado União", contact:"Compras", product:"Etiquetas térmicas 50×30 — 20mil", qty:20000, value:"R$ 1.420,00", stage:"entregue", deadline:"entregue 19/04", urgent:false, responsible:"Mateus PCP", team:"Expedição", created:"02/04", updated:"19/04" },
  { id:"4802", client:"Loja Bombom", contact:"Dani", product:"Sacolas personalizadas — 500un", qty:500, value:"R$ 950,00", stage:"orcado", deadline:"aguardando", urgent:false, responsible:"Bruna Vendas", team:"Comercial", created:"03/04", updated:"15/04" },
];

// Timeline mock para detalhe
const OS_TIMELINE = {
  "4821": [
    { who:"Bruna Vendas", role:"Comercial", when:"21/04 09:14", what:"OS criada a partir do orçamento #ORC-2188", kind:"create" },
    { who:"Camila (cliente)", role:"Cliente", when:"21/04 14:30", what:"Aprovou o orçamento por e-mail", kind:"client" },
    { who:"Joana Lima", role:"Arte", when:"21/04 15:02", what:"Iniciou desenvolvimento da arte v1", kind:"art" },
    { who:"Joana Lima", role:"Arte", when:"22/04 10:18", what:"Subiu v1 para revisão", kind:"art", file:"banner-acme-v1.pdf" },
    { who:"Camila (cliente)", role:"Cliente", when:"22/04 17:30", what:"Pediu logo +6%", kind:"client" },
    { who:"Joana Lima", role:"Arte", when:"hoje 09:14", what:"Subiu v3 com sangramento ajustado", kind:"art", file:"banner-acme-final-v3.pdf" },
    { who:"Mateus PCP", role:"PCP", when:"hoje 10:02", what:"Alocou na Roland 540, carga das 16h", kind:"prod" },
    { who:"você", role:"Aprovador", when:"hoje 13:55", what:"Aguardando sua aprovação para liberar produção", kind:"pending" },
  ],
};

// Stats agregados (calculado, mas mock para abrir rápido)
function osStats(list) {
  return {
    total:      list.length,
    abertas:    list.filter(o => !["entregue","cancelado"].includes(o.stage)).length,
    atrasadas:  list.filter(o => o.urgent && !["entregue","cancelado"].includes(o.stage)).length,
    valorAberto: list
      .filter(o => !["entregue","cancelado"].includes(o.stage))
      .reduce((acc, o) => acc + parseFloat(o.value.replace(/[^\d,]/g,'').replace(',','.')), 0),
  };
}

// ─── Clientes ───
const OS_CLIENTS = [
  { id:"c-acme",   name:"Acme Comércio Ltda",  doc:"12.345.678/0001-90", contact:"Camila Diniz",      phone:"+55 11 98712-3344", lastOs:"#4821" },
  { id:"c-tech",   name:"TechPro Equipamentos",doc:"23.456.789/0001-01", contact:"Diego Vasconcellos",phone:"+55 11 99812-4400", lastOs:"#4820" },
  { id:"c-pad",    name:"Padaria Estrela",     doc:"34.567.890/0001-12", contact:"Renato Lopes",      phone:"+55 11 98712-3344", lastOs:"#4819" },
  { id:"c-posto",  name:"Posto BR Centro",     doc:"45.678.901/0001-23", contact:"Marcos Vinícius",   phone:"+55 11 97654-2200", lastOs:"#4818" },
  { id:"c-clinica",name:"Clínica Vida",        doc:"56.789.012/0001-34", contact:"Marcos Saraiva",    phone:"+55 11 95544-1010", lastOs:"#4817" },
  { id:"c-merc",   name:"Mercado União",       doc:"67.890.123/0001-45", contact:"João Inst.",        phone:"+55 11 99812-7700", lastOs:"#4816" },
  { id:"c-escola", name:"Escola Aurora",       doc:"78.901.234/0001-56", contact:"Pedagógico",        phone:"+55 11 91234-5566", lastOs:"#4815" },
  { id:"c-vale",   name:"Auto Posto Vale",     doc:"89.012.345/0001-67", contact:"Sandro",            phone:"+55 11 92345-6677", lastOs:"#4814" },
  { id:"c-tomate", name:"Restaurante Tomate",  doc:"90.123.456/0001-78", contact:"Helena",            phone:"+55 11 93456-7788", lastOs:"#4812" },
  { id:"c-pet",    name:"Pet Shop Amigo",      doc:"01.234.567/0001-89", contact:"Telma",             phone:"+55 11 94567-8899", lastOs:"#4810" },
  { id:"c-imov",   name:"Imobiliária Norte",   doc:"12.345.678/0001-12", contact:"Diretoria",         phone:"+55 11 95678-9900", lastOs:"#4809" },
  { id:"c-junior", name:"Auto Center Júnior",  doc:"23.456.789/0001-23", contact:"Junior",            phone:"+55 11 96789-0011", lastOs:"#4806" },
  { id:"c-buffet", name:"Buffet Família",      doc:"34.567.890/0001-34", contact:"Luana",             phone:"+55 11 97890-1122", lastOs:"#4805" },
  { id:"c-pulse",  name:"Academia Pulse",      doc:"45.678.901/0001-45", contact:"Ricardo",           phone:"+55 11 98901-2233", lastOs:"#4804" },
  { id:"c-bombom", name:"Loja Bombom",         doc:"56.789.012/0001-56", contact:"Dani",              phone:"+55 11 99012-3344", lastOs:"#4802" },
];

// ─── Endereços por cliente ───
// Cada cliente tem 1+ endereços. Flags: principal (cobrança/cadastro) · entrega (default p/ frete).
// Matriz da gráfica = São Paulo → cidade ≠ "São Paulo" dispara "outro município" (MDF-e) na venda.
const OS_MATRIZ_CIDADE = "São Paulo";
const CLI_ADDR = {
  "c-acme":   [{ id:"ad-acme-1",  label:"Principal", cep:"01310-100", logradouro:"Av. Paulista",          numero:"1578", complemento:"Conj. 142", bairro:"Bela Vista",   cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-tech":   [{ id:"ad-tech-1",  label:"Sede",      cep:"04547-130", logradouro:"R. Funchal",            numero:"418",  complemento:"6º andar",  bairro:"Vila Olímpia", cidade:"São Paulo",            uf:"SP", principal:true, entrega:false },
               { id:"ad-tech-2",  label:"Galpão (entrega)", cep:"07034-000", logradouro:"Av. Tiradentes", numero:"920",  complemento:"Doca 3",    bairro:"Centro",       cidade:"Guarulhos",            uf:"SP", principal:false, entrega:true }],
  "c-pad":    [{ id:"ad-pad-1",   label:"Principal", cep:"05422-030", logradouro:"R. dos Pinheiros",       numero:"765",  complemento:"",          bairro:"Pinheiros",    cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-posto":  [{ id:"ad-posto-1", label:"Principal", cep:"06010-010", logradouro:"Av. dos Autonomistas",   numero:"3200", complemento:"",          bairro:"Centro",       cidade:"Osasco",               uf:"SP", principal:true, entrega:true }],
  "c-clinica":[{ id:"ad-clin-1",  label:"Principal", cep:"04077-020", logradouro:"Av. Ibirapuera",         numero:"2144", complemento:"Sala 8",    bairro:"Moema",        cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-merc":   [{ id:"ad-merc-1",  label:"Principal", cep:"07115-000", logradouro:"Av. Salgado Filho",      numero:"1540", complemento:"",          bairro:"Vila Rio",     cidade:"Guarulhos",            uf:"SP", principal:true, entrega:true }],
  "c-escola": [{ id:"ad-esc-1",   label:"Principal", cep:"02403-100", logradouro:"R. Voluntários da Pátria",numero:"2890",complemento:"Bloco B",   bairro:"Santana",      cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-vale":   [{ id:"ad-vale-1",  label:"Principal", cep:"09720-000", logradouro:"Av. Kennedy",            numero:"700",  complemento:"",          bairro:"Centro",       cidade:"São Bernardo do Campo",uf:"SP", principal:true, entrega:true }],
  "c-tomate": [{ id:"ad-tom-1",   label:"Principal", cep:"04532-060", logradouro:"R. Joaquim Floriano",    numero:"210",  complemento:"Loja 2",    bairro:"Itaim Bibi",   cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-pet":    [{ id:"ad-pet-1",   label:"Principal", cep:"09910-720", logradouro:"Av. Antônio Piranga",    numero:"880",  complemento:"",          bairro:"Centro",       cidade:"Diadema",              uf:"SP", principal:true, entrega:true }],
  "c-imov":   [{ id:"ad-imov-1",  label:"Principal", cep:"02011-000", logradouro:"Av. Cruzeiro do Sul",    numero:"1100", complemento:"Conj. 9",   bairro:"Santana",      cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-junior": [{ id:"ad-jun-1",   label:"Principal", cep:"09015-000", logradouro:"R. das Figueiras",       numero:"445",  complemento:"",          bairro:"Centro",       cidade:"Santo André",          uf:"SP", principal:true, entrega:true }],
  "c-buffet": [{ id:"ad-buf-1",   label:"Principal", cep:"03310-000", logradouro:"R. Tuiuti",              numero:"1502", complemento:"",          bairro:"Tatuapé",      cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-pulse":  [{ id:"ad-pul-1",   label:"Principal", cep:"05014-000", logradouro:"R. Cardoso de Almeida",  numero:"620",  complemento:"Térreo",    bairro:"Perdizes",     cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
  "c-bombom": [{ id:"ad-bom-1",   label:"Principal", cep:"01037-010", logradouro:"R. Florêncio de Abreu",  numero:"305",  complemento:"",          bairro:"Centro",       cidade:"São Paulo",            uf:"SP", principal:true, entrega:true }],
};
OS_CLIENTS.forEach((c) => { c.addresses = CLI_ADDR[c.id] || []; });

// Helpers de endereço (fonte única — usados por Clientes e Vendas)
function cliEntregaAddr(c) {
  const a = c?.addresses || [];
  return a.find((x) => x.entrega) || a.find((x) => x.principal) || a[0] || null;
}
function cliPrincipalAddr(c) {
  const a = c?.addresses || [];
  return a.find((x) => x.principal) || a[0] || null;
}
function fmtAddrLinha(a) {
  if (!a) return "—";
  const l1 = [a.logradouro, a.numero].filter(Boolean).join(", ");
  return [l1, a.complemento].filter(Boolean).join(" · ");
}
function fmtAddrCidade(a) {
  if (!a) return "—";
  return [a.bairro, [a.cidade, a.uf].filter(Boolean).join("/")].filter(Boolean).join(" · ");
}

// ─── Catálogo de produtos ───
const OS_PRODUCTS = [
  { id:"p-banner",   cat:"Banner",     name:"Banner Lona 440g",          unit:"m²",  price:80,  desc:"Lona front-light. Ideal para fachadas e externos." },
  { id:"p-lona",     cat:"Banner",     name:"Lona Front-Light",          unit:"m²",  price:62,  desc:"Translúcida, para back-light." },
  { id:"p-lona-perf",cat:"Banner",     name:"Lona Perfurada",            unit:"m²",  price:92,  desc:"Para fachadas com vento." },
  { id:"p-adesivo",  cat:"Adesivo",    name:"Adesivo Vinil Recortado",   unit:"un",  price:3.5, desc:"Vinil corte eletrônico, várias cores." },
  { id:"p-ades-perf",cat:"Adesivo",    name:"Adesivo Perfurado",         unit:"m²",  price:65,  desc:"Visão unilateral para janelas." },
  { id:"p-cartao",   cat:"Impressão",  name:"Cartão de Visita 4/4",      unit:"un",  price:0.18,desc:"Couché 300g, plastificado opcional." },
  { id:"p-cardapio", cat:"Impressão",  name:"Cardápio Plastificado A3",  unit:"un",  price:18,  desc:"Plastificação fosca ou brilho." },
  { id:"p-folder",   cat:"Impressão",  name:"Folder/Folheto A4",         unit:"un",  price:0.42,desc:"Couché 150g, dobra opcional." },
  { id:"p-placa",    cat:"Sinalização",name:"Placa PVC Expandido",       unit:"un",  price:120, desc:"PVC 5mm com adesivo." },
  { id:"p-acm",      cat:"Sinalização",name:"Fachada ACM com Letra Caixa",unit:"m²", price:680, desc:"Inclui projeto + instalação." },
  { id:"p-faixa",    cat:"Veicular",   name:"Faixa Lateral Veicular",    unit:"un",  price:480, desc:"Vinil automotivo recortado." },
  { id:"p-backdrop", cat:"Tecido",     name:"Backdrop Tecido Sublimado", unit:"m²",  price:130, desc:"Tecido oxford com sublimação." },
  { id:"p-etiqueta", cat:"Impressão",  name:"Etiqueta Térmica 50×30",    unit:"mil", price:72,  desc:"Adesivo branco, em rolo." },
  { id:"p-sacola",   cat:"Embalagem",  name:"Sacola Personalizada",      unit:"un",  price:1.85,desc:"Kraft 90g com alça de cordão." },
  { id:"p-painel",   cat:"Tecido",     name:"Painel Cenário",            unit:"m²",  price:95,  desc:"Lona ou tecido, eventos." },
];

// ─── Responsáveis ───
const OS_RESPONSIBLES = [
  { id:"u-bruna",  name:"Bruna Vendas",   team:"Comercial",  initials:"BV", grad:"av-2" },
  { id:"u-joana",  name:"Joana Lima",     team:"Arte",       initials:"JL", grad:"av-1" },
  { id:"u-mateus", name:"Mateus PCP",     team:"PCP",        initials:"MP", grad:"av-3" },
  { id:"u-carla",  name:"Carla Souza",    team:"Produção",   initials:"CS", grad:"av-5" },
  { id:"u-felipe", name:"Felipe Acab.",   team:"Acabamento", initials:"FA", grad:"av-6" },
  { id:"u-pedro",  name:"Pedro Expedição",team:"Expedição",  initials:"PE", grad:"av-4" },
];

window.OS_DATA = { OS_STAGES, OS_LIST, OS_TIMELINE, osStats, OS_CLIENTS, OS_PRODUCTS, OS_RESPONSIBLES, OS_MATRIZ_CIDADE, cliEntregaAddr, cliPrincipalAddr, fmtAddrLinha, fmtAddrCidade };

// ── COSTURA venda→produção ([W] "roda o fluxo" 2026-06-10) ───────────────
// Vendas emite oimpresso:venda-created; itens que geram produção viram OS na fila.
window.addEventListener("oimpresso:venda-created", (e) => {
  const det = e.detail || {};
  if (!det.geraProducao) return;
  const maxId = OS_LIST.reduce((m, o) => Math.max(m, parseInt(o.id, 10) || 0), 0);
  OS_LIST.unshift({
    id: String(maxId + 1),
    client: det.clientName || "Consumidor Final",
    contact: det.clientName || "—",
    product: det.firstItem || "Venda balcão",
    qty: det.itemCount || 1,
    value: "R$ " + ((det.totalCents || 0) / 100).toLocaleString("pt-BR", { minimumFractionDigits: 2 }),
    stage: "producao",
    deadline: "a programar",
    urgent: false,
    responsible: "Carla Souza", team: "Produção",
    created: "hoje", updated: "agora",
    fromVenda: det.vendaId || null,
  });
});
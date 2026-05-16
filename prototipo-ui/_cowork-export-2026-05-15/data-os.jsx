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
  { id:"c-acme",   name:"Acme Comércio Ltda",  doc:"[REDACTED]", contact:"Camila Diniz",      phone:"+55 11 98712-3344", lastOs:"#4821" },
  { id:"c-tech",   name:"TechPro Equipamentos",doc:"[REDACTED]", contact:"Diego Vasconcellos",phone:"+55 11 99812-4400", lastOs:"#4820" },
  { id:"c-pad",    name:"Padaria Estrela",     doc:"[REDACTED]", contact:"Renato Lopes",      phone:"+55 11 98712-3344", lastOs:"#4819" },
  { id:"c-posto",  name:"Posto BR Centro",     doc:"[REDACTED]", contact:"Marcos Vinícius",   phone:"+55 11 97654-2200", lastOs:"#4818" },
  { id:"c-clinica",name:"Clínica Vida",        doc:"[REDACTED]", contact:"Marcos Saraiva",    phone:"+55 11 95544-1010", lastOs:"#4817" },
  { id:"c-merc",   name:"Mercado União",       doc:"[REDACTED]", contact:"João Inst.",        phone:"+55 11 99812-7700", lastOs:"#4816" },
  { id:"c-escola", name:"Escola Aurora",       doc:"[REDACTED]", contact:"Pedagógico",        phone:"+55 11 91234-5566", lastOs:"#4815" },
  { id:"c-vale",   name:"Auto Posto Vale",     doc:"[REDACTED]", contact:"Sandro",            phone:"+55 11 92345-6677", lastOs:"#4814" },
  { id:"c-tomate", name:"Restaurante Tomate",  doc:"[REDACTED]", contact:"Helena",            phone:"+55 11 93456-7788", lastOs:"#4812" },
  { id:"c-pet",    name:"Pet Shop Amigo",      doc:"[REDACTED]", contact:"Telma",             phone:"+55 11 94567-8899", lastOs:"#4810" },
  { id:"c-imov",   name:"Imobiliária Norte",   doc:"[REDACTED]", contact:"Diretoria",         phone:"+55 11 95678-9900", lastOs:"#4809" },
  { id:"c-junior", name:"Auto Center Júnior",  doc:"[REDACTED]", contact:"Junior",            phone:"+55 11 96789-0011", lastOs:"#4806" },
  { id:"c-buffet", name:"Buffet Família",      doc:"[REDACTED]", contact:"Luana",             phone:"+55 11 97890-1122", lastOs:"#4805" },
  { id:"c-pulse",  name:"Academia Pulse",      doc:"[REDACTED]", contact:"Ricardo",           phone:"+55 11 98901-2233", lastOs:"#4804" },
  { id:"c-bombom", name:"Loja Bombom",         doc:"[REDACTED]", contact:"Dani",              phone:"+55 11 99012-3344", lastOs:"#4802" },
];

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

window.OS_DATA = { OS_STAGES, OS_LIST, OS_TIMELINE, osStats, OS_CLIENTS, OS_PRODUCTS, OS_RESPONSIBLES };
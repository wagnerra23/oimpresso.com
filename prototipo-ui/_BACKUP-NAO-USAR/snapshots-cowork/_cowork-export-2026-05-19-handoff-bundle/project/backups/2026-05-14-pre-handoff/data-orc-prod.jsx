// Mock data — Orçamentos
const ORC_LIST = [
  { id:"O-2841", client:"Padaria Estrela",        contact:"Renato Silva",    items:"Banner 3x1m + Cartão fidelidade 500un", value:"R$ 1.840,00", validity:"15 dias", created:"2026-04-28", status:"enviado",   responsible:"Bruna Vendas",   prob:70 },
  { id:"O-2842", client:"Mercado União",          contact:"Helena Rocha",    items:"Folder A4 colorido 2.000un",            value:"R$ 3.420,00", validity:"30 dias", created:"2026-04-28", status:"rascunho",  responsible:"Carlos Vendas",  prob:0  },
  { id:"O-2843", client:"Auto Posto Águia",       contact:"Marcos Pinheiro", items:"Adesivo bombas + Placa preço",         value:"R$ 2.180,00", validity:"15 dias", created:"2026-04-27", status:"enviado",   responsible:"Bruna Vendas",   prob:50 },
  { id:"O-2844", client:"Farmácia Vida Plena",    contact:"Camila Duarte",   items:"Sacolas personalizadas 5.000un",       value:"R$ 4.860,00", validity:"30 dias", created:"2026-04-27", status:"aprovado",  responsible:"Carlos Vendas",  prob:100, osId:"4830" },
  { id:"O-2845", client:"Construtora Horizonte",  contact:"Eduardo Mello",   items:"Banner obra 4x2m + Tapume 20m",        value:"R$ 8.940,00", validity:"15 dias", created:"2026-04-26", status:"negociacao",responsible:"Bruna Vendas",   prob:60 },
  { id:"O-2846", client:"Pet Shop Latido Feliz",  contact:"Aline Souza",     items:"Cartão visita 1.000un + Folder",        value:"R$ 980,00",   validity:"30 dias", created:"2026-04-25", status:"enviado",   responsible:"Carlos Vendas",  prob:75 },
  { id:"O-2847", client:"Restaurante Sabor da Casa",contact:"Júlio Mendes",  items:"Cardápio plastificado 50un + Banner",  value:"R$ 1.320,00", validity:"15 dias", created:"2026-04-24", status:"perdido",   responsible:"Bruna Vendas",   prob:0  },
  { id:"O-2848", client:"Distribuidora Brasil",   contact:"Sandra Vieira",   items:"Catálogo 100p A4 colorido 200un",       value:"R$ 12.400,00",validity:"30 dias", created:"2026-04-22", status:"aprovado",  responsible:"Carlos Vendas",  prob:100, osId:"4828" },
  { id:"O-2849", client:"Salão Beleza Pura",      contact:"Patrícia Gomes",  items:"Cartão fidelidade 300un",               value:"R$ 420,00",   validity:"15 dias", created:"2026-04-21", status:"enviado",   responsible:"Bruna Vendas",   prob:80 },
];

window.ORC_DATA = { ORC_LIST };

// Mock data — Produtos (catálogo da gráfica)
const PROD_LIST = [
  { id:"P-001", name:"Cartão de visita 9x5cm",      category:"Impressos",   unit:"milheiro", price:"R$ 220,00",  stock:"sob demanda", lead:"3 dias",  bom:["Papel couché 300g","Tinta CMYK","Verniz UV opcional"],     active:true,  popularity:95 },
  { id:"P-002", name:"Banner lona 440g",            category:"Comunicação Visual", unit:"m²", price:"R$ 38,00",   stock:"sob demanda", lead:"2 dias",  bom:["Lona 440g","Tinta solvente","Ilhós"],                       active:true,  popularity:88 },
  { id:"P-003", name:"Adesivo vinil recortado",     category:"Comunicação Visual", unit:"m²", price:"R$ 65,00",   stock:"sob demanda", lead:"3 dias",  bom:["Vinil adesivo","Plotter de recorte"],                       active:true,  popularity:82 },
  { id:"P-004", name:"Folder A4 colorido frente/verso", category:"Impressos",   unit:"milheiro", price:"R$ 480,00",  stock:"sob demanda", lead:"4 dias",  bom:["Papel couché 150g","Tinta CMYK","Dobra"],                   active:true,  popularity:78 },
  { id:"P-005", name:"Sacola personalizada kraft",  category:"Embalagens",  unit:"milheiro", price:"R$ 980,00",  stock:"sob demanda", lead:"7 dias",  bom:["Kraft 120g","Cordão","Impressão 1 cor"],                    active:true,  popularity:65 },
  { id:"P-006", name:"Catálogo A4 lombada quadrada",category:"Impressos",   unit:"unidade",  price:"R$ 18,00",   stock:"sob demanda", lead:"7 dias",  bom:["Couché 115g miolo","Cartão 250g capa","Cola PUR"],          active:true,  popularity:42 },
  { id:"P-007", name:"Placa PVC 3mm",               category:"Comunicação Visual", unit:"m²", price:"R$ 110,00",  stock:"sob demanda", lead:"3 dias",  bom:["PVC expandido 3mm","Tinta UV","Recorte"],                   active:true,  popularity:55 },
  { id:"P-008", name:"Cartão fidelidade",           category:"Impressos",   unit:"milheiro", price:"R$ 380,00",  stock:"sob demanda", lead:"4 dias",  bom:["Couché 250g","Tinta CMYK","Verniz UV"],                     active:true,  popularity:48 },
  { id:"P-009", name:"Tapume canteiro de obras",    category:"Comunicação Visual", unit:"m²", price:"R$ 78,00",   stock:"sob demanda", lead:"5 dias",  bom:["Lona","Estrutura metálica","Instalação"],                   active:true,  popularity:28 },
  { id:"P-010", name:"Cardápio plastificado",       category:"Impressos",   unit:"unidade",  price:"R$ 8,50",    stock:"sob demanda", lead:"3 dias",  bom:["Couché 250g","Plastificação"],                              active:true,  popularity:32 },
  { id:"P-011", name:"Convite casamento",           category:"Impressos",   unit:"unidade",  price:"R$ 6,80",    stock:"sob demanda", lead:"5 dias",  bom:["Papel especial","Envelope","Acabamento"],                   active:false, popularity:8  },
];
const PROD_CATEGORIES = [...new Set(PROD_LIST.map(p => p.category))];

window.PROD_DATA = { PROD_LIST, PROD_CATEGORIES };

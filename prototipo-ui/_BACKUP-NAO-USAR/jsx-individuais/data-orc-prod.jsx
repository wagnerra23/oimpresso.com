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
// Cada produto agora tem: imagem (gradient + label), estoque numérico por variante,
// grade de variantes (tamanho/cor/material), unidade e prazo.
const PROD_LIST = [
  { id:"P-001", name:"Cartão de visita 9x5cm",      category:"Impressos",   unit:"milheiro", price:"R$ 220,00",  lead:"3 dias",  popularity:95, active:true,
    img: { hue: 220, label: "CARTÃO" },
    variants: [
      { sku:"P-001-A", spec:"4×0 cores · 9×5cm",          stock: 8400, price:"R$ 220,00" },
      { sku:"P-001-B", spec:"4×4 cores · 9×5cm",          stock: 5200, price:"R$ 260,00" },
      { sku:"P-001-C", spec:"4×4 + Verniz UV · 9×5cm",    stock: 1800, price:"R$ 320,00" },
      { sku:"P-001-D", spec:"4×4 + Laminação · 9×5cm",    stock: 0,    price:"R$ 380,00" },
    ],
    bom:["Papel couché 300g","Tinta CMYK","Verniz UV opcional"] },
  { id:"P-002", name:"Banner lona 440g",            category:"Comunicação Visual", unit:"m²", price:"R$ 38,00",   lead:"2 dias",  popularity:88, active:true,
    img: { hue: 30, label: "BANNER" },
    variants: [
      { sku:"P-002-A", spec:"Lona 280g brilho",   stock: 420, price:"R$ 28,00" },
      { sku:"P-002-B", spec:"Lona 380g brilho",   stock: 680, price:"R$ 32,00" },
      { sku:"P-002-C", spec:"Lona 440g blackout", stock: 240, price:"R$ 38,00" },
      { sku:"P-002-D", spec:"Vinil + frontlight", stock: 60,  price:"R$ 58,00" },
    ],
    bom:["Lona 440g","Tinta solvente","Ilhós"] },
  { id:"P-003", name:"Adesivo vinil recortado",     category:"Comunicação Visual", unit:"m²", price:"R$ 65,00",   lead:"3 dias",  popularity:82, active:true,
    img: { hue: 295, label: "ADESIVO" },
    variants: [
      { sku:"P-003-A", spec:"Vinil brilho",  stock: 320, price:"R$ 42,00" },
      { sku:"P-003-B", spec:"Vinil fosco",   stock: 180, price:"R$ 48,00" },
      { sku:"P-003-C", spec:"Recorte simples",stock: 240, price:"R$ 65,00" },
      { sku:"P-003-D", spec:"Recorte + aplicação", stock: 12, price:"R$ 145,00" },
    ],
    bom:["Vinil adesivo","Plotter de recorte"] },
  { id:"P-004", name:"Folder A4 colorido f/v",      category:"Impressos",   unit:"milheiro", price:"R$ 480,00",  lead:"4 dias",  popularity:78, active:true,
    img: { hue: 145, label: "FOLDER" },
    variants: [
      { sku:"P-004-A", spec:"A4 · 4×4 · 150g",   stock: 3200, price:"R$ 480,00" },
      { sku:"P-004-B", spec:"A4 · 4×4 · 210g",   stock: 1400, price:"R$ 580,00" },
      { sku:"P-004-C", spec:"A5 · 4×4 · 150g",   stock: 2800, price:"R$ 360,00" },
    ],
    bom:["Papel couché 150g","Tinta CMYK","Dobra"] },
  { id:"P-005", name:"Sacola kraft personalizada",  category:"Embalagens",  unit:"milheiro", price:"R$ 980,00",  lead:"7 dias",  popularity:65, active:true,
    img: { hue: 60, label: "SACOLA" },
    variants: [
      { sku:"P-005-A", spec:"Kraft 120g · 25×35cm",   stock: 1200, price:"R$ 980,00" },
      { sku:"P-005-B", spec:"Kraft 150g · 30×40cm",   stock: 800,  price:"R$ 1.180,00" },
      { sku:"P-005-C", spec:"Branca 150g · 30×40cm",  stock: 0,    price:"R$ 1.320,00" },
    ],
    bom:["Kraft 120g","Cordão","Impressão 1 cor"] },
  { id:"P-006", name:"Catálogo A4 lombada quadrada",category:"Impressos",   unit:"unidade",  price:"R$ 18,00",   lead:"7 dias",  popularity:42, active:true,
    img: { hue: 200, label: "CATÁLOGO" },
    variants: [
      { sku:"P-006-A", spec:"24 páginas · couché 115g", stock: 240, price:"R$ 18,00" },
      { sku:"P-006-B", spec:"48 páginas · couché 115g", stock: 80,  price:"R$ 32,00" },
      { sku:"P-006-C", spec:"96 páginas · couché 115g", stock: 0,   price:"R$ 58,00" },
    ],
    bom:["Couché 115g miolo","Cartão 250g capa","Cola PUR"] },
  { id:"P-007", name:"Placa PVC 3mm",               category:"Comunicação Visual", unit:"m²", price:"R$ 110,00",  lead:"3 dias",  popularity:55, active:true,
    img: { hue: 240, label: "PLACA" },
    variants: [
      { sku:"P-007-A", spec:"PVC 3mm branco", stock: 28, price:"R$ 110,00" },
      { sku:"P-007-B", spec:"PVC 3mm preto",  stock: 14, price:"R$ 110,00" },
      { sku:"P-007-C", spec:"PVC 5mm branco", stock: 8,  price:"R$ 145,00" },
    ],
    bom:["PVC expandido 3mm","Tinta UV","Recorte"] },
  { id:"P-008", name:"Cartão fidelidade",           category:"Impressos",   unit:"milheiro", price:"R$ 380,00",  lead:"4 dias",  popularity:48, active:true,
    img: { hue: 350, label: "FIDELI" },
    variants: [
      { sku:"P-008-A", spec:"Couché 250g · 8×5cm",  stock: 4000, price:"R$ 380,00" },
      { sku:"P-008-B", spec:"PVC 0.3mm · 8.5×5.5cm", stock: 800, price:"R$ 580,00" },
    ],
    bom:["Couché 250g","Tinta CMYK","Verniz UV"] },
  { id:"P-009", name:"Tapume canteiro de obras",    category:"Comunicação Visual", unit:"m²", price:"R$ 78,00",   lead:"5 dias",  popularity:28, active:true,
    img: { hue: 50, label: "TAPUME" },
    variants: [
      { sku:"P-009-A", spec:"Lona impressa", stock: 180, price:"R$ 78,00" },
      { sku:"P-009-B", spec:"Lona + estrutura metálica", stock: 0, price:"R$ 145,00" },
    ],
    bom:["Lona","Estrutura metálica","Instalação"] },
  { id:"P-010", name:"Cardápio plastificado",       category:"Impressos",   unit:"unidade",  price:"R$ 8,50",    lead:"3 dias",  popularity:32, active:true,
    img: { hue: 90, label: "CARDÁPIO" },
    variants: [
      { sku:"P-010-A", spec:"A5 · couché 250g + plast.", stock: 320, price:"R$ 8,50" },
      { sku:"P-010-B", spec:"A4 · couché 250g + plast.", stock: 120, price:"R$ 14,80" },
    ],
    bom:["Couché 250g","Plastificação"] },
  { id:"P-011", name:"Convite casamento",           category:"Impressos",   unit:"unidade",  price:"R$ 6,80",    lead:"5 dias",  popularity:8,  active:false,
    img: { hue: 320, label: "CONVITE" },
    variants: [
      { sku:"P-011-A", spec:"Papel perolado + envelope", stock: 0, price:"R$ 6,80" },
    ],
    bom:["Papel especial","Envelope","Acabamento"] },

  // ─── Mecânica / Auto-peças (grade de aplicação por modelo de veículo) ──
  { id:"P-012", name:"Pastilha de freio cerâmica",    category:"Mecânica", unit:"jogo", price:"R$ 145,00", lead:"1 dia", popularity:90, active:true,
    img: { hue: 0, label: "PASTILHA" },
    brand: "Cobreq", oem: ["45022-T5A-J01", "45022-T7J-J01"], superseded: ["BREMBO P28095"],
    suppliers: [
      { name: "Cobreq Brasil",     cost: 78.00,  lead: "1 dia",   margin: 86 },
      { name: "Fras-le BR",        cost: 92.00,  lead: "2 dias",  margin: 58 },
      { name: "TRW Aftermarket",   cost: 110.00, lead: "3 dias",  margin: 32 },
    ],
    compat: { civic2019: true, civic2020: true, city2018: true, city2019: true, ka2018: true, hb202023: true, strada2022: true, hilux2020: false, sandero2017: true, gol2016: true },
    variants: [
      { sku:"P-012-A", spec:"Dianteira · cerâmica", stock: 24, price:"R$ 145,00" },
      { sku:"P-012-B", spec:"Traseira · cerâmica",  stock: 18, price:"R$ 125,00" },
    ],
    bom:["Cerâmica","Suporte metálico"],
    specs: { tipo:"Cerâmica low-dust", espessura:"15mm", pos:"Dianteira/Traseira", garantia:"12 meses ou 20.000 km" } },
  { id:"P-013", name:"Disco de freio ventilado",      category:"Mecânica", unit:"un",   price:"R$ 220,00", lead:"2 dias", popularity:78, active:true,
    img: { hue: 240, label: "DISCO" },
    brand: "Fremax", oem: ["45251-T5A-J01", "45251-TG0-T01"], superseded: ["BREMBO 09.B580.11"],
    suppliers: [
      { name: "Fremax BR",         cost: 128.00, lead: "2 dias",  margin: 72 },
      { name: "Bosch Aftermarket", cost: 145.00, lead: "3 dias",  margin: 52 },
      { name: "Brembo Imp.",       cost: 180.00, lead: "7 dias",  margin: 22 },
    ],
    compat: { civic2019: true, civic2020: true, city2018: true, city2019: true, ka2018: false, hb202023: true, strada2022: true, hilux2020: true, sandero2017: false, gol2016: false },
    variants: [
      { sku:"P-013-A", spec:"Dianteiro 280mm",  stock: 8,  price:"R$ 220,00" },
      { sku:"P-013-B", spec:"Dianteiro 320mm",  stock: 4,  price:"R$ 320,00" },
    ],
    bom:["Disco ferro fundido","Pintura anti-corrosão"],
    specs: { diametro:"280mm/320mm", espessura:"23mm", ventilado:"Sim", garantia:"12 meses" } },
  { id:"P-014", name:"Filtro de óleo",                category:"Mecânica", unit:"un",   price:"R$ 28,00",  lead:"em estoque", popularity:96, active:true,
    img: { hue: 60, label: "FILTRO" },
    brand: "Tecfil", oem: ["15400-RTA-003", "15400-PLM-A02"], superseded: ["WIX 51334", "MANN W920/21"],
    suppliers: [
      { name: "Tecfil",        cost: 14.50, lead: "em estoque", margin: 93 },
      { name: "Mann-Filter",   cost: 22.00, lead: "3 dias",     margin: 27 },
    ],
    compat: { civic2019: true, civic2020: true, city2018: true, city2019: true, ka2018: true, hb202023: true, strada2022: true, hilux2020: true, sandero2017: true, gol2016: true },
    variants: [
      { sku:"P-014-A", spec:"Universal pequeno", stock: 64, price:"R$ 28,00" },
      { sku:"P-014-B", spec:"Universal médio",   stock: 42, price:"R$ 32,00" },
    ],
    bom:["Papel filtrante","Vedação"],
    specs: { rosca:"3/4-16 UNF", altura:"68-95mm", garantia:"6 meses" } },
  { id:"P-015", name:"Bateria 60Ah selada",           category:"Mecânica", unit:"un",   price:"R$ 480,00", lead:"em estoque", popularity:84, active:true,
    img: { hue: 145, label: "BATERIA" },
    brand: "Moura", oem: ["MI60ED"], superseded: ["Heliar HEX60JD"],
    suppliers: [
      { name: "Moura",     cost: 320.00, lead: "em estoque", margin: 50 },
      { name: "Heliar",    cost: 340.00, lead: "em estoque", margin: 41 },
      { name: "AC Delco",  cost: 380.00, lead: "3 dias",     margin: 26 },
    ],
    compat: { civic2019: true, civic2020: true, city2018: true, city2019: true, ka2018: true, hb202023: false, strada2022: true, hilux2020: false, sandero2017: true, gol2016: true },
    variants: [
      { sku:"P-015-A", spec:"60Ah · 12V · selada",  stock: 12, price:"R$ 480,00" },
      { sku:"P-015-B", spec:"70Ah · 12V · selada",  stock: 8,  price:"R$ 580,00" },
    ],
    bom:["Bateria chumbo-ácido"],
    specs: { capacidade:"60Ah", tensão:"12V", cca:"540A", garantia:"18 meses" } },
  { id:"P-016", name:"Vela ignição iridium",          category:"Mecânica", unit:"jogo", price:"R$ 180,00", lead:"em estoque", popularity:72, active:true,
    img: { hue: 30, label: "VELA" },
    brand: "NGK", oem: ["ILZKR7B11", "DILKAR6A11"], superseded: ["DENSO SC20HR11"],
    suppliers: [
      { name: "NGK",        cost: 96.00,  lead: "em estoque", margin: 88 },
      { name: "Denso",      cost: 120.00, lead: "5 dias",     margin: 50 },
      { name: "Bosch",      cost: 140.00, lead: "3 dias",     margin: 29 },
    ],
    compat: { civic2019: true, civic2020: true, city2018: true, city2019: true, ka2018: true, hb202023: true, strada2022: false, hilux2020: false, sandero2017: true, gol2016: true },
    variants: [
      { sku:"P-016-A", spec:"Jogo 4 velas iridium", stock: 36, price:"R$ 180,00" },
    ],
    bom:["Vela iridium","Vedação"],
    specs: { eletrodo:"Iridium", folga:"1.1mm", torque:"25 N·m", garantia:"100.000 km" } },

  // ─── SERVIÇOS ─── (type: servico)
  { id:"S-001", name:"Alinhamento de direção 4 rodas", category:"Serviços", unit:"h", price:"R$ 120,00", lead:"30 min", popularity:88, active:true,
    type:"servico", img:{ hue:200, label:"ALINH" },
    variants:[{ sku:"S-001-A", spec:"Geometria padrão",      stock:99, price:"R$ 120,00" }],
    bom:["Mão-de-obra técnica","Computador de alinhamento"] },
  { id:"S-002", name:"Balanceamento (4 rodas)", category:"Serviços", unit:"h", price:"R$ 80,00", lead:"20 min", popularity:82, active:true,
    type:"servico", img:{ hue:210, label:"BAL"  },
    variants:[{ sku:"S-002-A", spec:"Roda até 17\"",          stock:99, price:"R$ 80,00"  }],
    bom:["Mão-de-obra técnica","Pesos balanceadores"] },
  { id:"S-003", name:"Troca de óleo + filtro", category:"Serviços", unit:"h", price:"R$ 60,00", lead:"30 min", popularity:74, active:true,
    type:"servico", img:{ hue:60, label:"TROCA" },
    variants:[{ sku:"S-003-A", spec:"Padrão (até 5L)",        stock:99, price:"R$ 60,00"  }],
    bom:["Mão-de-obra técnica"] },
  { id:"S-004", name:"Diagnóstico c/ scanner", category:"Serviços", unit:"h", price:"R$ 90,00", lead:"45 min", popularity:60, active:true,
    type:"servico", img:{ hue:190, label:"DIAG"  },
    variants:[{ sku:"S-004-A", spec:"Leitura completa",       stock:99, price:"R$ 90,00"  }],
    bom:["Mão-de-obra técnica","Scanner OBD-II"] },

  // ─── COMPOSIÇÕES ─── (type: composicao — kits que somam produtos+serviços)
  { id:"K-001", name:"Revisão 20.000 km · Civic", category:"Composições", unit:"un", price:"R$ 480,00", lead:"3 h", popularity:78, active:true,
    type:"composicao", img:{ hue:140, label:"REV20"  },
    variants:[{ sku:"K-001-A", spec:"Pacote completo Civic 1.5T", stock:99, price:"R$ 480,00" }],
    bom:["Óleo motor 5W30 (4L)","Filtro de óleo (P-014)","Filtro de ar","Mão-de-obra 1.5h","Inspeção visual 12 pontos"] },
  { id:"K-002", name:"Kit freios dianteiro · Civic", category:"Composições", unit:"jg", price:"R$ 720,00", lead:"1 dia", popularity:65, active:true,
    type:"composicao", img:{ hue:0,  label:"K-FREIO" },
    variants:[{ sku:"K-002-A", spec:"Pastilha + Disco + M.O.", stock:99, price:"R$ 720,00" }],
    bom:["Pastilha cerâmica (P-012)","Disco ventilado (P-013)","Fluido freio DOT4","Mão-de-obra 2h"] },
  { id:"K-003", name:"Pacote pré-viagem", category:"Composições", unit:"un", price:"R$ 380,00", lead:"2 h", popularity:55, active:true,
    type:"composicao", img:{ hue:170, label:"VIAG"  },
    variants:[{ sku:"K-003-A", spec:"Inspeção + Alinhamento + Balanceamento", stock:99, price:"R$ 380,00" }],
    bom:["Alinhamento (S-001)","Balanceamento (S-002)","Diagnóstico scanner (S-004)","Inspeção visual"] },
];

// Lista de veículos atendidos pela oficina (chave alinhada com p.compat)
const PROD_VEHICLES = [
  { key: "civic2019",   label: "Civic 19", brand: "Honda" },
  { key: "civic2020",   label: "Civic 20", brand: "Honda" },
  { key: "city2018",    label: "City 18",  brand: "Honda" },
  { key: "city2019",    label: "City 19",  brand: "Honda" },
  { key: "ka2018",      label: "Ka 18",    brand: "Ford"  },
  { key: "hb202023",    label: "HB20 23",  brand: "Hyundai" },
  { key: "strada2022",  label: "Strada 22",brand: "Fiat"  },
  { key: "hilux2020",   label: "Hilux 20", brand: "Toyota" },
  { key: "sandero2017", label: "Sandero 17",brand:"Renault"},
  { key: "gol2016",     label: "Gol 16",   brand: "VW"    },
];
const PROD_CATEGORIES = [...new Set(PROD_LIST.map(p => p.category))];

// Helpers de estoque consolidado por produto
function prodStock(p) { return (p.variants || []).reduce((s, v) => s + (v.stock || 0), 0); }
function prodStockStatus(p) {
  const total = prodStock(p);
  if (!p.active) return { l: "inativo", c: "muted" };
  if (total === 0) return { l: "sob demanda", c: "muted" };
  if (total < 100) return { l: "estoque baixo", c: "warn" };
  return { l: "em estoque", c: "ok" };
}

window.PROD_DATA = { PROD_LIST, PROD_CATEGORIES, prodStock, prodStockStatus, PROD_VEHICLES };

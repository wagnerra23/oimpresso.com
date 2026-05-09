// Mock data — Catálogo Produto · ROTA LIVRE estilo gráfica/comunicação visual.
// May 2026.

const PROD_CATEGORIES = [
  { id: "impressos",   label: "Impressos",            color: "stone"  },
  { id: "comvis",      label: "Comunicação visual",   color: "stone"  },
  { id: "embalagens",  label: "Embalagens",           color: "stone"  },
  { id: "brindes",     label: "Brindes",              color: "stone"  },
  { id: "adesivos",    label: "Adesivos",             color: "stone"  },
];

// Insumos (matérias-primas e serviços)
const INSUMOS = [
  { id:"INS-01", name:"Papel couché 250g",      unit:"folha BB",  cost: 1.40,  stock: 1240, supplier:"Suprigraf" },
  { id:"INS-02", name:"Papel couché 300g",      unit:"folha BB",  cost: 1.80,  stock:  860, supplier:"Suprigraf" },
  { id:"INS-03", name:"Papel couché 150g",      unit:"folha BB",  cost: 0.95,  stock: 2100, supplier:"Suprigraf" },
  { id:"INS-04", name:"Papel kraft 120g",       unit:"folha 66x96",cost:1.10,  stock:  640, supplier:"Embpack"   },
  { id:"INS-05", name:"Lona 440g",              unit:"m²",        cost: 9.20,  stock:  180, supplier:"LonaForte" },
  { id:"INS-06", name:"Vinil adesivo branco",   unit:"m²",        cost:14.50,  stock:   95, supplier:"Adesimax"  },
  { id:"INS-07", name:"PVC expandido 3mm",      unit:"m²",        cost:42.00,  stock:   38, supplier:"PlacaSul"  },
  { id:"INS-08", name:"Tinta CMYK solvente",    unit:"litro",     cost:88.00,  stock:   24, supplier:"TintaBR"   },
  { id:"INS-09", name:"Verniz UV",              unit:"litro",     cost:62.00,  stock:   16, supplier:"AcabFino"  },
  { id:"INS-10", name:"Laminação BOPP fosca",   unit:"m²",        cost: 3.10,  stock:  220, supplier:"Alphagraf" },
  { id:"INS-11", name:"Ilhós metálico",         unit:"unidade",   cost: 0.18,  stock: 4800, supplier:"FerragemVN" },
  { id:"INS-12", name:"Cordão sintético",       unit:"metro",     cost: 0.42,  stock: 1200, supplier:"Embpack"   },
  { id:"INS-13", name:"Imã flexível 0.5mm",     unit:"m²",        cost:38.00,  stock:   42, supplier:"MagBR"     },
  { id:"INS-14", name:"Cartão 250g branco",     unit:"folha BB",  cost: 1.50,  stock:  720, supplier:"Suprigraf" },
  { id:"INS-15", name:"Recorte plotter",        unit:"hora",      cost:55.00,  stock:    0, supplier:"Interno"   },
  { id:"INS-16", name:"Instalação fachada",     unit:"hora",      cost:85.00,  stock:    0, supplier:"Interno"   },
];

// Helper to compute custo from BOM
function bomCost(items) {
  return items.reduce((s, b) => {
    const ins = INSUMOS.find(i => i.id === b.insId);
    return s + (ins ? ins.cost * b.qty : 0);
  }, 0);
}

const PROD_LIST_RAW = [
  // ── IMPRESSOS ─────────────────────────────────────────────────────────
  { id:"P-001", name:"Cartão de visita 9×5cm 4×4",    cat:"impressos",  unit:"milheiro", price: 220.00, lead:3, pop:95, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-02", qty:8},  {insId:"INS-08", qty:0.04}, {insId:"INS-09", qty:0.02}], tags:["best-seller","express"], updated:"2026-04-22", uses30:142 },
  { id:"P-002", name:"Cartão fidelidade laminado",    cat:"impressos",  unit:"milheiro", price: 380.00, lead:4, pop:48, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-14", qty:8},  {insId:"INS-08", qty:0.04}, {insId:"INS-10", qty:0.5}],  tags:[],                       updated:"2026-04-12", uses30:18  },
  { id:"P-003", name:"Folder A4 frente/verso 4×4",    cat:"impressos",  unit:"milheiro", price: 480.00, lead:4, pop:78, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-03", qty:125},{insId:"INS-08", qty:0.08}],                              tags:["popular"],              updated:"2026-04-28", uses30:62  },
  { id:"P-004", name:"Catálogo A4 lombada quadrada",  cat:"impressos",  unit:"unidade",  price:  18.00, lead:7, pop:42, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-03", qty:32}, {insId:"INS-14", qty:1},     {insId:"INS-09", qty:0.05}], tags:[],                       updated:"2026-03-30", uses30:14  },
  { id:"P-005", name:"Cardápio plastificado",         cat:"impressos",  unit:"unidade",  price:   8.50, lead:3, pop:32, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-14", qty:1},  {insId:"INS-10", qty:0.3}],                              tags:[],                       updated:"2026-04-02", uses30:48  },
  { id:"P-006", name:"Convite casamento envelope",    cat:"impressos",  unit:"unidade",  price:   6.80, lead:5, pop:8,  active:false, stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-14", qty:1.5}],                                                          tags:[],                       updated:"2025-11-18", uses30:0   },
  { id:"P-007", name:"Bloco anotação 100fls",         cat:"impressos",  unit:"unidade",  price:  14.00, lead:5, pop:24, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-03", qty:50}, {insId:"INS-14", qty:1}],                                  tags:[],                       updated:"2026-02-10", uses30:9   },
  { id:"P-008", name:"Talão de pedido 50×2 vias",     cat:"impressos",  unit:"unidade",  price:  22.00, lead:6, pop:36, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-03", qty:25}],                                                           tags:[],                       updated:"2026-03-04", uses30:12  },

  // ── COMUNICAÇÃO VISUAL ────────────────────────────────────────────────
  { id:"P-101", name:"Banner lona 440g",              cat:"comvis",     unit:"m²",       price:  38.00, lead:2, pop:88, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-05", qty:1},  {insId:"INS-08", qty:0.05}, {insId:"INS-11", qty:4}],   tags:["best-seller","express"], updated:"2026-04-30", uses30:184 },
  { id:"P-102", name:"Faixa lona com bastão",         cat:"comvis",     unit:"m²",       price:  46.00, lead:2, pop:54, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-05", qty:1},  {insId:"INS-08", qty:0.05}, {insId:"INS-11", qty:6}],   tags:["express"],              updated:"2026-04-18", uses30:38  },
  { id:"P-103", name:"Placa PVC 3mm impressa",        cat:"comvis",     unit:"m²",       price: 110.00, lead:3, pop:55, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-07", qty:1},  {insId:"INS-08", qty:0.06}, {insId:"INS-15", qty:0.3}],  tags:[],                       updated:"2026-04-25", uses30:32  },
  { id:"P-104", name:"Placa ACM fachada premium",     cat:"comvis",     unit:"m²",       price: 280.00, lead:5, pop:38, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-07", qty:1},  {insId:"INS-08", qty:0.08}, {insId:"INS-16", qty:1.5}],  tags:["alta margem"],          updated:"2026-04-08", uses30:7   },
  { id:"P-105", name:"Tapume canteiro de obras",      cat:"comvis",     unit:"m²",       price:  78.00, lead:5, pop:28, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-05", qty:1},  {insId:"INS-08", qty:0.05}, {insId:"INS-16", qty:0.5}],  tags:[],                       updated:"2026-03-22", uses30:6   },
  { id:"P-106", name:"Wind banner 2.5m completo",     cat:"comvis",     unit:"unidade",  price: 280.00, lead:4, pop:46, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-05", qty:2.5},{insId:"INS-08", qty:0.12}],                              tags:["popular"],              updated:"2026-04-15", uses30:11  },
  { id:"P-107", name:"Backdrop estrutura 3×2m",       cat:"comvis",     unit:"unidade",  price: 380.00, lead:4, pop:34, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-05", qty:6},  {insId:"INS-08", qty:0.18}, {insId:"INS-11", qty:8}],    tags:[],                       updated:"2026-03-28", uses30:5   },
  { id:"P-108", name:"Letra caixa luminosa",          cat:"comvis",     unit:"unidade",  price: 420.00, lead:8, pop:18, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-07", qty:0.4},{insId:"INS-16", qty:2}],                                  tags:["alta margem"],          updated:"2026-02-05", uses30:3   },

  // ── ADESIVOS ──────────────────────────────────────────────────────────
  { id:"P-201", name:"Adesivo vinil recortado",       cat:"adesivos",   unit:"m²",       price:  65.00, lead:3, pop:82, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-06", qty:1},  {insId:"INS-15", qty:0.4}],                              tags:["best-seller"],          updated:"2026-04-26", uses30:96  },
  { id:"P-202", name:"Envelopamento veículo passeio", cat:"adesivos",   unit:"unidade",  price:1850.00, lead:6, pop:22, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-06", qty:18}, {insId:"INS-08", qty:0.6},  {insId:"INS-16", qty:6}],     tags:["alta margem"],          updated:"2026-03-15", uses30:2   },
  { id:"P-203", name:"Adesivo perolado rótulo",       cat:"adesivos",   unit:"milheiro", price: 820.00, lead:5, pop:42, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-06", qty:6},  {insId:"INS-08", qty:0.05}, {insId:"INS-09", qty:0.04}], tags:[],                       updated:"2026-04-19", uses30:7   },
  { id:"P-204", name:"Adesivo bombas combustível",    cat:"adesivos",   unit:"jogo",     price: 380.00, lead:4, pop:36, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-06", qty:4},  {insId:"INS-08", qty:0.06}],                              tags:[],                       updated:"2026-04-11", uses30:5   },
  { id:"P-205", name:"Adesivo translúcido vitrine",   cat:"adesivos",   unit:"m²",       price:  78.00, lead:3, pop:38, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-06", qty:1},  {insId:"INS-08", qty:0.04}],                              tags:[],                       updated:"2026-04-05", uses30:14  },

  // ── EMBALAGENS ────────────────────────────────────────────────────────
  { id:"P-301", name:"Sacola kraft personalizada",    cat:"embalagens", unit:"milheiro", price: 980.00, lead:7, pop:65, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-04", qty:120},{insId:"INS-12", qty:200}, {insId:"INS-08", qty:0.3}],   tags:["popular"],              updated:"2026-04-20", uses30:14  },
  { id:"P-302", name:"Caixa pizza 35cm impressa",     cat:"embalagens", unit:"milheiro", price:1240.00, lead:8, pop:48, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-04", qty:180},{insId:"INS-08", qty:0.4}],                              tags:[],                       updated:"2026-04-08", uses30:9   },
  { id:"P-303", name:"Sacola alça fita couché",       cat:"embalagens", unit:"milheiro", price: 720.00, lead:6, pop:38, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-01", qty:140},{insId:"INS-12", qty:240}, {insId:"INS-08", qty:0.3}],   tags:[],                       updated:"2026-03-26", uses30:4   },
  { id:"P-304", name:"Etiqueta tag pendurada",        cat:"embalagens", unit:"milheiro", price: 380.00, lead:4, pop:28, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-14", qty:6},  {insId:"INS-12", qty:80},  {insId:"INS-08", qty:0.04}],   tags:[],                       updated:"2026-03-12", uses30:5   },

  // ── BRINDES ───────────────────────────────────────────────────────────
  { id:"P-401", name:"Imã geladeira 8×5cm",           cat:"brindes",    unit:"milheiro", price: 460.00, lead:5, pop:34, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-13", qty:0.5},{insId:"INS-08", qty:0.06}],                              tags:[],                       updated:"2026-04-03", uses30:6   },
  { id:"P-402", name:"Calendário parede A3",          cat:"brindes",    unit:"unidade",  price:  16.00, lead:6, pop:22, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-01", qty:7},  {insId:"INS-08", qty:0.06}],                              tags:[],                       updated:"2025-12-08", uses30:1   },
  { id:"P-403", name:"Bloco mensageiro autoaderente", cat:"brindes",    unit:"milheiro", price: 580.00, lead:6, pop:18, active:true,  stockKind:"sob demanda", stockQty:0,  bom:[{insId:"INS-03", qty:80}],                                                           tags:[],                       updated:"2025-10-30", uses30:0   },
  { id:"P-404", name:"Squeeze personalizado 500ml",   cat:"brindes",    unit:"unidade",  price:  22.00, lead:8, pop:14, active:false, stockKind:"estoque",     stockQty:42, bom:[],                                                                                    tags:["descontinuar?"],        updated:"2025-09-14", uses30:0   },
];

// Sanitize qty parens-typo + compute cost / margin / status
const PROD_LIST = PROD_LIST_RAW.map(p => {
  const fixedBom = p.bom.map(b => ({ ...b, qty: typeof b.qty === "number" ? b.qty : parseFloat(b.qty) }));
  const cost = bomCost(fixedBom);
  const margin = p.price > 0 ? (p.price - cost) / p.price : 0;
  return { ...p, bom: fixedBom, cost, margin };
});

window.PRODUTO_DATA = { PROD_CATEGORIES, INSUMOS, PROD_LIST };

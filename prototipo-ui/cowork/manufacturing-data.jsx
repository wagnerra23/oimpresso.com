// manufacturing-data.jsx — dados e cálculo do módulo Manufacturing (mock do protótipo).
// Espelha mfg_recipes / mfg_recipe_ingredients / mfg_ingredient_groups + productions
// (transactions type=production_purchase no UltimatePOS). Expõe window.MFG.
(() => {
const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (n, d = 2) => Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: d, maximumFractionDigits: d });
const fmtDate = (iso) => new Date(iso + "T12:00").toLocaleDateString("pt-BR");

// Insumos/produtos disponíveis pra montar receita (variações do catálogo).
const INSUMOS = [
  { sku: "INS-004", n: "Lona brilho 440g", u: "m²", c: 9.20, est: 420 },
  { sku: "PROD-201", n: "Lona blackout 440g", u: "m²", c: 11.20, est: 180 },
  { sku: "PROD-022", n: "Vinil adesivo brilho", u: "m²", c: 5.80, est: 310 },
  { sku: "PROD-023", n: "Vinil adesivo fosco", u: "m²", c: 6.40, est: 140 },
  { sku: "INS-022", n: "Tinta solvente CMYK", u: "L", c: 108.00, est: 22 },
  { sku: "INS-014", n: "Ilhós metálico nº 12", u: "un", c: 0.12, est: 8400 },
  { sku: "INS-031", n: "Bastão PVC 20mm", u: "un", c: 4.80, est: 260 },
  { sku: "INS-041", n: "Papel de transferência", u: "m²", c: 2.10, est: 200 },
  { sku: "INS-055", n: "Lâmina de recorte (rateio)", u: "un", c: 96.00, est: 6 },
  { sku: "INS-060", n: "Chapa ACM 3mm branco", u: "m²", c: 128.00, est: 34 },
  { sku: "INS-062", n: "Metalon 20x20", u: "m", c: 11.40, est: 96 },
  { sku: "INS-070", n: "Rebite alumínio", u: "un", c: 0.09, est: 5200 },
  { sku: "INS-081", n: "Chapa PS 2mm", u: "m²", c: 42.00, est: 48 },
  { sku: "PROD-310", n: "Camiseta algodão branca", u: "un", c: 18.90, est: 190 },
  { sku: "INS-092", n: "Filme DTF A4", u: "un", c: 2.40, est: 800 },
  { sku: "INS-093", n: "Pó adesivo DTF", u: "kg", c: 78.00, est: 4 },
  { sku: "PROD-402", n: "Caneca cerâmica branca", u: "un", c: 7.40, est: 240 },
  { sku: "INS-101", n: "Papel sublimático A4", u: "un", c: 0.38, est: 1200 },
  { sku: "INS-102", n: "Tinta sublimática", u: "L", c: 240.00, est: 3 },
  { sku: "INS-120", n: "Haste fibra 2,8m", u: "un", c: 84.00, est: 18 },
  { sku: "INS-121", n: "Base cruz metálica", u: "un", c: 62.00, est: 14 },
  { sku: "INS-122", n: "Tecido bandeira 110g", u: "m²", c: 16.80, est: 90 },
];
// Sub-unidades de compra por insumo — o consumo pode ser lançado na sub-unidade e o
// custo da linha é multiplicado pelo base_unit_multiplier (RecipeBomService::calculateCost).
const SUBUN = {
  "INS-004":  [{ u: "rolo (50 m²)", m: 50 }],
  "PROD-201": [{ u: "rolo (40 m²)", m: 40 }],
  "PROD-022": [{ u: "rolo (50 m²)", m: 50 }],
  "PROD-023": [{ u: "rolo (50 m²)", m: 50 }],
  "INS-022":  [{ u: "galão (5 L)", m: 5 }],
  "INS-014":  [{ u: "cx (500 un)", m: 500 }],
  "INS-092":  [{ u: "pacote (100 un)", m: 100 }],
  "INS-101":  [{ u: "resma (500 un)", m: 500 }],
  "INS-070":  [{ u: "cx (1000 un)", m: 1000 }],
};
const bySku = (s) => INSUMOS.find((i) => i.sku === s);
const subUnsDe = (sku) => SUBUN[sku] || [];
// custo efetivo de uma linha: quantidade × preço × multiplicador da sub-unidade escolhida
const multDe = (it) => Number(it.mult || 1);

// Grupos de ingredientes (mfg_ingredient_groups) — reusáveis entre receitas.
const GRUPOS = ["Substrato", "Tinta", "Acabamento", "Estrutura", "Aplicação", "Peça", "Estampa", "Tecido", "Mão de obra"];

const RECIPES = [
  { id: 1, name: "Banner lona 440g — acabado", sku: "MFG-0001", cat: "Comunicação visual", sub: "Banner", qtd: 10, un: "m²",
    waste: 4, extra: 18, custoTipo: "fixo", venda: 62.00, atualizado: "hoje 08:12", produto: "PROD-BAN-440",
    subUn: "m linear", subFator: 1.5,
    grupos: [
      { g: "Substrato", itens: [{ sku: "INS-004", q: 10.4 }] },
      { g: "Tinta", itens: [{ sku: "INS-022", q: 0.044, subUn: "galão (5 L)", mult: 5 }] },
      { g: "Acabamento", itens: [{ sku: "INS-014", q: 0.028, subUn: "cx (500 un)", mult: 500 }, { sku: "INS-031", q: 2 }] },
    ] },
  { id: 2, name: "Adesivo vinil recortado", sku: "MFG-0002", cat: "Comunicação visual", sub: "Adesivo", qtd: 5, un: "m²",
    waste: 12, extra: 8, custoTipo: "percentual", venda: 78.00, atualizado: "hoje 08:12", produto: "PROD-ADE-REC",
    grupos: [
      { g: "Substrato", itens: [{ sku: "PROD-022", q: 5.6 }] },
      { g: "Tinta", itens: [{ sku: "INS-022", q: 0.14 }] },
      { g: "Acabamento", itens: [{ sku: "INS-041", q: 5.6 }, { sku: "INS-055", q: 0.05 }] },
    ] },
  { id: 3, name: "Fachada ACM 3mm — módulo 1m²", sku: "MFG-0003", cat: "Comunicação visual", sub: "Fachada", qtd: 1, un: "m²",
    waste: 8, extra: 45, custoTipo: "fixo", venda: 480.00, atualizado: "ontem 17:40", produto: "PROD-ACM-MOD",
    grupos: [
      { g: "Estrutura", itens: [{ sku: "INS-060", q: 1.1 }, { sku: "INS-062", q: 3.2 }] },
      { g: "Aplicação", itens: [{ sku: "PROD-022", q: 1.1 }, { sku: "INS-070", q: 18 }] },
    ] },
  { id: 4, name: "Placa PS 2mm A3", sku: "MFG-0004", cat: "Comunicação visual", sub: "Placa", qtd: 20, un: "un",
    waste: 3, extra: 0, custoTipo: "fixo", venda: 24.00, atualizado: "hoje 08:12", produto: "PROD-PLA-A3",
    subUn: "cx (10 un)", subFator: 0.1,
    grupos: [
      { g: "Substrato", itens: [{ sku: "INS-081", q: 3.1 }] },
      { g: "Aplicação", itens: [{ sku: "PROD-023", q: 3.1 }] },
    ] },
  { id: 5, name: "Camiseta DTF — estampa A4", sku: "MFG-0005", cat: "Têxtil", sub: "Camiseta", qtd: 1, un: "un",
    waste: 6, extra: 12, custoTipo: "percentual", venda: 59.90, atualizado: "hoje 07:55", produto: "PROD-CAM-DTF",
    grupos: [
      { g: "Peça", itens: [{ sku: "PROD-310", q: 1 }] },
      { g: "Estampa", itens: [{ sku: "INS-092", q: 1 }, { sku: "INS-093", q: 0.012 }] },
    ] },
  { id: 6, name: "Caneca sublimada 325ml", sku: "MFG-0006", cat: "Brindes", sub: "Caneca", qtd: 1, un: "un",
    waste: 5, extra: 1.8, custoTipo: "unidade", venda: 34.90, atualizado: "12/08 16:20", produto: "PROD-CAN-325",
    grupos: [
      { g: "Peça", itens: [{ sku: "PROD-402", q: 1 }] },
      { g: "Estampa", itens: [{ sku: "INS-101", q: 1 }, { sku: "INS-102", q: 0.004 }] },
    ] },
  { id: 7, name: "Lona blackout 2 faces 3x1m", sku: "MFG-0007", cat: "Comunicação visual", sub: "Banner", qtd: 3, un: "m²",
    waste: 4, extra: 22, custoTipo: "fixo", venda: 96.00, atualizado: "hoje 08:12", produto: "PROD-BAN-BLK",
    grupos: [
      { g: "Substrato", itens: [{ sku: "PROD-201", q: 3.2 }] },
      { g: "Tinta", itens: [{ sku: "INS-022", q: 0.13 }] },
      { g: "Acabamento", itens: [{ sku: "INS-014", q: 10 }] },
    ] },
  { id: 8, name: "Wind banner 2,8m — kit", sku: "MFG-0008", cat: "Comunicação visual", sub: "Estrutura", qtd: 1, un: "un",
    waste: 0, extra: 0, custoTipo: "fixo", venda: 320.00, atualizado: "05/08 11:02", produto: "PROD-WND-28",
    grupos: [
      { g: "Estrutura", itens: [{ sku: "INS-120", q: 1 }, { sku: "INS-121", q: 1 }] },
      { g: "Tecido", itens: [{ sku: "INS-122", q: 2.4 }] },
    ] },
];

const LOCAIS = ["Matriz", "Loja Centro", "Galpão produção"];

// Ordens de produção (mfg production). consumo = override por ingrediente quando editado.
const PRODUCOES = [
  { id: 101, ref: "MFG2026/0042", data: "2026-08-19", local: "Galpão produção", recipe: 1, qtd: 40, final: true,
    consumo: null, obs: "Lote fachada Rota Livre — 4 banners 3x2m", por: "Larissa", custoSnap: 486.32 },
  { id: 102, ref: "MFG2026/0041", data: "2026-08-18", local: "Matriz", recipe: 2, qtd: 15, final: true,
    consumo: { "INS-055": 0.2 }, obs: "Troca de lâmina no meio do lote", por: "Wagner", custoSnap: 214.90 },
  { id: 103, ref: "MFG2026/0040", data: "2026-08-18", local: "Galpão produção", recipe: 3, qtd: 6, final: true,
    consumo: null, obs: "", por: "Wagner", custoSnap: 1288.44 },
  { id: 104, ref: "MFG2026/0039", data: "2026-08-17", local: "Matriz", recipe: 5, qtd: 30, final: true,
    consumo: null, obs: "Pedido evento Martinho", por: "Larissa", custoSnap: 738.60 },
  { id: 105, ref: "MFG2026/0038", data: "2026-08-20", local: "Matriz", recipe: 6, qtd: 24, final: false,
    consumo: null, obs: "Rascunho — aguardando caneca chegar", por: "Larissa" },
  { id: 106, ref: "MFG2026/0037", data: "2026-08-14", local: "Loja Centro", recipe: 4, qtd: 100, final: true,
    consumo: null, obs: "", por: "Eliana", custoSnap: 705.10 },
];

const SETTINGS = { prefix: "MFG2026/", travarQtd: false, permitirPreco: true, versao: "6.2.1" };

// ── Cálculo (espelha ManufacturingUtil::getRecipeTotal) ──
function custos(r) {
  const ing = r.grupos.reduce((s, g) => s + g.itens.reduce((a, i) => {
    const p = bySku(i.sku);
    return a + i.q * (p ? p.c : 0) * multDe(i);
  }, 0), 0);
  // production_cost_type do legacy: fixed | percentage | per_unit (RecipeBomService::calculateCost)
  const extra = r.custoTipo === "percentual" ? ing * (Number(r.extra) / 100)
    : r.custoTipo === "unidade" ? Number(r.extra || 0) * Number(r.qtd || 0)
    : Number(r.extra || 0);
  const total = ing + extra;
  const qtdLiq = r.qtd - r.qtd * (r.waste / 100);
  const unit = r.qtd > 0 ? total / r.qtd : 0;
  const margem = r.venda > 0 ? (r.venda - unit) / r.venda * 100 : 0;
  return { ing, extra, total, qtdLiq, unit, margem };
}

// Consumo de uma OP: proporcional à qtd produzida vs qtd da receita (+ overrides).
function consumoOP(op, recipes) {
  const r = recipes.find((x) => x.id === op.recipe);
  if (!r) return { linhas: [], total: 0, r: null };
  const fator = r.qtd > 0 ? op.qtd / r.qtd : 0;
  const linhas = [];
  r.grupos.forEach((g) => g.itens.forEach((i) => {
    const p = bySku(i.sku);
    const q = op.consumo && op.consumo[i.sku] != null ? op.consumo[i.sku] : i.q * fator;
    const m = multDe(i);
    linhas.push({ sku: i.sku, n: p ? p.n : i.sku, u: i.subUn || (p ? p.u : ""), base: p ? p.u : "", mult: m,
      c: p ? p.c : 0, q, qBase: q * m, sub: q * (p ? p.c : 0) * m, grupo: g.g, est: p ? (m > 1 ? p.est / m : p.est) : 0 });
  }));
  const c = custos(r);
  const extra = r.custoTipo === "percentual" ? linhas.reduce((s, l) => s + l.sub, 0) * (r.extra / 100)
    : r.custoTipo === "unidade" ? r.extra * op.qtd
    : r.extra * fator;
  const vivo = linhas.reduce((s, l) => s + l.sub, 0) + extra;
  const congelado = op.final && op.custoSnap != null ? op.custoSnap : null;
  const total = congelado != null ? congelado : vivo;
  return { linhas, extra, total, vivo, congelado, unit: op.qtd > 0 ? total / op.qtd : 0, r, custoRef: c };
}

// Impacto reverso: receitas que consomem um insumo + quanto o custo unitário sobe
// se o preço do insumo variar X%.
function usosDoInsumo(sku, recipes, variacaoPct) {
  const p = bySku(sku);
  if (!p) return [];
  return recipes.map((r) => {
    let qtd = 0;
    r.grupos.forEach((g) => g.itens.forEach((i) => { if (i.sku === sku) qtd += i.q * multDe(i); }));
    if (!qtd) return null;
    const atual = custos(r);
    const simulado = custos({ ...r, grupos: r.grupos });
    const delta = qtd * p.c * (variacaoPct / 100);
    const unitNovo = r.qtd > 0 ? (simulado.total + delta) / r.qtd : 0;
    return { r, qtd, base: p.u, peso: atual.total > 0 ? qtd * p.c / atual.total * 100 : 0,
      unitAtual: atual.unit, unitNovo, margemNova: r.venda > 0 ? (r.venda - unitNovo) / r.venda * 100 : 0 };
  }).filter(Boolean).sort((a, b) => b.peso - a.peso);
}

window.MFG = { INSUMOS, SUBUN, GRUPOS, RECIPES, LOCAIS, PRODUCOES, SETTINGS, bySku, subUnsDe, multDe, custos, consumoOP, usosDoInsumo, fmt, num, fmtDate };
})();

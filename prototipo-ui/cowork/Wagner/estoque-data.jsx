// estoque-data.jsx — domínio do módulo Estoque (movimentações).
// Fonte lida no main 2026-08-22: Pages/StockAdjustment/{Index,Create}.tsx,
// Pages/StockTransfer/{Index,Create}.tsx (+ charters) e Pages/Estoque/Movimentacao.casos.md
// (UC-EST-01..08 · INV-2/3/5/6) → DOC-RAIZ-ESTOQUE §3 matriz + §7 invariantes.
// Regras espelhadas: R-ADJ-002 (tipo), R-ADJ-003 (recuperado ≤ total), R-ADJ-004/R-XFER-002
// (ownership view_own_purchase), R-XFER-003/004/005 (status, origem≠destino, completed move).
// IIFE — expõe window.EstData. Sem UI aqui.
(() => {
const HOJE = "2026-05-08";

const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtQtd = (n) => Number(n || 0).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
const fmtData = (d) => d ? new Date(d + "T00:00").toLocaleDateString("pt-BR") : "—";
const dias = (a, b) => Math.round((new Date(a + "T00:00") - new Date(b + "T00:00")) / 86400000);
const iso = (d) => d instanceof Date ? [d.getFullYear(), String(d.getMonth() + 1).padStart(2, "0"), String(d.getDate()).padStart(2, "0")].join("-") : d || "";

// ─── Locais (business_locations) ───
const LOCAIS = {
  matriz:   { l: "Matriz",      end: "Rua Industrial, 200 · Centro", cidade: "São Paulo · SP", cep: "04123-000", tel: "(11) 3344-1200", cnpj: "12.345.678/0001-90" },
  centro:   { l: "Loja Centro", end: "Av. São João, 918 · República", cidade: "São Paulo · SP", cep: "01035-000", tel: "(11) 3344-1288", cnpj: "12.345.678/0002-70" },
  deposito: { l: "Depósito",    end: "Rod. Anhanguera km 22 · Perus", cidade: "São Paulo · SP", cep: "05201-000", tel: "(11) 3344-1290", cnpj: "12.345.678/0003-51" },
};

// ─── Produtos ───
// `enable_stock: 0` = produto sem controle de estoque (INV-5: mutador não toca o saldo).
// `reserv` = quantidade reservada em venda/OS aberta (INV-4: reserva ≠ baixa).
const PRODUTOS = [
  { sku: "PROD-200", nome: "Lona 380gr brilho", un: "m²", custo: 7.50, venda: 55, enable_stock: 1,
    reserv: { matriz: 60, centro: 0, deposito: 300 },
    lotes: [
      { lote: "LL-330", val: "2026-04-28", local: "matriz",   qtd: 36 },
      { lote: "LL-355", val: "2026-12-10", local: "matriz",   qtd: 384 },
      { lote: "LL-356", val: "2026-11-01", local: "centro",   qtd: 96 },
      { lote: "LL-360", val: "2027-02-01", local: "deposito", qtd: 1180 },
    ] },
  { sku: "PROD-201", nome: "Lona blackout 440gr", un: "m²", custo: 11.20, venda: 75, enable_stock: 1,
    reserv: { matriz: 24, centro: 0, deposito: 0 }, est: { matriz: 168, centro: 24, deposito: 640 } },
  { sku: "PROD-022", nome: "Vinil adesivo brilho", un: "m²", custo: 5.80, venda: 42, enable_stock: 1,
    reserv: { matriz: 120, centro: 40, deposito: 200 },
    lotes: [
      { lote: "LV-118", val: "2026-05-22", local: "centro",   qtd: 210 },
      { lote: "LV-140", val: "2027-01-15", local: "matriz",   qtd: 862 },
      { lote: "LV-141", val: "2027-03-01", local: "deposito", qtd: 1540 },
    ] },
  { sku: "PROD-310", nome: "Placa ACM 3mm branco", un: "m²", custo: 38.90, venda: 180, enable_stock: 1,
    reserv: { matriz: 12, centro: 0, deposito: 24 }, est: { matriz: 74, centro: 12, deposito: 260 } },
  { sku: "INS-014", nome: "Ilhós metálico nº 12", un: "un", custo: 0.12, venda: 0.45, enable_stock: 1,
    reserv: { matriz: 0, centro: 0, deposito: 0 }, est: { matriz: 8400, centro: 1200, deposito: 22000 } },
  { sku: "INS-022", nome: "Tinta solvente CMYK 5L", un: "un", custo: 540.00, venda: 780, enable_stock: 1,
    reserv: { matriz: 2, centro: 0, deposito: 0 },
    lotes: [
      { lote: "L-2851", val: "2026-04-20", local: "deposito", qtd: 2 },
      { lote: "L-2860", val: "2026-06-05", local: "matriz",   qtd: 9 },
      { lote: "L-2902", val: "2026-09-30", local: "deposito", qtd: 24 },
    ] },
  // INV-5 — sem controle de estoque: não pode entrar em ajuste nem transferência.
  { sku: "INS-030", nome: "Fita dupla-face 3M (sem controle)", un: "un", custo: 18.90, venda: 39, enable_stock: 0,
    reserv: { matriz: 0, centro: 0, deposito: 0 }, est: { matriz: 0, centro: 0, deposito: 0 } },
];
const acharProd = (sku) => PRODUTOS.find((p) => p.sku === sku);
// Saldo físico: soma dos lotes quando o produto é controlado por lote.
const saldo = (p, local) => !local ? 0 : p.lotes ? p.lotes.filter((l) => l.local === local).reduce((s, l) => s + l.qtd, 0) : (p.est[local] || 0);
const reservado = (p, local) => !local ? 0 : ((p.reserv || {})[local] || 0);
const disponivel = (p, local) => Math.max(0, saldo(p, local) - reservado(p, local));
const lotesDo = (p, local) => p.lotes ? p.lotes.filter((l) => !local || l.local === local) : [];

// ─── Vencimentos (report/stock_expiry_report) ───
function vencimentos(janela = 30) {
  const out = [];
  for (const p of PRODUTOS) {
    for (const l of (p.lotes || [])) {
      const d = dias(l.val, HOJE);
      if (d <= janela) out.push({ sku: p.sku, nome: p.nome, un: p.un, custo: p.custo, ...l, dias: d, estado: d < 0 ? "vencido" : "vencendo" });
    }
  }
  return out.sort((a, b) => a.dias - b.dias);
}

// ─── Ajustes (R-ADJ-002: normal | abnormal) ───
const TIPOS = { normal: "Normal", abnormal: "Anormal" };
const AJUSTES = [
  { id: "AJ-0142", ref: "AJ2026/0142", data: "2026-05-08", local: "matriz", tipo: "abnormal", recuperado: 0, por: "Wagner",
    motivo: "Lona riscada na descarga — 3 bobinas inutilizadas.",
    itens: [{ sku: "PROD-200", qtd: 36, lote: "LL-330" }, { sku: "PROD-201", qtd: 12 }] },
  { id: "AJ-0141", ref: "AJ2026/0141", data: "2026-05-07", local: "centro", tipo: "normal", recuperado: 0, por: "Larissa",
    motivo: "Recontagem do balcão — sobra de vinil não lançada.", contagem: "CT-0008",
    itens: [{ sku: "PROD-022", qtd: 18, lote: "LV-118" }] },
  { id: "AJ-0140", ref: "AJ2026/0140", data: "2026-05-06", local: "deposito", tipo: "abnormal", recuperado: 180, por: "Bruna",
    motivo: "Tinta vencida — 2 galões descartados, embalagem devolvida ao fornecedor.",
    itens: [{ sku: "INS-022", qtd: 2, lote: "L-2851" }] },
  { id: "AJ-0139", ref: "AJ2026/0139", data: "2026-05-05", local: "matriz", tipo: "normal", recuperado: 0, por: "Wagner",
    motivo: "Perda de corte no plotter — refile do mês.",
    itens: [{ sku: "PROD-022", qtd: 42, lote: "LV-140" }, { sku: "PROD-200", qtd: 15, lote: "LL-355" }] },
  { id: "AJ-0138", ref: "AJ2026/0138", data: "2026-05-04", local: "matriz", tipo: "abnormal", recuperado: 0, por: "Bruna",
    motivo: "Placa ACM amassada no transporte interno.",
    itens: [{ sku: "PROD-310", qtd: 6 }] },
  { id: "AJ-0137", ref: "AJ2026/0137", data: "2026-04-30", local: "deposito", tipo: "normal", recuperado: 0, por: "Wagner",
    motivo: "Fechamento de abril — ajuste de contagem cíclica.", contagem: "CT-0007",
    itens: [{ sku: "INS-014", qtd: 850 }] },
];

// ─── Transferências ───
// 4 status como no vivo (StockTransfer/Index.tsx): pending · in_transit · completed · final.
// R-XFER-005: só completed/final movem saldo (INV-2 — status terminal).
const STATUS_TRF = {
  pending:    { l: "Pendente",    tone: "neutral", move: false, efeito: "Reserva na origem. Não move saldo." },
  in_transit: { l: "Em trânsito", tone: "warning", move: false, efeito: "Reserva na origem. Não move saldo." },
  completed:  { l: "Concluída",   tone: "success", move: true,  efeito: "Baixa na origem e entrada no destino, na hora." },
  final:      { l: "Finalizada",  tone: "success", move: true,  efeito: "Terminal e conferida no destino — não edita mais." },
};
const TRANSF = [
  { id: "TRF-0088", ref: "TR2026/0088", data: "2026-05-08", de: "deposito", para: "matriz", status: "in_transit", frete: 180, por: "Bruna",
    obs: "Carga sai às 14h — motorista Jorge.",
    itens: [{ sku: "PROD-200", qtd: 300, lote: "LL-360" }, { sku: "PROD-022", qtd: 200, lote: "LV-141" }] },
  { id: "TRF-0087", ref: "TR2026/0087", data: "2026-05-07", de: "matriz", para: "centro", status: "final", frete: 0, por: "Larissa",
    obs: "Reposição do balcão.",
    itens: [{ sku: "PROD-022", qtd: 80, lote: "LV-140" }, { sku: "INS-014", qtd: 1200 }] },
  { id: "TRF-0086", ref: "TR2026/0086", data: "2026-05-06", de: "deposito", para: "centro", status: "pending", frete: 120, por: "Wagner",
    obs: "Aguardando conferência de saída no depósito.",
    itens: [{ sku: "PROD-310", qtd: 24 }] },
  { id: "TRF-0085", ref: "TR2026/0085", data: "2026-05-05", de: "deposito", para: "matriz", status: "completed", frete: 180, por: "Bruna",
    obs: "",
    itens: [{ sku: "INS-022", qtd: 6, lote: "L-2902" }, { sku: "PROD-201", qtd: 120 }] },
  { id: "TRF-0084", ref: "TR2026/0084", data: "2026-05-02", de: "matriz", para: "deposito", status: "final", frete: 0, por: "Wagner",
    obs: "Devolução de sobra de obra.",
    itens: [{ sku: "PROD-200", qtd: 45, lote: "LL-355" }] },
];

// ─── Contagem cíclica (ESCOPO NOVO — não existe no Blade nem no React vivo) ───
const STATUS_CT = {
  aberta:   { l: "Aberta",   tone: "neutral" },
  contando: { l: "Contando", tone: "warning" },
  fechada:  { l: "Fechada",  tone: "success" },
};
const CONTAGENS = [
  { id: "CT-0009", data: "2026-05-08", local: "matriz", status: "contando", por: "Bruna", ajuste: null,
    itens: [
      { sku: "PROD-200", lote: "LL-355", sistema: 384, contado: 372 },
      { sku: "PROD-022", lote: "LV-140", sistema: 862, contado: 862 },
      { sku: "PROD-310", sistema: 74, contado: 71 },
      { sku: "INS-022", lote: "L-2860", sistema: 9, contado: null },
    ] },
  { id: "CT-0008", data: "2026-05-07", local: "centro", status: "fechada", por: "Larissa", ajuste: "AJ-0141",
    itens: [{ sku: "PROD-022", lote: "LV-118", sistema: 192, contado: 210 }] },
  { id: "CT-0007", data: "2026-04-30", local: "deposito", status: "fechada", por: "Wagner", ajuste: "AJ-0137",
    itens: [{ sku: "INS-014", sistema: 22850, contado: 22000 }] },
];
const divergencias = (c) => c.itens.filter((i) => i.contado != null && i.contado !== i.sistema);
const valorDiverg = (c) => divergencias(c).reduce((s, i) => { const p = acharProd(i.sku); return s + Math.abs(i.contado - i.sistema) * (p ? p.custo : 0); }, 0);

const totalItens = (itens) => itens.reduce((s, i) => { const p = acharProd(i.sku); return s + (p ? p.custo * i.qtd : 0); }, 0);

// ─── Permissões (view_purchase_price · view_own_purchase · escopo por local) ───
const PAPEIS = {
  gestor:     { l: "Gestor (Wagner)",       quem: "Wagner",  preco: true,  criar: true,  editar: true,  excluir: true,  status: true,  own: false, locais: null },
  deposito:   { l: "Depósito (conferente)", quem: "Bruna",   preco: false, criar: true,  editar: true,  excluir: false, status: true,  own: false, locais: ["deposito", "matriz"] },
  balcao:     { l: "Balcão (Larissa)",      quem: "Larissa", preco: false, criar: true,  editar: false, excluir: false, status: false, own: true,  locais: ["centro"] },
  financeiro: { l: "Financeiro (Eliana)",   quem: "Eliana",  preco: true,  criar: false, editar: false, excluir: false, status: false, own: false, locais: null },
};
const papel = (k) => PAPEIS[k] || PAPEIS.gestor;
const can = (k, perm) => !!papel(k)[perm];
const locaisDe = (k) => papel(k).locais || Object.keys(LOCAIS);
const podeVerLocal = (k, loc) => locaisDe(k).indexOf(loc) >= 0;
const meu = (k, reg) => !papel(k).own || reg.por === papel(k).quem;
const ajusteVisivel = (k, a) => podeVerLocal(k, a.local) && meu(k, a);
const transfVisivel = (k, t) => (podeVerLocal(k, t.de) || podeVerLocal(k, t.para)) && meu(k, t);
const contagemVisivel = (k, c) => podeVerLocal(k, c.local) && meu(k, c);

// ─── Export (CSV / Excel) ───
function baixar(nome, texto, mime) {
  try {
    const blob = new Blob(["\ufeff" + texto], { type: (mime || "text/csv") + ";charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    return true;
  } catch (e) { return false; }
}
const csv = (cabecalho, linhas, sep) => [cabecalho, ...linhas].map((l) => l.map((c) => {
  const s = String(c == null ? "" : c);
  return /[";\n\t]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}).join(sep || ";")).join("\n");

// Filtro de período (De/Até) — o vivo filtra server-side; aqui é o mesmo recorte.
const noPeriodo = (data, per) => {
  if (!per) return true;
  const de = iso(per.from), ate = iso(per.to);
  if (de && data < de) return false;
  if (ate && data > ate) return false;
  return true;
};

window.EstData = {
  HOJE, fmt, fmtQtd, fmtData, dias, iso, noPeriodo,
  LOCAIS, PRODUTOS, acharProd, saldo, reservado, disponivel, lotesDo, vencimentos,
  TIPOS, AJUSTES, STATUS_TRF, TRANSF, totalItens,
  STATUS_CT, CONTAGENS, divergencias, valorDiverg,
  PAPEIS, papel, can, locaisDe, podeVerLocal, ajusteVisivel, transfVisivel, contagemVisivel,
  baixar, csv,
};
})();

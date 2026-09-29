// ────────────────────────────────────────────────
// FINANCEIRO — domínio de dados (Caixa + A Receber + A Pagar)
// Modelo: um único conjunto de LANÇAMENTOS (títulos financeiros).
//   tipo: "receber" | "pagar"
//   status: "aberto" (a vencer) | "vencido" | "liquidado" (recebido/pago = caixa)
//   vencDias: dias até o vencimento a partir do "hoje" do protótipo (neg = passado)
// Liquidado = entrou/saiu do caixa (Recebidas/Pagas). Em aberto/vencido = previsão.
// ────────────────────────────────────────────────

// Contas / carteiras
const FIN_CONTAS = [
  { id: "cc",       nome: "Conta Corrente",   banco: "Bradesco Ag. 2210", saldo: 18420.50, ic: "card" },
  { id: "pix",      nome: "PIX · Inter",       banco: "Banco Inter",       saldo: 7235.00,  ic: "qr" },
  { id: "cielo",    nome: "Cielo (cartões)",   banco: "Recebíveis cartão", saldo: 3110.00,  ic: "card" },
  { id: "dinheiro", nome: "Dinheiro (gaveta)", banco: "Espécie",           saldo: 840.00,   ic: "dollar" },
];

// Categorias para DRE / classificação
const FIN_CATEGORIAS = {
  receita: ["Venda de impressos", "Sinalização", "Serviços de oficina", "Peças", "Outras receitas"],
  custo:   ["Insumos gráficos", "Peças (revenda)", "Terceirização"],
  despesa: ["Folha de pagamento", "Aluguel", "Energia / Água", "Combustível", "Impostos", "Frete", "Internet / Telefonia", "Marketing", "Outras despesas"],
};
const FIN_GRUPO = {}; // categoria -> grupo (receita/custo/despesa)
Object.keys(FIN_CATEGORIAS).forEach(g => FIN_CATEGORIAS[g].forEach(c => { FIN_GRUPO[c] = g; }));

// Lançamentos
const FIN_LANC = [
  // ── A RECEBER (em aberto / vencido) ──
  { id: "R-3041", tipo: "receber", desc: "1.000 cartões 9x5 4/4", parte: "Marília Costa",            parteId: "c1",  categoria: "Venda de impressos",  origem: "OS-3041", valor: 248,  vencDias: -1, vencLabel: "Ontem",     status: "vencido", meio: "Boleto" },
  { id: "R-2036", tipo: "receber", desc: "Freios + pneus (semirreboque)", parte: "Sul Cargas Transportes", parteId: "c12", categoria: "Serviços de oficina", origem: "MAN-2036", valor: 1840, vencDias: -5, vencLabel: "5 dias atrás", status: "vencido", meio: "Boleto" },
  { id: "R-2035", tipo: "receber", desc: "Revisão de freios (Cargo 1719)", parte: "Distribuidora Vale Verde", parteId: "c13", categoria: "Serviços de oficina", origem: "MAN-2035", valor: 940,  vencDias: -2, vencLabel: "2 dias atrás", status: "vencido", meio: "PIX" },
  { id: "R-3043", tipo: "receber", desc: "Cardápio QR de mesa",   parte: "Restaurante 88",          parteId: "c2",  categoria: "Venda de impressos",  origem: "OS-3043", valor: 360,  vencDias: 2,  vencLabel: "Em 2 dias",  status: "aberto",  meio: "PIX" },
  { id: "R-3033", tipo: "receber", desc: "200 receituários A5",   parte: "Clínica Vita",            parteId: "c3",  categoria: "Venda de impressos",  origem: "OS-3033", valor: 612,  vencDias: 12, vencLabel: "Em 12 dias", status: "aberto",  meio: "Boleto" },
  { id: "R-2041", tipo: "receber", desc: "Injeção + filtros (Volvo FH)", parte: "Transportes Andorinha", parteId: "c11", categoria: "Serviços de oficina", origem: "MAN-2041", valor: 1280, vencDias: 1,  vencLabel: "Amanhã",    status: "aberto",  meio: "Boleto" },
  { id: "R-2040", tipo: "receber", desc: "Revisão programada (Scania R450)", parte: "Sul Cargas Transportes", parteId: "c12", categoria: "Serviços de oficina", origem: "MAN-2040", valor: 1480, vencDias: 18, vencLabel: "Em 18 dias", status: "aberto",  meio: "Boleto" },

  // ── RECEBIDAS (caixa, entrada) ──
  { id: "R-3035", tipo: "receber", desc: "Banner 3x1m vinil",     parte: "Restaurante 88",          parteId: "c2",  categoria: "Sinalização",         origem: "OS-3035", valor: 480,  vencDias: 0,  vencLabel: "Hoje",      status: "liquidado", liqLabel: "Hoje 09:32",  conta: "pix",      meio: "PIX" },
  { id: "R-3025", tipo: "receber", desc: "Etiqueta térmica 5cm",  parte: "Açougue Premium",         parteId: "c6",  categoria: "Venda de impressos",  origem: "OS-3025", valor: 92,   vencDias: -1, vencLabel: "Ontem",     status: "liquidado", liqLabel: "Ontem 16:50", conta: "cielo",    meio: "Crédito" },
  { id: "R-3029", tipo: "receber", desc: "Adesivos vinil 5x5",    parte: "Studio Verde Lima",       parteId: "c4",  categoria: "Adesivos",            origem: "OS-3029", valor: 180,  vencDias: -3, vencLabel: "Sex 11:22", status: "liquidado", liqLabel: "Sex 11:22",   conta: "pix",      meio: "PIX" },
  { id: "R-mens2", tipo: "receber", desc: "Pacote mensal — material gráfico", parte: "Restaurante 88", parteId: "c2", categoria: "Venda de impressos", origem: "Contrato", valor: 4380, vencDias: -2, vencLabel: "2 dias atrás", status: "liquidado", liqLabel: "Qui 10:10", conta: "cc",       meio: "Transferência" },
  { id: "R-frota3", tipo: "receber", desc: "Manutenção de frota (mês)", parte: "Distribuidora Vale Verde", parteId: "c13", categoria: "Serviços de oficina", origem: "Contrato", valor: 1860, vencDias: -4, vencLabel: "4 dias atrás", status: "liquidado", liqLabel: "Qua 14:20", conta: "cc",      meio: "Boleto" },

  // ── A PAGAR (em aberto / vencido) ──
  { id: "P-alug",  tipo: "pagar", desc: "Aluguel do galpão",      parte: "Imobiliária Centro",      parteId: null,  categoria: "Aluguel",             origem: "Recorrente", valor: 3800, vencDias: 4,  vencLabel: "Em 4 dias",  status: "aberto",  meio: "Boleto" },
  { id: "P-ener",  tipo: "pagar", desc: "Energia elétrica",       parte: "Enel SP",                 parteId: null,  categoria: "Energia / Água",      origem: "Recorrente", valor: 1180, vencDias: -1, vencLabel: "Ontem",     status: "vencido", meio: "Boleto" },
  { id: "P-das",   tipo: "pagar", desc: "Simples Nacional (DAS)", parte: "Receita Federal",         parteId: null,  categoria: "Impostos",            origem: "Imposto",    valor: 2240, vencDias: 6,  vencLabel: "Em 6 dias",  status: "aberto",  meio: "Guia" },
  { id: "P-pecas", tipo: "pagar", desc: "Reposição de peças",     parte: "Auto Peças Diesel SP",    parteId: null,  categoria: "Peças (revenda)",     origem: "Compra",     valor: 2100, vencDias: 3,  vencLabel: "Em 3 dias",  status: "aberto",  meio: "Boleto" },
  { id: "P-net",   tipo: "pagar", desc: "Internet + telefonia",   parte: "Vivo Empresas",           parteId: null,  categoria: "Internet / Telefonia", origem: "Recorrente", valor: 290, vencDias: 8,  vencLabel: "Em 8 dias",  status: "aberto",  meio: "Débito" },
  { id: "P-frete", tipo: "pagar", desc: "Frete de entregas",      parte: "Transportadora Veloz",    parteId: "c9",  categoria: "Frete",               origem: "Serviço",    valor: 540,  vencDias: -3, vencLabel: "3 dias atrás", status: "vencido", meio: "PIX" },

  // ── PAGAS (caixa, saída) ──
  { id: "P-suz",   tipo: "pagar", desc: "Insumos gráficos (papel/tinta)", parte: "Suzano Papéis S.A.", parteId: "c8", categoria: "Insumos gráficos", origem: "Compra",     valor: 1290, vencDias: 0,  vencLabel: "Hoje",      status: "liquidado", liqLabel: "Hoje 08:14",  conta: "cc",       meio: "Boleto" },
  { id: "P-folha", tipo: "pagar", desc: "Folha — quinzena",       parte: "Folha de pagamento",      parteId: null,  categoria: "Folha de pagamento",  origem: "Pessoal",    valor: 4280, vencDias: -3, vencLabel: "Sex 09:00", status: "liquidado", liqLabel: "Sex 09:00",   conta: "cc",       meio: "TED" },
  { id: "P-comb",  tipo: "pagar", desc: "Combustível — frota",    parte: "Posto Rede Sol",          parteId: null,  categoria: "Combustível",         origem: "Despesa op.", valor: 320, vencDias: -1, vencLabel: "Ontem 12:10", status: "liquidado", liqLabel: "Ontem 12:10", conta: "dinheiro", meio: "Débito" },
];

// ─── Helpers ───
const _finSum = (arr) => arr.reduce((s, l) => s + l.valor, 0);
window.MOCK.FIN_CONTAS = FIN_CONTAS;
window.MOCK.FIN_CATEGORIAS = FIN_CATEGORIAS;
window.MOCK.FIN_GRUPO = FIN_GRUPO;
window.MOCK.FIN_LANC = FIN_LANC;
window.MOCK.finSum = _finSum;

window.MOCK.finResumo = (LIST) => {
  const L = LIST || FIN_LANC;
  const aReceber = L.filter(l => l.tipo === "receber" && l.status !== "liquidado");
  const aPagar   = L.filter(l => l.tipo === "pagar"   && l.status !== "liquidado");
  const recebido = L.filter(l => l.tipo === "receber" && l.status === "liquidado");
  const pago     = L.filter(l => l.tipo === "pagar"   && l.status === "liquidado");
  const saldoContas = FIN_CONTAS.reduce((s, c) => s + c.saldo, 0);
  const r = {
    saldoContas,
    aReceber: _finSum(aReceber),
    aReceberVenc: _finSum(aReceber.filter(l => l.status === "vencido")),
    aPagar: _finSum(aPagar),
    aPagarVenc: _finSum(aPagar.filter(l => l.status === "vencido")),
    recebidoMes: _finSum(recebido),
    pagoMes: _finSum(pago),
    counts: { aReceber: aReceber.length, aPagar: aPagar.length, recebido: recebido.length, pago: pago.length,
              aReceberVenc: aReceber.filter(l => l.status === "vencido").length, aPagarVenc: aPagar.filter(l => l.status === "vencido").length },
  };
  r.resultadoMes = r.recebidoMes - r.pagoMes;
  r.saldoProjetado = saldoContas + r.aReceber - r.aPagar;
  return r;
};

// Mantém o painel Início coerente com o financeiro — FONTE ÚNICA DE VERDADE.
// Os números de dinheiro do dashboard são DERIVADOS do ledger (finResumo),
// nunca literais escritos à mão — assim Início e Financeiro jamais divergem.
(() => {
  const r = window.MOCK.finResumo();
  if (window.MOCK.OPS_KPIS) {
    window.MOCK.OPS_KPIS.contas_a_receber = r.aReceber;
    window.MOCK.OPS_KPIS.contas_a_pagar = r.aPagar;
  }
  const D = window.MOCK.HOME_DASH;
  const B = window.BRL || (n => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  if (!D) return;

  // Tile de recebíveis: total a receber + destaque de vencidos (antes "Receber hoje" fixo)
  const tileReceber = D.tiles.find(t => /receber/i.test(t.label));
  if (tileReceber) {
    tileReceber.label = "A receber";
    tileReceber.value = B(r.aReceber);
    tileReceber.sub = r.aReceberVenc > 0 ? B(r.aReceberVenc) + " vencidos" : r.counts.aReceber + " em aberto";
    tileReceber.subTone = r.aReceberVenc > 0 ? "danger" : "mute";
    tileReceber.tone = r.aReceberVenc > 0 ? "danger" : "ok";
  }

  // Par de sparkcards: fluxo do mês (entradas × saídas) — bate com o Resumo do Financeiro
  if (D.spark && D.spark.length >= 2) {
    D.spark[0] = { ...D.spark[0], label: "Recebido no mês", value: B(r.recebidoMes),
                   sub: r.counts.recebido + " recebimentos", tone: "ok" };
    D.spark[1] = { ...D.spark[1], label: "Pago no mês", value: B(r.pagoMes),
                   sub: r.counts.pago + " pagamentos", tone: "danger" };
  }
})();

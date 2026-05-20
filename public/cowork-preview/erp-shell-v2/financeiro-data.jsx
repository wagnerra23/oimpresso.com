// Mock data — ROTA LIVRE-style print/visual-comm shop, May 2026.
// Entries mix Receivable (entrada ↑) and Payable (saída ↓) so the unified
// table can intermix them by date — the way Wagner asked.

const TODAY = new Date(2026, 4, 9); // 2026-05-09 (Sat) — matches "today" in env

function d(yyyy_mm_dd) { return new Date(yyyy_mm_dd + "T12:00:00"); }
function daysFromToday(date) {
  return Math.round((date - TODAY) / 86400000);
}
function statusFor(row) {
  // Pílula canônica · 7 estados (KB-9.75 R#1.c)
  //   recebido · pago · pendente · vencendo · atrasado(=vencido) · conciliado · estornado
  if (row.estornado_em) return "estornado";
  if (row.kind === "receivable") {
    if (row.paid_at) return row.conc_ref ? "conciliado" : "recebido";
    const delta = daysFromToday(row.due);
    if (delta < 0) return "atrasado";
    if (delta <= 3) return "vencendo";
    return "pendente";
  } else {
    if (row.paid_at) return row.conc_ref ? "conciliado" : "pago";
    const delta = daysFromToday(row.due);
    if (delta < 0) return "atrasado";
    if (delta <= 3) return "vencendo";
    return "pendente";
  }
}

const RAW = [
  // ── A RECEBER · entrada ↑ ────────────────────────────────────────────────
  { id: "R-2641", kind: "receivable", desc: "Banner lona 4×1m — promo dia das mães", party: "Padaria Pão Quente", category: "Banner", amount: 480.00, due: d("2026-05-12"), paid_at: null, channel: "PIX", invoice: "NFe 8421" },
  { id: "R-2642", kind: "receivable", desc: "Adesivagem frota (12 veículos)", party: "Auto Posto Trevo", category: "Adesivo", amount: 2340.00, due: d("2026-05-15"), paid_at: null, channel: "Boleto", invoice: "NFe 8422", os_ref: "OS-4821", boleto_ref: "BOL-1240" },
  { id: "R-2643", kind: "receivable", desc: "Lona fachada 2×6m + instalação", party: "Loja Bella Moda", category: "Fachada", amount: 1890.00, due: d("2026-05-03"), paid_at: null, channel: "Boleto", invoice: "NFe 8418" },
  { id: "R-2644", kind: "receivable", desc: "Placas de obra (8un + suporte)", party: "Construtora Vértice", category: "Placa", amount: 3200.00, due: d("2026-05-22"), paid_at: null, channel: "Boleto", invoice: "NFe 8425" },
  { id: "R-2645", kind: "receivable", desc: "Cartão fidelidade laminado 1000un", party: "Farmácia Saúde Total", category: "Gráfica rápida", amount: 720.00, due: d("2026-05-06"), paid_at: d("2026-05-06"), channel: "PIX", invoice: "NFe 8419", conc_ref: "OFX-04393" },
  { id: "R-2646", kind: "receivable", desc: "Cardápio menu plastificado 40un", party: "Restaurante Sabor & Cia", category: "Gráfica rápida", amount: 340.00, due: d("2026-05-18"), paid_at: null, channel: "PIX", invoice: "NFe 8426" },
  { id: "R-2647", kind: "receivable", desc: "Banner 3×1m — show local", party: "Studio Foco", category: "Banner", amount: 380.00, due: d("2026-05-04"), paid_at: d("2026-05-04"), channel: "PIX", invoice: "NFe 8417", conc_ref: "OFX-04390" },
  { id: "R-2648", kind: "receivable", desc: "Wind banner 2.5m + base", party: "Imobiliária Horizonte", category: "Banner", amount: 560.00, due: d("2026-05-20"), paid_at: null, channel: "Boleto", invoice: "NFe 8427" },
  { id: "R-2649", kind: "receivable", desc: "Envelopamento veículo Hilux", party: "Transporte Veloz Ltda", category: "Adesivo", amount: 4200.00, due: d("2026-04-28"), paid_at: null, channel: "Boleto", invoice: "NFe 8410", os_ref: "OS-4798", boleto_ref: "BOL-1218" },
  { id: "R-2650", kind: "receivable", desc: "Lona backdrop 3×2m + ilhós", party: "Academia Movimento", category: "Banner", amount: 290.00, due: d("2026-05-10"), paid_at: null, channel: "PIX", invoice: "NFe 8423" },
  { id: "R-2651", kind: "receivable", desc: "Placa ACM fachada 1.5×0.6m", party: "Clínica Vida Plena", category: "Fachada", amount: 880.00, due: d("2026-05-07"), paid_at: d("2026-05-07"), channel: "PIX", invoice: "NFe 8420", conc_ref: "OFX-04394" },
  { id: "R-2652", kind: "receivable", desc: "Faixa aniversário 5×0.7m", party: "Maria Aparecida (PF)", category: "Banner", amount: 120.00, due: d("2026-05-09"), paid_at: null, channel: "PIX", invoice: "NFe 8424" },
  { id: "R-2653", kind: "receivable", desc: "Cartões de visita 4×4 — 4000un", party: "Imobiliária Horizonte", category: "Gráfica rápida", amount: 540.00, due: d("2026-05-25"), paid_at: null, channel: "Boleto", invoice: "NFe 8428" },
  { id: "R-2654", kind: "receivable", desc: "Rótulo adesivo perolado 2000un", party: "Cervejaria Lupulada", category: "Adesivo", amount: 1640.00, due: d("2026-04-30"), paid_at: null, channel: "Boleto", invoice: "NFe 8412" },

  // ── A PAGAR · saída ↓ ────────────────────────────────────────────────────
  { id: "P-1882", kind: "payable", desc: "Papel couché 250g — 4 resmas", party: "Suprigraf Distribuidora", category: "Insumo", amount: 2450.00, due: d("2026-05-10"), paid_at: null, channel: "Boleto", invoice: "NF 11402" },
  { id: "P-1883", kind: "payable", desc: "Energia elétrica abril/2026", party: "Equatorial Energia", category: "Utilidade", amount: 1180.40, due: d("2026-05-14"), paid_at: null, channel: "Débito autom.", invoice: "Fat 9981" },
  { id: "P-1884", kind: "payable", desc: "Aluguel galpão maio/2026", party: "Imobiliária Centro", category: "Aluguel", amount: 4500.00, due: d("2026-05-02"), paid_at: d("2026-05-02"), channel: "Transferência", invoice: "Recibo 045", conc_ref: "OFX-04388", cc_ref: "CC-301" },
  { id: "P-1885", kind: "payable", desc: "Internet + telefonia maio", party: "Vivo Empresas", category: "Utilidade", amount: 320.00, due: d("2026-05-03"), paid_at: d("2026-05-03"), channel: "Débito autom.", invoice: "Fat 7711" },
  { id: "P-1886", kind: "payable", desc: "IPTU 5ª parcela", party: "Prefeitura Municipal", category: "Imposto", amount: 890.00, due: d("2026-05-20"), paid_at: null, channel: "Boleto", invoice: "DAM 0058" },
  { id: "P-1887", kind: "payable", desc: "Tinta solvente CMYK plotter", party: "Tinta Solvent BR", category: "Insumo", amount: 650.00, due: d("2026-05-16"), paid_at: null, channel: "PIX", invoice: "NF 03340" },
  { id: "P-1888", kind: "payable", desc: "Laminação BOPP 200m", party: "Alphagraf Acabamento", category: "Serviço", amount: 1120.00, due: d("2026-05-05"), paid_at: d("2026-05-05"), channel: "PIX", invoice: "NF 02281", conc_ref: "OFX-04392" },
  { id: "P-1889", kind: "payable", desc: "Manutenção plotter Roland", party: "TecPlot Assistência", category: "Serviço", amount: 780.00, due: d("2026-05-11"), paid_at: null, channel: "PIX", invoice: "OS 1144" },
  { id: "P-1890", kind: "payable", desc: "Salário Larissa — abril (compl.)", party: "Folha — Larissa Souza", category: "Folha", amount: 2800.00, due: d("2026-05-05"), paid_at: d("2026-05-05"), channel: "Transferência", invoice: "Folha 04/26" },
  { id: "P-1891", kind: "payable", desc: "Lona 440g blackout — 50m", party: "Suprigraf Distribuidora", category: "Insumo", amount: 1980.00, due: d("2026-05-19"), paid_at: null, channel: "Boleto", invoice: "NF 11418" },
  { id: "P-1892", kind: "payable", desc: "Simples Nacional DAS abril", party: "Receita Federal", category: "Imposto", amount: 2110.00, due: d("2026-04-22"), paid_at: d("2026-04-22"), channel: "Boleto", invoice: "DAS 04/26", conc_ref: "OFX-04201" },
  { id: "P-1881", kind: "payable", desc: "Tinta plotter — pedido cancelado", party: "Tinta Solvent BR", category: "Insumo", amount: 480.00, due: d("2026-04-26"), paid_at: d("2026-04-28"), channel: "PIX", invoice: "NF 03282", estornado_em: d("2026-05-01") },
  { id: "P-1893", kind: "payable", desc: "Recolhimento INSS abril", party: "Receita Federal", category: "Imposto", amount: 612.00, due: d("2026-05-21"), paid_at: null, channel: "Boleto", invoice: "GPS 04/26" },

  // ── Casos demo para Jana (KB-9.75 R#2) — gerados para exercitar as 3 heurísticas
  { id: "R-2655", kind: "receivable", desc: "Banner promocional dia das mães 2026", party: "Padaria Pão Quente", category: "Banner", amount: 320.00, due: d("2026-05-09"), paid_at: null, channel: "PIX", invoice: "NFe 8429" },
  { id: "R-2656", kind: "receivable", desc: "Backlight luminoso 80×120 — vitrine outono", party: "Loja Bella Moda", category: "Fachada", amount: 1240.00, due: d("2026-04-12"), paid_at: d("2026-04-12"), channel: "PIX", invoice: "NFe 8401", conc_ref: "OFX-04201" },
  { id: "P-1894", kind: "payable", desc: "Papel adesivo arconvert 300m — recompra", party: "Suprigraf Distribuidora", category: "Insumo", amount: 2280.00, due: d("2026-04-18"), paid_at: d("2026-04-18"), channel: "Boleto", invoice: "NF 11295", conc_ref: "OFX-04210" },
  { id: "P-1895", kind: "payable", desc: "Bobina lona front-light 510g — estoque trimestral", party: "Suprigraf Distribuidora", category: "Insumo", amount: 6840.00, due: d("2026-05-17"), paid_at: null, channel: "Boleto", invoice: "NF 11430", cc_ref: "CC-205" },
  { id: "P-1896", kind: "payable", desc: "Tinta solvente CMYK plotter — pedido extra", party: "Tinta Solvent BR", category: "Insumo", amount: 650.00, due: d("2026-05-14"), paid_at: null, channel: "PIX", invoice: "NF 03345" },
];

const ROWS = RAW.map((r) => ({ ...r, status: statusFor(r) }))
  .sort((a, b) => a.due - b.due);

window.FIN_TODAY = TODAY;
window.FIN_ROWS = ROWS;
window.FIN_DAYS_FROM_TODAY = daysFromToday;

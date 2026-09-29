// ────────────────────────────────────────────────
// OIFlow — motor de faturamento compartilhado (gráfica + oficina).
// Fecha o ciclo: produção/manutenção finalizada → FATURAR → cria
// título + parcelas no financeiro (store "fin.extra", o MESMO que a
// tela de Financeiro já lê). Vínculo por ID da OS, nunca por string.
//
// Idempotente: faturar a mesma OS duas vezes não duplica títulos.
// Persistência: via OIStore (sobrevive a refresh, propaga a todas as telas).
// ────────────────────────────────────────────────
(() => {
  const S = window.OIStore;

  // Gera N parcelas distribuindo o total SEM perder centavo (última absorve a sobra).
  // Espelha domain/finance/titulos.ts do backend.
  function gerarParcelas(totalReais, n, primeiroVencDias) {
    const totalCents = Math.round(totalReais * 100);
    const base = Math.floor(totalCents / n);
    const resto = totalCents - base * n;
    const LABELS = (d) => {
      if (d === 0) return "Hoje";
      if (d === 1) return "Amanhã";
      if (d < 0) return Math.abs(d) + " dias atrás";
      return "Em " + d + " dias";
    };
    return Array.from({ length: n }, (_, i) => {
      const cents = base + (i === n - 1 ? resto : 0);
      const vencDias = primeiroVencDias + i * 30;
      return { numero: i + 1, valor: cents / 100, vencDias, vencLabel: LABELS(vencDias) };
    });
  }

  // Já existe título para esta origem? (idempotência)
  function jaFaturado(origemId) {
    const extra = S.get("fin.extra") || S.init("fin.extra", []);
    return extra.some(l => l.origem === origemId);
  }

  // Emite título a receber a partir de uma OS finalizada.
  // opts: { origemId, parte, parteId, desc, categoria, valor, parcelas, primeiroVencDias, meio }
  function faturar(opts) {
    const {
      origemId, parte, parteId, desc, valor,
      categoria = "Venda de impressos", parcelas = 1, primeiroVencDias = 7, meio = "Boleto",
    } = opts;

    S.init("fin.extra", []);
    if (jaFaturado(origemId)) {
      return { ok: false, motivo: "OS já faturada", origemId };
    }

    const ps = gerarParcelas(valor, parcelas, primeiroVencDias);
    const novos = ps.map((p) => ({
      id: `R-${origemId}-${p.numero}`,
      tipo: "receber",
      desc: parcelas > 1 ? `${desc} (${p.numero}/${parcelas})` : desc,
      parte, parteId,
      categoria,
      origem: origemId,                 // VÍNCULO POR ID — não string solta
      valor: p.valor,
      vencDias: p.vencDias,
      vencLabel: p.vencLabel,
      status: p.vencDias < 0 ? "vencido" : "aberto",
      meio,
      emitidoPor: "faturamento",        // marca a origem para auditoria
    }));

    S.set("fin.extra", (e) => [...novos, ...(e || [])]);
    return { ok: true, origemId, parcelas: novos, totalValor: valor };
  }

  // Estorna o faturamento de uma OS (remove títulos ainda não liquidados).
  function estornar(origemId) {
    const liq = S.get("fin.liq") || {};
    S.set("fin.extra", (e) => (e || []).filter(l => l.origem !== origemId || liq[l.id]));
  }

  // Resumo do que foi faturado para uma OS (para mostrar no detalhe do pedido).
  function faturamentoDe(origemId) {
    const extra = S.get("fin.extra") || [];
    const ts = extra.filter(l => l.origem === origemId);
    return ts.length ? { faturado: true, parcelas: ts, total: ts.reduce((s, l) => s + l.valor, 0) } : { faturado: false };
  }

  window.OIFlow = { gerarParcelas, faturar, estornar, jaFaturado, faturamentoDe };
})();

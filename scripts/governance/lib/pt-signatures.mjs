// pt-signatures.mjs — FONTE ÚNICA das assinaturas estruturais dos 5 Padrões de Tela.
//
// Extraído de pt-conformance.mjs (que agora IMPORTA daqui) pra que o GERADOR (criar-tela.mjs)
// e o VERIFICADOR (pt-conformance.mjs) compartilhem a MESMA definição de "o que é a assinatura
// de um PT-0X". Sem isto os dois driftariam: o gerador carimbaria um tsx que o gate reprovaria.
// Ao consumir a mesma tabela, o scaffold PASSA no pt-conformance POR CONSTRUÇÃO — provado no
// --selftest de ambos (anti-fantasma, ADR 0256: a lib é exercida por fixtures nos dois lados).
//
// Contrato: memory/requisitos/_DesignSystem/padroes-tela/PT-0{1..5,7}-*.md (assinaturas do golden).
// PT-07 (Feed/Timeline) add 2026-07-11. PT-06 (Ferramenta) fica sem assinatura até ter ≥2 telas.

// ── assinaturas estruturais por arquétipo (do golden de cada PT-0X) ──
const has = (code, ...res) => res.some((r) => r.test(code));

export function detectSignals(code) {
  return {
    form: has(code, /\buseForm\b/, /<form[\s>]/, /FormSection/, /FormGrid/),
    list: has(code, /<table[\s>]/, /<thead/, /<tbody/, /DataTable/i, /Pagination/) ||
          has(code, /grid-cols/, /ProdutoCard/i, /<article[\s>]/),
    kanban: has(code, /dnd-kit/, /KanbanDndProvider/, /BoardColumn/, /onDragStart/, /draggable/, /\bKanban\b/),
    kpi: has(code, /KpiCard/, /KpiGrid/, /KpiFilterCard/),
    detail: has(code, /FsmActionPanel/, /NextActionPanel/, /VdNextActionPanel/, /Timeline/, /Histórico/i,
                      /<dl[\s>]/, /StatCard/, /StatTile/),
    // feed/timeline: lista CRONOLÓGICA de eventos/itens renderizada em cards via .map (NÃO <table>),
    // com carimbo de tempo relativo. Assinatura: container timeline/feed OU helper de tempo relativo.
    feed: has(code, /fx-timeline/, /-timeline["'\s]/, /\bTimeline\b/,
                    /data-testid="[^"]*feed/, /role="feed"/, /ActivityFeed/,
                    /\btimeAgo\b/, /\bfmtRelative\b/),
  };
}

// PT declarado → sinal MÍNIMO que a tela precisa ter pra a declaração não ser mentira.
export const REQUIRED = {
  'PT-01': (s) => s.list,
  'PT-02': (s) => s.form,
  'PT-03': (s) => s.detail || s.kpi,
  'PT-04': (s) => s.kpi,
  'PT-05': (s) => s.kanban,
  'PT-07': (s) => s.feed,   // feed/timeline: cronologia de eventos/itens em cards (não <table>)
  // PT-06 (Ferramenta/Calculadora) NÃO existe: só 1 tela do arquétipo no repo (ComunicacaoVisual/Index) —
  // não há golden nem ≥2 pra formalizar. Reabrir quando surgir 2ª ferramenta. Por isso claimedPT NÃO
  // reconhece "PT-06" (usa [1-57]): declaração de PT inexistente não deve passar trivialmente.
};

export const claimedPT = (relProto) => {
  // [1-57] = PTs que EXISTEM hoje: 01-05 + 07. Exclui 06 de propósito (não formalizado).
  const m = (relProto || '').match(/PT-0[1-57]/i);
  return m ? m[0].toUpperCase() : null;
};

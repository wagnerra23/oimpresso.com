// COUNTERFACTUAL (ADR 0314 F1) — prova que DS gate fica VERMELHO quando ui-lint (LEI) regride.
// Viola ui:lint R1 (hex cru) + R3 (emoji em UI). Arquivo descartável — PR scratch nunca mergeia.
export default function CfDeleteMe() {
  return <div style={{ color: '#ff0000' }}>🚀 contrato de cor quebrado de propósito 🔥</div>;
}

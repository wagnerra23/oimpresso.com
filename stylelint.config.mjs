// stylelint.config.mjs — G5 anti-drift CSS (gêmeo do ratchet ESLint `ds/*`, ADR 0209)
//
// Filosofia IDÊNTICA às regras `ds/*` do eslint.config.js: isto NÃO é um linter de
// formatação — é um GUARD DE DRIFT do Design System. Liga só regras de alto sinal que
// mapeiam o "Checklist pre-commit" do CODE_DESIGN_CONTRACT.md. O ratchet
// (scripts/stylelint-baseline.mjs) absorve a dívida atual e barra só REGRESSÃO (delta>0)
// — mesmo padrão do scripts/eslint-baseline.mjs.
//
// Nota factual (medido 2026-05-31): NÃO existe um tokens.css central no repo — os `:root`
// vivem espalhados (inertia.css, fiscal-cockpit.css, cowork-fields.css) e os 759 hex se
// concentram nos bundles cowork (drift já apontado em AUDITORIA_DS_V4.md). Por isso
// `color-no-hex` roda em TODOS os CSS sem isenção: o baseline congela os 759 atuais e
// barra hex NOVO, empurrando var(--token)/oklch pra frente (o accent já é oklch-first).
//
// Refs: ADR 0209 · CODE_DESIGN_CONTRACT.md · prototipo-ui/F0-AUDITORIA-ROTINAS-DESIGN-2026-05-31.md (G5)

export default {
  rules: {
    // CODE_DESIGN_CONTRACT grep #1 — "zero hex/rgb fora dos tokens"
    'color-no-hex': true,
    // grep #6 — redeclaração de seletor (ex.: `.os-search` em 2 lugares com valores diferentes)
    'no-duplicate-selectors': true,
    // drift de especificidade — `!important` pra forçar (concentrado nos cowork-*-bundle.css)
    'declaration-no-important': true,
    // barato e sempre correto
    'no-duplicate-at-import-rules': true,
  },
  // Onda 2 (futuro, fora do MVP G5-finish): radius/spacing px-cru via
  // `declaration-property-value-disallowed-list` (espelha `ds/no-rounded-xl`). Deixado de
  // fora pra manter o baseline inicial enxuto e focado em drift de cor/especificidade/dup.
};

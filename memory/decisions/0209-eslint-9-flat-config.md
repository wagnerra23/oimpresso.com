---
slug: 0209-eslint-9-flat-config
number: 209
title: "ESLint 9 flat-config + react-hooks + jsx-a11y baseline ratchet — enforcement passivo JS/TS"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-28
module: DesignSystem
quarter: 2026-Q2
tags: [enforcement, static-analysis, eslint, react-hooks, anti-pattern, prevencao-bugs]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0208-larastan-baseline-ratchet
pii: false
review_triggers:
  - "Race condition em componente novo (similar a R7 Larissa)"
  - "PR UI Judge falha por hook violations"
---

# ADR 0209 — ESLint 9 flat-config baseline ratchet

## Contexto

Projeto Inertia v3 + React 19 em `resources/js/` com **~14.270 arquivos**, dos quais ~1.500 `.tsx`/`.ts`. Sem ESLint configurado:

- Sem `.eslintrc*` em qualquer formato
- Sem `eslint.config.*` (flat config 2026 padrão)
- `package.json` confirmado: nenhum dep `eslint`, `@typescript-eslint`, `eslint-plugin-react-hooks`
- Type-checking só roda `tsc --noEmit` (catches type errors mas NÃO catches hook violations, deps array stale, race patterns)

Resultado direto na sessão Larissa 2026-05-28:

- **R7 race scanner**: `useEffect` com fetch sem AbortController, sem `signal` no fetch, sem cleanup. Regra `react-hooks/exhaustive-deps` teria flaggado deps incompleta. Regra custom `no-uncancelled-fetch-in-effect` (a criar) teria flaggado o pattern.
- Mais bugs JS-side em todas as MWART migrations sem detection automática — depende de humano/IA ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) ou catálogo de design.

Alternativas avaliadas:

1. **ESLint 9 flat-config** (release Aug 2024, ecosystem migrado em 2026) — recommended path forward, plugin ecosystem mature
2. **Biome** (~Rust-based, 10-100x mais rápido que ESLint, single binary) — fantastic mas adoption ainda menor em projetos Laravel/Inertia, custom rules ecosystem imaturo vs ESLint
3. **Stylelint só** (cobre CSS, não JS) — insuficiente

## Decisão

**Adotar `eslint@9` flat-config + plugins canônicos + baseline ratchet** — mesmo padrão de `ui-lint.yml` e [ADR 0208 Larastan](0208-larastan-baseline-ratchet.md).

**Especificação técnica:**

- `npm install -D eslint @typescript-eslint/parser @typescript-eslint/eslint-plugin eslint-plugin-react-hooks eslint-plugin-jsx-a11y eslint-plugin-react-refresh`
- `eslint.config.js` na raiz (flat config) com:
  - `@typescript-eslint/recommended` base
  - `react-hooks/recommended` (exhaustive-deps, rules-of-hooks)
  - `jsx-a11y/recommended` subset relevante (sem `no-autofocus`, sem `no-onchange` deprecated)
  - `react-refresh/only-export-components` (compat com Vite HMR)
- `.eslintrc-baseline.json` gerado com violações pre-existentes (mesmo padrão `ui-lint-baseline.json`)
- Workflow `.github/workflows/eslint-gate.yml` em CI:
  - Dispara em PR tocando `resources/js/**/*.{ts,tsx}`
  - Roda `npx eslint --format=json` + comparação com baseline
  - Falha só em REGRESSÃO (delta > 0)
- `package.json` script `npm run lint` pra rodar local
- Pre-commit hook **opcional** (decision Wagner): se bloqueia local OU só warning. Recomendação: warning local + falha CI.

**Plugins NÃO incluídos nesta onda:**

- `eslint-plugin-import` (resolução de paths) — útil mas adoption pesa. Adicionar onda 2 se demanda.
- `prettier-plugin-eslint` — formatting é problema separado. Prettier não está adotado ainda; PR canon separado se Wagner quiser.
- Custom rules específicas (`no-uncancelled-fetch-in-effect`, `no-untyped-inertia-props`) — entram em [ADR 0211 TanStack](0211-tanstack-query-data-fetching-padrao.md) e [ADR 0210 Wayfinder](0210-type-safety-end-to-end-wayfinder.md) respectivamente.

## Justificativa

**Por que ESLint 9 e não Biome:** flat config é canon agora (Aug 2024+); ecossistema completo de plugins (200+ official). Biome é tentador (Rust speed) mas custom rules em Biome ainda exigem GritQL knowledge e ecosystem é 1/100 de ESLint. ROI vs adoção: ESLint vence em 2026.

**Por que ratchet baseline igual ao Larastan/UI-lint:** consistência com [ADR 0208](0208-larastan-baseline-ratchet.md). Já funciona em `ui-lint.yml`. Cognitive overhead zero pra Felipe/Maiara/Eliana/Luiz entenderem o padrão.

**Por que react-hooks plugin (recommended) é critical:** `exhaustive-deps` sozinho teria detectado o deps array errado do `useEffect` race condition do R7 ANTES do PR mergear. `rules-of-hooks` previne calls condicionais. Custo zero, ROI alto. Anthropic [react.dev oficial recomenda](https://react.dev/reference/eslint-plugin-react-hooks/lints/exhaustive-deps).

**Por que jsx-a11y:** Larissa @ Rota Livre não é desenvolvedora, opera teclado/scanner. A11y violations criam pegadinha invisível pra ela (focus management, screen reader hints, role attributes). Custo zero.

**Por que NÃO bloqueia pre-commit local (Wagner-default):** desenvolvedor experiente precisa flexibilidade em WIP. Pre-commit forçado interrompe fluxo de pensamento. CI gate é suficiente pra bloquear merge.

## Consequências

**Positivas:**

- `react-hooks/exhaustive-deps` flagga deps array incompletos automaticamente — R7-class bugs catched antes de PR mergear
- `react-hooks/rules-of-hooks` previne hook calls condicionais (anti-pattern recorrente em refactors)
- `@typescript-eslint/no-floating-promises` flagga Promises não-awaited (cousin do R7)
- Baseline ratchet permite adoção incremental sem big-bang
- Habilita custom rules em ADRs futuros (no-uncancelled-fetch, no-untyped-inertia-props)
- Annotations GitHub inline no PR — Wagner vê review com contexto

**Negativas / Trade-offs:**

- **Falsos positivos exhaustive-deps em refs intencionais:** developer sometimes wants stale closure. ESLint pode warn errado. Mitigação: `// eslint-disable-next-line` line-level com comentário justificando.
- **Custo CI:** +30-90s por PR. Mitigação: cache ESLint no GitHub Actions, paralelismo com PHPStan.
- **Time futuro precisa entender flat-config:** documentação Anthropic-like skill `eslint-9-workflow` (Tier C on-demand) com runbook.

**Riscos mitigados:**

- R7 (race condition) — partial: `exhaustive-deps` detecta deps; custom rule futura captura padrão completo
- Hook violations gerais (calls condicionais, deps stale)
- A11y regressions em refactors UI

**Riscos não-mitigados:**

- Race condition completa exige custom rule no-uncancelled-fetch (vem com [ADR 0211 TanStack Query](0211-tanstack-query-data-fetching-padrao.md))
- Type drift entre PHP↔TS — vem com [ADR 0210 Wayfinder](0210-type-safety-end-to-end-wayfinder.md)

## Referências

- ADR 0094 — Constituição v2 §princípio 5 (SoC brutal — frontend tem suas regras)
- ADR 0104 — Processo MWART canônico — F3 frontend incremental
- ADR 0208 — Larastan PHPStan baseline ratchet (paralelo PHP)
- [react.dev — exhaustive-deps lint](https://react.dev/reference/eslint-plugin-react-hooks/lints/exhaustive-deps)
- [ESLint 9 flat config docs](https://eslint.org/docs/latest/use/configure/configuration-files)
- [`memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md`](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) — Frente 5 ação F5-B

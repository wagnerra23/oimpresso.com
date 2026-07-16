# ADR UI-0021 · Primary roxo no dark clareado 0.62→0.7 (emenda à ADR 0190)

- **Status**: accepted
- **Data**: 2026-07-08
- **Aprovado em**: 2026-07-08 — Wagner escolheu **"App inteiro (emenda ADR 0190)"** na pergunta explícita sobre o escopo do clareamento, e depois **"faça"**.
- **Decisores**: Wagner (aprovação + escopo), Claude Code (medição + execução)
- **Categoria**: ui · fundações · tokens
- **Amends**: [ADR 0190](../../../../decisions/0190-primary-button-roxo-universal-295.md) — só o valor DARK do primary; light permanece `oklch(0.55 0.15 295)`.
- **Refs**:
  - [UI-0020](0020-dark-warm-ds-v6-tokens.md) — retune dark WARM (mesmo fluxo DTCG, `tokens:build`)
  - Fonte da medição: smoke dark do Financeiro 2026-07-08 (fingerprint `style-fingerprint.mjs` proto×prod) — [`financeiro-unificado-visual-comparison.md`](../../../Financeiro/financeiro-unificado-visual-comparison.md)
  - ADR 0300 (DTCG SSOT) — `semantic.tokens.json` → `tokens:build` → `_generated-inertia-dark.css`

## Contexto

O smoke dark do Financeiro (2026-07-08) mediu por sonda DOM que o **primary roxo do protótipo Cowork (fonte ADR 0299) é `oklch(0.7 0.15 295)` no dark** (ex.: `.fin-pb-preset.on` = pílula de período ativa, `Novo título`), enquanto a produção usava `--color-primary` dark = `oklch(0.62 0.15 295)` — visivelmente mais apagado. O delta foi confirmado por três caminhos (olho → fingerprint → DOM computado).

A ADR 0190 fixou o **primary universal ROXO** (`oklch(0.55 0.15 295)`) mas não calibrou explicitamente o valor no tema dark; o dark herdou `0.62` (mais escuro que o design pede).

## Decisão

**O `--color-primary` no dark passa de `0.62` para `oklch(0.7 0.15 295)` — app inteiro.** Clarear o primary afeta o roxo de **todos os módulos** no dark (utilities `bg-primary`/`text-primary`/`border-primary`), então é decisão de fundação, não de tela — Wagner autorizou o escopo global explicitamente.

- Editado na **fonte DTCG** (`semantic.tokens.json` → `primary.$extensions.com.oimpresso.dark`), regenerado via `npm run tokens:build`. Muda só `_generated-inertia-dark.css` (`--color-primary`). Light e demais tokens intactos. Equivalência DTCG↔CSS preservada.

## Consequências

- **Positivas:** o primary no dark fica fiel ao protótipo; as pills de período (UI-0021 companion — bg-primary) e todo elemento `bg-primary` no dark ganham o roxo certo sem override por-tela.
- **Blast radius:** toda tela em dark com primary muda de pixel → **baselines de regressão visual regeneradas** (modo UPDATE do VRT).
- **Residual honesto (fora do escopo desta emenda):** o token **legado `--accent`** (`oklch(0.55 0.15 295)`, hardcoded em `cockpit.css` + bundles `fin-cowork`/`sells-cowork`, **sem override dark**) é um roxo SEPARADO do `--color-primary` — elementos que usam `--accent` (ex.: o CTA "Novo título" do shell) seguem em `0.55` no dark. Alinhá-lo ao `0.7` é uma varredura própria (7+ arquivos de bundle, sem mecanismo de dark override) — catalogado para decisão futura, NÃO incluído aqui para manter 1 PR = 1 intent.

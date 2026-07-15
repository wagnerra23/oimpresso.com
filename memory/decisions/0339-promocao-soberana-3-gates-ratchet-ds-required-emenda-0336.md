---
slug: 0339-promocao-soberana-3-gates-ratchet-ds-required-emenda-0336
number: 339
title: "Emenda à 0336/0314 — promoção SOBERANA [W] de 3 gates ratchet DS (layout/stylelint/eslint) a required SEM bite-log formal (desvio consciente registrado)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-15"
accepted_at: "2026-07-15"
accepted_via: "Wagner 'flip' 2026-07-15 (esta sessão), exercendo soberania (ADR 0238) DEPOIS de [CC] expor que a DR-2 da 0336 (bite-log ≥2 PRs contrafactuais) não foi cumprida. Aceite explícito do desvio, não do cumprimento."
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, design, promocao, soberania, excecao, honestidade, anti-teatro]
supersedes: []
superseded_by: []
related:
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0238-soberania-wagner-decisao-final
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
pii: false
---

# ADR 0339 — promoção soberana [W] de 3 gates ratchet DS a required (emenda à 0336)

> **Status:** `aceito` (2026-07-15, Wagner "flip"). Append-only — **não edito a [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) nem a [0314](0314-poda-gates-onda-2-lei-fusoes.md).** Esta ADR registra, com honestidade, uma promoção que **desviou** do trilho DR-2 da 0336, autorizada por soberania ([0238](0238-soberania-wagner-decisao-final.md)).

## Contexto

Auditoria adversarial das dimensões de qualidade visual/DS (2026-07-15, esta sessão) repontuou 15 dimensões contra `origin/main` e identificou 3 gates advisory como Tier 1 pró-promoção: **`Layout primitives · ratchet`**, **`Stylelint · ratchet vs baseline`**, **`ESLint · ratchet vs baseline`**. Todos são Node-puro, ratchet-vs-baseline, com **exit-code REAL** (rodam o `.mjs` direto — não o wrapper `::warning::`+`exit 0` que a [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) condena nos 5 gates `ds-*`).

Bloqueador técnico resolvido antes do flip: os 3 usavam `paths:` no trigger (pulavam o job) → required travaria PR fora do path ("Expected — waiting for status"). **[PR #4301](https://github.com/wagnerra23/oimpresso.com/pull/4301)** converteu-os para `pull_request` always-run + `dorny/paths-filter` skip-as-pass (padrão `e2e-gate`/`visual-regression`), mergeado verde. Flip do vivo aplicado via `gh api PATCH` (required 24 → 27, `enforce_admins` intacto, 24 originais preservados).

## O desvio — honestidade obrigatória

Ao ir registrar, [CC] achou a [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) (aceita 2026-07-11) e a trouxe à mesa **antes** de finalizar. A DR-2 da 0336 exige, pra promover gate de design/qualidade não-Tier-0:

- **exit-code desembrulhado** — ✅ os 3 já cumprem (falham de verdade);
- **bite-log de ≥2 PRs contrafactuais por gate** (violação que *mergeou* vermelho) — ❌ **NÃO foi coletado**;
- **PR por item + Wagner + janela** — parcial (Wagner sim; sem bite-log por-item).

A evidência que **existe** é mais fraca que o critério: `layout-primitives` 1 failure na janela de 60, `stylelint` 2 failures, `eslint` 0 na janela (mas o ratchet conta `warnings`, então morde por delta de contagem). Isso **não é** o bite-log formal da DR-2.

[CC] ofereceu (a) exceção soberana registrada vs (b) reverter e coletar a evidência pelo trilho. Wagner escolheu **(a)** — "flip".

## Decisão

Os 3 gates ficam **required** como **exceção consciente e pontual** à DR-2 da 0336, sob soberania [W] ([0238](0238-soberania-wagner-decisao-final.md)). Isto é registrado como **desvio**, não como cumprimento — a régua da 0336 (bite-log ≥2 PRs) **permanece o default** pra qualquer promoção futura de gate de design.

Mitigantes que tornam o desvio defensável (não anulam):
1. **DR-3.1 cumprida** — os 3 têm exit-code real; o pior anti-padrão da 0336 ("required sempre verde") **não** se aplica.
2. **Reversível** — `gh api` re-remove volta a 24 (a própria 0314 prevê).
3. **Require-safe provado** (#4301 verde, skip-as-pass idêntico a gates required existentes).

## Consequências

- Required 24 → **27**. Os 3 passam a **barrar merge** de regressão de flex/grid solto, drift CSS e regressão ESLint (inclui `ds/no-inline-raw-color` via contagem de warnings do ratchet).
- **Precedente restrito:** esta ADR **não** é atalho. Promoção de gate de design segue exigindo o bite-log DR-2 da 0336. Esta é exceção soberana pontual pra 3 gates com exit-code real já provado — invocá-la pra outros gates exige nova decisão [W].
- **Follow-up honesto (opcional, recomendado):** coletar retroativamente o bite-log dos 3 (ativar `memory/governance/design-gate-bites.jsonl` da DR-2a) — se algum não acumular ≥2 mordidas reais em N semanas, reconsiderar a demoção daquele item. Sem isso, o desvio fica sem fechamento empírico.

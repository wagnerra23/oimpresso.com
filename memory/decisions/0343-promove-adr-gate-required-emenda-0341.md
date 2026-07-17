---
slug: 0343-promove-adr-gate-required-emenda-0341
number: 343
title: "Promove o gate ADR (memory/decisions/*.md) a required — emenda à 0341 (o adr é corrigível via 0297)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-07-17"
module: governance
kind: meta
supersedes: []
supersedes_partially:
  - 0341-memory-schema-charter-spec-required-emenda-0314
related:
  - 0261-enforcement-faseado-gates-ci
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0297-excecao-append-only-migracao-legacy-frontmatter-adr
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0341-memory-schema-charter-spec-required-emenda-0314
  - 0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula
pii: false
---

# ADR 0343 — Promove o gate `ADR (memory/decisions/*.md)` a required

> Emenda operacional da [ADR 0341](0341-memory-schema-charter-spec-required-emenda-0314.md).
> Fecha o item que a 0341 deixou explicitamente em aberto: o `adr`, a 3ª família limpa do
> `memory-schema-gate`. Aprovada por Wagner nesta sessão (2026-07-17, "pode terminar tudo /
> fazer o que falta").

## Contexto

A [ADR 0341](0341-memory-schema-charter-spec-required-emenda-0314.md) promoveu o `memory-schema-gate`
a **required** em `charter` (0/237) e `spec` (0/59) — as 2 famílias que já custavam zero — e **deferiu
o `adr`** com a justificativa: *"append-only: consertar é PROIBIDO, Art. 3 — required tornaria 143
decisões PERMANENTEMENTE intocáveis"*. Na mesma medição, `adr` estava em **143/346 (41%)**.

Esse framing não considerou a [ADR 0297](0297-excecao-append-only-migracao-legacy-frontmatter-adr.md)
(`adr-legacy-schema-migration`): a exceção append-only que **já permite** migrar o frontmatter de ADR
*desde que o corpo seja byte-idêntico*. As Fases 1 e 1b desta sessão exerceram esse caminho e zeraram
a dívida — **empiricamente, não em tese**:

- **[#4456](https://github.com/wagnerra23/oimpresso.com/pull/4456)** (Fase 1): 140 ADRs normalizados
  (title `!!binary`→literal, `decided_at` com aspas, `number` int, refs bare-number→slug) — corpo
  byte-idêntico 140/140 — **139 → 0** inválidos no AJV. + relax do slug pattern pros 3 filenames
  legacy irrenomeáveis ([ADR 0342](0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula.md)).
- **[#4467](https://github.com/wagnerra23/oimpresso.com/pull/4467)** (Fase 1b): 54 refs `related` a
  arquivo inexistente removidas + 1 `superseded_by` corrigida — corpo byte-idêntico 39/39.

Estado em `main`: **348 ADRs · 344 válidos · 0 inválidos**. A premissa da 0341 ("o adr não é
corrigível") caiu.

## Decisão

Promover o check **`ADR (memory/decisions/*.md)`** (matriz do `memory-schema-gate.yml`) a **required**
na branch protection de `main`.

**Rebate direto ao medo da 0341** ("143 decisões PERMANENTEMENTE intocáveis"): o gate é **diff-aware**
(valida só os ADRs *tocados* num PR) e **always-run** (a 0341 removeu o `paths:` — sem deadlock ADR
0261). Ele **não** congela as decisões — o corpo (a decisão) segue imutável pelo append-only
(`governance-gate.yml`), e a **etiqueta** (frontmatter) segue migrável pela exceção 0297. O required
só garante que **um ADR novo ou tocado esteja schema-válido** — exatamente o que Fase 1 provou ser
alcançável. Nenhuma restrição nova é criada sobre ADRs parados; a superfície é idêntica à do
append-only + 0297 que já vigora.

Reconcilia também, no MESMO PR, o `screen-coverage-gate` — que estava **🟡 novo no vivo** (na branch
protection, fora do `required-checks-baseline.json`; detectado por `protection-drift.mjs`). Baseline
vai de 29 → 31 (`+ADR (memory/decisions/*.md)` `+screen-coverage-gate`).

## Rito (mesmo da 0327/0341)

1. **Este PR**: emenda ADR + `governance/required-checks-baseline.json` (contexts 29→31 + entrada em
   `_meta.promocoes` com a medição 139→0).
2. **Após o merge**: flip do vivo via `gh api --input <arquivo UTF-8 sem BOM>` — **NUNCA** payload
   inline (proibições §Ambiente: shell Windows re-encoda → mojibake deadlockou merge em 2026-07-02).
   O context `ADR (memory/decisions/*.md)` é ASCII puro (sem `·`/acento), mas a disciplina do `--input`
   se mantém. Add-only (`screen-coverage-gate` já está no vivo).
3. **Validação obrigatória**: `node scripts/governance/protection-drift.mjs` (baseline↔vivo string-exato).

## Honestidade sobre o rito 0336

A promoção se apoia em: **0 violações** medidas (AJV, árvore inteira) + gate **always-run** + o gate
**mordeu de fato** (o check `ADR (...)` validou os 140 ADRs tocados no #4456 — sem o fix, eram
vermelhos). O **bite-log formal de ≥2 PRs contrafactuais** da [ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)
DR-2 **não** foi coletado separadamente — a promoção é **exceção soberana de Wagner** (ADR 0238),
registrada como **desvio consciente**, mesmo padrão da [ADR 0339](0339-promocao-soberana-3-gates-ratchet-ds-required-emenda-0336.md).

## Gate de reversão

Falso-positivo (ex. um ADR legacy que a exceção 0297 não cobre por precisar mudar o corpo) → rebaixar
via `gh api` re-remove do context + PR editando o baseline (31→30) + nota. Reversível.

## Refs
- Gate: `.github/workflows/memory-schema-gate.yml` (matriz `ADR (memory/decisions/*.md)`, always-run desde a 0341)
- Baseline: `governance/required-checks-baseline.json` + `scripts/governance/protection-drift.mjs`
- Exceção que torna o adr corrigível: [ADR 0297](0297-excecao-append-only-migracao-legacy-frontmatter-adr.md)
- Origem: Wagner 2026-07-17, "pode terminar tudo / fazer o que falta"

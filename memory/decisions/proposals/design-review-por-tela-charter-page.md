---
status: proposed
date: 2026-06-01
deciders: [Wagner]
mãe: [0114, 0236, 0239]
relacionadas: [0110, 0209, 0235]
tags: [design, loop-cowork-code, governanca, charter, ratchet]
# Número da ADR é cunhado POR [W] no merge (soberania da constituição — ADR 0238).
# [CL] NÃO atribui número de git (lição L-09 / 0200-0201).
---

# (proposta) Gerador `design:review` por tela — charter page viva + gatilho de frescor

## Status

**Proposta** (§10.4 — evolução do PROTOCOL do loop Cowork↔Code). Tier 0: abre PR e
**espera [W]** (PROTOCOL/constituição-adjacente = não auto-merge). Origem: [W] 2026-06-01
— *"vai precisar criar uma automatização para gerar um relatório com tarefas para você…
compare com os melhores, dê nota e o porquê, documente o que falta, faça o champion.
acredito que deveria ser no charter page?"* (após confirmar Jana Pro F3 feito, sem `Pro.review.md`).

## Contexto

A máquina de auditoria de tela **já existe meio-construída** (anti L-11 — estender, não recriar):

- `prototipo-ui/audit/score-mechanized.mjs` — **Fase 1** (regex R1-R10 mecanizáveis + `ds/*`),
  1 `design-report.json`/tela, com `measured_against_sha`.
- `consolidate.mjs` → `CONSOLIDADO.md` (placar worst-first, média 86/100).
- `design-report.schema.json` (já com `top_gaps{dim,best_of_class,fix,esforco}`).
- `<Tela>.review.md` append-only ao lado do `<Tela>.charter.md`.
- `GOLDEN-REFERENCE.md` (10 regras) + `CharterHealthChecker`.

**4 gaps reais** (confirmados vs `origin/main` 2026-06-01):

1. **Fase 2 LLM nunca rodou em escala** → R5/R8/R10 + nota holística + `best_of_class` vazios (nota = só conformidade-DS, mascarável).
2. **`<Tela>.review.md` é one-off de 2026-05-17, nunca regenerado** → tela nova nasce SEM relatório de tarefas. **Caso vivo: `Jana/Pro` (#2069)** tem `.tsx` + `.charter.md` `status: live`, mas **nenhum** `Pro.review.md` (toda outra tela Jana tem review round 1).
3. **Sem gatilho de frescor** → nada regenera o review quando o `.tsx` muda (`design_return_skipped`/G4 cobre `SYNC_LOG`, não review-vs-sha-da-tela).
4. Charter + review + benchmark vivem em 3 arquivos soltos, não numa **charter page única**.

## Decisão

Formalizar o **review por tela** como artefato canônico da **charter page viva** (charter = spec;
review = nota viva + backlog de tarefas + benchmark), e o seu **frescor** como gate ratchet:

**(a) Canal de retorno F1.5/F3.5→F0 no nível da TELA.** Hoje o §10.2 só retorna no nível da
worklist-DS. O `<Tela>.review.md` round N (append-only) passa a ser o retorno por TELA: o bloco
"Top recomendações" É o backlog de tarefas do `[CC]`/`[CL]` pra aquela tela.

**(b) PROTOCOL §6 ganha 2 checks** (já emendados neste PR):
- `design_review_missing` — tela charter `status: live` sem `.review.md` **fora** do baseline → falha.
- `design_review_stale` — `.review.md` com `measured_against_sha` ≠ sha do último commit do `.tsx`
  → **advisory na v1** (reviews legados de 2026-05-17 não têm o campo), vira HARD ao regenerar.

**(c) A charter page viva é o artefato canônico** — `<Tela>.charter.md` + `<Tela>.review.md`
lado a lado. O rollup CROSS-tela continua no `CONSOLIDADO.md`/Governança, não na charter page.

### Implementação (entregue neste PR — Fase 1, autônoma; Fase 2 = ratchet pago [W])

| Peça | Papel | Natureza |
|---|---|---|
| `prototipo-ui/audit/review-gen.mjs` | `design:review <tela>` — renderiza o `design-report.json` (Fase 1) num `<Tela>.review.md` append-only, ancorado por `measured_against_sha`; puxa guardrails Tier 0 do charter (Non-Goals/Anti-hooks) | aditivo |
| `review-freshness.mjs` | gate node (missing/stale/fresh) + ratchet `--write-baseline` | aditivo |
| `review-freshness-baseline.json` | dívida herdada (21 telas live sem review em 2026-06-01); só encolhe | aditivo |
| `tests/Feature/Design/DesignReviewFreshnessTest.php` | espelha o gate em PHP (roda no CT 100) | aditivo |
| `resources/js/Pages/Jana/Pro.review.md` | **1ª execução** — fecha o gap do `Jana/Pro` | aditivo |
| `npm run design:review[:check|:baseline]` | atalhos | aditivo |

**Fase 2 (juiz LLM)** — preenche R5/R8/R10 + nota holística + `best_of_class`/`fix`/`esforco`;
cadência real-mode na régua que **[W] paga** (espelha o gate RAGAS da IA). Ratchet: nota só sobe
(ADR 0236). **Fora deste PR** (custo/infra = Tier 0).

## Consequências

**Boas:** tela nova não nasce mais sem relatório de tarefas (o teste pega); o backlog de design
fica versionado ao lado da tela; frescor é evidência reproduzível (sha), não opinião; estende a
máquina existente (zero duplicação).

**Custo/risco:** a Fase 1 é só conformidade-DS (a nota é **teto provisório** — `Jana/Pro` = 88
mecanizado, mas tem `oklch(...)` cru + gradient 135° que a Fase 2 precisa julgar). O `design_review_stale`
fica advisory até os reviews legados serem regenerados (senão o gate nasce vermelho — anti
"subir régua que quebra o próprio CI", lição RAGAS).

## Benchmark (best-of-class — por que isto é "champion")

SonarQube/Code Climate (gate por arquivo) · CodeRabbit/Danger (PR review) · Chromatic/Percy
(visual-regression) · Linear (project health) · RAGAS (ratchet) · Storybook Docs/Figma Dev Mode
(spec ao lado do componente). O `<Tela>.charter.md` + `<Tela>.review.md` = o "Storybook Docs +
SonarQube gate" do oimpresso, versionado em git e auditado por Pest.

## Refs

- `prototipo-ui/audit/{review-gen,review-freshness,score-mechanized,consolidate}.mjs` · `GOLDEN-REFERENCE.md`
- `prototipo-ui/PROTOCOL.md §6` (checks) · §10.2 (retorno) — emendados neste PR
- ADR 0114 (loop Cowork↔Code) · 0236 (ratchet nota-só-sobe) · 0239 (SSOT do DS) · 0209 (eslint-baseline ratchet) · 0238 (soberania [W])
- COWORK_NOTES → "Gerador `design:review`" (handoff 2026-06-01)

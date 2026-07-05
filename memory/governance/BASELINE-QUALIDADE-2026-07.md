---
slug: baseline-qualidade-2026-07
title: "Baseline consolidado de qualidade — 6 lentes de avaliação (Onda 0)"
date: "2026-07-05"
status: vivo
authors: [C]
topic: "baseline consolidado das lentes de avaliação do projeto — snapshot único pra não re-medir (Onda 0 do plano de aprofundamento)"
parent_plan: ../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md
related_adrs: ["0155", "0264", "0275", "0294"]
---

# Baseline consolidado de qualidade — 6 lentes (Onda 0)

> **O que é:** snapshot único das notas vivas do projeto, pra a Onda 0 do
> [`PLANO-APROFUNDAMENTO-AVALIACOES.md`](../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) —
> "não re-medir o que já está medido". Cada número vem de uma **fonte-de-verdade no git** (não do olho).
> Snapshot medido **2026-07-05** deste ambiente (sem CT100 — os números que exigem staging estão marcados 🔒).

## Status vivo

- status: vivo
- reviewed_at: 2026-07-05
- proximo_passo: refrescar quando qualquer fonte-de-verdade mudar; é o ponto de partida das Ondas 1-2-4-5.

## As 6 lentes

| # | Lente | Nota / estado | Fonte-de-verdade (git) | Frescor | Dono |
|---|---|---|---|---|---|
| 1 | **Module-grade** (código, 9 dims) | **média 76.7** / 36 módulos | `governance/module-grades-baseline.json` (v3.5.5) | required no CI (não regride) | Wagner |
| 2 | **SDD** (spec→código→verificação) | **~79** composto (pós-BALDE D) | `governance/sdd-scorecard.json` + roadmap `_Governanca/roadmap/_ROADMAP.md` | ~2026-07-02 | Felipe/Wagner |
| 3 | **Jana RAG** (retrieval de conhecimento) | **~46%** maturidade (11 dims) | grade em `sessions/2026-07-04-arte-rag-retrieval-conhecimento-jana.md` (PR #3814) | 2026-07-04 | Wagner |
| 4 | **RAGAS canary** (qualidade de resposta Jana) | faithfulness **1.0** · answer_relevancy **0.851** | `governance/jana-ragas-baseline.json` | 2026-07-01 | Wagner |
| 5 | **Screen-grade** (UX das telas) | **217 scorecards STALE** (Check B) | `scripts/governance/memory-health.mjs` Check B + `scripts/qa/screen-grade-report.mjs` | stale (é o alvo da Onda 1) | Wagner |
| 6 | **Memory-health** (governança de conhecimento) | **0 🔴 fail · 10 🟡 warn** | `scripts/governance/memory-health.mjs` | 2026-07-05 | Wagner |

## Onde a régua manda começar (risco × ausência-de-lente)

A régua NÃO é a nota crua — é **risco × falta de catraca** (ADR 0264/0275). Ordem que sai do baseline:

1. **Módulos Tier-0 fracos** — `Compras` **58** e `PaymentGateway` **60** (mexem em dinheiro/estoque = REGRA MESTRE). São os 2 piores E Tier-0 → Onda 2.
2. **217 telas stale** — lente existe, está velha; barato de refrescar → Onda 1.
3. Demais piores module-grade (não-Tier-0): Ponto 69, Cms 71, Fiscal 71. Melhores: Governance 88, Crm 87.

## Caveats honestos

- **SDD composto** oficial não é calculado enquanto houver métrica `not_yet_measured` no scorecard (régua honesta embutida) — o **~79** vem da última avaliação adversarial (2026-07-02), não do campo `composta` do JSON.
- **Screen-grade:** o `memory/governance/scorecards/*.yaml` tem 6 arquivos, mas o Check B conta **217** scorecards stale (a contagem vem de entradas dentro das fontes, não do nº de arquivos) — reporto o número do Check B, que é a régua viva.
- **Números 🔒 CT100** (module-grade `--detail`, perf p95, drill de restore) não foram re-medidos aqui — este baseline usa o que está **commitado** no git; a nota agregada 76.7 é a baseline oficial do CI.

---
slug: baseline-qualidade-2026-07
title: "Baseline de qualidade — lentes de avaliação consolidadas (Onda 0)"
date: "2026-07-05"
status: vivo
authors: [C]
topic: "baseline consolidado das lentes de avaliação do projeto — DoD da Onda 0 do plano de aprofundamento"
parent_plan: PLANO-APROFUNDAMENTO-AVALIACOES.md
related_adrs: ["0155", "0264", "0275", "0294", "0320"]
---

# Baseline de qualidade — lentes de avaliação (Onda 0)

> DoD da Onda 0 do [`PLANO-APROFUNDAMENTO-AVALIACOES.md`](../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md).
> Consolida o que **já está medido** pra não re-medir. Sem valores BRL. Números conferidos em 2026-07-05.

## Status vivo

- status: vivo
- reviewed_at: 2026-07-05
- proximo_passo: Onda 1 (re-grade das 217 telas stale) — a lente mais desatualizada e mais barata.

## Quadro consolidado

| Lente | Nota / estado | Fonte | Frescor | Dono |
|---|---|---|---|---|
| **module-grade** (rubrica v3, ADR 0155) | 36 módulos · média **76,7** · melhores: Governance 88, Crm 87 · piores: **Compras 58, PaymentGateway 60**, Ponto 69 | `governance/module-grades-baseline.json` (v3.5.5) | **2026-05-27** (~5 sem — o mais velho do quadro) | gate CI `module-grades` / [W] |
| **SDD** (12 métricas · streams SA/FV/KL/GT/MEM) | **Composta NÃO calculada** (5/12 `not_yet_measured`). Medido: anchor_coverage 85,3% · full_suite **298 arquivos falhando** (floor) · quarentena 26 · ghosts 8 · backfill_error 1,82% | `governance/sdd-scorecard.json` + `nightly-floor.json` | floor **2026-07-01** | `sdd-scorecard.mjs` (ADR 0275/0279) / [W] |
| **Jana RAG / RAGAS** (canary) | faithfulness **1,0** · answer_relevancy **0,851** · 51 questões · mode `real` | `governance/jana-ragas-baseline.json` | **2026-07-01** | `jana-ragas-canary.yml` (US-COPI-116) |
| **screen-grade** (comportamento · ADR 0320) | 18 telas c/ `.casos.md` · UX 66–90 · **7/18 com drift** · comportamento: só Financeiro/Unificado 100% + OficinaAuto/Board 89%; 8 telas UC 100% 🧪 (0% verde) | `node scripts/qa/screen-grade-report.mjs` | ao vivo (2026-07-05) | ratchet §7 / [W] |
| **screen-grade** (UX · baseline) | **217 scorecards STALE** (tela mudou após `graded_at`) · só 6 frescos | `memory/governance/scorecards/screens/*.yaml` (seed 2026-05-30) | baseline **2026-05-30** | Check B / [W] |
| **memory-health** | **0 🔴 fail / 10 🟡 warn** · Check B 217 stale · 90 proposals em limbo · 15 links quebrados · Check X: PaymentGateway Tier-0 sem AUDIT | `node scripts/governance/memory-health.mjs` | ao vivo (2026-07-05) | (ADR 0294/0316/0317) / [W] |

## Leitura — onde doer primeiro (risco × ausência-de-lente)

1. **module-grade é o snapshot mais velho** (27/05). Piores: Compras 58 e **PaymentGateway 60 — Tier-0 sem nenhum `AUDIT*.md`** (Check X aponta). Candidatos nº 1 da Onda 2.
2. **Nota de tela ≠ realidade:** 217 scorecards stale + telas "Leader" (Sells/Create UX 88) com comportamento 0% verde e D1 🔴. A lente UX está medida; a de comportamento está quase toda por converter (🧪→✅).
3. **SDD sem composta:** 5/12 métricas não-medidas + 298 arquivos de teste falhando no floor. `ragas_real_uptime` depende do cron semanal CT100 (relógio real).
4. **Não re-medir:** RAGAS/Jana está fresco (01/07, mode real) — o que envelhece em memory-health/screen-grade é o **baseline gravado**, não o script.

## Insumos já levantados pras próximas ondas (chips read-only 2026-07-05)

Achados de 3 chips de análise (read-only, sem CT100) — servem de ponto de partida, **não substituem** a execução com CT100:

- **Onda 1 (telas):** os 217 são o baseline inteiro seedado em 30/05, nunca re-gradeado (sweeps de junho tocaram quase todas as Pages). Work-list priorizado: Wave 1 = Sells + TransactionPayment (11 telas, coração do biz=4), Wave 2 = Financeiro (20), Wave 3 = Fiscal/NfeBrasil (16, +resolve os 7 drift). Executar via fan-out `screen-qa-specialist`, 1 módulo/agent, YAML-only, ratchet por wave.
- **Onda 2 (módulos):** **Compras** é fraco por incompletude funcional (a feature-âncora — import DF-e→Compra — não existe em código; só docs) + 2 testes tautológicos. **PaymentGateway**: a etiqueta "docs-only" em `what-oimpresso.md` está **STALE** (o módulo tem ~140 arquivos, 6 drivers REST, 11 CNAB, boleto Inter parcial em prod) — merece PR de correção; fraqueza é última-milha (webhooks nunca cortados pra prod, refund parcial, zero smoke humano). Nenhum gap exige arquitetura nova. Vários itens marcados **[REGRA MESTRE]** (valor/estoque).
- **Onda 4/5b (performance):** scan estático achou **1 N+1 grave** (`ContaReceberController.php:44` — `BoletoRemessa::first()` dentro de `.map()`, até ~100 queries/render) + ~10 telas do Financeiro (fase mai/2026) que nasceram antes da regra `inertia-defer-default` e nunca foram retrofitadas. Baseline p95 real exige OTel/Jaeger CT100.

> As catracas de cada onda (Check B < 50, Check X = 0, ratchet screen-grade) são o DoD real — relatório sozinho envelhece (ADR 0264/0275).

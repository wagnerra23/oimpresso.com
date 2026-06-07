---
slug: 0259-errata-0091-brief-modelo-gpt4o-mini
number: 259
title: "Errata 0091 — modelo do Brief é gpt-4o-mini (não Sonnet); §Geração superseded por 0097/0226"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-06-07"
module: governance
related: ['0091-daily-brief', '0097-brief-model-gpt4o-mini-supersede-parcial-0091', '0226-brief-v2-1m-aware-rico']
---

# ADR 0259 — Errata 0091 (modelo do Brief)

## Contexto

A auditoria de conflitos 2026-06-07 (PR #2390) achou contradição entre ADRs ATIVAS: o **corpo da ADR 0091** (`daily-brief`) ainda diz que o Brain B do Brief usa `claude-sonnet-4-6`, mas a decisão vigente é **`gpt-4o-mini`** (ADR 0097, 20–25× mais barato) com limite de token 8k (ADR 0226).

É supersessão **parcial** (só a §Geração de 0091 mudou; o resto do contrato do Brief vale). Como o append-only proíbe editar o corpo de uma ADR ratificada, esta **errata** é o ponteiro canônico da correção.

## Decisão

Quem lê a ADR 0091 deve ler junto esta errata: **o modelo do Brief é `gpt-4o-mini` (ADR 0097) + token limit ~8k (ADR 0226)**, NÃO `claude-sonnet-4-6`. A linha de modelo no corpo de 0091 é histórica.

## Consequências
- ✅ Resolve a "mentira do Sonnet" (PR #2270) sem violar append-only.
- ✅ Padrão de erratas (`kind: errata`) pra contradições de corpo onde supersede total não cabe (GAP 5, ADR 0258).

## Refs
ADR 0091 · 0097 · 0226 · auditoria `memory/governance/AUDITORIA-CONFLITOS-ADR-2026-06-07.md`

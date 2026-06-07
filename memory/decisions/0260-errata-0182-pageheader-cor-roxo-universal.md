---
slug: 0260-errata-0182-pageheader-cor-roxo-universal
number: 260
title: "Errata 0182 — cor primária do PageHeader é roxo universal 295 (não hue-per-grupo); §cor superseded por 0235"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-06-07"
module: governance
related: ['0182-pageheadertabs-canon-pattern-telas', '0235-ds-v4-accent-roxo-universal']
---

# ADR 0260 — Errata 0182 (cor do PageHeader)

## Contexto

A auditoria de conflitos 2026-06-07 (PR #2390) achou contradição entre ADRs ATIVAS: o **corpo da ADR 0182** (`pageheadertabs-canon-pattern-telas`) ainda manda usar `oklch(0.6 0.15 {hue})` **hue-por-grupo** no botão primário (Zona R), mas a decisão vigente é **roxo universal `oklch(0.55 0.15 295)`** (ADR 0235 DS v4 — "hue-per-grupo deixa de valer").

É supersessão **parcial** (só a regra de cor de 0182 mudou; o pattern de PageHeaderTabs vale). Append-only proíbe editar o corpo → esta **errata** é o ponteiro canônico.

## Decisão

Quem implementa tela lendo a ADR 0182 deve ler junto esta errata: **a cor primária é roxo universal `oklch(0.55 0.15 295)` (ADR 0235)**, NÃO hue-por-grupo. A instrução de hue no corpo de 0182 é histórica.

## Consequências
- ✅ Acaba o risco de tela nova nascer com cor errada (hue) por ler só 0182.
- ✅ Padrão de erratas (GAP 5, ADR 0258) pra contradição de corpo sem supersede total.

## Refs
ADR 0182 · 0235 · auditoria `memory/governance/AUDITORIA-CONFLITOS-ADR-2026-06-07.md`

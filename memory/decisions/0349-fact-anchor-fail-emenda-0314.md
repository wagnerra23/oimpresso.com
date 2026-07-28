---
slug: 0349-fact-anchor-fail-emenda-0314
number: 349
title: "Emenda à 0314 — fact-anchor (Check T) promovido a FAIL-class (fato que contradiz a fonte bloqueia)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-23"
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, fact-anchor, apodrecimento, derivacao, memory-health]
supersedes: []
superseded_by: []
related: [0314-poda-gates-onda-2-lei-fusoes, 0327-anchor-content-required-emenda-0314, 0347-deadlink-gate-required-emenda-0314, 0256-knowledge-survival-meia-vida-catraca-sentinela, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
---

# ADR 0349 — Emenda à 0314: `fact-anchor` (Check T) promovido a FAIL-class

## Contexto

Item 4 da grade anti-apodrecimento (sessão 2026-07-23): o `fact-anchor` (Check T de `memory-health.mjs`) ancora fatos afirmados em docs de entrada (versões de stack, `Modules/<Nome>`) na fonte-de-verdade versionada (`package.json`/`composer.json`/árvore `Modules/`) e devolve as contradições — determinístico, sem LLM, **major-only** de propósito (a constraint do composer é um floor, não o runtime — `^8.1` vs "PHP 8.4" não falsa-positiva). Combate o apodrecimento de conteúdo: o fato escrito à mão que descreve um passado que já mudou (ex.: `what-oimpresso.md` dizia "4 Agents" quando eram ~14).

Ele nasceu **🟡 warn** ([#4707](https://github.com/wagnerra23/oimpresso.com/pull/4707), ADR 0275 advisory-primeiro), com a própria mensagem dizendo *"promover a fail quando maduro"*. O [#4708](https://github.com/wagnerra23/oimpresso.com/pull/4708) de-numberficou os fatos falsos conhecidos → o corpus está em **zero contradição viva**.

## Decisão

O Check T (`fato-ancora-drift`) passa de `warns.push` → `fails.push` no `memory-health.mjs`, e o `catch` de erro do check vira **fail-safe** (`fails.push`, como o Check Q document-authority) — se o guardião de fato crasha, bloqueia. Como o Check T vive **dentro** do `memory-health` (já required via "Governance Gate"), isto o torna efetivamente required **sem tocar a branch protection** — diferente do deadlink-gate (0347), que era workflow novo.

Exceção consciente à regra "required = só Tier-0" da [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) — a **3ª** (após 0327 anchor-content e 0347 deadlink), pelo mesmo processo: emenda ADR reabrindo a 0314 pro item + flip [W].

**Autorização [W]:** "a OK" (2026-07-23), sobre a recomendação de que o item estava pronto (determinístico, testado, zero contradição viva).

## Segurança da promoção (provado, não afirmado)

1. **Zero contradição viva** na promoção: `memory-health` = 0 🔴 antes e depois da troca (senão travaria todo PR).
2. **Morde de verdade** (controle-negativo): injetar "Laravel 99.9" em `what-oimpresso.md` → Check T emite 🔴 e `memory-health` sai exit≠0; doc restaurado. Fixtures boa/ruim em `fact-anchor.test.mjs` cobrem a lógica de detecção.
3. **Major-only** evita o falso-positivo floor-vs-runtime.

## Gate de reversão

Falso-positivo que trave PR legítimo → `fails.push` volta a `warns.push` via PR de demoção + nota aqui (a 0314 permanece a lei; esta emenda só a estende). Não silenciar um FP editando o doc-fonte pra "passar".

## Consequências

- Fato de stack/módulo que contradiz a fonte **bloqueia merge** — a derivação deixa de ser só detectada e passa a ser enforçada (o padrão Swimm/Tessl da grade: o fato é ancorado na fonte, não afirmado à mão).
- Item 4 da grade fecha o ciclo detecta→**morde**. Restam abertos: o backfill de `id:` físico + auto-religador (item 2, decisão [W], em outra sessão).

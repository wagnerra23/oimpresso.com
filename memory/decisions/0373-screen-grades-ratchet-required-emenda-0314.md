---
slug: 0373-screen-grades-ratchet-required-emenda-0314
number: 373
title: "Emenda à 0314 — screen-grades-ratchet a required: exceção soberana [W] com a DR-2 da 0336 dispensada e o prazo da 0369 antecipado"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-10"
module: governance
quarter: 2026-Q3
tags: [governanca, gates, required, screen-qa, emenda-0314, excecao-soberana, catraca]
supersedes: []
supersedes_partially: []
superseded_by: []
amends:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314
related:
  - 0327-anchor-content-required-emenda-0314
  - 0250-screen-qa-specialist-sustentavel
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# ADR 0373 — `screen-grades-ratchet` a required (emenda à 0314)

## Contexto

A catraca [`screen-grades-ratchet.mjs`](../../scripts/qa/screen-grades-ratchet.mjs) impede que a nota 16-dim de uma tela **desça** vs `origin/main`. Nasceu advisory por força da [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md), cuja política é **required = só Tier-0** (dinheiro/PII/multi-tenant/fiscal) — e nota de tela é *quality*.

Em 2026-08-10, ao fechar o gap #22 (scorecard órfão), mediu-se que a catraca tinha um **vetor de fuga**: o laço itera `readdirSync` do lado do PR, então **apagar** o scorecard passava por baixo dela. Fechado no [#5552](https://github.com/wagnerra23/oimpresso.com/pull/5552), com FP medido 0/258 e bite provado por mutação.

## A medição, registrada por inteiro — inclusive contra esta decisão

A régua que a [ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) **DR-2** impõe para promover é **≥2 mordidas reais**. Medido no histórico completo de `origin/main`:

| medição | resultado |
|---|---|
| Modificações de scorecard | **241** |
| └ nota subiu | 126 |
| └ nota igual | 115 |
| └ **nota caiu** ← o que a catraca existe pra barrar | **0** |
| Deleções de scorecard | **258** |
| └ com `.tsx` morto junto (legítimo) | 258 |
| └ **com `.tsx` vivo (fuga)** | **0** |

**Mordidas reais: 0.** A DR-2 exige ≥2.

Isto é a mesma situação que o §5 lapidou **duas vezes**: o `foundation-ratchet` (2026-07-01 — *"0 failures em 300+ runs"*, e a própria 0314 o **rebaixou** de required) e o `component-registry-check` (2026-07-17 — *"DR-2 exige ≥2; há 0"*). O agente **recomendou não promover** e apresentou estes números; [W] reafirmou a promoção.

## Esta decisão antecipa uma decisão anterior do próprio [W]

A [ADR 0369](0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md) (**2026-08-05**, 5 dias antes) promoveu 3 lanes Pest a required e **deixou este gate de fora de propósito**, classificando-o entre os de *"amostra pequena demais (5 a 28 runs)"*. O `gates-registry.json` registrava a razão textual: *"apenas 28 runs (medido 2026-08-05) — amostra insuficiente; decidir no escuro seria pior que esperar"*, com `promote_by: 2026-09-05`.

Ou seja: **o prazo não venceu, e a razão do adiamento não mudou** — a amostra segue pequena e as mordidas seguem em 0. O que mudou foi a decisão do dono. Registrado aqui porque, sem isto, uma sessão futura leria a 0369 e a 0373 como contraditórias sem saber qual vale.

## Decisão

Promover **`Nota de tela não desce vs origin/main`** a **required**, como **exceção soberana [W]** à política da 0314, com a **DR-2 da 0336 explicitamente dispensada** e o **`promote_by` de 2026-09-05 da 0369 antecipado**.

Precedente de forma: [ADR 0327](0327-anchor-content-required-emenda-0314.md) — a 1ª exceção formal à 0314, também com a janela dispensada por [W]. A política vigente desde a 0327 já é *"required = Tier-0 **+** exceções explicitamente autorizadas via emenda + flip [W]"*, e esta é a 2ª exceção.

**Esta ADR não afirma que a evidência sustenta a promoção.** Ela registra que o dono decidiu promover **sabendo** que não sustenta. A distinção importa: uma sessão futura que ler só a decisão sem os números repetiria o erro do `foundation-ratchet` achando que havia base.

## Pré-condições mecânicas (todas obrigatórias, nenhuma opcional)

1. **Remover `paths:`** do workflow. Required + `paths:` = o check **não nasce** em PR que não toca o path → `Expected — waiting for status` **permanente** → deadlock do repo. Foi o incidente de 2026-08-08, que travou `main` por 2 dias. O invariante vigente (`required-always-run.mjs`) passa a **43 required · 43 always-run · 0 filtrado**.
2. **Tirar `(advisory)` do nome do job** — [LC-10](../LICOES_CODE.md): artefato não afirma o próprio enforcement em presente, e no flip a palavra vira mentira. O rename muda o **context**, então é o mesmo vetor do item 1 para PRs já abertos.
3. **`gh pr update-branch` em todos os PRs abertos** após o flip — PR cujo head foi computado antes espera por um check que não vai nascer.
4. **Flip via `gh api --input <arquivo UTF-8 sem BOM>`**, nunca payload inline no shell Windows (mojibake — 22 dos 42 contexts têm não-ASCII), seguido de `node scripts/governance/protection-drift.mjs` para validar string-exata.

## Consequências

**Aceitas:**
- O job passa a rodar em **todo** PR (era filtrado por 3 paths). Custo medido nas últimas 5 execuções: **33s a 9min**, variável.
- Um gate que nunca ficou vermelho em 241 oportunidades passa a poder bloquear merge.

**Risco declarado:** required sobre gate de superfície quase-parada é o padrão que a 0314 podou. Se ele nunca disparar, é carimbo — e carimbo required custa fila de CI sem comprar defesa.

## Gate de reversão

Rebaixar a advisory (via emenda nova, não no calado) se qualquer um ocorrer:

- **falso-positivo** que bloqueie PR legítimo;
- **90 dias** sem uma única mordida real (o relógio começa no merge desta ADR);
- a fila de CI se tornar o gargalo e este job estiver entre os mais caros.

O contador de mordidas é o próprio veredito do job: `🔻 N regrediram` ou `🚨 N suspeita(s)`.

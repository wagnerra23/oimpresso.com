---
slug: 0347-deadlink-gate-required-emenda-0314
number: 347
title: "Emenda à 0314 — deadlink-gate promovido a REQUIRED (integridade referencial doc↔doc como exceção consciente)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-23"
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, integridade-referencial, apodrecimento, branch-protection, deadlink]
supersedes: []
superseded_by: []
related: [0314-poda-gates-onda-2-lei-fusoes, 0327-anchor-content-required-emenda-0314, 0336-gates-design-promocao-por-mordida-provada-emenda-0314, 0256-knowledge-survival-meia-vida-catraca-sentinela, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
---

# ADR 0347 — Emenda à 0314: `deadlink-gate` promovido a REQUIRED

## Contexto

A [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) fixou a política **"required = só Tier-0"** (dinheiro/PII/multi-tenant/fiscal). A [ADR 0327](0327-anchor-content-required-emenda-0314.md) abriu a 1ª exceção formal (anchor-content-check) pelo processo correto: emenda ADR reabrindo a 0314 pro item + flip [W]. Esta é a **2ª exceção**, pelo mesmo processo.

**Sinal de custo (medido, sessão 2026-07-23):** varredura no corpus vivo achou **~1.105 links markdown internos mortos em 571 arquivos** de `memory/` (7,5% de ~17,9k links), com **zero checker doc↔doc no CI** (o `memory-health` Check V vigiava só um subconjunto front-facing, advisory). Mecanismo demonstrado: arquivos renomeados/movidos/purgados (ex.: `requisitos/Copiloto/` → Jana [ADR 0088](0088-module-rename-php-only.md); auto-mem purgada 2026-06-07) deixam quem os referenciava apontando pro vazio, sem aviso. Uma grade de estado-da-arte anti-apodrecimento (2026-07-23, com fontes) mediu integridade referencial (D1) como a **única dimensão vermelha** do oimpresso (35/100); o padrão de mercado (lychee/Docusaurus/Antora) é **link morto falha o build**.

O gate `deadlink-gate` ([PR #4700](https://github.com/wagnerra23/oimpresso.com/pull/4700)) fecha esse buraco: ratchet POR ARQUIVO contra `governance/deadlink-baseline.json` (dívida grandfathered — boa parte vive em ADR/handoff **append-only**, proibido editar), reprova só quem PIORA, história nunca enforça, case-sensitive sempre (paridade CI Linux). Bite-test hermético prova a mordida.

## Decisão

`deadlink-gate` (context de CI **`deadlink-gate (ratchet · integridade referencial)`**) passa a **required** na branch protection de `main`, como exceção consciente à regra "required = só Tier-0" da 0314.

**Justificativa que a régua Tier-0 não pesa (mesmo espírito da 0327):** o apodrecimento referencial é **reincidência estrutural medida** (1.105 mortos acumulados por anos sem gate), e advisory que deixa o erro reincidir é o oposto de gate que morde. A política vigente passa a ser **"required = Tier-0 + exceções explicitamente autorizadas via emenda + flip [W]"**.

**Override de cadência (consciente, [W]):** a doutrina de "mordida provada antes de promover" ([ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)) recomenda coletar mordidas reais antes do flip. Aqui [W] autorizou promoção imediata ("promova", 2026-07-23) — precedente idêntico ao override do baseline `required-checks-baseline.json` (#3444, "promova agora"). O risco residual (0 mordidas reais além do bite-test) é mitigado por: (a) ratchet grandfather (não reprova a dívida existente, só a piora); (b) require-safe sem paths-filter; (c) o **gate de reversão** abaixo.

## Require-safe (pré-condições do flip)

1. **Sem paths-filter** — o workflow roda em TODO PR (o `--check` dá exit 0 quando nada piora); um required com paths-filter travaria PRs que não tocam docs. Feito neste PR.
2. **Nome não mente** — o job foi renomeado de `deadlink-gate (ratchet advisory)` → `deadlink-gate (ratchet · integridade referencial)` (lição P14: o nome do required não pode afirmar "advisory").
3. **Flip da branch protection = ato [W]** (R10 / ADR 0275 §5): adicionar o context ao `required_status_checks` de `main` + a entrada em `governance/required-checks-baseline.json`, pelo procedimento anti-mojibake (`gh api --input` com arquivo UTF-8 sem BOM, `node scripts/governance/protection-drift.mjs` pós-PUT). NÃO é feito por agente.

## Gate de reversão

Se o gate produzir **falso-positivo** que trave PR legítimo (ex.: parser marca morto um link que resolve em runtime), a demoção a advisory é imediata via PR editando `required-checks-baseline.json` (removendo o context) + nota nesta ADR — sem nova ADR-mãe (a 0314 permanece a lei; esta emenda só a estende). O baseline `deadlink-baseline.json` NÃO deve ser inflado pra "passar" um FP (isso é `baseline-tamper` — vigiado).

## Consequências

- Integridade referencial doc↔doc deixa de apodrecer em silêncio: novo link morto (arquivo novo ou existente piorando) reprova merge.
- A dívida dos 1.105 é limpa gradualmente (ratchet só-desce; melhorar um arquivo destrava re-baseline).
- Relação com o `memory-health` Check V: este gate o **generaliza** pro corpus vivo inteiro com mordida; Check V segue como sentinela do subconjunto front-facing (não duplica — declarado no `gates-registry.json`).

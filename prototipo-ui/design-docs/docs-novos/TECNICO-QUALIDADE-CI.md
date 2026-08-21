---
id: reference-tecnico-qualidade-ci
name: Técnico — Qualidade e CI
description: A régua que o CI cobra de verdade — baselines que só descem, gates diff-aware, doutrina de teste — e como saber, sem adivinhar, qual comando reprovou.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: tecnico
nav_order: 50
lente: [construir]
---

# Técnico — Qualidade e CI

> Régua aqui não é opinião: é **script com nome**. O inventário vivo dos gates está em
> [`gates-registry.json`](../../scripts/governance/gates-registry.json) — esta página explica a
> lógica, não repete a lista.

## Comandos do dia a dia

| Comando | O que garante |
|---|---|
| `npm run typecheck` | TypeScript sem erro (`tsc --noEmit`) |
| `npm run lint:baseline:check` | ESLint **não piora**: dívida antiga congelada, nova barrada |
| `npm run stylelint:baseline:check` | o mesmo para CSS |
| `npm run css:size:check` | orçamento de bytes do CSS não estoura |
| `npm run pageheader:guard` | a migração de PageHeader não regride |
| `npm run ds:report` | conformância com o design system |
| `npm run docs:loop` | saúde da documentação (órfão, staleness, frescor) |
| `npm run docs:relink` | link-rot detectado antes de virar 404 |
| `npm run handoff:check` | integridade do handoff append-only |

## Baseline é catraca, não permissão

Uma baseline fotografa a dívida existente pra que ela **não bloqueie** o trabalho de hoje — e
para que **nada pior** entre amanhã. Ela só desce. Regravar baseline pra "passar o CI" é forjar a
métrica, e métrica-de-forma é proibida.

## Gates diff-aware — por que tocar um arquivo acorda o CI

Vários gates só olham o que mudou. Consequência prática: **tocar** um documento antigo põe a
dívida pré-existente dele no diff e o gate morde — mesmo que sua alteração seja inofensiva. Por
isso o carimbador de `id` tem lista de prefixos tóxicos e **defere** em vez de forçar
([`doc-id-stamp.mjs`](../../scripts/governance/doc-id-stamp.mjs)). Ao mexer em documento de
família guardada (spec, runbook, charter, requisitos), espere o gate — ou não toque.

## Schema de frontmatter

Cada família tem schema AJV em [`scripts/memory-schemas/`](../../scripts/memory-schemas/), com o
mapa glob→schema em [`validate.mjs`](../../scripts/memory-schemas/validate.mjs). Famílias
`strict` reprovam; `reference` e `BRIEFING` são **grace** (avisam, não travam) até o backfill
fechar ([ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md)).

## Doutrina de teste

- **Pest** é o runner; teste roda no CT 100, nunca no shared
  ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).
- Tenant de teste é o **98** — ver [Dados e multi-tenant](TECNICO-DADOS-MULTITENANT.md).
- Eval de IA roda o pipeline **de verdade**: a versão que comparava o gabarito consigo mesmo
  passava sempre e foi morta por isso
  ([ADR 0318](../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md)).

---
last_validated: "2026-10-13"
slug: runbook-recurring-billing-index
title: "RUNBOOK — Cobrança Recorrente: os 4 Index (Assinaturas · Planos · Faturas · Configurações)"
type: runbook
module: RecurringBilling
page: /recurring-billing
component: resources/js/Pages/RecurringBilling/Index.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — Cobrança Recorrente: os 4 Index

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para telas que
> **já estão em React**. Criado na thread 01 do playbook Recorrente (contratos de tela), que só põe atributo
> `data-contract` nas Pages: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e estas
> não tinham. Um RUNBOOK cobre as 4, porque o hook aceita `RUNBOOK-<modulo>-index.md` para `<Mod>/<Sub>/Index.tsx`.

## 1. Estrutura

| rota | Page | charter / casos |
|---|---|---|
| `/recurring-billing` | `RecurringBilling/Index.tsx` (assinaturas) | `Index.charter.md` · `Index.casos.md` |
| `/recurring-billing/planos` | `RecurringBilling/Planos/Index.tsx` | idem na pasta |
| `/recurring-billing/faturas` | `RecurringBilling/Faturas/Index.tsx` | idem na pasta |
| `/recurring-billing/configuracoes` | `RecurringBilling/Configuracoes/Index.tsx` | idem na pasta |

Controllers em `Modules/RecurringBilling/Http/Controllers/`; props caras chegam por `Inertia::defer` (kpis, listas,
gateways).

## 2. Âncoras de contrato

| Page | âncoras (ordem) | contrato |
|---|---|---|
| Assinaturas | `cabecalho` · `kpis` · `filtros` · `lista` | `recorrente-assinaturas.contract.json` |
| Planos | `cabecalho` · `kpis` · `ciclos` · `lista` | `recorrente-planos.contract.json` |
| Faturas | `cabecalho` · `kpis` · `filtros` · `lista` | `recorrente-faturas.contract.json` |
| Configurações | `cabecalho` · `gateways` · `dunning` · `nfe` · `webhooks` | `recorrente-configuracoes.contract.json` |

Rodar: `node scripts/contrato-de-tela.mjs --contract governance/design/contracts/<arquivo>`. As âncoras são só
atributos e não mudam dado, handler, copy nem classe. Em Configurações o atributo é repassado pelo `SectionCard`
ao `<section>` que ele renderiza.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

O escopo por `business_id` é dos controllers; as Pages só mostram o que recebem. Mudança em controller passa pelos
testes de contrato de cada tela (`*.casos.md`).

## 4. O que estas telas NÃO fazem (diferenças com o protótipo, decisão [W])

- Não têm a barra de sub-navegação do Financeiro (`ph-nav`) que o protótipo mostra em Planos, Faturas e
  Configurações; Assinaturas navega pelas abas do próprio cabeçalho.
- Assinaturas não tem a barra de período (`fin-periodbar`); o filtro "Próxima cobrança" fica na coluna de filtros.

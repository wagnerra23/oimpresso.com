---
last_validated: "2026-10-13"
slug: runbook-comunicacao-visual-index
title: "RUNBOOK — /comunicacao-visual (Orçamento por m²)"
type: runbook
module: ComunicacaoVisual
page: /comunicacao-visual
component: resources/js/Pages/ComunicacaoVisual/Index.tsx
status: rascunho
updated_at: 2026-10-13
version: 0.1
owner: W
---

# RUNBOOK — /comunicacao-visual (Orçamento por m²)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 01 do playbook Módulos faltantes (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter: [`Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md) · casos:
> [`Index.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.casos.md) · SDD:
> [`SDD-tela-orcamento-m2-v1.0.md`](SDD-tela-orcamento-m2-v1.0.md).

## 1. Estrutura

- Rota em `Modules/ComunicacaoVisual/Routes/web.php` (`Inertia::render('ComunicacaoVisual/Index')`).
- Calculadora de m² no cliente; "Conferir no servidor" recalcula no backend (a conta do servidor é a que vale).

## 2. Âncoras de contrato

| âncora | elemento |
|---|---|
| `cabecalho` | `<div>` sem classe em volta do `PageHeader` canônico (que não repassa atributos ao DOM) |
| `calculadora` | `<Card>` da calculadora (repassa o atributo ao `<div>`) |
| `ajustes` | grade de acabamento/instalação/entrega e desconto |
| `total` | total estimado + conferir no servidor |
| `em-breve` | `<Card>` "Em breve nesta tela" |

Contrato: `governance/design/contracts/comunicacao-visual.contract.json`. Rodar:
`node scripts/contrato-de-tela.mjs --contract <arquivo>`. As âncoras são só atributos: não mudam dado, handler, copy,
classe nem cálculo.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

Catálogo de materiais e preço vêm escopados por `business_id` da rota; a Page só mostra o que recebe. Qualquer mudança
no cálculo segue a regra mestre de valor (`memory/proibicoes.md`): dupla confirmação e antes→depois.

## 4. O que esta tela NÃO faz

- Não tem o bloco "Na fábrica agora" (PCP) do protótipo, nem "Salvar orçamento" / "Enviar PDF no WhatsApp".
  Construir ou podar é decisão de produto ([W]).

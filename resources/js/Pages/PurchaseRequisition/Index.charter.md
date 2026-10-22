---
id: resources-js-pages-purchaserequisition-index-charter
page: /purchase-requisition
component: resources/js/Pages/PurchaseRequisition/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/compras-extras.jsx
related_us: [US-MWART-008]
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Compras
related_adrs: [66, 93, 104, 358]
tier: B
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/compras-extras.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [RequisicoesCompra]
  divergence_from_blueprint: "sem o KPI 'Itens requisitados' nem a coluna 'Pedido gerado' (a Blade não tem); ver e excluir na tela clássica; coluna 'Fornecedor' a mais (US-COM-027)"
related_runbook: memory/requisitos/Compras/_telas/RUNBOOK-purchase-requisition-index.md
---

# Page Charter — /purchase-requisition (DRAFT)

> **Status:** draft. Thread `compras/playbook/07` (Requisições de compra). `GET /purchase-requisition` responde Inertia só
> com a flag `useV2ComprasRequisicoes` ligada para o negócio; desligada, ou com `?legacy=1`, a Blade
> `purchase_requisition/index` segue. Vira `live` no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).
> Âncora de design: `compras-extras.jsx` → `telaReqs()` (rota `cmp-requisicoes`), alvo medido em
> `governance/design/targets/compras--requisicoes--index.alvo.json` (thread A2).

## Mission

Mostrar ao comprador as requisições internas de reposição do negócio — de qual local, em que status e para quando o
material é necessário — com o mesmo escopo e as mesmas datas que a Blade mostra.

## Goals

- Lista das requisições (data, nº de referência, local, status, necessário até, quem adicionou) com os filtros de local
  e status da Blade.
- O mesmo SQL da Blade (`PurchaseRequisitionController::consultaRequisicoes`) e as datas formatadas pela mesma expressão
  do `@format_datetime` (com o shift da ADR 0066).
- Coluna Fornecedor (`transactions.contact_id`, razão social ou nome; vazio sem fornecedor): a requisição pelo
  estoque mínimo nasce com o da última compra do item (US-COM-027 · UC-REQMIN-07 · UC-REQC-06). Não está no
  protótipo; o comprador ainda escolhe o fornecedor ao gerar o pedido.
- KPIs: requisições sem pedido ainda (`ordered`) e parcialmente pedidas (`partial`), contadas na lista.
- Criar (com `purchase_requisition.create`).

## Non-Goals

- ❌ Formatar data no navegador: a data chega formatada pelo controller.
- ❌ Ver o detalhe ou excluir nesta tela antes de PR próprio — seguem na tela clássica (`?legacy=1`).
- ❌ KPI "Itens requisitados" e coluna "Pedido gerado" do protótipo: a lista da Blade não tem (decisão [W]).
- ❌ Editar requisição: a rota não tem edit/update.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só requisições do negócio da sessão | `PurchaseRequisitionController::consultaRequisicoes` |
| R2 | Com a flag desligada, ou com `?legacy=1`, a rota devolve a Blade | `PurchaseRequisitionController::index` (`FLAG_V2`) |
| R3 | Sem `access_all_locations`, só requisições dos locais permitidos | `consultaRequisicoes` (`permitted_locations`) |
| R4 | Com `view_own` sem `view_all`, só requisições que o usuário criou | `consultaRequisicoes` |
| R5 | "Necessário até" = `delivery_date` pela expressão do `@format_datetime`; vazio sai vazio | `formatarDataComoBlade` |

## Refs

RUNBOOK: [`RUNBOOK-purchase-requisition-index.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-requisition-index.md) · paridade: [`purchase-requisition-parity.md`](../../../../memory/requisitos/Compras/_telas/purchase-requisition-parity.md) · ADR 0066 · ADR 0093 · ADR 0104 · ADR 0358.

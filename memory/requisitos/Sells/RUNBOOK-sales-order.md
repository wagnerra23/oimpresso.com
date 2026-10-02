---
slug: sells-runbook-sales-order
title: "Sells — Runbook da tela Pedido de venda /sales-order (migração MWART)"
type: runbook
module: Sells
tela: SalesOrder/Index
status: ativo
owner: W
last_validated: "2026-10-01"
date: 2026-10-01
preconditions:
  - "Permissão so.view_all, so.view_own ou so.create (mesma trava do Blade)"
  - "Endpoint AJAX /sells?sale_type=sales_order (SellController@index) preservado"
  - "Status pelos endpoints existentes edit-/update-sales-orders/{id}/status"
steps:
  - "SalesOrderController@index ganha branch X-Inertia → Inertia::render('SalesOrder/Index')"
  - "view('sales_order.index') segue como fallback (cutover F5 é humano)"
  - "Page lê as linhas do mesmo endpoint AJAX do Blade"
  - "Editar status abre drawer PT-02 e chama o PUT existente"
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/venda-blade-telas.jsx (VendaPedidos)"
  derived_from: "Sells/Drafts (lista dual com endpoint AJAX legado)"
  alvo_medido: "governance/design/targets/vendas--pedidos--index.secoes.json"
---

# RUNBOOK — Pedido de venda (`/sales-order`)

> **Tipo:** runbook MWART (Blade → Inertia/React) · thread 06 do playbook `venda-menu`.
> **Refs:** ADR 0104 (MWART), ADR 0093 (multi-tenant), ADR 0358 (tenant 98 em teste).
> **Estado origem:** Blade `sales_order.index` via `SalesOrderController@index`; linhas pelo
> DataTable `/sells?sale_type=sales_order` (`SellController@index`, ramo `ajax && !X-Inertia`).
> **Estado alvo:** `resources/js/Pages/SalesOrder/Index.tsx` (PT-01 Lista + drawer PT-02 de status).

## 1. Objetivo

Trocar a casca Blade da lista de pedidos de venda pela Page React, sem rota nova e sem mudar
a regra de quem vê o quê. A lista continua vindo do mesmo endpoint que o Blade usa, então os
filtros de permissão (`so.view_own` vê só os próprios, locais permitidos, `business_id`) ficam
num lugar só.

## 2. Pré-condições

- Permissão `so.view_all`, `so.view_own` ou `so.create` (senão 403, igual ao Blade).
- O item de menu continua condicionado a `enable_sales_order` em
  `app/Http/Middleware/AdminSidebarMenu.php` — esta migração **não** toca o menu.
- Escopo `business_id` da sessão em toda consulta (Tier 0).

## 3. Passo a passo

1. `SalesOrderController@index`: depois da trava de permissão, se `X-Inertia` →
   `Inertia::render('SalesOrder/Index', [...])` com locais, status, status de envio,
   permissões, `salesOrderEnabled` (lido de `business.pos_settings`) e as URLs.
   Clientes vão em `Inertia::defer` (dropdown grande).
2. Sem `X-Inertia` → `view('sales_order.index')` como hoje (fallback até o cutover F5).
3. A Page busca `/sells?sale_type=sales_order&...` com `Accept: application/json` +
   `X-Requested-With: XMLHttpRequest`, como `Sells/Drafts`.
4. "Editar status" só aparece na linha que o endpoint marca como editável (`edit-so-status`:
   admin e status ≠ concluído) e abre um drawer lateral (PT-02). Salvar = `PUT
   /update-sales-orders/{id}/status`; a linha muda no lugar, sem recarregar a lista.

## 4. Verificação

- Pest: `tests/Feature/Sells/SalesOrderIndexContratoTest.php` (lane `sells-pest.yml`).
- `node scripts/design/ds-guard.mjs resources/js/Pages/SalesOrder/Index.tsx`.

## 5. Fora deste runbook

- Gerar venda a partir do pedido (fica no Create de venda, como hoje).
- Cutover F5 (remover o Blade) — humano, com aviso ao cliente.

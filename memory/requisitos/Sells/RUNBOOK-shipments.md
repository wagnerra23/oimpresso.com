---
slug: sells-runbook-shipments
title: "Sells — Runbook da tela Remessas /shipments (migração MWART, thread 02 do playbook venda-menu)"
type: runbook
module: Sells
tela: Sells/Shipments/Index
status: ativo
owner: W
last_validated: "2026-10-01"
date: 2026-10-01
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0]
---

# RUNBOOK — Remessas (`/shipments`)

> **Tipo:** runbook MWART (Blade → Inertia/React), thread 02 do playbook
> `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/` (00-INDICE.md §2).
> **Estado origem:** Blade `sell.shipments` via `SellController@shipments`; a lista vem do
> DataTables de `SellController@index` (`GET /sells?only_shipments=true`, AJAX sem `X-Inertia`);
> o status abre o modal Blade `sell.partials.edit_shipping` (`GET /sells/edit-shipping/{id}`)
> e salva em `PUT /sells/update-shipping/{id}`.
> **Estado alvo:** `Pages/Sells/Shipments/Index.tsx` (PT-01 Lista) + drawer PT-02 de edição
> da remessa (o modal Blade vira Sheet lateral — proibido modal full-screen pra detalhe).
> **Golden:** `Sells/Drafts.tsx` + `SellController@getDrafts` (padrão dual).
> **Persona:** quem monta o romaneio e o entregador — fila de entrega.

## 1. Objetivo

Trocar a tela Blade por PT-01 sem mudar comportamento: mesmos filtros, mesma fonte de dados,
mesmas permissões e o MESMO endpoint de escrita. Nenhuma rota nova.

## 2. Pré-condições

- [ ] Permissão `access_shipping` · `access_own_shipping` · `access_commission_agent_shipping` (ou admin) — a mesma checagem do método Blade
- [ ] `GET /sells?only_shipments=true` (AJAX) segue devolvendo DataTables JSON — preservado, não editado
- [ ] Multi-tenant: toda leitura/escrita por `transactions.business_id = session('user.business_id')` (ADR 0093)

## 3. Passo-a-passo

1. **`SellController@shipments` dual.** `if (request()->header('X-Inertia')) return Inertia::render('Sells/Shipments/Index', …)`;
   o `view('sell.shipments')` fica como fallback (cutover F5 é humano). Props: `shippingStatuses`,
   filtros (`businessLocations`, `customers` deferido, `salesRepresentative`, `deliveryPersons`),
   rótulos dos campos personalizados de remessa, `permissions` e `urls`.
2. **`SellController@editShipping` responde JSON quando o cliente pede JSON** (`wantsJson()` e sem
   `X-Inertia`). É o endpoint de dados do drawer — o modal Blade continua recebendo HTML
   (jQuery `dataType: 'html'` manda `Accept: text/html`). Mesma checagem de permissão e mesmo
   `where business_id` do método.
3. **Lista** — `fetch('/sells?only_shipments=true&…')` com `Accept: application/json` +
   `X-Requested-With: XMLHttpRequest` (padrão Drafts). Paginação/ordem/busca no servidor
   (parâmetros DataTables `start`/`length`/`order`/`search`). Status e pagamento vêm como HTML
   no JSON legado: o texto é extraído por `DOMParser` (não executa script).
4. **Drawer** — `Sheet` lateral 760px: status de envio, entregador, entregue a, detalhes de envio,
   endereço de entrega, campos personalizados nomeados pelo negócio e observação da alteração.
   Salva por `PUT /sells/update-shipping/{id}` (JSON + `X-CSRF-TOKEN`).
5. **Romaneio** — `printSaleReceipt({ mode: 'packing_slip' })` (`/sells/{id}/print?package_slip=true`),
   só com `print_invoice`.

## 4. Fora desta thread (declarado)

- Upload/listagem de **documentos da remessa** (dropzone `shipping_document`) e o histórico de
  atividades do modal Blade — continuam no Blade/`Sells/Show`.
- Filtro de **garçom** (`service_staffs`) — só existe com o módulo `service_staff`; não está no charter.

## 5. Estados

| Estado | Gatilho | UI |
|---|---|---|
| carregando | fetch da lista | linha "Carregando remessas…" |
| vazio | 0 linhas com o filtro | `EmptyState` "Nenhuma remessa com esses filtros" |
| erro | fetch falhou | `EmptyState` variante erro + "Tentar de novo" |
| 403 | sem permissão de remessa | `abort(403)` no backend |

## 6. Teste

`tests/Feature/Sells/SellsShipmentsContratoTest.php` (lane `sells-pest.yml`, MySQL) — UC-REM-01..07
do `Sells/Shipments/Index.casos.md`. Tenant 98 × 99, nunca biz=4.

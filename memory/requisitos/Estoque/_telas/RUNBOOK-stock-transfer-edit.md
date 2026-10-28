---
title: "RUNBOOK — `stock_transfers/edit` (FORM EDIT)"
owner: W
last_validated: "2026-10-27"
tela: stock_transfers/edit
modulo: Inventory / StockTransfer
tipo: FORM EDIT
generated_at: 2026-10-27
generated_by: "[C]"
status: ativo
adr_refs: [0104, 0093]
blueprint_cowork: prototipo-ui/cowork/Wagner/estoque-forms.jsx
---

# RUNBOOK — `stock_transfers/edit` (FORM EDIT)

## 1. Contexto
- **Rota:** `GET /stock-transfers/{id}/edit` · grava em `PUT /stock-transfers/{id}`
- **Controller:** `App\Http\Controllers\StockTransferController@edit` (+ `editInertia`) e `@update`
- **Blade legacy:** `resources/views/stock_transfer/edit.blade.php` (só com `?classico=1` até o cutover)
- **Inertia destino:** `resources/js/Pages/StockTransfer/Edit.tsx`
- **Protótipo:** `FormTransferencia` (create + edit) em `prototipo-ui/cowork/Wagner/estoque-forms.jsx:253`

## 2. Persona
Quem cuida do estoque e corrige uma transferência ainda não concluída.

## 3. Multi-tenant Tier 0
- `edit()` busca `sell_transfer` e `purchase_transfer` com `business_id` da sessão; outra empresa cai no `findOrFail` (404).
- As linhas vêm de `dadosDaLinha()`, que resolve a variação com `p.business_id`.
- Permissão `purchase.create` (a mesma do `update()`).

## 4. Props
| Prop | Origem |
|---|---|
| `transferencia` | `sell_transfer` + `purchase_transfer` + linhas (`dadosDaLinha` sem exigir saldo, quantidade gravada ÷ multiplicador, sub-unidade, lote, `transaction_sell_lines_id`) |
| `business_locations` | `BusinessLocation::forDropdown` |
| `statuses` | `stockTransferStatuses()` |
| `permissions` | `view_purchase_price`, `edit_purchase_price` |

## 5. O que muda e o que fica travado (paridade com a Blade)
- Muda: data, status, itens, frete, observação.
- Travado: origem, destino, referência.
- Concluída não abre (o `edit()` filtra `status != final`) e o `update()` recusa (R-XFER-003).

## 6. Envio
Mesmo formato do Create (UC-TRCRT-03/06): números por `paraNumUf`, `base_unit_multiplier` com ponto, `final_total` com ponto em 2 casas, data ISO (lida por `uf_datetime_input` no `update()`).

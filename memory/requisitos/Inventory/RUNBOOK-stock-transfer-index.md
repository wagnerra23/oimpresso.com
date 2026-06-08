---
tela: stock_transfers/index
modulo: Inventory / StockTransfer (raiz UltimatePOS)
tipo: LIST
generated_at: 2026-05-15
generated_by: Agent W2-D
status: F3 implementado
adr_refs: [0104, 0093, 0114, 0149]
blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/visual-source.html
---

# RUNBOOK — `stock_transfers/index` (LIST)

## 1. Contexto

- **Rota:** `GET /stock-transfers`
- **Controller:** `App\Http\Controllers\StockTransferController@index`
- **Blade legacy:** `resources/views/stock_transfer/index.blade.php`
- **Inertia destino:** `resources/js/Pages/StockTransfer/Index.tsx`

## 2. Persona
Maiara — vê transferências entre filiais (lance, status, totais).

## 3. Multi-tenant Tier 0
- `business_id` sessão.
- `transactions.type='sell_transfer'` (gêmeas: origem + destino).
- Permission `purchase.view OR purchase.create OR view_own_purchase` (legacy).
- `view_own_purchase` filtra por `created_by` (ownership scope).

## 4. Props

| Prop | Tipo | Origem |
|---|---|---|
| `rows` | `StockTransferRow[]` | Transaction join business_locations dupla (l1=from, l2=to) |
| `statuses` | `{key, label}[]` | `stockTransferStatuses()` |
| `permissions` | `{view, create, update, delete}` | `auth()->user()->can(...)` |
| `filters` | `{location_id, status, start_date, end_date}` | request() input |

## 5. Layout
- PageHeader "Transferências de estoque" + CTA "Nova transferência".
- Filtros sticky.
- Tabela: data, ref_no, origem→destino, status, frete, total, ações.

## 6. F2 BACKEND BASELINE
`Wave2StockTransferIndexBaselineTest.php` — Blade legacy preservado.

## 7. F3 FRONTEND
Index.tsx mesmo padrão Purchase/Index.

## 8. F4 QA
`Wave2StockTransferIndexInertiaTest.php` — estrutural + Tier 0 + locations from/to MESMA business.

## 9. F5 CUTOVER
Dual path `?v=2`.

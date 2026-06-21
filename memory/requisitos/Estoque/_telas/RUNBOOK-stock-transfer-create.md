---
tela: stock_transfers/create
modulo: Inventory / StockTransfer
tipo: FORM CREATE
generated_at: 2026-05-15
generated_by: Agent W2-D
status: F3 implementado
adr_refs: [0104, 0093, 0114, 0149]
blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/visual-source.html
---

# RUNBOOK — `stock_transfers/create` (FORM CREATE)

## 1. Contexto
- **Rota:** `GET /stock-transfers/create`
- **Controller:** `App\Http\Controllers\StockTransferController@create`
- **Blade legacy:** `resources/views/stock_transfer/create.blade.php`
- **Inertia destino:** `resources/js/Pages/StockTransfer/Create.tsx`

## 2. Persona
Maiara/Felipe — cria transferência (filial origem → filial destino + itens).

## 3. Multi-tenant Tier 0
- `business_id` sessão.
- **Crítico R-XFER-004:** `business_locations` dropdown filtra por business — origem E destino DEVEM ser da MESMA business.
- Permission `purchase.create`.

## 4. Props

| Prop | Tipo | Origem |
|---|---|---|
| `business_locations` | `Record<id, name>` | `BusinessLocation::forDropdown($business_id)` |
| `statuses` | `Record<key, label>` | `stockTransferStatuses()` |
| `default_datetime` | string ISO | `now()` |
| `permissions` | `{view_purchase_price, edit_price}` | `auth()->user()->can(...)` |

## 5. Layout
- PageHeader "Nova transferência" + ações Cancelar/Salvar.
- Card 1: Dados gerais (Data, Ref, Status, Origem, Destino).
- Card 2: Itens (busca + tabela qtd/preço/subtotal).
- Card 3: Frete + Total + Notas.

## 6. Validação chave
- `location_id` (origem) ≠ `transfer_location_id` (destino).
- Status: pending / in_transit / completed.
- products[] não vazio se status != pending.

## 7. F2 BASELINE
`Wave2StockTransferCreateBaselineTest.php`.

## 8. F3 FRONTEND
Create.tsx com useForm + repeater itens.

## 9. F4 QA
`Wave2StockTransferCreateInertiaTest.php` — Tier 0 + origem≠destino validação client-side.

## 10. F5 CUTOVER
Dual path `?v=2`.

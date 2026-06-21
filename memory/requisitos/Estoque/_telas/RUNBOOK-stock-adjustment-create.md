---
tela: stock_adjustment/create
modulo: Inventory / StockAdjustment
tipo: FORM CREATE
generated_at: 2026-05-15
generated_by: Agent W2-D
status: F3 implementado
adr_refs: [0104, 0093, 0114, 0149]
blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/F1.html
---

# RUNBOOK — `stock_adjustment/create` (FORM CREATE)

## 1. Contexto
- **Rota:** `GET /stock-adjustments/create`
- **Controller:** `App\Http\Controllers\StockAdjustmentController@create`
- **Blade legacy:** `resources/views/stock_adjustment/create.blade.php`
- **Inertia destino:** `resources/js/Pages/StockAdjustment/Create.tsx`

## 2. Persona
Maiara/Felipe — registra ajuste manual de estoque (perda, contagem inventário, quebra).

## 3. Multi-tenant Tier 0
- `business_id` sessão.
- `business_locations` filtrado.
- Permission `purchase.create` obrigatória.

## 4. Props

| Prop | Tipo | Origem |
|---|---|---|
| `business_locations` | `Record<id, name>` | `BusinessLocation::forDropdown` |
| `default_datetime` | string | `now()` |
| `permissions` | `{view_purchase_price, edit_price}` | can() |

## 5. Layout
- Card 1: Filial + Ref + Data + Tipo (normal/abnormal).
- Card 2: Itens (qtd, preço unit, subtotal).
- Card 3: Valor recuperado + total + notas.

## 6. Validação
- `location_id`, `transaction_date`, `adjustment_type` required.
- `total_amount_recovered ≤ final_total` (R-ADJ-003).
- products[] não vazio.

## 7-10: idem pattern Stock Transfer Create

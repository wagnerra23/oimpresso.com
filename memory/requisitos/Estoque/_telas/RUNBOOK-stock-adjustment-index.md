---
title: "RUNBOOK — `stock_adjustment/index` (LIST)"
owner: W
last_validated: "2026-06-15"
tela: stock_adjustment/index
modulo: Inventory / StockAdjustment
tipo: LIST
generated_at: 2026-05-15
generated_by: Agent W2-D
status: ativo
adr_refs: [0104, 0093, 0114, 0149]
# blueprint_cowork removido em 2026-09-09 — apontava
# prototipo-ui/prototipos/inventario-migracao/ (visual-source.html == F1.html, mesmo blob),
# que é o relatório "Migração Blade → React", não o desenho desta tela.
# Medido no dia (grep -oi <termo> | wc -l, rc=0): Blade=67 React=36 Inventário=8 · estoque=0 SKU=0 saldo=0 quantidade=0 ajuste=0.
# O recorte tem alvo próprio, nunca construído: o README dele pede charter em
# Pages/Stocks|Inventario/Index e marca "F3 bloqueado por charter ausente".
# Casou por homônimo ("inventário" de código × "inventário" de estoque).
# A âncora de design da tela vive no charter ao lado do .tsx (`bundle_source`).
---

# RUNBOOK — `stock_adjustment/index` (LIST)

## 1. Contexto
- **Rota:** `GET /stock-adjustments`
- **Controller:** `App\Http\Controllers\StockAdjustmentController@index`
- **Blade legacy:** `resources/views/stock_adjustment/index.blade.php`
- **Inertia destino:** `resources/js/Pages/StockAdjustment/Index.tsx`

## 2. Persona
Maiara — ajustes manuais de estoque (perda, quebra, contagem inventário).

## 3. Multi-tenant Tier 0
- `business_id` sessão.
- `transactions.type='stock_adjustment'`.
- Permission `purchase.view OR purchase.create OR view_own_purchase`.
- `permitted_locations` filter.

## 4. Props

| Prop | Tipo | Origem |
|---|---|---|
| `rows` | `AdjustmentRow[]` | Transaction join business_locations + users (added_by) |
| `business_locations` | `Option[]` | `BusinessLocation::forDropdown` |
| `permissions` | `{view, create, delete, view_purchase_price}` | `auth()->user()->can(...)` |
| `filters` | `{location_id, start_date, end_date}` | request() |

## 5. Layout
Listagem com filtros (filial, intervalo data) + tabela (data, ref, filial, tipo, total, recuperado, motivo, autor).

## 6-10: idem padrão Purchase/Index

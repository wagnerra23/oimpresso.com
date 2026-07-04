---
page: /stock-adjustments
component: resources/js/Pages/StockAdjustment/Index.tsx
related_us: [US-MWART-007]
tela: stock_adjustment/index
tipo: LIST
modulo: Inventory / StockAdjustment
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/visual-source.html
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Index]
  divergence_from_blueprint: "Inspirado em Purchase/Index.tsx + StockTransfer/Index.tsx."
---

# Charter — StockAdjustment/Index.tsx

## Regras invariantes
- R-ADJ-001 (Tier 0)
- R-ADJ-002: adjustment_type ∈ {normal, abnormal}
- R-ADJ-003: total_amount_recovered ≤ final_total
- R-ADJ-004: ownership via view_own_purchase

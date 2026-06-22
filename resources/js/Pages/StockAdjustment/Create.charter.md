---
page: /stock-adjustments/create
component: resources/js/Pages/StockAdjustment/Create.tsx
tela: stock_adjustment/create
tipo: FORM CREATE
modulo: Inventory / StockAdjustment
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/F1.html
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "Tipo Normal/Abnormal destacado com cor (abnormal=rose perda)."
---

# Charter — StockAdjustment/Create.tsx

## Regras invariantes
- R-ADJ-001 (Tier 0)
- R-ADJ-002: adjustment_type ∈ {normal, abnormal}
- R-ADJ-003: total_amount_recovered ≤ final_total (validado client + server)
- R-ADJ-004: purchase.create obrigatória

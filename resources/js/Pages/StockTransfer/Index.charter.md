---
page: /stock-transfers
component: resources/js/Pages/StockTransfer/Index.tsx
related_us: [US-MWART-007]
tela: stock_transfers/index
tipo: LIST
modulo: Inventory / StockTransfer
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/visual-source.html
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Index]
  divergence_from_blueprint: "Inspirado em Purchase/Index.tsx (mesma densidade)."
---

# Charter — StockTransfer/Index.tsx

## Persona
Maiara — listagem rápida transferências (origem→destino, status, total).

## Regras invariantes
- R-XFER-001 (Tier 0)
- R-XFER-002: ownership filter via `view_own_purchase`
- R-XFER-003: status final só após `completed` (estoque movido)
- R-XFER-004: origem ≠ destino (validado server-side)

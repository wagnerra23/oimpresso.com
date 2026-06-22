---
page: /stock-transfers/create
component: resources/js/Pages/StockTransfer/Create.tsx
tela: stock_transfers/create
tipo: FORM CREATE
modulo: Inventory / StockTransfer
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/inventario-migracao/visual-source.html
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "Origem→Destino destacado no topo (regra crítica R-XFER-004)."
---

# Charter — StockTransfer/Create.tsx

## Regras invariantes
- R-XFER-001 (Tier 0 IRREVOGÁVEL)
- R-XFER-004: origem ≠ destino (validado client + server)
- R-XFER-005: status=completed → estoque movido server-side

## UX crítica
Bloqueio visual se origem == destino (forma + button disabled). Server-side garante anyway.

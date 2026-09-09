---
id: resources-js-pages-stock-transfer-index-charter
page: /stock-transfers
component: resources/js/Pages/StockTransfer/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_visual_comparison: memory/requisitos/Estoque/_telas/stock-transfer-index-visual-comparison.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_transfers/index
tipo: LIST
modulo: Inventory / StockTransfer
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  # blueprint_cowork removido em 2026-09-09 — apontava
  # prototipo-ui/prototipos/inventario-migracao/ (visual-source.html == F1.html, mesmo blob),
  # que é o relatório "Migração Blade → React", não o desenho desta tela.
  # Medido no dia (grep -oi <termo> | wc -l, rc=0): Blade=67 React=36 Inventário=8 · estoque=0 SKU=0 saldo=0 quantidade=0 ajuste=0.
  # O recorte tem alvo próprio, nunca construído: o README dele pede charter em
  # Pages/Stocks|Inventario/Index e marca "F3 bloqueado por charter ausente".
  # Casou por homônimo ("inventário" de código × "inventário" de estoque).
  # O desenho desta tela vive em `bundle_source` (estoque-page.jsx) — o campo que o ancora.mjs resolve.
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

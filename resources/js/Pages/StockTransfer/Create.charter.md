---
id: resources-js-pages-stock-transfer-create-charter
page: /stock-transfers/create
component: resources/js/Pages/StockTransfer/Create.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
related_visual_comparison: memory/requisitos/Estoque/_telas/stock-transfer-create-visual-comparison.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_transfers/create
tipo: FORM CREATE
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

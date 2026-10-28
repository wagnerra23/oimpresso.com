---
id: resources-js-pages-stock-transfer-edit-charter
page: /stock-transfers/{id}/edit
component: resources/js/Pages/StockTransfer/Edit.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
related_runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-edit.md
runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-edit.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_transfers/edit
tipo: FORM EDIT
modulo: Inventory / StockTransfer
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/Wagner/estoque-forms.jsx
  derived_screens: [Create, Edit]
  divergence_from_blueprint: "Origem e destino travados, como na Blade de edição."
---

# Charter — StockTransfer/Edit.tsx

## Regras invariantes
- R-XFER-001 (Tier 0): transferência e linhas só do business da sessão.
- R-XFER-003: concluída não abre para edição e o `update()` recusa.
- R-XFER-005: salvar como `completed` move o estoque na hora (servidor).

## Casos
[Edit.casos.md](Edit.casos.md) — UC-TREDT-01..03.

## Permissões
- Abrir e salvar: `purchase.create` (403 sem ela).
- `edit_price` decide só o `readonly` do custo; o servidor não re-checa no save.

## Non-Goals
- Trocar origem ou destino (a Blade também não deixa).

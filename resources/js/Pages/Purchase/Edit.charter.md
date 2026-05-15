---
page: resources/js/Pages/Purchase/Edit.tsx
tela: purchase/edit
tipo: FORM (EDIT)
modulo: Purchase
runbook: memory/requisitos/Inventory/RUNBOOK-purchase-edit.md
status: F3 implementado
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/compras/visual-source.html
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Edit]
  divergence_from_blueprint: "Pré-populado com purchase props. Caso contrário idêntico ao Create — reutiliza pattern."
---

# Charter — Purchase/Edit.tsx

## Persona
Maiara/Felipe corrigem campos de compra dentro de `transaction_edit_days`.

## Regras invariantes
- R-PUR-001 (Tier 0 IRREVOGÁVEL)
- R-PUR-005: `canBeEdited` time-gate
- R-PUR-006: bloqueado se devolução já criada
- R-PUR-007: `purchase.update` obrigatória

## Reuso
~80% UI igual ao Create.tsx. Diferença: pré-população + PUT.

---
page: /purchases/{id}/edit
component: resources/js/Pages/Purchase/Edit.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
tela: purchase/edit
tipo: FORM (EDIT)
modulo: Purchase
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/compras-page.jsx
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

---
page: /purchases/create
component: resources/js/Pages/Purchase/Create.tsx
tela: purchase/create
tipo: FORM (CREATE)
modulo: Purchase
runbook: memory/requisitos/Inventory/RUNBOOK-purchase-create.md
status: draft
status_note: "F3 implementado (aguarda smoke Wagner)"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/compras/visual-source.html
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "Layout linear top-to-bottom (não drawer-based como Index). Repeater simplificado MVP1 — sem advanced product variation picker."
---

# Charter — Purchase/Create.tsx

## Persona
Maiara/Felipe escritório. Lança compras pequenas e médias (~5-30 itens). Foco velocidade entrada e validação fiscal.

## Hierarquia de informação
1. Identificação (filial, fornecedor, data, status, ref)
2. Itens
3. Totais (desconto, impostos, frete, total final)
4. Pagamentos (opcional)
5. Notas

## Regras invariantes
- R-PUR-001 (Tier 0): `business_id` global scope IRREVOGÁVEL.
- R-PUR-002: `permitted_locations` filtra filiais visíveis.
- R-PUR-003: `purchase.create` obrigatória, senão 403.
- R-PUR-004: Status final só após `received` (estoque entra).

## Dependências
- AppShellV2 layout persistent.
- PageHeader shared.
- `useForm` Inertia.
- Card/Input/Button/Select primitives.

## Anti-padrões evitados (LICOES_F3_FINANCEIRO_REJEITADO)
- Sem cópia 1:1 do Blade (não replica `select2`/`select2 inputmask`).
- Validação client-side mínima — backend é fonte de verdade.
- Sem business_id hardcoded.

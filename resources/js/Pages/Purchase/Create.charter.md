---
page: /purchases/create
component: resources/js/Pages/Purchase/Create.tsx
tela: purchase/create
tipo: FORM (CREATE)
modulo: Purchase
status: draft
status_note: "F3 implementado + modo grade tam×cor (US-COM-005, aguarda smoke/canary Wagner)"
charter_version: 2
last_validated: "2026-06-22"
adr_refs: [0104, 0093, 0114, 0149, 0105]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/prototipos/compras/visual-source.html
  blueprint_grade: prototipo-ui/prototipos/compras-grade-matrix/page.jsx
  blueprint_screenshot_approval: "SYNC_LOG (pendente — inclui modo grade)"
  derived_screens: [Create]
  divergence_from_blueprint: "Layout linear top-to-bottom (não drawer-based como Index). Modo grade tam×cor (US-COM-005) usa GradeMatrixInput; entrada manual segue repeater simples."
---

# Charter — Purchase/Create.tsx

## Persona
Maiara/Felipe escritório (compras pequenas/médias ~5-30 itens). **+ Larissa @ ROTA LIVRE biz=4** (vestuário, 1280px, não-técnica): compra por grade tam×cor, 50+ modelos/entrega. Foco velocidade de entrada.

## Goals
- Lançar compra manual rápida (filial, fornecedor, itens, totais).
- **Modo grade (US-COM-005):** produto `variable` → matriz tam×cor → 1 POST único de N `purchase_lines` (1 célula = 1 `variation_id`).

## Non-Goals / Anti-hooks
- NÃO nasce `Pages/Compras/Create.tsx` — a grade vive aqui (convergência C1 · `compras-purchase-convergencia-c1`).
- NÃO força 2D: catálogo sem variação composta cai pra grade de 1 eixo (auto-detect backend) — nunca grade vazia silenciosa.
- NÃO cria/edita produto pra montar 2 eixos nativos (precisaria ADR de schema — M-AP-4).

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
- Grade: `product_id` resolvido com scope `business_id` (firstOrFail → 404 cross-tenant); `store()` valida ownership das variations.
- Sem Model/Service/endpoint inventado: reusa `App\Variation`/`App\ProductVariation` + `ProductUtil::createOrUpdatePurchaseLines` reais.

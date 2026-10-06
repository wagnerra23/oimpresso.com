---
id: resources-js-pages-stock-adjustment-index-charter
page: /stock-adjustments
component: resources/js/Pages/StockAdjustment/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_visual_comparison: memory/requisitos/Estoque/_telas/stock-adjustment-index-visual-comparison.md
related_runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-index.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_adjustment/index
tipo: LIST
modulo: Inventory / StockAdjustment
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/Wagner/estoque-page.jsx
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Index]
  divergence_from_blueprint: "Regiao do blueprint: aba Ajustes (AbaAjustes). Densidade de tabela herdada de Purchase/Index.tsx."
---

# Charter — StockAdjustment/Index.tsx

## Regras invariantes
- R-ADJ-001 (Tier 0)
- R-ADJ-002: adjustment_type ∈ {normal, abnormal}
- R-ADJ-003: total_amount_recovered ≤ final_total
- R-ADJ-004: ownership via view_own_purchase

## Casos
[Index.casos.md](Index.casos.md) — achados abertos em §Backlog · UC-AJIDX-01/02.

## Permissões
- Abrir: `purchase.view` **ou** `purchase.create` **ou** `view_own_purchase` (403 sem nenhuma).
- `view_own_purchase` sem `purchase.view` mostra só os ajustes criados pelo usuário; `permitted_locations` limita as filiais.
- Excluir: `purchase.delete`. Valores só com `view_purchase_price`.

## Estados
- Lista vazia sem filtro: "Nenhum ajuste de estoque registrado" + "Registrar primeiro ajuste" (se `create`); com filtro: "Nenhum ajuste com filtros atuais".
- A lista traz no máximo 200 ajustes, do mais recente para o mais antigo, sem paginação.
- Só abre em React com `?v=2`; sem isso o mesmo `index()` serve a Blade.

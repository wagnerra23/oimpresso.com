---
module: Compras
purpose: "Gestão de compras (entrada de mercadoria) — substitui UltimatePOS Purchase legacy."
contains:
  - "ComprasController"
  - "DataController"
  - "InstallController"
not_contains:
  - "Estoque/inventário operacional → Modules/AssetManagement + core UltimatePOS stock"
  - "Vendas/saídas → core UltimatePOS Sells (futuro Modules/Sells)"
  - "Financeiro de Contas a Pagar → Modules/Financeiro"
  - "NF-e de entrada → Modules/NfeBrasil"
trust_required: L3
owner: wagner
permission_prefix: compras.*
charter_adr: 0079
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0180-sidebar-v3-5-grupos-ghosts-header
---

# Modules/Compras — SCOPE

Módulo novo (Wave 1-4.5 do scaffold, PRs #1310/#1315) que organiza fluxo de
compras (entrada de mercadoria) em Inertia/React. Sucessor incremental do core
UltimatePOS Purchase legacy.

## Estado atual (2026-05-21)

- **Wave 1-4.5 scaffold:** Page Inertia + GradeMatrixInput vestuário + Drawer
  5 tabs + Ações dropdown (PRs #1310/#1315/#1317/#1318)
- **Wave A Lanes (2026-05-21):** Exports + Filtros (#?), Importar XML DFE (#?),
  Pest deep (#?) — em paralelo
- **Wave 3 (TODO):** rota `/compras/create` (atualmente declarada no sidebar v3
  ghost — ver Modules/Compras/Http/Controllers/DataController.php Fase 4 ADR 0180)

## Permissões

Prefix `compras.*` (futuro — hoje usa `purchase.*` legacy do core UPOS).

## Multi-tenant Tier 0

ADR 0093 — todas as queries filtram por `business_id` global scope via core
UltimatePOS `Transaction::auth_scope()`. Compras não introduz tabela própria
ainda (reusa `transactions` + `transaction_lines`).

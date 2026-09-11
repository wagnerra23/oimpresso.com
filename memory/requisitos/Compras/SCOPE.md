---
module: Compras
purpose: "Cockpit de leitura de compras (/compras): lista, KPIs e drawer de detalhe sobre transactions type=purchase. Complementa — não substitui — o CRUD /purchases do core, pra onde delega criar, editar e excluir."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "ComprasController"
  - "DataController"
  - "InstallController"
not_contains:
  - "Estoque/inventário operacional → Modules/AssetManagement + core UltimatePOS stock"
  - "Vendas/saídas → core UltimatePOS Sells (NÃO é módulo: telas legacy em app/ + resources/views — ghost-rename-map excluded classe C)"
  - "Financeiro de Contas a Pagar → Modules/Financeiro"
  - "NF-e de entrada → Modules/NfeBrasil"
trust_required: L3
owner: wagner
permission_prefix: compras.*
charter_adr: 0079
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0180-sidebar-v3-5-grupos-ghosts-header
url_prefixes:
  - /compras/* (única superfície — cockpit `GET /` · `GET /{id}/detalhe` sob throttle:60,1, Routes/web.php:36; install 1-clique em :23. O CRUD vive em /purchases do núcleo, não aqui)
---

# Modules/Compras — SCOPE

Módulo novo (Wave 1-4.5 do scaffold, PRs #1310/#1315) que organiza fluxo de
compras (entrada de mercadoria) em Inertia/React. Sucessor incremental do core
UltimatePOS Purchase legacy.

## Estado atual — retrato de 2026-05-21 (ponteiro corrigido em 2026-09-04)

- **Wave 1-4.5 scaffold:** Page Inertia + GradeMatrixInput vestuário + Drawer
  5 tabs + Ações dropdown (PRs #1310/#1315/#1317/#1318)
- **Wave A Lanes (2026-05-21):** Exports + Filtros (#?), Importar XML DFE (#?),
  Pest deep (#?) — em paralelo
- ~~**Wave 3 (TODO):** rota `/compras/create`~~ — **CANCELADA pela convergência C1**
  (2026-05-25 · PR #1525). Medido em `origin/main` 2026-09-04: o `primary` do sidebar
  aponta `/purchases/create` e o único ghost é `lista → /compras` — **não há ghost
  `/compras/create`**, e `git grep "compras/create"` devolve **0 hits em código**
  (19 linhas em 5 arquivos, todas em doc). Oráculo — releia, não confie nesta linha:
  `modifyAdminMenu()` em [`DataController.php`](../../../Modules/Compras/Http/Controllers/DataController.php)
  · ADR proposta [`compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md),
  §Consequências: a rota fica *"404 definitivamente"*.
  _Fato datado preservado:_ entre 2026-05-21 (PR #1353 · ADR 0180 Fase 4 Wave B) e
  2026-05-25 (PR #1525) o `/compras/create` existiu no menu — mas no **`primary`**
  (`:114` daquela revisão), **nunca** nos `ghosts`; a palavra "ghost" desta linha já
  estava imprecisa no dia em que foi escrita.

## Permissões

Prefix `compras.*` (futuro — hoje usa `purchase.*` legacy do core UPOS).

## Multi-tenant Tier 0

ADR 0093 — todas as queries filtram por `business_id` global scope via core
UltimatePOS `Transaction::auth_scope()`. Compras não introduz tabela própria
ainda (reusa `transactions` + `transaction_lines`).

---
module: Compras
purpose: "Cockpit de leitura de compras (/compras): lista, KPIs e drawer de detalhe sobre transactions type=purchase. Complementa — não substitui — o CRUD /purchases do core, pra onde delega criar, editar e excluir."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "ComprasController"
  - "DataController"
  - "InstallController"
  - "CustoDoInsumoService — custo do insumo numa data pelo método da regra da empresa (US-COM-024 · UC-FLX-38); só lê purchase_lines + transactions"
  - "RequisicaoEstoqueMinimoJob + comando compras:requisicao-estoque-minimo — US-COM-027 · UC-FLX-19 estoque abaixo de alert_quantity vira requisição de compra do núcleo (transactions type=purchase_requisition) por local, teto(mínimo − saldo), sem duplicar item em requisição aberta; não move estoque nem cria pedido; desligado por padrão em business.common_settings"
  - "AplicarCustoDoRecebimento — compra recebida grava o custo pela regra da empresa e trata o preço (so_custo | aplicar | propor), só quando a empresa gravou a regra (US-COM-026 · UC-FLX-17)"
  - "Database/Migrations/2026_10_20_120000_create_compras_propostas_preco_table — tabela compras_propostas_preco, proposta de preço pendente gerada no recebimento (business_id, variation_id, transaction_id)"
  - "InsumoMetodoCustoController — PUT /compras/insumos/{variation}/metodo-custo grava o método do insumo (medio | ultimo) da gaveta de /manufacturing/insumos; empresa da sessão, variação da empresa ou 404, purchase.update (US-COM-024 · UC-FLX-38)"
  - "MetodoCustoDoProduto + DataController::product_form_part/after_product_saved — campo Método de custo no formulário Blade de novo e de editar produto, aplicado a todas as variações; só roda com o módulo instalado (system.compras_version)"
  - "InsumoMetodoCusto — entidade da tabela-ponte compras_insumo_metodo_custo (business_id, variation_id) com o método próprio do insumo (medio | ultimo), lida quando a regra é por_insumo; variations do core não ganha coluna"
not_contains:
  - "Estoque/inventário operacional → Modules/AssetManagement + core UltimatePOS stock"
  - "Vendas/saídas → core UltimatePOS Sells (NÃO é módulo: telas legacy em app/ + resources/views — ghost-rename-map excluded classe C)"
  - "Financeiro de Contas a Pagar → Modules/Financeiro"
  - "NF-e de entrada → Modules/NfeBrasil"
  - "Rastreio do rolo (em que OS cada fração do lote foi usada, US-COM-025) → Modules/Producao: as frações vêm de pcp_consumos, tabela de lá"
trust_required: L3
owner: wagner
permission_prefix: compras.*
charter_adr: 0079
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0180-sidebar-v3-5-grupos-ghosts-header
url_prefixes:
  - /compras/* (única superfície — cockpit `GET /` · `GET /{id}/detalhe` sob throttle:60,1, Routes/web.php:36; install 1-clique em :23; `PUT /insumos/{variation}/metodo-custo` no mesmo grupo. O CRUD vive em /purchases do núcleo, não aqui)
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
UltimatePOS `Transaction::auth_scope()`. Compras reusa `transactions` + `purchase_lines`;
desde 2026-10-20 tem tabelas próprias — `compras_insumo_metodo_custo` (US-COM-024) e
`compras_propostas_preco` (US-COM-026) —, ambas com `business_id` indexado + FK.

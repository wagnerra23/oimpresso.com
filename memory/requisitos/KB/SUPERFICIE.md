---
name: "SUPERFÍCIE — KB"
description: "Índice GERADO dos artefatos do módulo KB reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: KB
---

# 🗺️ Superfície de código — KB

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs KB --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/KB/**` + `resources/js/Pages/kb/**` (namespace Inertia `kb`, declarado em `module-surface.mjs::PAGES_NS` porque difere do nome do módulo `KB`), separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 142 arquivos em 18 papéis.

## Controllers — 14

- [GraphController.php](../../../Modules/KB/Http/Controllers/Admin/GraphController.php)
- [DataController.php](../../../Modules/KB/Http/Controllers/DataController.php)
- [FontesController.php](../../../Modules/KB/Http/Controllers/FontesController.php)
- [InstallController.php](../../../Modules/KB/Http/Controllers/InstallController.php)
- [KbAiController.php](../../../Modules/KB/Http/Controllers/KbAiController.php)
- [KbCommentController.php](../../../Modules/KB/Http/Controllers/KbCommentController.php)
- [KbController.php](../../../Modules/KB/Http/Controllers/KbController.php)
- [KbDecisionTreeController.php](../../../Modules/KB/Http/Controllers/KbDecisionTreeController.php)
- [KbEdgeController.php](../../../Modules/KB/Http/Controllers/KbEdgeController.php)
- [KbFavoriteController.php](../../../Modules/KB/Http/Controllers/KbFavoriteController.php)
- [KbNodeController.php](../../../Modules/KB/Http/Controllers/KbNodeController.php)
- [KbPathController.php](../../../Modules/KB/Http/Controllers/KbPathController.php)
- [KbVersionController.php](../../../Modules/KB/Http/Controllers/KbVersionController.php)
- [MemoriaController.php](../../../Modules/KB/Http/Controllers/MemoriaController.php)

## Requests (validação) — 5

- [StoreDecisionTreeRequest.php](../../../Modules/KB/Http/Requests/StoreDecisionTreeRequest.php)
- [StoreKbCommentRequest.php](../../../Modules/KB/Http/Requests/StoreKbCommentRequest.php)
- [StoreKbEdgeRequest.php](../../../Modules/KB/Http/Requests/StoreKbEdgeRequest.php)
- [StoreKbNodeRequest.php](../../../Modules/KB/Http/Requests/StoreKbNodeRequest.php)
- [StoreKbPathRequest.php](../../../Modules/KB/Http/Requests/StoreKbPathRequest.php)

## Services — 10

- [MetaSuggestion.php](../../../Modules/KB/Services/Dtos/MetaSuggestion.php)
- [RagResult.php](../../../Modules/KB/Services/Dtos/RagResult.php)
- [SummaryResult.php](../../../Modules/KB/Services/Dtos/SummaryResult.php)
- [KbArticleService.php](../../../Modules/KB/Services/KbArticleService.php)
- [KbAutoClassifierService.php](../../../Modules/KB/Services/KbAutoClassifierService.php)
- [KbBgeRerankerService.php](../../../Modules/KB/Services/KbBgeRerankerService.php)
- [KbBridgeStateService.php](../../../Modules/KB/Services/KbBridgeStateService.php)
- [KbCorpusBuilder.php](../../../Modules/KB/Services/KbCorpusBuilder.php)
- [KbEdgeAutoDeriver.php](../../../Modules/KB/Services/KbEdgeAutoDeriver.php)
- [KbRagService.php](../../../Modules/KB/Services/KbRagService.php)

## Models / Entities — 13

- [BelongsToBusinessTrait.php](../../../Modules/KB/Entities/Concerns/BelongsToBusinessTrait.php)
- [KbBridgeState.php](../../../Modules/KB/Entities/KbBridgeState.php)
- [KbCategory.php](../../../Modules/KB/Entities/KbCategory.php)
- [KbComment.php](../../../Modules/KB/Entities/KbComment.php)
- [KbDecisionTree.php](../../../Modules/KB/Entities/KbDecisionTree.php)
- [KbDecisionTreeStep.php](../../../Modules/KB/Entities/KbDecisionTreeStep.php)
- [KbEdge.php](../../../Modules/KB/Entities/KbEdge.php)
- [KbFavorite.php](../../../Modules/KB/Entities/KbFavorite.php)
- [KbNode.php](../../../Modules/KB/Entities/KbNode.php)
- [KbNodeVersion.php](../../../Modules/KB/Entities/KbNodeVersion.php)
- [KbPath.php](../../../Modules/KB/Entities/KbPath.php)
- [KbPathStep.php](../../../Modules/KB/Entities/KbPathStep.php)
- [KbSubcategory.php](../../../Modules/KB/Entities/KbSubcategory.php)

## Observers — 3

- [KbDecisionTreeStepObserver.php](../../../Modules/KB/Observers/KbDecisionTreeStepObserver.php)
- [KbNodeObserver.php](../../../Modules/KB/Observers/KbNodeObserver.php)
- [KbNodeVersionObserver.php](../../../Modules/KB/Observers/KbNodeVersionObserver.php)

## Jobs — 2

- [KbBridgeFromMcpJob.php](../../../Modules/KB/Jobs/KbBridgeFromMcpJob.php)
- [KbEdgeAutoDeriverJob.php](../../../Modules/KB/Jobs/KbEdgeAutoDeriverJob.php)

## Console / Commands — 5

- [KbClassifyCommand.php](../../../Modules/KB/Console/Commands/KbClassifyCommand.php)
- [KbCodeScanCommand.php](../../../Modules/KB/Console/Commands/KbCodeScanCommand.php)
- [KbDriftDetectorCommand.php](../../../Modules/KB/Console/Commands/KbDriftDetectorCommand.php)
- [KbHealthCommand.php](../../../Modules/KB/Console/Commands/KbHealthCommand.php)
- [KbReindexCommand.php](../../../Modules/KB/Console/Commands/KbReindexCommand.php)

## Providers — 1

- [KBServiceProvider.php](../../../Modules/KB/Providers/KBServiceProvider.php)

## Rotas — 1

- [api.php](../../../Modules/KB/Routes/api.php)

## Migrations (schema) — 13

- [2026_05_15_100001_create_kb_categories_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100001_create_kb_categories_table.php)
- [2026_05_15_100002_create_kb_subcategories_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100002_create_kb_subcategories_table.php)
- [2026_05_15_100003_create_kb_nodes_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100003_create_kb_nodes_table.php)
- [2026_05_15_100004_create_kb_edges_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100004_create_kb_edges_table.php)
- [2026_05_15_100005_create_kb_paths_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100005_create_kb_paths_table.php)
- [2026_05_15_100006_create_kb_path_steps_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100006_create_kb_path_steps_table.php)
- [2026_05_15_100007_create_kb_decision_trees_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100007_create_kb_decision_trees_table.php)
- [2026_05_15_100008_create_kb_decision_tree_steps_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100008_create_kb_decision_tree_steps_table.php)
- [2026_05_15_100009_create_kb_node_versions_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100009_create_kb_node_versions_table.php)
- [2026_05_15_100010_create_kb_favorites_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100010_create_kb_favorites_table.php)
- [2026_05_15_100011_create_kb_comments_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100011_create_kb_comments_table.php)
- [2026_05_15_100012_create_kb_bridge_state_table.php](../../../Modules/KB/Database/Migrations/2026_05_15_100012_create_kb_bridge_state_table.php)
- [2026_07_23_100000_add_code_drift_state_to_kb_nodes.php](../../../Modules/KB/Database/Migrations/2026_07_23_100000_add_code_drift_state_to_kb_nodes.php)

## Seeders — 5

- [KbBridgeFromMcpSeeder.php](../../../Modules/KB/Database/Seeders/KbBridgeFromMcpSeeder.php)
- [KbCategoriesSeeder.php](../../../Modules/KB/Database/Seeders/KbCategoriesSeeder.php)
- [KbDatabaseSeeder.php](../../../Modules/KB/Database/Seeders/KbDatabaseSeeder.php)
- [KbOperacionalSeeder.php](../../../Modules/KB/Database/Seeders/KbOperacionalSeeder.php)
- [KbSubcategoriesSeeder.php](../../../Modules/KB/Database/Seeders/KbSubcategoriesSeeder.php)

## Config — 2

- [config.php](../../../Modules/KB/Config/config.php)
- [retention.php](../../../Modules/KB/Config/retention.php)

## Telas (Inertia/React) — 3

- [Graph.tsx](../../../resources/js/Pages/kb/Graph.tsx)
- [Index.tsx](../../../resources/js/Pages/kb/Index.tsx)
- [Index.v2.tsx](../../../resources/js/Pages/kb/Index.v2.tsx)

## Componentes / apoio de tela — 13

- [BlockRenderer.tsx](../../../resources/js/Pages/kb/_components/BlockRenderer.tsx)
- [CategorySidebar.tsx](../../../resources/js/Pages/kb/_components/CategorySidebar.tsx)
- [GraphCanvas.tsx](../../../resources/js/Pages/kb/_components/GraphCanvas.tsx)
- [GraphFilters.tsx](../../../resources/js/Pages/kb/_components/GraphFilters.tsx)
- [GraphLegend.tsx](../../../resources/js/Pages/kb/_components/GraphLegend.tsx)
- [GraphNodeDetail.tsx](../../../resources/js/Pages/kb/_components/GraphNodeDetail.tsx)
- [HealthPanel.tsx](../../../resources/js/Pages/kb/_components/HealthPanel.tsx)
- [KbCommandPalette.tsx](../../../resources/js/Pages/kb/_components/KbCommandPalette.tsx)
- [KbFavStar.tsx](../../../resources/js/Pages/kb/_components/KbFavStar.tsx)
- [NodeList.tsx](../../../resources/js/Pages/kb/_components/NodeList.tsx)
- [NodeReader.tsx](../../../resources/js/Pages/kb/_components/NodeReader.tsx)
- [PathsDialog.tsx](../../../resources/js/Pages/kb/_components/PathsDialog.tsx)
- [TroubleshooterDialog.tsx](../../../resources/js/Pages/kb/_components/TroubleshooterDialog.tsx)

## Charters (lei da tela) — 6

- [Graph.charter.md](../../../resources/js/Pages/kb/Graph.charter.md)
- [Index.charter.md](../../../resources/js/Pages/kb/Index.charter.md)
- [Index.v2.charter.md](../../../resources/js/Pages/kb/Index.v2.charter.md)
- [NodeReader.charter.md](../../../resources/js/Pages/kb/_components/NodeReader.charter.md)
- [PathsDialog.charter.md](../../../resources/js/Pages/kb/_components/PathsDialog.charter.md)
- [TroubleshooterDialog.charter.md](../../../resources/js/Pages/kb/_components/TroubleshooterDialog.charter.md)

## Casos (contrato UC) — 1

- [Index.v2.casos.md](../../../resources/js/Pages/kb/Index.v2.casos.md)

## Testes (Pest) — 32

- 32 arquivos em [Modules/KB/Tests/Feature/](../../../Modules/KB/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 13

- [KbCategoryFactory.php](../../../Modules/KB/Database/Factories/KbCategoryFactory.php)
- [KbCommentFactory.php](../../../Modules/KB/Database/Factories/KbCommentFactory.php)
- [KbDecisionTreeFactory.php](../../../Modules/KB/Database/Factories/KbDecisionTreeFactory.php)
- [KbDecisionTreeStepFactory.php](../../../Modules/KB/Database/Factories/KbDecisionTreeStepFactory.php)
- [KbEdgeFactory.php](../../../Modules/KB/Database/Factories/KbEdgeFactory.php)
- [KbFavoriteFactory.php](../../../Modules/KB/Database/Factories/KbFavoriteFactory.php)
- [KbNodeFactory.php](../../../Modules/KB/Database/Factories/KbNodeFactory.php)
- [KbNodeVersionFactory.php](../../../Modules/KB/Database/Factories/KbNodeVersionFactory.php)
- [KbPathFactory.php](../../../Modules/KB/Database/Factories/KbPathFactory.php)
- [KbPathStepFactory.php](../../../Modules/KB/Database/Factories/KbPathStepFactory.php)
- [KbSubcategoryFactory.php](../../../Modules/KB/Database/Factories/KbSubcategoryFactory.php)
- [routes.php](../../../Modules/KB/Http/routes.php)
- [start.php](../../../Modules/KB/start.php)

# Módulo: KB

> **Knowledge Base — biblioteca compartilhada de ADRs, sessions, runbooks, comparativos. Split do Copiloto (Etapa 2 modularização) — vive em /kb e consome a tabela mcp_memory_documents (sincronizada do git via webhook GitHub).**

- **Alias:** `kb`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/KB`
- **Status:** 🟢 ativo
- **Providers:** Modules\KB\Providers\KBServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 43 rotas — escopo médio
- ✅ Tem testes (27)
- 🔐 Registra 4 permissão(ões) Spatie
- ⚙️ Processamento assíncrono: 2 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 43 |
| Controllers | 14 |
| Entities (Models) | 12 |
| Services | 9 |
| FormRequests | 5 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 12 |
| Arquivos de lang | 3 |
| Testes | 27 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `'KbController@index'` |
| `GET` | `/v2` | `function (` |
| `GET` | `/graph` | `function (` |
| `GET` | `/graph/data` | `function (` |
| `GET` | `/{slug}/show` | `'KbController@show'` |
| `GET` | `/{slug}/history` | `'KbController@history'` |
| `DELETE` | `/{slug}` | `'KbController@softDelete'` |
| `POST` | `/{slug}/restore` | `'KbController@restore'` |
| `GET` | `/nodes` | `'KbNodeController@index'` |
| `POST` | `/nodes` | `'KbNodeController@store'` |
| `GET` | `/nodes/{slug}` | `'KbNodeController@show'` |
| `PUT` | `/nodes/{slug}` | `'KbNodeController@update'` |
| `DELETE` | `/nodes/{slug}` | `'KbNodeController@destroy'` |
| `POST` | `/nodes/{slug}/restore` | `'KbNodeController@restore'` |
| `POST` | `/nodes/{slug}/reverify` | `'KbNodeController@reverify'` |
| `GET` | `/nodes/{slug}/versions` | `'KbVersionController@index'` |
| `POST` | `/nodes/{slug}/restore-version` | `'KbVersionController@restoreVersion'` |
| `POST` | `/nodes/{slug}/favorite` | `'KbFavoriteController@toggle'` |
| `POST` | `/nodes/{slug}/comments` | `'KbCommentController@store'` |
| `DELETE` | `/comments/{id}` | `'KbCommentController@destroy'` |
| `GET` | `/paths` | `'KbPathController@index'` |
| `POST` | `/paths` | `'KbPathController@store'` |
| `GET` | `/paths/{slug}` | `'KbPathController@show'` |
| `PUT` | `/paths/{slug}` | `'KbPathController@update'` |
| `GET` | `/decision-trees` | `'KbDecisionTreeController@index'` |
| `POST` | `/decision-trees` | `'KbDecisionTreeController@store'` |
| `GET` | `/decision-trees/{slug}` | `'KbDecisionTreeController@show'` |
| `PUT` | `/decision-trees/{slug}` | `'KbDecisionTreeController@update'` |
| `GET` | `/edges` | `'KbEdgeController@index'` |
| `POST` | `/edges` | `'KbEdgeController@store'` |
| `DELETE` | `/edges/{id}` | `'KbEdgeController@destroy'` |
| `GET` | `/print-sop/{slug}` | `'PrintSopController@show'` |
| `GET` | `/` | `'InstallController@index'` |
| `POST` | `/` | `'InstallController@install'` |
| `GET` | `/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/update` | `'InstallController@update'` |
| `GET` | `/` | `function (` |
| `POST` | `/ask` | `'KbAiController@ask'` |
| `POST` | `/summarize/{slug}` | `'KbAiController@summarize'` |
| `POST` | `/suggest-meta` | `'KbAiController@suggestMeta'` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/health` | `function (` |
| `POST` | `/tools/kb-search` | `KbSearchMcpController::class` |
| `GET` | `/api/kb/ping` | `function (` |

## Controllers

- **`GraphController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`FontesController`** — 2 ação(ões): show, update
- **`InstallController`** — 0 ação(ões): 
- **`KbAiController`** — 3 ação(ões): ask, summarize, suggestMeta
- **`KbCommentController`** — 2 ação(ões): store, destroy
- **`KbController`** — 5 ação(ões): index, show, softDelete, restore, history
- **`KbDecisionTreeController`** — 4 ação(ões): index, show, store, update
- **`KbEdgeController`** — 3 ação(ões): index, store, destroy
- **`KbFavoriteController`** — 1 ação(ões): toggle
- **`KbNodeController`** — 7 ação(ões): index, show, store, update, destroy, restore, reverify
- **`KbPathController`** — 4 ação(ões): index, show, store, update
- **`KbVersionController`** — 2 ação(ões): index, restoreVersion
- **`MemoriaController`** — 3 ação(ões): index, destroy, update

## Entities (Models Eloquent)

- **`KbBridgeState`** (tabela: `kb_bridge_state`)
- **`KbCategory`** (tabela: `kb_categories`)
- **`KbComment`** (tabela: `kb_comments`)
- **`KbDecisionTree`** (tabela: `kb_decision_trees`)
- **`KbDecisionTreeStep`** (tabela: `kb_decision_tree_steps`)
- **`KbEdge`** (tabela: `kb_edges`)
- **`KbFavorite`** (tabela: `kb_favorites`)
- **`KbNode`** (tabela: `kb_nodes`)
- **`KbNodeVersion`** (tabela: `kb_node_versions`)
- **`KbPath`** (tabela: `kb_paths`)
- **`KbPathStep`** (tabela: `kb_path_steps`)
- **`KbSubcategory`** (tabela: `kb_subcategories`)

## Migrations

- `2026_05_15_100001_create_kb_categories_table.php`
- `2026_05_15_100002_create_kb_subcategories_table.php`
- `2026_05_15_100003_create_kb_nodes_table.php`
- `2026_05_15_100004_create_kb_edges_table.php`
- `2026_05_15_100005_create_kb_paths_table.php`
- `2026_05_15_100006_create_kb_path_steps_table.php`
- `2026_05_15_100007_create_kb_decision_trees_table.php`
- `2026_05_15_100008_create_kb_decision_tree_steps_table.php`
- `2026_05_15_100009_create_kb_node_versions_table.php`
- `2026_05_15_100010_create_kb_favorites_table.php`
- `2026_05_15_100011_create_kb_comments_table.php`
- `2026_05_15_100012_create_kb_bridge_state_table.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `kb.view`
- `kb.softdelete`
- `kb.restore`
- `kb.history.view`

## Processamento / eventos

**Jobs (queue):** `KbBridgeFromMcpJob`, `KbEdgeAutoDeriverJob`

**Commands (artisan):** `KbDriftDetectorCommand`, `KbHealthCommand`, `KbReindexCommand`

**Observers:** `KbDecisionTreeStepObserver`, `KbNodeObserver`, `KbNodeVersionObserver`

## Peças adicionais

- **Factories:** 11 (`KbCategoryFactory`, `KbCommentFactory`, `KbDecisionTreeFactory`, `KbDecisionTreeStepFactory`, `KbEdgeFactory`)
- **Seeders:** `KbBridgeFromMcpSeeder`, `KbCategoriesSeeder`, `KbDatabaseSeeder`, `KbOperacionalSeeder`, `KbSubcategoriesSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `KB` |
| `module_label` | `Knowledge Base` |
| `module_description` | `Knowledge Base — biblioteca compartilhada de ADRs, sessions, runbooks, comparativos.` |
| `module_icon` | `fa fa-book-open` |
| `module_version` | `0.1` |
| `pid` | `` |
| `retention` | `[array(6 itens)]` |
| `pii_redaction` | `[array(2 itens)]` |
| `activity_log` | `[array(1 itens)]` |
| `bge` | `[array(3 itens)]` |
| `usd_to_brl` | `5` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Jana` | 5 |

## Integridade do banco

**Unique indexes:** 10

## Dependências Composer

- `php` ^8.1
- `spatie/laravel-permission` ^6.0

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (main) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ❌ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |

## Diferenças vs versões anteriores

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-05-29 08:06.**
**Reaxecutar com:** `php artisan module:spec KB`

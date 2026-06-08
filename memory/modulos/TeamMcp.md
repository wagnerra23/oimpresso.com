# Módulo: TeamMcp

> **Team MCP — governança self-host equivalente ao Anthropic Team plan: tokens MCP, DXT, quotas, Kanban backlog do time e auditoria de sessões Claude Code. Separado do Copiloto (agente IA do ERP) — ver ADR 0055/0057/0059.**

- **Alias:** `teammcp`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/TeamMcp`
- **Status:** 🟢 ativo
- **Providers:** Modules\TeamMcp\Providers\TeamMcpServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (11)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 18 |
| Controllers | 11 |
| Entities (Models) | 1 |
| Services | 7 |
| FormRequests | 5 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 3 |
| Arquivos de lang | 2 |
| Testes | 11 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/team` | `'TeamController@index'` |
| `POST` | `/team/{user}/token` | `'TeamController@gerarToken'` |
| `POST` | `/team/{user}/dxt` | `'TeamController@gerarDxt'` |
| `GET` | `/team/{user}/tokens` | `'TeamController@listTokens'` |
| `DELETE` | `/team/{user}/token/{tokenId}` | `'TeamController@revokeToken'` |
| `DELETE` | `/team/token/{token}` | `'TeamController@revogarToken'` |
| `POST` | `/team/{user}/quota` | `'TeamController@atualizarQuota'` |
| `GET` | `/team/export.csv` | `'TeamController@exportCsv'` |
| `GET` | `/tasks` | `'TasksAdminController@index'` |
| `PATCH` | `/tasks/{taskId}/status` | `'TasksAdminController@updateStatus'` |
| `GET` | `/cc-sessions` | `'CcSessionsController@index'` |
| `GET` | `/cc-sessions/search` | `'CcSessionsController@search'` |
| `GET` | `/cc-sessions/{sessionUuid}` | `'CcSessionsController@show'` |
| `GET` | `/scorecard` | `'ScorecardController@index'` |
| `GET` | `/` | `'InstallController@index'` |
| `POST` | `/` | `'InstallController@install'` |
| `GET` | `/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/update` | `'InstallController@update'` |

## Controllers

- **`TeamScopesController`** — 3 ação(ões): index, grant, revoke
- **`ToolsController`** — 2 ação(ões): index, execute
- **`CcSessionsController`** — 3 ação(ões): index, show, search
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`CcIngestController`** — 1 ação(ões): ingest
- **`HealthController`** — 2 ação(ões): publico, autenticado
- **`SyncMemoryWebhookController`** — 1 ação(ões): handle
- **`ScorecardController`** — 1 ação(ões): index
- **`TasksAdminController`** — 2 ação(ões): index, updateStatus
- **`TeamController`** — 8 ação(ões): index, gerarToken, gerarDxt, revogarToken, listTokens, revokeToken, atualizarQuota, exportCsv

## Entities (Models Eloquent)

- **`McpActor`** (tabela: `mcp_actors`)

## Migrations

- `2026_05_05_240001_create_mcp_actors_and_link_tokens.php`
- `2026_05_05_240002_seed_initial_actors.php`
- `2026_05_07_140000_update_actor_display_name_maiara.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `RotateTokenCommand`, `SeedActorsCommand`

## Peças adicionais

- **Seeders:** `McpActorsSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `TeamMcp` |
| `module_label` | `Team MCP` |
| `module_description` | `Governança self-host: tokens MCP, DXT, quotas, Kanban e auditoria Claude Code do time.` |
| `module_icon` | `fa fa-users` |
| `module_version` | `0.1` |
| `pid` | `` |

## Integridade do banco

**Unique indexes:** 1

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
**Reaxecutar com:** `php artisan module:spec TeamMcp`

# Módulo: ProjectMgmt

> **Project Management Jira-style — Kanban + Backlog + Roadmap + My Work + Inbox + Triage sobre as tabelas mcp_jira_projects/epics/cycles/tasks. Promovido a módulo próprio em 2026-05-04 (ADR 0070, supersede absorção em TeamMcp).**

- **Alias:** `projectmgmt`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/ProjectMgmt`
- **Status:** 🟢 ativo
- **Providers:** Modules\ProjectMgmt\Providers\ProjectMgmtServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 23 rotas — escopo médio
- ✅ Tem testes (11)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 23 |
| Controllers | 10 |
| Entities (Models) | 0 |
| Services | 2 |
| FormRequests | 9 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 0 |
| Arquivos de lang | 2 |
| Testes | 11 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `function (` |
| `GET` | `/board` | `'BoardController@index'` |
| `PATCH` | `/board/{taskId}/status` | `'BoardController@updateStatus'` |
| `GET` | `/board/{taskId}/detail` | `'BoardController@show'` |
| `POST` | `/board/{taskId}/comment` | `'BoardController@addComment'` |
| `GET` | `/board/users/suggest` | `'BoardController@suggestUsers'` |
| `POST` | `/board/{taskId}/watch` | `'BoardController@watch'` |
| `DELETE` | `/board/{taskId}/watch` | `'BoardController@unwatch'` |
| `POST` | `/board/{taskId}/subtask` | `'BoardController@addSubtask'` |
| `GET` | `/search` | `'SearchController@index'` |
| `GET` | `/my-work` | `'MyWorkController@index'` |
| `POST` | `/my-work/inbox/read-all` | `'MyWorkController@markAllRead'` |
| `POST` | `/my-work/inbox/{id}/read` | `'MyWorkController@markRead'` |
| `PATCH` | `/my-work/{taskId}/status` | `'MyWorkController@bumpStatus'` |
| `GET` | `/backlog` | `'BacklogController@index'` |
| `POST` | `/backlog/bulk` | `'BacklogController@bulk'` |
| `GET` | `/roadmap` | `'RoadmapController@index'` |
| `GET` | `/activity` | `'ActivityController@index'` |
| `GET` | `/burndown` | `'BurndownController@index'` |
| `GET` | `/` | `'InstallController@index'` |
| `POST` | `/` | `'InstallController@install'` |
| `GET` | `/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/update` | `'InstallController@update'` |

## Controllers

- **`ActivityController`** — 1 ação(ões): index
- **`ProjectsController`** — 4 ação(ões): index, show, store, decompose
- **`BacklogController`** — 2 ação(ões): index, bulk
- **`BoardController`** — 8 ação(ões): index, updateStatus, show, addComment, suggestUsers, watch, unwatch, addSubtask
- **`BurndownController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`MyWorkController`** — 4 ação(ões): index, markRead, markAllRead, bumpStatus
- **`RoadmapController`** — 1 ação(ões): index
- **`SearchController`** — 1 ação(ões): index

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `ProjectMgmtHealthCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ProjectMgmt` |
| `module_version` | `0.1` |
| `default_project_key` | `COPI` |
| `kanban_columns` | `[array(5 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `TeamMcp` | 1 |

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
**Reaxecutar com:** `php artisan module:spec ProjectMgmt`

# Módulo: SRS

> **SRS — System Requirements Spec (ex-MemCofre, renomeado Fase 3.7 PR-2). Cofre de documentação viva — ingestão de evidências (screenshots, chat logs, erros) → IA classifica → vira requisitos estruturados em memory/requisitos/. URL/permissions/config keys mantêm prefixo legacy `memcofre.*` por compatibilidade.**

- **Alias:** `srs`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/SRS`
- **Status:** 🟢 ativo
- **Providers:** Modules\SRS\Providers\SrsServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (10)
- 🔐 Registra 2 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 17 |
| Controllers | 8 |
| Entities (Models) | 7 |
| Services | 6 |
| FormRequests | 4 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 8 |
| Arquivos de lang | 1 |
| Testes | 10 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `[\Modules\SRS\Http\Controllers\DashboardController::class, 'index']` |
| `GET` | `/ingest` | `[\Modules\SRS\Http\Controllers\IngestController::class, 'show']` |
| `POST` | `/ingest` | `[\Modules\SRS\Http\Controllers\IngestController::class, 'store']` |
| `GET` | `/inbox` | `[\Modules\SRS\Http\Controllers\InboxController::class, 'index']` |
| `POST` | `/inbox/{evidence}/triage` | `[\Modules\SRS\Http\Controllers\InboxController::class, 'triage']` |
| `POST` | `/inbox/{evidence}/apply` | `[\Modules\SRS\Http\Controllers\InboxController::class, 'apply']` |
| `DELETE` | `/inbox/{evidence}` | `[\Modules\SRS\Http\Controllers\InboxController::class, 'destroy']` |
| `GET` | `/modulos/{module}` | `[\Modules\SRS\Http\Controllers\ModuloController::class, 'show']` |
| `GET` | `/memoria` | `[\Modules\SRS\Http\Controllers\MemoriaController::class, 'index']` |
| `GET` | `/memoria/file` | `[\Modules\SRS\Http\Controllers\MemoriaController::class, 'file']` |
| `GET` | `/chat` | `[\Modules\SRS\Http\Controllers\ChatController::class, 'index']` |
| `POST` | `/chat/ask` | `[\Modules\SRS\Http\Controllers\ChatController::class, 'ask']` |
| `POST` | `/chat/new` | `[\Modules\SRS\Http\Controllers\ChatController::class, 'newSession']` |
| `GET` | `/` | `[\Modules\SRS\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `/` | `[\Modules\SRS\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `/uninstall` | `[\Modules\SRS\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/update` | `[\Modules\SRS\Http\Controllers\InstallController::class, 'update']` |

## Controllers

- **`ChatController`** — 3 ação(ões): index, ask, newSession
- **`DashboardController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): modifyAdminMenu, superadmin_package, user_permissions
- **`InboxController`** — 4 ação(ões): index, triage, apply, destroy
- **`IngestController`** — 2 ação(ões): show, store
- **`InstallController`** — 0 ação(ões): 
- **`MemoriaController`** — 2 ação(ões): index, file
- **`ModuloController`** — 1 ação(ões): show

## Entities (Models Eloquent)

- **`DocChatMessage`** (tabela: `docs_chat_messages`)
- **`DocEvidence`** (tabela: `docs_evidences`)
- **`DocLink`** (tabela: `docs_links`)
- **`DocPage`** (tabela: `docs_pages`)
- **`DocRequirement`** (tabela: `docs_requirements`)
- **`DocSource`** (tabela: `docs_sources`)
- **`DocValidationRun`** (tabela: `docs_validation_runs`)

## Migrations

- `2026_04_22_000001_create_docs_sources_table.php`
- `2026_04_22_000002_create_docs_evidences_table.php`
- `2026_04_22_000003_create_docs_requirements_table.php`
- `2026_04_22_000004_create_docs_links_table.php`
- `2026_04_22_000005_create_docs_chat_messages_table.php`
- `2026_04_22_000006_create_docs_pages_table.php`
- `2026_04_22_000007_create_docs_validation_runs_table.php`
- `2026_04_22_000008_add_fulltext_index_to_docs_evidences.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `memcofre.access`
- `memcofre.admin`

## Processamento / eventos

**Commands (artisan):** `AuditModuleCommand`, `GenTestCommand`, `InstallHooksCommand`, `MigrateModuleCommand`, `SrsHealthCommand`, `SyncMemoriesCommand`, `SyncPagesCommand`, `ValidateCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `MemCofre` |
| `module_label` | `MemCofre` |
| `module_description` | `Documentação viva · evidências → requisitos` |
| `module_icon` | `fa fa-folder-open` |
| `module_version` | `0.1` |
| `requirements_dir` | `D:\oimpresso.com\memory/requisitos` |
| `memory` | `[array(3 itens)]` |
| `source_types` | `[array(6 itens)]` |
| `evidence_status` | `[array(5 itens)]` |
| `ai` | `[array(4 itens)]` |
| `upload` | `[array(4 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Jana` | 1 |

## Integridade do banco

**Unique indexes:** 3

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
**Reaxecutar com:** `php artisan module:spec SRS`

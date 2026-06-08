# Módulo: Jana

> **Jana — chat IA conversacional do business (ex-Copiloto, renomeado Fase 3.7 PR-2). Conversa, sugere metas, monitora execução. Tenancy híbrida (business_id nullable). URL/permissions/config keys mantêm prefixo legacy `copiloto.*` por compatibilidade.**

- **Alias:** `jana`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Jana`
- **Status:** 🟢 ativo
- **Providers:** Modules\Jana\Providers\JanaServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 39 rotas — escopo médio
- ✅ Tem testes (67)
- 🔐 Registra 5 permissão(ões) Spatie
- ⚙️ Processamento assíncrono: 8 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 4 outro(s) módulo(s)
- 🗃️ 31 foreign keys — alto acoplamento em dados
- 🗄️ Tem triggers MySQL (2) — append-only / imutabilidade

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 39 |
| Controllers | 16 |
| Entities (Models) | 40 |
| Services | 60 |
| FormRequests | 8 |
| Middleware | 1 |
| Views Blade | 9 |
| Migrations | 64 |
| Arquivos de lang | 1 |
| Testes | 67 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `'ChatController@index'` |
| `GET` | `/cockpit` | `'ChatController@cockpit'` |
| `GET` | `/painel` | `'PainelController@index'` |
| `POST` | `/conversas` | `'ChatController@criarConversa'` |
| `GET` | `/conversas/nova` | `'ChatController@novaConversa'` |
| `GET` | `/conversas/{id}` | `'ChatController@show'` |
| `POST` | `/conversas/{id}/mensagens` | `'ChatController@send'` |
| `POST` | `/conversas/{id}/mensagens/stream` | `'ChatController@sendStream'` |
| `PATCH` | `/conversas/{id}` | `'ChatController@updateConversa'` |
| `POST` | `/sugestoes/{id}/escolher` | `'ChatController@escolher'` |
| `POST` | `/sugestoes/{id}/rejeitar` | `'ChatController@rejeitar'` |
| `GET` | `/dashboard` | `'DashboardController@index'` |
| `POST` | `/metas/{id}/reapurar` | `'MetasController@reapurar'` |
| `GET` | `/metas/{id}/fonte` | `[\Modules\KB\Http\Controllers\FontesController::class, 'show']` |
| `PATCH` | `/metas/{id}/fonte` | `[\Modules\KB\Http\Controllers\FontesController::class, 'update']` |
| `GET` | `/alertas` | `'AlertasController@index'` |
| `GET` | `/alertas/config` | `'AlertasController@config'` |
| `PATCH` | `/alertas/config` | `'AlertasController@updateConfig'` |
| `GET` | `/memoria` | `[\Modules\KB\Http\Controllers\MemoriaController::class, 'index']` |
| `PATCH` | `/memoria/{id}` | `[\Modules\KB\Http\Controllers\MemoriaController::class, 'update']` |
| `DELETE` | `/memoria/{id}` | `[\Modules\KB\Http\Controllers\MemoriaController::class, 'destroy']` |
| `GET` | `/brief` | `'BriefController@index'` |
| `GET` | `/regras` | `'RegrasController@index'` |
| `GET` | `/superadmin/metas` | `'SuperadminController@metas'` |
| `GET` | `/admin/custos` | `'Admin\CustosController@index'` |
| `GET` | `/admin/governanca` | `'Admin\GovernancaController@index'` |
| `GET` | `/admin/qualidade` | `'Admin\QualidadeController@index'` |
| `GET` | `/admin/roadmap` | `'Admin\RoadmapController@index'` |
| `GET` | `/admin/jana-pro/preview` | `'Admin\JanaProController@preview'` |
| `GET` | `/` | `'InstallController@index'` |
| `POST` | `/` | `'InstallController@install'` |
| `GET` | `/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/update` | `'InstallController@update'` |
| `POST` | `/sync-memory` | `'SyncMemoryWebhookController@handle'` |
| `GET` | `/health` | `'HealthController@publico'` |
| `GET` | `/health/auth` | `'HealthController@autenticado'` |
| `POST` | `/ingest` | `'CcIngestController@ingest'` |
| `RESOURCE` | `/metas` | `'MetasController'` |
| `RESOURCE` | `/metas.periodos` | `'PeriodosController'` |

## Controllers

- **`CustosController`** — 1 ação(ões): index
- **`GovernancaController`** — 1 ação(ões): index
- **`JanaProController`** — 1 ação(ões): preview
- **`QualidadeController`** — 1 ação(ões): index
- **`RoadmapController`** — 1 ação(ões): index
- **`AlertasController`** — 3 ação(ões): index, config, updateConfig
- **`BriefController`** — 1 ação(ões): index
- **`ChatController`** — 10 ação(ões): index, show, criarConversa, novaConversa, updateConversa, send, sendStream, escolher +2
- **`DashboardController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`MetasController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, reapurar
- **`PainelController`** — 1 ação(ões): index
- **`PeriodosController`** — 3 ação(ões): store, update, destroy
- **`RegrasController`** — 1 ação(ões): index
- **`SuperadminController`** — 1 ação(ões): metas

## Entities (Models Eloquent)

- **`CacheSemantico`** (tabela: `jana_cache_semantico`)
- **`Conversa`** (tabela: `jana_conversas`)
- **`HealthNarrative`** (tabela: `jana_health_narratives`)
- **`McpAlerta`** (tabela: `mcp_alertas`)
- **`McpAuditLog`** (tabela: `mcp_audit_log`)
- **`McpCcBlob`** (tabela: `mcp_cc_blobs`)
- **`McpCcMessage`** (tabela: `mcp_cc_messages`)
- **`McpCcSession`** (tabela: `mcp_cc_sessions`)
- **`McpComponent`** (tabela: `mcp_components`)
- **`McpCycle`** (tabela: `mcp_cycles`)
- **`McpCycleGoal`** (tabela: `mcp_cycle_goals`)
- **`McpEpic`** (tabela: `mcp_epics`)
- **`McpInboxNotification`** (tabela: `mcp_inbox_notifications`)
- **`McpMemoryDocument`** (tabela: `mcp_memory_documents`)
- **`McpMemoryDocumentHistory`** (tabela: `mcp_memory_documents_history`)
- **`McpProject`** (tabela: `mcp_jira_projects`)
- **`McpQuota`** (tabela: `mcp_quotas`)
- **`McpScope`** (tabela: `mcp_scopes`)
- **`McpSkill`** (tabela: `mcp_skills`)
- **`McpSkillApproval`** (tabela: `mcp_skill_approvals`)
- **`McpSkillLabel`** (tabela: `mcp_skill_labels`)
- **`McpSkillTestRun`** (tabela: `mcp_skill_test_runs`)
- **`McpSkillVersion`** (tabela: `mcp_skill_versions`)
- **`McpTask`** (tabela: `mcp_tasks`)
- **`McpTaskComment`** (tabela: `mcp_task_comments`)
- **`McpTaskDependency`** (tabela: `mcp_task_dependencies`)
- **`McpTaskEvent`** (tabela: `mcp_task_events`)
- **`McpTaskWatcher`** (tabela: `mcp_task_watchers`)
- **`McpToken`** (tabela: `mcp_tokens`)
- **`McpUsageDiaria`** (tabela: `mcp_usage_diaria`)
- **`McpUserScope`** (tabela: `mcp_user_scopes`)
- **`MemoriaFato`** (tabela: `jana_memoria_facts`)
- **`MemoriaGabarito`** (tabela: `jana_memoria_gabarito`)
- **`MemoriaMetrica`** (tabela: `jana_memoria_metricas`)
- **`Mensagem`** (tabela: `jana_mensagens`)
- **`Meta`** (tabela: `jana_metas`)
- **`MetaApuracao`** (tabela: `jana_meta_apuracoes`)
- **`MetaFonte`** (tabela: `jana_meta_fontes`)
- **`MetaPeriodo`** (tabela: `jana_meta_periodos`)
- **`Sugestao`** (tabela: `jana_sugestoes`)

## Migrations

- `2026_04_24_000001_create_copiloto_metas_table.php`
- `2026_04_24_000002_create_copiloto_meta_periodos_table.php`
- `2026_04_24_000003_create_copiloto_meta_fontes_table.php`
- `2026_04_24_000004_create_copiloto_meta_apuracoes_table.php`
- `2026_04_24_000005_create_copiloto_conversas_table.php`
- `2026_04_24_000006_create_copiloto_mensagens_table.php`
- `2026_04_24_000007_create_copiloto_sugestoes_table.php`
- `2026_04_27_000001_create_copiloto_memoria_facts_table.php`
- `2026_04_29_000001_create_copiloto_memoria_metricas_table.php`
- `2026_04_29_100001_create_mcp_scopes_table.php`
- `2026_04_29_100002_create_mcp_user_scopes_table.php`
- `2026_04_29_100003_create_mcp_tokens_table.php`
- `2026_04_29_100004_create_mcp_quotas_table.php`
- `2026_04_29_100005_create_mcp_audit_log_table.php`
- `2026_04_29_100006_create_mcp_usage_diaria_table.php`
- `2026_04_29_100007_create_mcp_alertas_table.php`
- `2026_04_29_100008_create_mcp_memory_documents_table.php`
- `2026_04_29_100009_create_mcp_memory_documents_history_table.php`
- `2026_04_29_200001_create_copiloto_memoria_gabarito_table.php`
- `2026_04_29_300001_create_mcp_cc_sessions_table.php`
- `2026_04_29_300002_create_mcp_cc_messages_table.php`
- `2026_04_29_300003_create_mcp_cc_blobs_table.php`
- `2026_04_29_400001_create_copiloto_cache_semantico_table.php`
- `2026_04_29_500001_create_copiloto_business_profile_table.php`
- `2026_04_29_500002_add_promotion_to_memoria_facts.php`
- `2026_04_29_500003_create_copiloto_negative_cache_table.php`
- `2026_04_29_600001_create_mcp_alertas_eventos_table.php`
- `2026_04_30_120001_expand_mcp_memory_documents_type_enum.php`
- `2026_04_30_180001_create_mcp_tasks_table.php`
- `2026_04_30_200001_add_business_id_to_mcp_memory_documents.php`
- `2026_05_01_100001_add_typed_cols_to_mcp_memory_documents.php`
- `2026_05_01_120001_create_mcp_task_comments_table.php`
- `2026_05_01_120002_create_mcp_task_events_table.php`
- `2026_05_04_180001_create_mcp_jira_projects_table.php`
- `2026_05_04_180002_create_mcp_epics_table.php`
- `2026_05_04_180003_create_mcp_cycles_table.php`
- `2026_05_04_180004_create_mcp_cycle_goals_table.php`
- `2026_05_04_180005_create_mcp_components_table.php`
- `2026_05_04_180006_create_mcp_workflows_table.php`
- `2026_05_04_180007_create_mcp_issue_templates_table.php`
- `2026_05_04_180008_create_mcp_views_table.php`
- `2026_05_04_180009_create_mcp_inbox_notifications_table.php`
- `2026_05_04_180010_create_mcp_task_dependencies_table.php`
- `2026_05_04_180011_create_mcp_task_watchers_table.php`
- `2026_05_04_180012_create_mcp_task_attachments_table.php`
- `2026_05_04_180013_create_mcp_task_memory_links_table.php`
- `2026_05_04_180014_create_mcp_git_links_table.php`
- `2026_05_04_180015_extend_mcp_tasks_for_jira_style.php`
- `2026_05_05_220001_create_mcp_skills_table.php`
- `2026_05_05_220002_create_mcp_skill_versions_table.php`
- `2026_05_05_220003_create_mcp_skill_labels_table.php`
- `2026_05_05_220004_create_mcp_skill_test_runs_table.php`
- `2026_05_05_220005_create_mcp_skill_approvals_table.php`
- `2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php`
- `2026_05_06_120000_rename_copiloto_tables_to_jana.php`
- `2026_05_09_140000_rename_copiloto_permissions_to_jana.php`
- `2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php`
- `2026_05_10_150000_seed_auditoria_mcp_jira_project.php`
- `2026_05_13_120000_create_mcp_handoff_summaries_table.php`
- `2026_05_13_130000_create_mcp_handoff_diffs_table.php`
- `2026_05_13_140000_create_mcp_weekly_digests_table.php`
- `2026_05_13_150000_create_mcp_doc_summaries_table.php`
- `2026_05_15_120000_add_contextual_context_to_mcp_memory_documents.php`
- `2026_05_16_220001_create_mcp_scorecard_ai_suggestions_table.php`

## Views (Blade)

**Total:** 9 arquivos

**Pastas principais:**

- `metas/` — 4 arquivo(s)
- `alertas/` — 2 arquivo(s)
- `emails/` — 1 arquivo(s)
- `fontes/` — 1 arquivo(s)
- `superadmin/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `jana.access`
- `jana.chat`
- `jana.metas.manage`
- `jana.superadmin`
- `jana.admin.custos.view`

## Processamento / eventos

**Jobs (queue):** `ApurarMetaJob`, `ExtrairFatosDaConversaJob`, `InboxAutoCleanupJob`, `ReindexarDocumentoJob`, `NarrarSaudeEcosistemaJob`, `LangfuseTraceJob`

**Commands (artisan):** `ApurarMetricasCommand`, `AvaliarGabaritoCommand`, `BackfillFatosCommand`, `BackfillTasksFromMarkdownCommand`, `CacheStatsCommand`, `CleanupMemoriaCommand`, `ContextualizeBackfillCommand`, `FreshnessCheckCommand`, `HealthCheckCommand`, `JanaBacklinksSweepCommand`, `JanaCyclesAutoCloseExpiredCommand`, `JanaDriftSentinelCommand`, `JanaRagasCiCommand`, `JanaRagasEvalCommand`, `JanaValidateMemoryCommand`, `JanaWeeklyDigestCommand`, `McpAdrMigrarFrontmatterCommand`, `McpGenerateDxtCommand`, `McpSkillsImportFromGitCommand`, `McpSyncMemoryCommand`, `McpSystemTokenCommand`, `McpTasksHealthCheckCommand`, `McpTasksSyncCommand`, `McpTokenGerarCommand`, `MetricasReflexivasCommand`, `RetentionPurgeCommand`, `SeedAdrsCommand`, `SinteseSemanalCommand`, `SystemAuditCommand`

**Events:** `CopilotoDesvioDetectado`

**Listeners:** `NotificarDesvioListener`

## Peças adicionais

- **Notifications:** `MetaDesvioNotification`
- **Seeders:** `CopilotoDatabaseSeeder`, `McpDefaultsSeeder`, `McpScopesSeeder`, `MemoriaGabaritoSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Copiloto` |
| `module_label` | `Copiloto` |
| `module_description` | `Copiloto de IA do negócio — chat + metas + monitoramento` |
| `module_icon` | `fa fa-compass` |
| `module_version` | `0.1` |
| `pid` | `` |
| `ai_adapter` | `auto` |
| `openai` | `[array(5 itens)]` |
| `ai` | `[array(3 itens)]` |
| `dry_run` | `false` |
| `memoria` | `[array(4 itens)]` |
| `apuracao` | `[array(4 itens)]` |
| `context_cache_ttl_minutes` | `10` |
| `alertas` | `[array(3 itens)]` |
| `mcp` | `[array(6 itens)]` |
| `meta_plataforma` | `[array(5 itens)]` |
| `hits` | `[array(1 itens)]` |
| `hyde` | `[array(1 itens)]` |
| `reranker` | `[array(3 itens)]` |
| `freshness` | `[array(3 itens)]` |
| `time_decay` | `[array(4 itens)]` |
| `negative_cache` | `[array(2 itens)]` |
| `cache` | `[array(3 itens)]` |
| `summarizer` | `[array(3 itens)]` |
| `auto_summarizer` | `[array(8 itens)]` |
| `contextual_retrieval` | `[array(7 itens)]` |
| `telemetry` | `[array(3 itens)]` |
| `peso_real` | `[array(7 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `ADS` | 1 |
| `Governance` | 1 |
| `KB` | 1 |
| `TeamMcp` | 1 |

## Integridade do banco

**Foreign Keys** (31):

- `business_id` → `business.id`
- `criada_por_user_id` → `users.id`
- `meta_id` → `copiloto_metas.id`
- `meta_id` → `copiloto_metas.id`
- `meta_id` → `copiloto_metas.id`
- `business_id` → `business.id`
- `user_id` → `users.id`
- `conversa_id` → `copiloto_conversas.id`
- `conversa_id` → `copiloto_conversas.id`
- `meta_id` → `copiloto_metas.id`
- `business_id` → `business.id`
- `scope_id` → `mcp_scopes.id`
- `user_id` → `users.id`
- `business_id` → `business.id`
- `user_id` → `users.id`
- `user_id` → `users.id`
- `user_id` → `users.id`
- `business_id` → `business.id`
- `mcp_token_id` → `mcp_tokens.id`
- `user_id` → `users.id`
- _... +11 FKs_

**Triggers MySQL** (2): `trg_mcp_audit_log_no_update`, `trg_mcp_audit_log_no_delete`

**Unique indexes:** 34

## Dependências Composer

- `php` ^8.1
- `spatie/laravel-permission` ^6.0
- `spatie/laravel-activitylog` ^4.0
- `openai-php/laravel` *

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (main) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ❌ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |

## Diferenças vs versões anteriores

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-05-29 08:06.**
**Reaxecutar com:** `php artisan module:spec Jana`

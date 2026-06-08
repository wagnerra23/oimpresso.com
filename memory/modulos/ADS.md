# Módulo: ADS

> **Adaptive Decision System — meta-orquestrador de decisões automatizadas. Risk Engine + Confidence Engine + Policy Engine + Decision Router + Learning Loop.**

- **Alias:** `ads`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/ADS`
- **Status:** 🟢 ativo
- **Providers:** Modules\ADS\Providers\AdsServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 46 rotas — escopo médio
- ✅ Tem testes (18)
- 🔗 Acoplamento: depende de 2 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 46 |
| Controllers | 15 |
| Entities (Models) | 0 |
| Services | 22 |
| FormRequests | 16 |
| Middleware | 1 |
| Views Blade | 0 |
| Migrations | 15 |
| Arquivos de lang | 0 |
| Testes | 18 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/admin/decisoes` | `[DecisoesController::class, 'index']` |
| `GET` | `/admin/decisoes/{id}` | `[DecisoesController::class, 'show']` |
| `POST` | `/admin/decisoes/{id}/approve` | `[DecisoesController::class, 'approve']` |
| `POST` | `/admin/decisoes/{id}/reject` | `[DecisoesController::class, 'reject']` |
| `POST` | `/admin/decisoes/{id}/dismiss` | `[DecisoesController::class, 'dismiss']` |
| `GET` | `/admin/policy` | `[PolicyController::class, 'index']` |
| `GET` | `/admin/confidence` | `[ConfidenceController::class, 'index']` |
| `GET` | `/admin/metricas` | `[MetricasController::class, 'index']` |
| `GET` | `/admin/patterns` | `[PatternsController::class, 'index']` |
| `GET` | `/admin/skills` | `[SkillsController::class, 'index']` |
| `GET` | `/admin/skills/{slug}` | `[SkillsController::class, 'show']` |
| `GET` | `/admin/skills/{slug}/edit` | `[SkillsController::class, 'edit']` |
| `POST` | `/admin/skills/{slug}` | `[SkillsController::class, 'store']` |
| `GET` | `/admin/skills/{slug}/test` | `[SkillsController::class, 'test']` |
| `POST` | `/admin/skills/{slug}/test` | `[SkillsController::class, 'runTest']` |
| `GET` | `/admin/skills-review` | `[SkillsController::class, 'review']` |
| `POST` | `/admin/skills/versions/{versionId}/approve` | `[SkillsController::class, 'approve']` |
| `POST` | `/admin/skills/versions/{versionId}/reject` | `[SkillsController::class, 'reject']` |
| `POST` | `/admin/skills/versions/{versionId}/publish` | `[SkillsController::class, 'publish']` |
| `POST` | `/admin/skills/{slug}/move-label` | `[SkillsController::class, 'moveLabel']` |
| `GET` | `/admin/tools` | `[ToolsController::class, 'index']` |
| `POST` | `/admin/tools/{name}/execute` | `[ToolsController::class, 'execute']` |
| `GET` | `/admin/learning` | `[LearningController::class, 'index']` |
| `GET` | `/admin/meta-skills` | `[MetaSkillsController::class, 'index']` |
| `POST` | `/admin/meta-skills/{id}/toggle` | `[MetaSkillsController::class, 'toggle']` |
| `POST` | `/admin/meta-skills` | `[MetaSkillsController::class, 'store']` |
| `POST` | `/admin/meta-skills/validate` | `[MetaSkillsController::class, 'validateRule']` |
| `GET` | `/admin/team-scopes` | `[TeamScopesController::class, 'index']` |
| `POST` | `/admin/team-scopes/grant` | `[TeamScopesController::class, 'grant']` |
| `POST` | `/admin/team-scopes/revoke` | `[TeamScopesController::class, 'revoke']` |
| `GET` | `/admin/graph` | `[GraphController::class, 'index']` |
| `GET` | `/admin/conflicts` | `[ConflictsController::class, 'index']` |
| `GET` | `/admin/projects` | `[ProjectsController::class, 'index']` |
| `POST` | `/admin/projects` | `[ProjectsController::class, 'store']` |
| `GET` | `/admin/projects/{id}` | `[ProjectsController::class, 'show']` |
| `POST` | `/admin/projects/{id}/decompose` | `[ProjectsController::class, 'decompose']` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `ads/health` | `[DecisionController::class, 'health']` |
| `POST` | `ads/route` | `[DecisionController::class, 'route']` |
| `GET` | `ads/recent-commits` | `[RecentEventsController::class, 'commits']` |
| `GET` | `ads/recent-errors` | `[RecentEventsController::class, 'errors']` |
| `GET` | `ads/scope/check` | `[ScopeController::class, 'check']` |
| `GET` | `ads/scope/user/{user_id}` | `[ScopeController::class, 'listUserModules']` |
| `POST` | `ads/context-for-task` | `[ContextController::class, 'forTask']` |

## Controllers

- **`ConfidenceController`** — 1 ação(ões): index
- **`ConflictsController`** — 1 ação(ões): index
- **`DecisoesController`** — 5 ação(ões): index, show, approve, reject, dismiss
- **`LearningController`** — 1 ação(ões): index
- **`MetaSkillsController`** — 4 ação(ões): index, toggle, store, validateRule
- **`MetricasController`** — 1 ação(ões): index
- **`PatternsController`** — 1 ação(ões): index
- **`PolicyController`** — 1 ação(ões): index
- **`SkillsController`** — 11 ação(ões): index, show, edit, store, test, runTest, review, approve +3
- **`ContextController`** — 1 ação(ões): forTask
- **`DecisionController`** — 2 ação(ões): route, health
- **`RecentEventsController`** — 2 ação(ões): commits, errors
- **`ScopeController`** — 2 ação(ões): check, listUserModules
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 

## Migrations

- `2026_05_03_000001_create_mcp_file_locks_table.php`
- `2026_05_03_000002_create_mcp_decision_thresholds_table.php`
- `2026_05_03_000003_create_mcp_confidence_scores_table.php`
- `2026_05_03_000004_create_mcp_dual_brain_decisions_table.php`
- `2026_05_03_000005_create_mcp_decision_patterns_table.php`
- `2026_05_03_180001_add_dismissed_at_to_dual_brain_decisions.php`
- `2026_05_03_200001_add_learning_loop_columns.php`
- `2026_05_03_220001_create_mcp_governance_rules_table.php`
- `2026_05_03_230001_create_mcp_projects_table.php`
- `2026_05_03_230002_create_mcp_project_parts_table.php`
- `2026_05_03_230003_link_decisions_to_projects.php`
- `2026_05_03_240001_create_mcp_tool_executions_table.php`
- `2026_05_03_250001_create_mcp_decision_links_table.php`
- `2026_05_03_260001_create_mcp_user_module_access_table.php`
- `2026_05_11_190001_repair_dual_brain_learning_loop_drift.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `AdsHealthCommand`, `AutoGenerateTasksCommand`, `LearnPatternsCommand`, `PlanDecisionsCommand`, `ProcessBrainBCommand`, `ReviewDecisionsCommand`, `SkillScaffoldCommand`

## Peças adicionais

- **Seeders:** `AdsAdminSkillsPermissionsSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ADS` |
| `api_key` | `9OdzFA0stWqp1GaLC3fNB8VeoxlTPvKn4hjrbcZ6DMUkwi2R` |
| `brain_a_risk_max` | `0.3` |
| `brain_a_conf_min` | `0.7` |
| `brain_b_risk_max` | `0.7` |
| `hitl1_cancel_window_seconds` | `600` |
| `file_lock_ttl_seconds` | `1800` |
| `confidence_human_modify_weight` | `3` |
| `confidence_decay_days` | `90` |
| `confidence_decay_factor` | `0.5` |
| `confidence_initial` | `0.5` |
| `learning_min_samples` | `10` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Compras` | 2 |
| `NFSe` | 2 |

## Integridade do banco

**Foreign Keys** (5):

- `business_id` → `business.id`
- `business_id` → `business.id`
- `business_id` → `business.id`
- `project_id` → `mcp_projects.id`
- `user_id` → `users.id`

**Unique indexes:** 9

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
**Reaxecutar com:** `php artisan module:spec ADS`

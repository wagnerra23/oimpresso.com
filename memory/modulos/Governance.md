# Módulo: Governance

> **Governança consolidada — ActionGate runtime, audit dashboard, ADRs pending approvals, policies CRUD, drift alerts. Constituição Art. 8 + Art. 9 operacional. Trust L1 (Wagner + ADR aprovado).**

- **Alias:** `governance`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Governance`
- **Status:** 🟢 ativo
- **Providers:** Modules\Governance\Providers\GovernanceServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (32)
- 🔐 Registra 3 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 3 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 11 |
| Controllers | 7 |
| Entities (Models) | 1 |
| Services | 18 |
| FormRequests | 4 |
| Middleware | 1 |
| Views Blade | 0 |
| Migrations | 4 |
| Arquivos de lang | 3 |
| Testes | 32 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `fn (` |
| `GET` | `/dashboard` | `[DashboardController::class, 'index']` |
| `GET` | `/policies` | `[PoliciesController::class, 'index']` |
| `POST` | `/policies/{id}/toggle` | `[PoliciesController::class, 'toggle']` |
| `GET` | `/audit` | `[AuditController::class, 'index']` |
| `GET` | `/drift` | `[DriftAlertsController::class, 'index']` |
| `GET` | `/module-grades` | `[ModuleGradeController::class, 'index']` |
| `GET` | `/module-grades/{name}` | `[ModuleGradeController::class, 'show']` |
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |

## Controllers

- **`AuditController`** — 1 ação(ões): index
- **`DashboardController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`DriftAlertsController`** — 1 ação(ões): index
- **`InstallController`** — 0 ação(ões): 
- **`ModuleGradeController`** — 2 ação(ões): index, show
- **`PoliciesController`** — 2 ação(ões): index, toggle

## Entities (Models Eloquent)

- **`Initiative`** (tabela: `mcp_governance_initiatives`)

## Migrations

- `2026_05_16_120000_create_mcp_module_grades_history_table.php`
- `2026_05_17_000001_create_mcp_scorecard_runs_table.php`
- `2026_05_17_000002_create_mcp_observability_spans_table.php`
- `2026_05_17_000003_create_mcp_governance_initiatives_table.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `governance.dashboard.view`
- `governance.policies.edit`
- `governance.audit.view`

## Processamento / eventos

**Commands (artisan):** `CharterAuditCommand`, `CharterHealthCommand`, `CharterMetricsCommand`, `DetectDriftCommand`, `GovernanceAuditCommand`, `GovernanceHealthCommand`, `ModuleGradeCommand`, `ModuleGradeSnapshotCommand`, `ModuleGradeV4Command`, `ObservabilityAggregateCommand`, `ScorecardInitiativeSyncCommand`, `ScorecardSnapshotCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Governance` |
| `actiongate_mode` | `warn` |
| `next_review_at` | `2026-08-05` |
| `d1_hardened` | `true` |
| `drift_framework_enabled` | `true` |
| `drift_checkers` | `[array(6 itens)]` |
| `multi_tenant_scope_allowlist` | `[array(6 itens)]` |
| `routes_zombie_allowlist` | `[array(7 itens)]` |
| `drift_centrifugo_channel` | `governance:drift` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `TeamMcp` | 1 |
| `ADS` | 1 |
| `Vestuario` | 1 |

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
**Reaxecutar com:** `php artisan module:spec Governance`

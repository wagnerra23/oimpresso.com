# Módulo: Admin

> **Centro de Operações Wagner-only @ CT 100 (Tailscale-only). Agrega Curador, Health Checks, Cycles+Tasks, ADRs Tier 0 violados. Read-mostly. Não substitui Officeimpresso superadmin nem /copiloto/admin/team — agrega visão deles. Ver ADR 0122.**

- **Alias:** `admin`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Admin`
- **Status:** 🟢 ativo
- **Providers:** Modules\Admin\Providers\AdminServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (19)
- 🔗 Acoplamento: depende de 4 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 20 |
| Controllers | 8 |
| Entities (Models) | 0 |
| Services | 12 |
| FormRequests | 7 |
| Middleware | 2 |
| Views Blade | 0 |
| Migrations | 1 |
| Arquivos de lang | 0 |
| Testes | 19 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/` | `IndexController::class` |
| `POST` | `mutations/curador/apply` | `[MutationsController::class, 'applyCurador']` |
| `POST` | `mutations/mcp-token/regenerate` | `[MutationsController::class, 'regenerateMcpToken']` |
| `POST` | `mutations/health-check/run-now` | `[MutationsController::class, 'runHealthCheckNow']` |
| `GET` | `governance-v4` | `GovernanceV4DashboardController::class` |
| `GET` | `governance/v4` | `[GovernanceV4DashboardController::class, 'indexV2']` |
| `POST` | `governance/v4/initiative` | `[GovernanceV4DashboardController::class, 'createInitiative']` |
| `POST` | `governance/v4/override-bucket` | `[GovernanceV4DashboardController::class, 'overrideBucket']` |
| `GET` | `rag-quality` | `RagQualityDashboardController::class` |
| `GET` | `screen-review/dashboard` | `[ScreenReviewController::class, 'dashboard']` |
| `GET` | `screen-review` | `[ScreenReviewController::class, 'index']` |
| `POST` | `screen-review/{screenPath}/status` | `[ScreenReviewController::class, 'updateStatus']` |
| `GET` | `/` | `[FeatureFlagsController::class, 'index']` |
| `GET` | `{key}` | `[FeatureFlagsController::class, 'show']` |
| `POST` | `{key}/biz-rule` | `[FeatureFlagsController::class, 'setBizRule']` |
| `POST` | `{key}/env-enabled` | `[FeatureFlagsController::class, 'setEnvEnabled']` |
| `POST` | `cache/clear` | `[FeatureFlagsController::class, 'clearCache']` |

## Controllers

- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`FeatureFlagsController`** — 5 ação(ões): index, show, setBizRule, setEnvEnabled, clearCache
- **`GovernanceV4DashboardController`** — 3 ação(ões): indexV2, createInitiative, overrideBucket
- **`IndexController`** — 0 ação(ões): 
- **`InstallController`** — 0 ação(ões): 
- **`MutationsController`** — 3 ação(ões): applyCurador, regenerateMcpToken, runHealthCheckNow
- **`RagQualityDashboardController`** — 0 ação(ões): 
- **`ScreenReviewController`** — 3 ação(ões): dashboard, index, updateStatus

## Migrations

- `2026_05_10_000001_create_mcp_admin_audit_log_table.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `AdminHealthCommand`, `ExportAuditCommand`, `ScreenCatalogGenerateCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Admin` |
| `wagner_user_id` | `1` |
| `wagner_business_id` | `1` |
| `fallback_username` | `` |
| `tailscale_cidrs` | `100.99.0.0/16` |
| `subdomain` | `admin.oimpresso.com` |
| `bypass_local` | `false` |
| `bypass_tailscale` | `false` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Governance` | 2 |
| `Jana` | 1 |
| `KB` | 1 |
| `Brief` | 1 |

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
**Reaxecutar com:** `php artisan module:spec Admin`

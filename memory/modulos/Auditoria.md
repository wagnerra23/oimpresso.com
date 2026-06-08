# Módulo: Auditoria

> **Camada de governança transversal — UI rica /auditoria + undo sobre activity_log existente. Distingue User vs IA (causer_kind). Whitelist UNREVERTIBLE de 5 categorias. Per ADR 0127.**

- **Alias:** `auditoria`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Auditoria`
- **Status:** 🟢 ativo
- **Providers:** Modules\Auditoria\Providers\AuditoriaServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (10)
- 🔗 Acoplamento: depende de 4 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 7 |
| Controllers | 3 |
| Entities (Models) | 1 |
| Services | 3 |
| FormRequests | 6 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 1 |
| Arquivos de lang | 0 |
| Testes | 10 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `[AuditoriaController::class, 'index']` |
| `GET` | `/{activityId}` | `[AuditoriaController::class, 'show']` |
| `POST` | `/{activityId}/revert` | `[AuditoriaController::class, 'revert']` |
| `GET` | `/reports/activity-log` | `function (` |
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`AuditoriaController`** — 3 ação(ões): index, show, revert
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 

## Entities (Models Eloquent)

- **`AuditNote`** (tabela: `auditoria_audit_notes`)

## Migrations

- `2026_05_16_190000_create_auditoria_audit_notes_table.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `AuditoriaHealthCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Auditoria` |
| `revert_window_own_hours` | `24` |
| `revert_window_admin_days` | `30` |
| `revert_window_unlimited` | `` |
| `page_size` | `50` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Governance` | 1 |
| `Financeiro` | 1 |
| `NfeBrasil` | 1 |
| `Repair` | 1 |

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
**Reaxecutar com:** `php artisan module:spec Auditoria`

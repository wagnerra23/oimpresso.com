# Módulo: Brief

> **Daily Brief (camada L7) — gerador 6x/dia + tool MCP brief-fetch. Sprint 1 da Constituicao V2 (ADR 0091). Reduz onboarding de sessao Claude de 30k para 3k tokens.**

- **Alias:** `brief`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Brief`
- **Status:** 🟢 ativo
- **Providers:** Modules\Brief\Providers\BriefServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (8)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 4 |
| Controllers | 3 |
| Entities (Models) | 0 |
| Services | 3 |
| FormRequests | 9 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 0 |
| Arquivos de lang | 0 |
| Testes | 8 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `POST` | `/tools/brief-fetch` | `BriefFetchController::class` |

## Controllers

- **`BriefFetchController`** — 0 ação(ões): 
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `BriefHealthCommand`, `GenerateBriefCommand`

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
**Reaxecutar com:** `php artisan module:spec Brief`

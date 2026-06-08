# Módulo: ConsultaOs

> **Portal público de consulta de Ordem de Serviço — cliente acompanha pipeline de produção (orçado → aprovação → produção → acabamento → expedição → entregue) sem login.**

- **Alias:** `consultaos`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/ConsultaOs`
- **Status:** 🟢 ativo
- **Providers:** Modules\ConsultaOs\Providers\ConsultaOsServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (11)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 5 |
| Controllers | 3 |
| Entities (Models) | 0 |
| Services | 1 |
| FormRequests | 3 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 0 |
| Arquivos de lang | 0 |
| Testes | 11 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `[ConsultaOsController::class, 'index']` |
| `GET` | `/buscar` | `[ConsultaOsController::class, 'buscar']` |
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |

## Controllers

- **`ConsultaOsController`** — 2 ação(ões): index, buscar
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `ConsultaOsHealthCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ConsultaOs` |
| `mock_enabled` | `true` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Jana` | 1 |

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
**Reaxecutar com:** `php artisan module:spec ConsultaOs`

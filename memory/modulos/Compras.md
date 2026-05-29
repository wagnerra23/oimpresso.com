# Módulo: Compras

> **Compras como cockpit operacional: FSM 6 estágios (rascunho/pedido/trânsito/recebido/conferido/pago), import XML DF-e, entrada matricial tam×cor pra vestuário. Substitui Blade legacy purchase/*.**

- **Alias:** `compras`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Compras`
- **Status:** 🟢 ativo
- **Providers:** Modules\Compras\Providers\ComprasServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (5)
- 🔐 Registra 5 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 2 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 5 |
| Controllers | 3 |
| Entities (Models) | 0 |
| Services | 1 |
| FormRequests | 1 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 0 |
| Arquivos de lang | 0 |
| Testes | 5 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/` | `[ComprasController::class, 'index']` |
| `GET` | `/{id}/detalhe` | `[ComprasController::class, 'show']` |

## Controllers

- **`ComprasController`** — 2 ação(ões): index, show
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 3 ação(ões): index, uninstall, update

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `compras.view`
- `compras.create`
- `compras.edit`
- `compras.delete`
- `compras.import_xml`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Compras` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `NfeBrasil` | 2 |
| `Connector` | 1 |

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
**Reaxecutar com:** `php artisan module:spec Compras`

# Módulo: Fiscal

> **Cockpit fiscal unificado — agrega NF-e/NFC-e (Modules/NfeBrasil) + NFS-e (Modules/NFSe) + Manifesto DF-e + Eventos (CC-e/Cancelamento/Inutilização) + Certificado/Config + SPED em uma só visão. Não duplica backend — Controllers thin agregam Services existentes.**

- **Alias:** `fiscal`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Fiscal`
- **Status:** 🟢 ativo
- **Providers:** Modules\Fiscal\Providers\FiscalServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (18)
- ⚙️ Processamento assíncrono: 1 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 3 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 17 |
| Controllers | 11 |
| Entities (Models) | 0 |
| Services | 1 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 0 |
| Arquivos de lang | 1 |
| Testes | 18 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/` | `[CockpitController::class, 'index']` |
| `GET` | `/nfe` | `[NfeCockpitController::class, 'index']` |
| `GET` | `/nfse` | `[NfseCockpitController::class, 'index']` |
| `GET` | `/eventos` | `[EventosController::class, 'index']` |
| `GET` | `/dfe` | `[DfeController::class, 'index']` |
| `GET` | `/config` | `[ConfigController::class, 'index']` |
| `GET` | `/sped` | `[SpedController::class, 'index']` |
| `GET` | `/sped/icms-ipi/{ano}/{mes}` | `[SpedController::class, 'gerar']` |
| `POST` | `/acoes/nfe/{emissao}/cancelar` | `[AcoesController::class, 'cancelarNfe']` |
| `POST` | `/acoes/dfe/{recebido}/{acao}` | `[AcoesController::class, 'manifestarDfe']` |
| `POST` | `/acoes/nfe/{emissao}/cce` | `[AcoesController::class, 'cartaCorrecao']` |
| `POST` | `/acoes/nfe/inutilizar` | `[AcoesController::class, 'inutilizar']` |
| `POST` | `/acoes/nfe/{emissao}/retransmitir` | `[AcoesController::class, 'retransmitir']` |
| `GET` | `/palette/search` | `[PaletteSearchController::class, 'search']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`AcoesController`** — 5 ação(ões): cancelarNfe, manifestarDfe, cartaCorrecao, inutilizar, retransmitir
- **`CockpitController`** — 2 ação(ões): index, kpisCacheKey
- **`ConfigController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`DfeController`** — 1 ação(ões): index
- **`EventosController`** — 1 ação(ões): index
- **`InstallController`** — 0 ação(ões): 
- **`NfeCockpitController`** — 1 ação(ões): index
- **`NfseCockpitController`** — 1 ação(ões): index
- **`PaletteSearchController`** — 1 ação(ões): search
- **`SpedController`** — 2 ação(ões): index, gerar

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `HabilitarBusinessCommand`

**Listeners:** `InvalidaCockpitCacheListener`

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `NfeBrasil` | 2 |
| `Financeiro` | 1 |
| `ProductCatalogue` | 1 |

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
**Reaxecutar com:** `php artisan module:spec Fiscal`

# Módulo: NFSe

> **Emissão de Nota Fiscal de Serviços Eletrônica via Sistema Nacional NFSe (LC 214/2025). Integração direta com webservice federal — sem provider terceiro, custo zero por emissão.**

- **Alias:** `nfse`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/NFSe`
- **Status:** 🟢 ativo
- **Providers:** Modules\NFSe\Providers\NfseServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (9)
- ⚙️ Processamento assíncrono: 1 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 9 |
| Controllers | 3 |
| Entities (Models) | 0 |
| Services | 1 |
| FormRequests | 3 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 5 |
| Arquivos de lang | 0 |
| Testes | 9 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/` | `[NfseController::class, 'index']` |
| `GET` | `/emitir` | `[NfseController::class, 'create']` |
| `POST` | `/emitir` | `[NfseController::class, 'store']` |
| `GET` | `/{nfse}` | `[NfseController::class, 'show']` |
| `POST` | `/{nfse}/cancelar` | `[NfseController::class, 'cancelar']` |
| `GET` | `/{nfse}/pdf` | `[NfseController::class, 'pdf']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`NfseController`** — 6 ação(ões): index, create, store, show, cancelar, pdf

## Migrations

- `2026_05_01_000001_create_nfe_certificados_table.php`
- `2026_05_01_000002_create_nfse_provider_configs_table.php`
- `2026_05_01_000003_create_nfse_emissoes_table.php`
- `2026_05_01_000004_add_prestador_cnpj_to_nfse_provider_configs.php`
- `2026_05_03_000001_add_transaction_id_to_nfse_emissoes.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Jobs (queue):** `EmitirNfseJob`

**Commands (artisan):** `ImportarCertificadoCommand`, `NfseHealthCommand`

**Observers:** `TransactionNfseObserver`

## Peças adicionais

- **Seeders:** `NfseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `NFSe` |
| `module_version` | `0.1.0` |
| `ambiente` | `homologacao` |
| `cert_path` | `` |
| `cert_senha` | `` |
| `endpoints` | `[array(2 itens)]` |
| `municipio_ibge_default` | `4218707` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `NfeBrasil` | 1 |

## Integridade do banco

**Foreign Keys** (4):

- `business_id` → `business.id`
- `business_id` → `business.id`
- `cert_id` → `nfe_certificados.id`
- `business_id` → `business.id`

**Unique indexes:** 2

## Dependências Composer

- `nfse-nacional/nfse-php` ^1.19

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
**Reaxecutar com:** `php artisan module:spec NFSe`

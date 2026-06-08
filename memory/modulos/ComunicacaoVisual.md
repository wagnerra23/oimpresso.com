# Módulo: ComunicacaoVisual

> **Modules/ComunicacaoVisual — vertical gráfica rápida e comunicação visual BR (CNAE 1813-0/01). Estado em construção (planejado piloto 2026-Q3 entre 6 saudáveis OfficeImpresso). Concorrentes: Mubisys, Zênite, Calcgraf. Diferencial: cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional. Ver memory/requisitos/ComunicacaoVisual/SPEC.md.**

- **Alias:** `comunicacao-visual`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/ComunicacaoVisual`
- **Status:** 🟢 ativo
- **Providers:** Modules\ComunicacaoVisual\Providers\ComunicacaoVisualServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (20)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 12 |
| Controllers | 4 |
| Entities (Models) | 10 |
| Services | 2 |
| FormRequests | 6 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 9 |
| Arquivos de lang | 1 |
| Testes | 20 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `function (` |
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `POST` | `calcular` | `[OrcamentoController::class, 'calcular']` |
| `POST` | `orcamentos` | `[OrcamentoController::class, 'store']` |
| `GET` | `orcamentos/{id}` | `[OrcamentoController::class, 'show']` |
| `GET` | `apontamentos/em-andamento` | `[ApontamentoController::class, 'emAndamento']` |
| `GET` | `apontamentos` | `[ApontamentoController::class, 'index']` |
| `POST` | `apontamentos/iniciar` | `[ApontamentoController::class, 'iniciar']` |
| `POST` | `apontamentos/{apontamento}/finalizar` | `[ApontamentoController::class, 'finalizar']` |
| `POST` | `apontamentos/{apontamento}/cancelar` | `[ApontamentoController::class, 'cancelar']` |

## Controllers

- **`ApontamentoController`** — 5 ação(ões): index, iniciar, finalizar, cancelar, emAndamento
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`OrcamentoController`** — 3 ação(ões): calcular, store, show

## Entities (Models Eloquent)

- **`Acabamento`** (tabela: `cv_acabamentos`)
- **`Apontamento`** (tabela: `comvis_apontamentos`)
- **`Instalacao`** (tabela: `cv_instalacoes`)
- **`InstalacaoCatalogo`** (tabela: `cv_instalacoes_catalogo`)
- **`Material`** (tabela: `comvis_materiais`)
- **`Orcamento`** (tabela: `comvis_orcamentos`)
- **`OrcamentoItem`** (tabela: `comvis_orcamento_itens`)
- **`OrdemProducao`** (tabela: `cv_ordens_producao`)
- **`Os`** (tabela: `comvis_os`)
- **`Substrato`** (tabela: `cv_substratos`)

## Migrations

- `2026_05_10_000040_create_comvis_materiais_table.php`
- `2026_05_10_000041_create_comvis_orcamentos_table.php`
- `2026_05_10_000042_create_comvis_os_table.php`
- `2026_05_10_000043_create_comvis_apontamentos_table.php`
- `2026_05_12_000010_create_cv_substratos_table.php`
- `2026_05_12_000011_create_cv_acabamentos_table.php`
- `2026_05_12_000012_create_cv_instalacoes_catalogo_table.php`
- `2026_05_12_000013_create_cv_ordens_producao_table.php`
- `2026_05_12_000014_create_cv_instalacoes_table.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `ComvisHealthCommand`, `DemoSeedCommand`

## Peças adicionais

- **Seeders:** `MaterialSeeder`, `RepairSettingsSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ComunicacaoVisual` |
| `cnae` | `1813-0/01` |
| `unidade_medida_default` | `m2` |

## Integridade do banco

**Unique indexes:** 3

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
**Reaxecutar com:** `php artisan module:spec ComunicacaoVisual`

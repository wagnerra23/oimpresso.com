# Módulo: PontoWr2

> **Módulo de Ponto Eletrônico conforme Portaria MTP 671/2021 — WR2 Sistemas. Estende UltimatePOS 6 + Essentials & HRM.**

- **Alias:** `pontowr2`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/PontoWr2`
- **Status:** 🟢 ativo
- **Providers:** Modules\PontoWr2\Providers\PontoWr2ServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 40 rotas — escopo médio
- ✅ Tem testes (2)
- 🔐 Registra 5 permissão(ões) Spatie
- ⚙️ Processamento assíncrono: 2 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 2 outro(s) módulo(s)
- 🗃️ 26 foreign keys — alto acoplamento em dados
- 🗄️ Tem triggers MySQL (2) — append-only / imutabilidade

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 40 |
| Controllers | 12 |
| Entities (Models) | 10 |
| Services | 8 |
| FormRequests | 2 |
| Middleware | 1 |
| Views Blade | 26 |
| Migrations | 8 |
| Arquivos de lang | 2 |
| Testes | 2 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `'DashboardController@index'` |
| `GET` | `/react` | `function (` |
| `GET` | `/espelho` | `'EspelhoController@index'` |
| `GET` | `/espelho/{colaborador}` | `'EspelhoController@show'` |
| `GET` | `/espelho/{colaborador}/imprimir` | `'EspelhoController@imprimir'` |
| `GET` | `/aprovacoes` | `'AprovacaoController@index'` |
| `POST` | `/aprovacoes/{id}/aprovar` | `'AprovacaoController@aprovar'` |
| `POST` | `/aprovacoes/{id}/rejeitar` | `'AprovacaoController@rejeitar'` |
| `POST` | `/aprovacoes/lote` | `'AprovacaoController@aprovarEmLote'` |
| `POST` | `/intercorrencias/{id}/submeter` | `'IntercorrenciaController@submeter'` |
| `POST` | `/intercorrencias/{id}/cancelar` | `'IntercorrenciaController@cancelar'` |
| `GET` | `/banco-horas` | `'BancoHorasController@index'` |
| `GET` | `/banco-horas/{colaborador}` | `'BancoHorasController@show'` |
| `POST` | `/banco-horas/{colaborador}/ajuste` | `'BancoHorasController@ajustarManual'` |
| `GET` | `/importacoes` | `'ImportacaoController@index'` |
| `GET` | `/importacoes/novo` | `'ImportacaoController@create'` |
| `POST` | `/importacoes` | `'ImportacaoController@store'` |
| `GET` | `/importacoes/{id}` | `'ImportacaoController@show'` |
| `GET` | `/importacoes/{id}/original` | `'ImportacaoController@baixarOriginal'` |
| `GET` | `/relatorios` | `'RelatorioController@index'` |
| `GET` | `/relatorios/{chave}` | `'RelatorioController@gerar'` |
| `GET` | `/colaboradores` | `'ColaboradorController@index'` |
| `GET` | `/colaboradores/{id}/editar` | `'ColaboradorController@edit'` |
| `PUT` | `/colaboradores/{id}` | `'ColaboradorController@update'` |
| `GET` | `/configuracoes` | `'ConfiguracaoController@index'` |
| `GET` | `/configuracoes/reps` | `'ConfiguracaoController@reps'` |
| `POST` | `/configuracoes/reps` | `'ConfiguracaoController@storeRep'` |
| `POST` | `/marcar` | `function (` |
| `GET` | `/marcacoes/hoje` | `function (` |
| `GET` | `/saldo` | `function (` |
| `GET` | `/intercorrencias` | `function (` |
| `POST` | `/intercorrencias` | `function (` |
| `GET` | `/escala/hoje` | `function (` |
| `GET` | `/dashboard/kpis` | `function (` |
| `GET` | `/` | `'InstallController@index'` |
| `POST` | `/` | `'InstallController@install'` |
| `GET` | `/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/update` | `'InstallController@update'` |
| `RESOURCE` | `/intercorrencias` | `'IntercorrenciaController'` |
| `RESOURCE` | `/escalas` | `'EscalaController'` |

## Controllers

- **`AprovacaoController`** — 4 ação(ões): index, aprovar, rejeitar, aprovarEmLote
- **`BancoHorasController`** — 3 ação(ões): index, show, ajustarManual
- **`ColaboradorController`** — 3 ação(ões): index, edit, update
- **`ConfiguracaoController`** — 3 ação(ões): index, reps, storeRep
- **`DashboardController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`EscalaController`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`EspelhoController`** — 3 ação(ões): index, show, imprimir
- **`ImportacaoController`** — 5 ação(ões): index, create, store, show, baixarOriginal
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`IntercorrenciaController`** — 8 ação(ões): index, create, store, show, edit, update, submeter, cancelar
- **`RelatorioController`** — 2 ação(ões): index, gerar

## Entities (Models Eloquent)

- **`ApuracaoDia`** (tabela: `ponto_apuracao_dia`)
- **`BancoHorasMovimento`** (tabela: `ponto_banco_horas_movimentos`)
- **`BancoHorasSaldo`** (tabela: `ponto_banco_horas_saldo`)
- **`Colaborador`** (tabela: `ponto_colaborador_config`)
- **`Escala`** (tabela: `ponto_escalas`)
- **`EscalaTurno`** (tabela: `ponto_escala_turnos`)
- **`Importacao`** (tabela: `ponto_importacoes`)
- **`Intercorrencia`** (tabela: `ponto_intercorrencias`)
- **`Marcacao`** (tabela: `ponto_marcacoes`)
- **`Rep`** (tabela: `ponto_reps`)

## Migrations

- `2026_04_18_000001_create_ponto_colaborador_config_table.php`
- `2026_04_18_000002_create_ponto_reps_table.php`
- `2026_04_18_000003_create_ponto_escalas_table.php`
- `2026_04_18_000004_create_ponto_marcacoes_table.php`
- `2026_04_18_000005_create_ponto_intercorrencias_table.php`
- `2026_04_18_000006_create_ponto_apuracao_dia_table.php`
- `2026_04_18_000007_create_ponto_banco_horas_table.php`
- `2026_04_18_000008_create_ponto_importacoes_table.php`

## Views (Blade)

**Total:** 26 arquivos

**Pastas principais:**

- `intercorrencias/` — 5 arquivo(s)
- `escalas/` — 4 arquivo(s)
- `importacoes/` — 3 arquivo(s)
- `aprovacoes/` — 2 arquivo(s)
- `banco-horas/` — 2 arquivo(s)
- `colaboradores/` — 2 arquivo(s)
- `configuracoes/` — 2 arquivo(s)
- `espelho/` — 2 arquivo(s)
- `dashboard/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)
- `relatorios/` — 1 arquivo(s)
- `reports/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `ponto.access`
- `ponto.colaboradores.manage`
- `ponto.aprovacoes.manage`
- `ponto.relatorios.view`
- `ponto.configuracoes.manage`

## Processamento / eventos

**Jobs (queue):** `ProcessarImportacaoAfdJob`, `ReapurarDiaJob`

**Commands (artisan):** `AfdInspecionarCommand`, `ImportAfdCommand`

## Peças adicionais

- **Factories:** 5 (`ColaboradorFactory`, `EscalaFactory`, `EscalaTurnoFactory`, `IntercorrenciaFactory`, `MarcacaoFactory`)
- **Seeders:** `DevPontoSeeder`, `PontoWr2DatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `PontoWr2` |
| `module_label` | `Ponto WR2` |
| `module_description` | `Ponto Eletrônico · Portaria 671/2021` |
| `module_icon` | `fa fa-clock-o` |
| `module_version` | `0.1` |
| `pid` | `` |
| `clt` | `[array(9 itens)]` |
| `banco_horas` | `[array(7 itens)]` |
| `rep` | `[array(5 itens)]` |
| `afd` | `[array(4 itens)]` |
| `marcacao` | `[array(3 itens)]` |
| `esocial` | `[array(5 itens)]` |
| `ultimatepos` | `[array(4 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Project` | 1 |
| `Repair` | 1 |

## Integridade do banco

**Foreign Keys** (26):

- `user_id` → `users.id`
- `business_id` → `business.id`
- `business_id` → `business.id`
- `business_id` → `business.id`
- `escala_id` → `ponto_escalas.id`
- `escala_atual_id` → `ponto_escalas.id`
- `business_id` → `business.id`
- `colaborador_config_id` → `ponto_colaborador_config.id`
- `rep_id` → `ponto_reps.id`
- `usuario_criador_id` → `users.id`
- `business_id` → `business.id`
- `colaborador_config_id` → `ponto_colaborador_config.id`
- `solicitante_id` → `users.id`
- `aprovador_id` → `users.id`
- `business_id` → `business.id`
- `colaborador_config_id` → `ponto_colaborador_config.id`
- `escala_id` → `ponto_escalas.id`
- `business_id` → `business.id`
- `colaborador_config_id` → `ponto_colaborador_config.id`
- `business_id` → `business.id`
- _... +6 FKs_

**Triggers MySQL** (2): `trg_ponto_marcacoes_no_update`, `trg_ponto_marcacoes_no_delete`

**Unique indexes:** 7

## Dependências Composer

- `php` ^8.1
- `spatie/laravel-permission` ^6.0
- `spatie/laravel-activitylog` ^4.0
- `maatwebsite/excel` ^3.1
- `barryvdh/laravel-dompdf` ^2.0

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ❌ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 92
- **Linhas +:** 8854 **-:** 0
- **Primeiros arquivos alterados:**
  - `.gitignore`
  - `Config/config.php`
  - `Console/Commands/AfdInspecionarCommand.php`
  - `Console/Commands/ImportAfdCommand.php`
  - `Database/Migrations/2026_04_18_000001_create_ponto_colaborador_config_table.php`
  - `Database/Migrations/2026_04_18_000002_create_ponto_reps_table.php`
  - `Database/Migrations/2026_04_18_000003_create_ponto_escalas_table.php`
  - `Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php`
  - `Database/Migrations/2026_04_18_000005_create_ponto_intercorrencias_table.php`
  - `Database/Migrations/2026_04_18_000006_create_ponto_apuracao_dia_table.php`
  - `Database/Migrations/2026_04_18_000007_create_ponto_banco_horas_table.php`
  - `Database/Migrations/2026_04_18_000008_create_ponto_importacoes_table.php`
  - `Database/Migrations/test_write.tmp`
  - `Database/Seeders/DevPontoSeeder.php`
  - `Database/Seeders/PontoWr2DatabaseSeeder.php`
  - `Database/factories/ColaboradorFactory.php`
  - `Database/factories/EscalaFactory.php`
  - `Database/factories/EscalaTurnoFactory.php`
  - `Database/factories/IntercorrenciaFactory.php`
  - `Database/factories/MarcacaoFactory.php`
  - `Entities/ApuracaoDia.php`
  - `Entities/BancoHorasMovimento.php`
  - `Entities/BancoHorasSaldo.php`
  - `Entities/Colaborador.php`
  - `Entities/Escala.php`
  - `Entities/EscalaTurno.php`
  - `Entities/Importacao.php`
  - `Entities/Intercorrencia.php`
  - `Entities/Marcacao.php`
  - `Entities/Rep.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 92
- **Linhas +:** 8854 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `.gitignore`
  - `Config/config.php`
  - `Console/Commands/AfdInspecionarCommand.php`
  - `Console/Commands/ImportAfdCommand.php`
  - `Database/Migrations/2026_04_18_000001_create_ponto_colaborador_config_table.php`
  - `Database/Migrations/2026_04_18_000002_create_ponto_reps_table.php`
  - `Database/Migrations/2026_04_18_000003_create_ponto_escalas_table.php`
  - `Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php`
  - `Database/Migrations/2026_04_18_000005_create_ponto_intercorrencias_table.php`
  - `Database/Migrations/2026_04_18_000006_create_ponto_apuracao_dia_table.php`
  - `Database/Migrations/2026_04_18_000007_create_ponto_banco_horas_table.php`
  - `Database/Migrations/2026_04_18_000008_create_ponto_importacoes_table.php`
  - `Database/Migrations/test_write.tmp`
  - `Database/Seeders/DevPontoSeeder.php`
  - `Database/Seeders/PontoWr2DatabaseSeeder.php`
  - `Database/factories/ColaboradorFactory.php`
  - `Database/factories/EscalaFactory.php`
  - `Database/factories/EscalaTurnoFactory.php`
  - `Database/factories/IntercorrenciaFactory.php`
  - `Database/factories/MarcacaoFactory.php`
  - `Entities/ApuracaoDia.php`
  - `Entities/BancoHorasMovimento.php`
  - `Entities/BancoHorasSaldo.php`
  - `Entities/Colaborador.php`
  - `Entities/Escala.php`
  - `Entities/EscalaTurno.php`
  - `Entities/Importacao.php`
  - `Entities/Intercorrencia.php`
  - `Entities/Marcacao.php`
  - `Entities/Rep.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec PontoWr2`

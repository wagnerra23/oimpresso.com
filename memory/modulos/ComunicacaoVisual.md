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
- ℹ️ O gerador não olha `resources/js/Pages/`: as telas Inertia do módulo (Index, Agenda, AceitePublico) ficam lá
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (36)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 26 |
| Controllers | 12 |
| Entities (Models) | 11 |
| Services | 9 |
| FormRequests | 5 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 16 |
| Arquivos de lang | 1 |
| Testes | 36 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `function (\Illuminate\Http\Request $request` |
| `GET` | `agenda` | `[AgendaController::class, 'index']` |
| `POST` | `install` | `[InstallController::class, 'install']` |
| `GET` | `install` | `[InstallController::class, 'index']` |
| `POST` | `calcular` | `[OrcamentoController::class, 'calcular']` |
| `GET` | `orcamentos` | `[OrcamentoController::class, 'index']` |
| `POST` | `orcamentos` | `[OrcamentoController::class, 'store']` |
| `GET` | `orcamentos/{id}` | `[OrcamentoController::class, 'show']` |
| `PUT` | `orcamentos/{id}` | `[OrcamentoController::class, 'update']` |
| `POST` | `orcamentos/{id}/aprovar` | `[OrcamentoController::class, 'aprovar']` |
| `POST` | `orcamentos/{id}/link` | `[OrcamentoAceiteController::class, 'gerar']` |
| `GET` | `roteiro-padrao` | `[RoteiroPadraoController::class, 'show']` |
| `PUT` | `roteiro-padrao` | `[RoteiroPadraoController::class, 'update']` |
| `POST` | `orcamentos/{id}/arquivos` | `[OrcamentoArquivoController::class, 'store']` |
| `DELETE` | `orcamentos/{id}/arquivos/{media}` | `[OrcamentoArquivoController::class, 'destroy']` |
| `GET` | `apontamentos/em-andamento` | `[ApontamentoController::class, 'emAndamento']` |
| `GET` | `apontamentos` | `[ApontamentoController::class, 'index']` |
| `POST` | `apontamentos/iniciar` | `[ApontamentoController::class, 'iniciar']` |
| `POST` | `apontamentos/{apontamento}/finalizar` | `[ApontamentoController::class, 'finalizar']` |
| `POST` | `apontamentos/{apontamento}/cancelar` | `[ApontamentoController::class, 'cancelar']` |
| `GET` | `instalacoes` | `[InstalacaoController::class, 'index']` |
| `POST` | `os/{os}/instalacao` | `[InstalacaoController::class, 'agendar']` |
| `GET` | `os/{os}/pos-calculo` | `[OsPosCalculoController::class, 'show']` |
| `GET` | `{token}` | `[OrcamentoAceitePublicoController::class, 'mostrar']` |
| `POST` | `{token}/aceitar` | `[OrcamentoAceitePublicoController::class, 'aceitar']` |
| `POST` | `{token}/ajuste` | `[OrcamentoAceitePublicoController::class, 'ajuste']` |

## Controllers

- **`AgendaController`** — 1 ação(ões): index
- **`ApontamentoController`** — 5 ação(ões): index, iniciar, finalizar, cancelar, emAndamento
- **`AppOrcamentoRapidoController`** — 3 ação(ões): materiais, calcular, store
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstalacaoController`** — 2 ação(ões): index, agendar
- **`InstallController`** — 0 ação(ões): 
- **`OrcamentoAceiteController`** — 1 ação(ões): gerar
- **`OrcamentoArquivoController`** — 2 ação(ões): store, destroy
- **`OrcamentoController`** — 6 ação(ões): calcular, store, index, update, show, aprovar
- **`OsPosCalculoController`** — 1 ação(ões): show
- **`OrcamentoAceitePublicoController`** — 3 ação(ões): mostrar, aceitar, ajuste
- **`RoteiroPadraoController`** — 2 ação(ões): show, update

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
- **`OsItem`** (tabela: `comvis_os_itens`)
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
- `2026_10_19_210000_add_custo_previsto_to_comvis_orcamento_itens.php`
- `2026_10_20_100000_add_custo_maquina_previsto_to_comvis_orcamento_itens.php`
- `2026_10_20_200000_create_comvis_os_itens_table.php`
- `2026_10_20_300000_add_agenda_to_cv_instalacoes.php`
- `2026_10_26_100000_add_minimo_e_acabamentos_ao_orcamento_cv.php`
- `2026_10_26_110000_add_perda_e_emenda_to_comvis_materiais.php`
- `2026_10_26_200000_create_comvis_orcamento_aceites_table.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `ComvisHealthCommand`, `DemoSeedCommand`

**Events:** `InstalacaoAgendada`

## Peças adicionais

- **Seeders:** `MaterialSeeder`, `RepairSettingsSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ComunicacaoVisual` |
| `cnae` | `1813-0/01` |
| `unidade_medida_default` | `m2` |

## Integridade do banco

**Unique indexes:** 5

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
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-05-29 08:06.** Em 2026-11-10 as seções Escopo, Rotas, Controllers, Entities, Migrations, Processamento, Seeders, testes e unique indexes foram recontadas com as mesmas regras do gerador (porta em script), porque o CT 100 não respondeu para rodar o comando. As outras seções seguem de 2026-05-29.
**Reaxecutar com:** `php artisan module:spec ComunicacaoVisual`

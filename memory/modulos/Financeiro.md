# Módulo: Financeiro

> **Contas a pagar, a receber, fluxo de caixa e DRE — tudo na mesma tela. Foundation BR pra virar ERP completo.**

- **Alias:** `financeiro`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Financeiro`
- **Status:** 🟢 ativo
- **Providers:** Modules\Financeiro\Providers\FinanceiroServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔴 +50 rotas — módulo grande, migrar em fases
- ✅ Tem testes (57)
- ⚙️ Processamento assíncrono: 7 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 2 outro(s) módulo(s)
- 🗃️ 31 foreign keys — alto acoplamento em dados

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 67 |
| Controllers | 23 |
| Entities (Models) | 0 |
| Services | 13 |
| FormRequests | 10 |
| Middleware | 1 |
| Views Blade | 3 |
| Migrations | 22 |
| Arquivos de lang | 1 |
| Testes | 57 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `assinaturas/atualizar` | `[AssinaturaController::class, 'showAtualizar']` |
| `PATCH` | `assinaturas/{assinatura}` | `[AssinaturaController::class, 'atualizar']` |
| `GET` | `/` | `[DashboardController::class, 'index']` |
| `GET` | `/unificado` | `[UnificadoController::class, 'index']` |
| `GET` | `/unificado/novo` | `[UnificadoController::class, 'novo']` |
| `POST` | `/unificado/{id}/baixar` | `[UnificadoController::class, 'baixar']` |
| `POST` | `/unificado/bulk-update-categoria` | `[UnificadoController::class, 'bulkUpdateCategoria']` |
| `PUT` | `/unificado/{id}` | `[UnificadoController::class, 'update']` |
| `POST` | `/unificado` | `[UnificadoController::class, 'store']` |
| `POST` | `/unificado/{id}/conferir` | `[UnificadoController::class, 'conferir']` |
| `DELETE` | `/unificado/{id}/conferir` | `[UnificadoController::class, 'unconferir']` |
| `GET` | `/unificado/saldo-sparkline` | `[UnificadoController::class, 'saldoSparkline']` |
| `GET` | `/unificado/sugerir-valor` | `[UnificadoController::class, 'sugerirValor']` |
| `GET` | `/unificado/buscar-cliente` | `[UnificadoController::class, 'buscarCliente']` |
| `GET` | `/cowork-sidebar-data` | `[\Modules\Financeiro\Http\Controllers\CoworkSidebarController::class, 'data']` |
| `GET` | `/unificado/{tituloId}/comments` | `[UnificadoController::class, 'comments']` |
| `POST` | `/unificado/{tituloId}/comments` | `[UnificadoController::class, 'addComment']` |
| `GET` | `/unificado/{tituloId}/audit` | `[UnificadoController::class, 'auditTrail']` |
| `GET` | `/fluxo` | `[FluxoController::class, 'index']` |
| `GET` | `/dre` | `[DreController::class, 'index']` |
| `GET` | `/dre/export-pdf` | `[DreController::class, 'exportPdf']` |
| `GET` | `/dre/export-xlsx` | `[DreController::class, 'exportXlsx']` |
| `GET` | `/dre/export-csv` | `[DreController::class, 'exportCsv']` |
| `GET` | `/contas-receber` | `[ContaReceberController::class, 'index']` |
| `POST` | `/contas-receber/{tituloId}/boleto` | `[ContaReceberController::class, 'emitirBoleto']` |
| `POST` | `/boletos/{remessaId}/cancelar` | `[BoletoController::class, 'cancelar']` |
| `GET` | `/cobranca` | `[CobrancaController::class, 'index']` |
| `POST` | `/cobranca/emitir` | `[CobrancaController::class, 'store']` |
| `POST` | `/cobranca/cartao` | `[CobrancaController::class, 'storeCartao']` |
| `GET` | `/contas-pagar` | `[ContaPagarController::class, 'index']` |
| `POST` | `/contas-pagar/{tituloId}/pagar` | `[ContaPagarController::class, 'pagar']` |
| `GET` | `/contas-bancarias` | `[ContaBancariaController::class, 'index']` |
| `POST` | `/contas-bancarias/{accountId}` | `[ContaBancariaController::class, 'upsert']` |
| `GET` | `/extrato/{contaBancariaId}` | `[ExtratoController::class, 'index']` |
| `GET` | `/relatorios` | `[RelatoriosController::class, 'index']` |
| `GET` | `/relatorios/export-csv` | `[RelatoriosController::class, 'exportCsv']` |
| `GET` | `/caixa` | `[CaixaController::class, 'index']` |

_... +26 rotas_

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `financeiro` | `fn (Request $request` |

## Controllers

- **`AdvisorAccessController`** — 3 ação(ões): index, grant, revoke
- **`AdvisorAuthController`** — 3 ação(ões): showLogin, login, logout
- **`AdvisorPortalController`** — 1 ação(ões): index
- **`AssinaturaController`** — 2 ação(ões): showAtualizar, atualizar
- **`BoletoController`** — 2 ação(ões): index, cancelar
- **`CaixaController`** — 2 ação(ões): index, lancar
- **`CategoriaController`** — 5 ação(ões): index, store, update, destroy, toggleAtivo
- **`CobrancaController`** — 3 ação(ões): index, store, storeCartao
- **`ConciliacaoController`** — 4 ação(ões): index, upload, match, ignorar
- **`ContaBancariaController`** — 2 ação(ões): index, upsert
- **`ContaPagarController`** — 2 ação(ões): index, pagar
- **`ContaReceberController`** — 2 ação(ões): index, emitirBoleto
- **`CoworkSidebarController`** — 1 ação(ões): data
- **`DashboardController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`DreController`** — 4 ação(ões): index, exportPdf, exportXlsx, exportCsv
- **`ExtratoController`** — 1 ação(ões): index
- **`FinanceiroController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`FluxoController`** — 1 ação(ões): index
- **`InstallController`** — 0 ação(ões): 
- **`PlanoContaController`** — 1 ação(ões): index
- **`RelatoriosController`** — 2 ação(ões): index, exportCsv
- **`UnificadoController`** — 22 ação(ões): index, novo, store, update, bulkUpdateCategoria, conferir, unconferir, baixar +14

## Migrations

- `2026_04_24_140001_create_fin_planos_conta_table.php`
- `2026_04_24_140002_create_fin_categorias_table.php`
- `2026_04_24_140003_create_fin_contas_bancarias_table.php`
- `2026_04_24_140004_create_fin_titulos_table.php`
- `2026_04_24_140005_create_fin_titulo_baixas_table.php`
- `2026_04_24_140006_create_fin_caixa_movimentos_table.php`
- `2026_04_25_140101_create_fin_boleto_remessas_table.php`
- `2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias.php`
- `2026_05_06_000002_add_saldo_cached_to_fin_contas_bancarias.php`
- `2026_05_07_220000_create_fin_extrato_lancamentos_table.php`
- `2026_05_09_210000_create_accounts_legacy_map_table.php`
- `2026_05_09_210001_add_legacy_columns_to_fin_contas_bancarias.php`
- `2026_05_18_180000_add_conferido_to_fin_titulos.php`
- `2026_05_18_190000_create_fin_titulo_comments_table.php`
- `2026_05_19_220000_create_fin_bank_statement_lines_table.php`
- `2026_05_19_220001_create_fin_titulo_anexos_table.php`
- `2026_05_19_220002_add_aprovacao_to_fin_titulos.php`
- `2026_05_20_140000_create_advisors_table.php`
- `2026_05_20_140001_create_advisor_business_access_table.php`
- `2026_05_20_180000_create_ai_usage_log_table.php`
- `2026_05_20_200000_make_titulo_baixa_conta_bancaria_optional.php`
- `2026_05_21_220000_add_caixa_bridge_to_fin_titulos_and_contas.php`

## Views (Blade)

**Total:** 3 arquivos

**Pastas principais:**

- `D:/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)
- `pdf/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Jobs (queue):** `CriarTituloDeVendaJob`

**Commands (artisan):** `BackfillPlanoContaCommand`, `BridgeExpenseToTitulosCommand`, `FinanceiroHealthCommand`, `InstallCommand`

**Events:** `CashRegisterClosed`, `TituloCriado`

**Listeners:** `OnCashRegisterClosedCreateFinanceiroTitulo`, `OnCobrancaPagaCreateFinanceiroTitulo`, `OnTituloCriadoLog`, `ProcessAsaasPixWebhookListener`

**Observers:** `CashRegisterObserver`, `TransactionObserver`, `TransactionPaymentObserver`

## Peças adicionais

- **Seeders:** `FinanceiroDatabaseSeeder`, `FinanceiroDemoSeeder`, `PlanoContasBrSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Financeiro` |
| `module_version` | `0.1.0` |
| `juros_mora_diario` | `0.0033` |
| `multa_atraso` | `0.02` |
| `asaas` | `[array(4 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `PaymentGateway` | 2 |
| `Superadmin` | 1 |

## Integridade do banco

**Foreign Keys** (31):

- `business_id` → `business.id`
- `parent_id` → `fin_planos_conta.id`
- `business_id` → `business.id`
- `plano_conta_id` → `fin_planos_conta.id`
- `business_id` → `business.id`
- `account_id` → `accounts.id`
- `business_id` → `business.id`
- `plano_conta_id` → `fin_planos_conta.id`
- `categoria_id` → `fin_categorias.id`
- `titulo_pai_id` → `fin_titulos.id`
- `created_by` → `users.id`
- `business_id` → `business.id`
- `titulo_id` → `fin_titulos.id`
- `conta_bancaria_id` → `fin_contas_bancarias.id`
- `estorno_de_id` → `fin_titulo_baixas.id`
- `created_by` → `users.id`
- `business_id` → `business.id`
- `conta_bancaria_id` → `fin_contas_bancarias.id`
- `created_by` → `users.id`
- `titulo_id` → `fin_titulos.id`
- _... +11 FKs_

**Unique indexes:** 12

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 1 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 1 |
| Imagens | 0 |

- Build: **Vite** (vite.config.js/ts presente)
- `package.json` presente
- **Deps JS:** `axios`, `laravel-vite-plugin`, `sass`, `postcss`, `vite`

**Arquivos JS** (primeiros 1):

- `js\app.js` (0 B)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\app.scss` (0 B)

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
**Reaxecutar com:** `php artisan module:spec Financeiro`

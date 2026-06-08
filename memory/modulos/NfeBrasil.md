# Módulo: NfeBrasil

> **Vender com nota fiscal sem virar contador. NFC-e em 1 clique no caixa, NF-e B2B sem fricção, SPED pronto no fim do mês.**

- **Alias:** `nfebrasil`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/NfeBrasil`
- **Status:** 🟢 ativo
- **Providers:** Modules\NfeBrasil\Providers\NfeBrasilServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 39 rotas — escopo médio
- ✅ Tem testes (45)
- 🔐 Registra 9 permissão(ões) Spatie
- ⚙️ Processamento assíncrono: 15 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 3 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 39 |
| Controllers | 11 |
| Entities (Models) | 0 |
| Services | 15 |
| FormRequests | 6 |
| Middleware | 0 |
| Views Blade | 4 |
| Migrations | 16 |
| Arquivos de lang | 1 |
| Testes | 45 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `certificado` | `fn (` |
| `POST` | `certificado` | `[CertificadoController::class, 'upload']` |
| `POST` | `certificado/testar` | `[CertificadoController::class, 'testar']` |
| `POST` | `certificado/ambiente` | `[CertificadoController::class, 'updateAmbiente']` |
| `GET` | `/` | `[TributacaoController::class, 'index']` |
| `POST` | `auto-emission/toggle` | `[TributacaoController::class, 'toggleAutoEmission']` |
| `GET` | `config-default` | `[ConfigDefaultController::class, 'show']` |
| `POST` | `config-default` | `[ConfigDefaultController::class, 'upsert']` |
| `POST` | `templates/{slug}/aplicar` | `[TributacaoController::class, 'aplicarTemplate']` |
| `GET` | `regras/create` | `[TributacaoController::class, 'create']` |
| `POST` | `regras` | `[TributacaoController::class, 'store']` |
| `GET` | `regras/{id}/edit` | `[TributacaoController::class, 'edit']` |
| `PUT` | `regras/{id}` | `[TributacaoController::class, 'update']` |
| `DELETE` | `regras/{id}` | `[TributacaoController::class, 'destroy']` |
| `GET` | `import` | `[ImportRegrasController::class, 'show']` |
| `POST` | `import/preview` | `[ImportRegrasController::class, 'preview']` |
| `POST` | `import/aplicar` | `[ImportRegrasController::class, 'aplicar']` |
| `GET` | `transactions/{tx}/nfe-status` | `[NfeStatusController::class, 'show']` |
| `GET` | `transactions/{tx}/emissoes` | `[\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'listar']` |
| `POST` | `transactions/{tx}/emitir` | `[\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'emitir']` |
| `POST` | `emissoes/{id}/reenviar-email` | `[\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'reenviarEmail']` |
| `GET` | `emissoes/{id}/danfe-pdf` | `[\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class, 'danfePdf']` |
| `GET` | `{tx}/status` | `[NfeStatusController::class, 'showPage']` |
| `GET` | `/` | `[NfeInutilizacaoController::class, 'index']` |
| `POST` | `/` | `[NfeInutilizacaoController::class, 'store']` |
| `GET` | `/` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'index']` |
| `POST` | `{id}/cienciar` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'cienciar']` |
| `POST` | `{id}/confirmar` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'confirmar']` |
| `POST` | `{id}/desconhecer` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'desconhecer']` |
| `POST` | `{id}/nao-realizada` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'naoRealizada']` |
| `POST` | `bulk/confirmar` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'bulkConfirmar']` |
| `POST` | `sync-now` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'syncNow']` |
| `GET` | `{id}/itens` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'listarItens']` |
| `GET` | `{id}/eventos` | `[\Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class, 'listarEventos']` |
| `RESOURCE` | `nfebrasil` | `NfeBrasilController::class` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `nfebrasil` | `fn (Request $request` |

## Controllers

- **`CertificadoController`** — 4 ação(ões): status, upload, testar, updateAmbiente
- **`ConfigDefaultController`** — 2 ação(ões): show, upsert
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`ImportRegrasController`** — 3 ação(ões): show, preview, aplicar
- **`InstallController`** — 0 ação(ões): 
- **`ManifestacaoController`** — 9 ação(ões): index, cienciar, confirmar, desconhecer, naoRealizada, bulkConfirmar, syncNow, listarItens +1
- **`NfeBrasilController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`NfeEmissaoController`** — 4 ação(ões): emitir, reenviarEmail, danfePdf, listar
- **`NfeInutilizacaoController`** — 2 ação(ões): index, store
- **`NfeStatusController`** — 2 ação(ões): show, showPage
- **`TributacaoController`** — 8 ação(ões): index, toggleAutoEmission, aplicarTemplate, create, store, edit, update, destroy

## Migrations

- `2026_05_06_002000_create_nfe_certificados_table.php`
- `2026_05_06_002001_create_nfe_emissoes_table.php`
- `2026_05_06_002002_create_nfe_eventos_table.php`
- `2026_05_06_002003_create_nfe_inutilizacoes_table.php`
- `2026_05_06_010000_create_nfe_fiscal_rules_table.php`
- `2026_05_06_010001_create_nfe_business_configs_table.php`
- `2026_05_06_020000_create_nfe_fiscal_rule_tax_rate_links_table.php`
- `2026_05_08_000000_add_auto_emission_enabled_to_nfe_business_configs.php`
- `2026_05_09_100000_create_nfe_dfe_recebidos_table.php`
- `2026_05_09_100001_create_nfe_dfe_itens_table.php`
- `2026_05_09_100002_create_nfe_dfe_eventos_table.php`
- `2026_05_09_100003_create_nfe_dfe_nsu_state_table.php`
- `2026_05_10_120000_alter_nfe_emissoes_status_enum_add_enviando_erro_envio.php`
- `2026_05_11_150001_create_nfse_emissoes_table.php`
- `2026_05_12_120000_create_nfse_eventos_cancelamento_table.php`
- `2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules.php`

## Views (Blade)

**Total:** 4 arquivos

**Pastas principais:**

- `mail/` — 2 arquivo(s)
- `D:/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `nfebrasil.access`
- `nfebrasil.emit.manage`
- `nfebrasil.consult.view`
- `nfebrasil.sped.view`
- `nfebrasil.settings.manage`
- `nfe.configuracao.manage`
- `nfe.tributacao.manage`
- `nfe.manifestacao.view`
- `nfe.manifestacao.manage`

## Processamento / eventos

**Jobs (queue):** `BuscarDfesRecebidosJob`, `CancelarNfeJob`, `CancelarNfseJob`, `EmitirNFSeJob`, `EmitirNfceJob`

**Commands (artisan):** `MigrateCertFromBusiness`, `NfeHealthCommand`, `PuxarDfesRecebidosCommand`

**Events:** `FiscalRuleCreated`, `FiscalRuleDeleted`, `FiscalRuleUpdated`, `NFCeAutorizada`, `NFeAutorizada`

**Listeners:** `EmitirNFeAoReceberPagamento`, `EmitirNfceAoFinalizarVenda`, `EnviarDanfeNFCePorEmail`, `EnviarDanfePorEmail`, `SyncFiscalRuleToTaxRate`

## Peças adicionais

- **Seeders:** `NfeBrasilDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `NfeBrasil` |
| `module_version` | `0.1.0` |
| `ambiente_default` | `homologacao` |
| `auto_emission_on_invoice_paid` | `false` |
| `auto_emission_on_sell_completed` | `false` |
| `email_danfe_on_autorizada` | `true` |
| `email_danfe_nfce_on_autorizada` | `false` |
| `resp_tec` | `[array(4 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Fiscal` | 1 |
| `Jana` | 1 |
| `RecurringBilling` | 1 |

## Integridade do banco

**Unique indexes:** 9

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
**Reaxecutar com:** `php artisan module:spec NfeBrasil`

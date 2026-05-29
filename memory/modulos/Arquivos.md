# Módulo: Arquivos

> **DMS backbone — todo arquivo anexado da empresa cai aqui (multi-tenant Tier 0). Polimorfismo Eloquent morph (arquivable_type/arquivable_id). Outros módulos adotam trait HasArquivos opt-in. Storage abstraído (disk local-ct100 + vault encrypted). Curador como engine de classificação. Soft-delete + audit log integral. Ver ADR 0123.**

- **Alias:** `arquivos`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Arquivos`
- **Status:** 🟢 ativo
- **Providers:** Modules\Arquivos\Providers\ArquivosServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (22)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 3 |
| Controllers | 3 |
| Entities (Models) | 1 |
| Services | 4 |
| FormRequests | 7 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 6 |
| Arquivos de lang | 0 |
| Testes | 22 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |

## Controllers

- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`DownloadController`** — 0 ação(ões): 
- **`InstallController`** — 0 ação(ões): 

## Entities (Models Eloquent)

- **`Arquivo`** (tabela: `arquivos`)

## Migrations

- `2026_05_10_000001_create_arquivos_table.php`
- `2026_05_10_000002_create_arquivos_audit_log_table.php`
- `2026_05_10_000003_create_arquivos_dedupe_table.php`
- `2026_05_10_000010_backfill_nfe_xml_arquivos.php`
- `2026_05_10_000020_backfill_consumers_arquivos.php`
- `2026_05_10_000030_add_metadata_recalculated_at_to_arquivos.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Commands (artisan):** `AuditLogCommand`, `DedupeStatsCommand`, `ExportZipCommand`, `HealthCheckCommand`, `RecalcularMetadataCommand`, `ReencryptVaultCommand`, `RetentionCleanupCommand`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Arquivos` |
| `disk_default` | `local` |
| `disk_vault` | `vault` |
| `upload_max_mb` | `50` |
| `vault_max_file_size_mb` | `50` |
| `retention_days_default` | `90` |
| `retention_days_policy` | `[array(8 itens)]` |
| `signed_url_expiration_minutes` | `60` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Admin` | 1 |

## Integridade do banco

**Foreign Keys** (3):

- `business_id` → `business.id`
- `uploaded_by_user_id` → `users.id`
- `arquivo_id` → `arquivos.id`

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
**Reaxecutar com:** `php artisan module:spec Arquivos`

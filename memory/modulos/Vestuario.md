# Módulo: Vestuario

> **Modules/Vestuario — vertical lojas de vestuário/moda BR (CNAE 4781-4/00). Cliente piloto: ROTA LIVRE biz=4 (Larissa Termas do Gravatal/SC) em prod desde 2024-Q1. Estado especial: SPEC live + scaffold formal (ADR 0121 §P7). Consome shared Modules/Repair (kanban OS opcional) + núcleo UltimatePOS. Ver memory/requisitos/Vestuario/SPEC.md.**

- **Alias:** `vestuario`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Vestuario`
- **Status:** 🟢 ativo
- **Providers:** Modules\Vestuario\Providers\VestuarioServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ✅ Tem testes (15)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 6 |
| Controllers | 2 |
| Entities (Models) | 1 |
| Services | 4 |
| FormRequests | 2 |
| Middleware | 0 |
| Views Blade | 1 |
| Migrations | 3 |
| Arquivos de lang | 0 |
| Testes | 15 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/` | `[EtiquetaTagController::class, 'index']` |
| `POST` | `lote/zpl` | `[EtiquetaTagController::class, 'storeZpl']` |
| `POST` | `lote/pdf` | `[EtiquetaTagController::class, 'storePdf']` |

## Controllers

- **`EtiquetaTagController`** — 3 ação(ões): index, storeZpl, storePdf
- **`InstallController`** — 0 ação(ões): 

## Entities (Models Eloquent)

- **`VestuarioSetting`** (tabela: `vestuario_settings`)

## Migrations

- `2026_05_10_000001_create_vestuario_settings_table.php`
- `2026_05_17_000001_create_vestuario_devolucoes_table.php`
- `2026_05_17_000002_create_vestuario_creditos_cliente_table.php`

## Views (Blade)

**Total:** 1 arquivos

**Pastas principais:**

- `etiquetas/` — 1 arquivo(s)

## Processamento / eventos

**Commands (artisan):** `VestuarioHealthCommand`, `VestuarioSettingsCommand`

## Peças adicionais

- **Seeders:** `RepairSettingsSeeder`

## Integridade do banco

**Foreign Keys** (6):

- `business_id` → `business.id`
- `business_id` → `business.id`
- `transaction_id` → `transactions.id`
- `processed_by_user_id` → `users.id`
- `business_id` → `business.id`
- `contact_id` → `contacts.id`

**Unique indexes:** 2

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
**Reaxecutar com:** `php artisan module:spec Vestuario`

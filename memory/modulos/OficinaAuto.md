# Módulo: OficinaAuto

> **Modules/OficinaAuto — vertical oficinas automotivas BR (CNAEs 4520-0/01, 2212-9/00, 4581-4/00). Estado V0 em construção (ADR 0137 — qualificada por sinal Vargas + Martinho). Scaffold inicial com CRUD Vehicle + ServiceOrder multi-tenant Tier 0. Ver memory/requisitos/OficinaAuto/SPEC.md.**

- **Alias:** `oficina-auto`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/OficinaAuto`
- **Status:** 🟢 ativo
- **Providers:** Modules\OficinaAuto\Providers\OficinaAutoServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 34 rotas — escopo médio
- ✅ Tem testes (30)
- ⚙️ Processamento assíncrono: 1 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 3 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 34 |
| Controllers | 8 |
| Entities (Models) | 4 |
| Services | 6 |
| FormRequests | 9 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 11 |
| Arquivos de lang | 1 |
| Testes | 30 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `producao-oficina` | `[ProducaoOficinaController::class, 'index']` |
| `GET` | `veiculos` | `[VehicleController::class, 'index']` |
| `GET` | `veiculos/create` | `[VehicleController::class, 'create']` |
| `POST` | `veiculos` | `[VehicleController::class, 'store']` |
| `GET` | `veiculos/{vehicle}` | `[VehicleController::class, 'show']` |
| `GET` | `veiculos/{vehicle}/edit` | `[VehicleController::class, 'edit']` |
| `PUT` | `veiculos/{vehicle}` | `[VehicleController::class, 'update']` |
| `DELETE` | `veiculos/{vehicle}` | `[VehicleController::class, 'destroy']` |
| `GET` | `ordens-servico` | `[ServiceOrderController::class, 'index']` |
| `GET` | `ordens-servico/create` | `[ServiceOrderController::class, 'create']` |
| `POST` | `ordens-servico` | `[ServiceOrderController::class, 'store']` |
| `GET` | `ordens-servico/{order}` | `[ServiceOrderController::class, 'show']` |
| `GET` | `ordens-servico/{order}/edit` | `[ServiceOrderController::class, 'edit']` |
| `PUT` | `ordens-servico/{order}` | `[ServiceOrderController::class, 'update']` |
| `DELETE` | `ordens-servico/{order}` | `[ServiceOrderController::class, 'destroy']` |
| `GET` | `ordens-servico/{order}/print` | `[ServiceOrderController::class, 'printInvoice']` |
| `POST` | `ordens-servico/{order}/items` | `[ServiceOrderItemController::class, 'store']` |
| `PUT` | `ordens-servico/{order}/items/{item}` | `[ServiceOrderItemController::class, 'update']` |
| `DELETE` | `ordens-servico/{order}/items/{item}` | `[ServiceOrderItemController::class, 'destroy']` |
| `GET` | `service-orders/{order}` | `[ServiceOrderController::class, 'show']` |
| `GET` | `service-orders/{order}/fsm/actions` | `[ServiceOrderFsmActionController::class, 'actions']` |
| `POST` | `service-orders/{order}/fsm/execute` | `[ServiceOrderFsmActionController::class, 'execute']` |
| `POST` | `service-orders/{order}/fsm/start-pipeline` | `[ServiceOrderFsmActionController::class, 'startPipeline']` |
| `GET` | `service-orders/{order}/history` | `[ServiceOrderFsmActionController::class, 'history']` |
| `POST` | `ordens-servico/{order}/dvi` | `[DviInspectionController::class, 'store']` |
| `PUT` | `ordens-servico/{order}/dvi/{item}` | `[DviInspectionController::class, 'update']` |
| `DELETE` | `ordens-servico/{order}/dvi/{item}` | `[DviInspectionController::class, 'destroy']` |
| `POST` | `ordens-servico/{order}/dvi/{item}/photo` | `[DviInspectionController::class, 'uploadPhoto']` |
| `DELETE` | `ordens-servico/{order}/dvi/{item}/photo/{arquivo}` | `[DviInspectionController::class, 'deletePhoto']` |
| `GET` | `/aprovar-os/{token}` | `[AprovacaoOsController::class, 'show']` |
| `POST` | `/aprovar-os/{token}` | `[AprovacaoOsController::class, 'submit']` |

## Controllers

- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`DviInspectionController`** — 5 ação(ões): store, update, destroy, uploadPhoto, deletePhoto
- **`InstallController`** — 0 ação(ões): 
- **`ProducaoOficinaController`** — 1 ação(ões): index
- **`AprovacaoOsController`** — 2 ação(ões): show, submit
- **`ServiceOrderController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, printInvoice
- **`ServiceOrderItemController`** — 3 ação(ões): store, update, destroy
- **`VehicleController`** — 7 ação(ões): index, create, store, show, edit, update, destroy

## Entities (Models Eloquent)

- **`OaInspectionItem`** (tabela: `oa_inspection_items`)
- **`ServiceOrder`** (tabela: `service_orders`)
- **`ServiceOrderItem`** (tabela: `oficina_service_order_items`)
- **`Vehicle`** (tabela: `vehicles`)

## Migrations

- `2026_05_11_000010_create_vehicles_table.php`
- `2026_05_11_000020_create_service_orders_table.php`
- `2026_05_12_220001_add_cacamba_fields_to_vehicles.php`
- `2026_05_12_220002_add_rental_fields_to_service_orders.php`
- `2026_05_12_230001_add_transaction_sell_line_id_to_service_orders.php`
- `2026_05_13_010001_add_current_stage_id_to_service_orders.php`
- `2026_05_13_010002_add_contact_id_to_service_orders.php`
- `2026_05_17_000010_create_oficina_service_order_items_table.php`
- `2026_05_26_120001_add_box_and_assigned_user_to_service_orders.php`
- `2026_05_26_120002_create_oa_inspection_items_table.php`
- `2026_05_27_000010_add_client_decision_to_oa_inspection_items.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Jobs (queue):** `EnviarLinkAprovacaoWhatsappJob`

**Commands (artisan):** `ImportFirebirdMartinhoCommand`, `OficinaAutoCleanupMigratedClientCommand`, `OficinaAutoMigrationReportCommand`, `OficinaAutoSanityCheckCommand`

**Observers:** `ServiceOrderObserver`

## Peças adicionais

- **Policies:** `ServiceOrderPolicy`, `VehiclePolicy`
- **Seeders:** `OficinaAutoDatabaseSeeder`, `OficinaAutoFsmSeeder`, `RepairSettingsSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `OficinaAuto` |
| `module_version` | `0.1.0` |
| `cnaes` | `[array(3 itens)]` |
| `cnae_principal` | `4520-0/01` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Repair` | 2 |
| `Jana` | 1 |
| `Whatsapp` | 1 |

## Integridade do banco

**Unique indexes:** 4

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
**Reaxecutar com:** `php artisan module:spec OficinaAuto`

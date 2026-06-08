---
module: AssetManagement
na_justified:
  D5: "Módulo legacy UltimatePOS de gestão de ativos (impressoras, máquinas, equipamentos). Cross-business interno — sem cliente externo pagante específico reportando dor. ADR 0121 §modular especializado legacy + ADR 0105 §cliente como sinal qualificado: módulo dormente sem sinal qualificado de cliente real ativo."
  D6.c: "Scaffold UltimatePOS v6 legacy — controllers usam DataTables server-side (yajra) com SQL determinístico simples, sem paginate Eloquent complexo nem eager-load com N+1 risk. Volume típico <2k assets/business — perf adequada sem otimização avançada."
  D9.b: "AssetManagement é módulo CRUD síncrono — sem jobs assíncronos Horizon. Operações de allocate/revoke/maintenance são single-shot via Controller. failed_jobs N/A por design."
related_adrs: [0011, 0093, 0105, 0121, 0153, 0154, 0155, 0156]
---

# SPEC — Modules/AssetManagement

> Catálogo append-only de User Stories aprovadas pro módulo de gestão de ativos físicos (notebooks, impressoras, servidores, móveis, veículos). Núcleo herdado UltimatePOS v6 ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)) — preserva schema legacy (`assets`, `asset_transactions`, `asset_warranties`, `asset_maintenances`) com isolamento multi-tenant manual via `where('business_id', ...)` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

## Estado

- ✅ Em produção (legacy UltimatePOS, 8 controllers, 4 Entities)
- 🟡 Sem `BusinessScope` global — isolamento Tier 0 via filtro manual em Controllers
- 🟡 Frontend Blade only — sem MWART/Inertia ainda
- ⏸️ Backlog feature-wish: Depreciação automática (ADR 0105 — sem cliente sinalizando)

## Tabela de US

| ID | Título | Status | Owner | Notas |
|---|---|---|---|---|
| US-ASSET-001 | Cadastro de ativo (asset registry) | ✅ done | [W] | `AssetController@store`, `Asset::create` com `business_id` + `asset_code` único per-biz |
| US-ASSET-002 | Alocação de ativo a colaborador (`AssetTransaction transaction_type=allocate`) | ✅ done | [W] | `AssetAllocationController`, vincula `user_id` + `quantity` reservada |
| US-ASSET-003 | Revogação/devolução de alocação (`transaction_type=revoke`) | ✅ done | [W] | `RevokeAllocatedAssetController`, restaura quantidade disponível |
| US-ASSET-004 | Log de manutenções (preventiva/corretiva) | ✅ done | [W] | `AssetMaitenanceController`, `AssetMaintenance` com `maintenance_date` + `completion_date` + `cost` + status |
| US-ASSET-005 | Garantia/warranty (período + fornecedor) | ✅ done | [W] | `AssetWarranty` com `start_date`/`end_date`, accessor `is_in_warranty` em Asset.php |
| US-ASSET-006 | Notificações de manutenção (mail) | ✅ done | [W] | `AssetAssignedForMaintenance` + `AssetSentForMaintenance` Notifications |
| US-ASSET-007 | Settings per-business (prefix, notification) | ✅ done | [W] | `AssetSettingsController`, coluna `asset_settings` em `business` table |
| US-ASSET-008 | Multi-tenant isolation test biz=1 vs biz=99 (Tier 0) | ✅ done | [Claude] | `Tests/Feature/MultiTenantIsolationTest.php` + `CrossTenantAssetTest.php` (Wave I-W 2026-05-16) |

## Backlog (feature wish — sem sinal qualificado ADR 0105)

- 🔒 US-ASSET-W01: Depreciação automática linear/SAC (depreciation_rate + book_value)
- 🔒 US-ASSET-W02: Transferência entre filiais (transfer between BusinessLocations)
- 🔒 US-ASSET-W03: Disposal/baixa contábil com motivo + data
- 🔒 US-ASSET-W04: QR code físico no ativo + scan mobile pra check-in/out
- 🔒 US-ASSET-W05: Migração Blade → Inertia/React (skill `mwart-process`)

## Schema canônico

- `assets` — `business_id`, `name`, `asset_code`, `quantity`, `unit_price`, `is_allocatable`, `purchase_type`, `location_id` FK BusinessLocation
- `asset_transactions` — `business_id`, `asset_id`, `user_id`, `quantity`, `transaction_type` (allocate/revoke), `transaction_date`
- `asset_warranties` — `asset_id`, `start_date`, `end_date`, `supplier`
- `asset_maintenances` — `business_id`, `asset_id`, `maintenance_date`, `completion_date`, `cost`, `status`, `assigned_to`

## Refs

- [Modules/AssetManagement/Entities/Asset.php](../../../Modules/AssetManagement/Entities/Asset.php)
- [Modules/AssetManagement/Http/Controllers/](../../../Modules/AssetManagement/Http/Controllers/)
- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1 nunca biz=cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
- [BRIEFING.md](BRIEFING.md)

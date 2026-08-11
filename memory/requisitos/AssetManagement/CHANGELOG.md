# CHANGELOG — Modules/AssetManagement

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 27 — Polish final ≥88] — 2026-05-17 (73-77 → 88, +11 a +15pp)

### Adicionado — D9.a OTel spans AssetWarrantyService (+2 spans)
- `AssetWarrantyService::contagemAtivas` — span `assetmanagement.warranty.count_active`
  (widget dashboard "N garantias ativas"). Findless safe (return 0 se asset não existe).
- `AssetWarrantyService::contagemExpiradas` — span `assetmanagement.warranty.count_expired`
  (relatório alerta garantias vencidas). Pareado pra observability simétrica.
- Total AssetWarrantyService: 3 originais (add/revoke/list_active) + 2 W27 = **5 spans**.

### Adicionado — D5 README "como cliente usa" (customer journey)
- `Modules/AssetManagement/README.md` documentando:
  - 4 cenários canônicos (cadastrar asset, alocar, abrir OS manutenção, contar garantias)
  - Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) chain warranty via Asset.business_id
  - D7 retention LGPD 4 categorias (3650d/1825d/2555d com bases CC Art. 206 / CTN Art. 195)
  - D9 observability tabela 14 spans (3 Services + AssetWarrantyService W27)
  - Health check commands `php artisan assetmanagement:health [--json|--alert|--detail]`

### Adicionado — D2 Pest expand Wave 27 (9 cenários novos)
- `Tests/Feature/Wave27AssetManagementPolishTest.php`:
  - Spans novos (Reflection source) — count canônico ≥5 spanBiz invocations
  - README existence + cenários A/B/C/D documentados
  - Cross-tenant biz=99: contagemAtivas/contagemExpiradas retorna 0 (Tier 0)
  - Contract int type sanity

### Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Multi-tenant Tier 0 (ADR 0093) — `business_id` global scope em Asset/Allocation/Maintenance
- ⛔ `am_warranties` SEM business_id direto — scope via JOIN parent Asset preservado
- ⛔ NUNCA biz=4 cliente real (ADR 0101) — biz=1 / biz=99 em testes
- ⛔ OtelHelper canônico (`App\Util\OtelHelper`)
- ⛔ PT-BR comentários

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D9.a 14 spans + D5 customer journey README)
- ADR 0159 Wave 25 polish base

## [Wave 25 — Polish D9.a/c saturation + retention compliance] — 2026-05-16

### Adicionado — D9.a OTel spans (+9 spans em 3 Services)
- `AssetService::criar/atualizar/remover` — agora envoltos em `OtelHelper::spanBiz()`:
  - `assetmanagement.asset.criar` (+attrs: business_id, user_id)
  - `assetmanagement.asset.atualizar` (+attrs: business_id, asset_id)
  - `assetmanagement.asset.remover` (+attrs: business_id, asset_id)
- `AssetAllocationService::criar/atualizar/remover` — idem:
  - `assetmanagement.allocation.criar/atualizar/remover` (+attrs com allocation_id quando aplicável)
- `AssetMaintenanceService::criar/atualizar/remover` — idem:
  - `assetmanagement.maintenance.criar/atualizar/remover` (+attrs com maintenance_id)
- Total: **3 Services × 3 métodos = 9 spans** com business_id Tier 0 propagado.

### Adicionado — D9.c Health Check (+1 check canônico)
- `AssetManagementHealthCommand` agora tem **8 checks** (era 7):
  - **Novo**: `retention_config_present` — verifica `Config/retention.php` presente + 4 entities mapeadas (am_assets, am_asset_transactions, am_maintenance_logs, am_warranties).
- Output: `total=8`, `retention_config_present=OK` (4 entities + strategy=anonymize).

### Alterado — Provider registration
- `AssetManagementServiceProvider::boot()` agora registra `AssetManagementHealthCommand::class` via `$this->commands([...])` (estava órfão pré-Wave 25 — `php artisan list` não exibia).

### Alterado — Tests
- `AssetManagementHealthCommandTest` atualizado: `total` expected 7→8 + adicionada `retention_config_present` na lista de checks canônicos esperados.

### Pest local
- `Modules/AssetManagement/Tests/` — **52 passed (119 assertions), 7 skipped intencional** (CrossTenantAssetTest SQLite-incompat — schema legacy MySQL UltimatePOS, ADR 0101).

### Não alterado (intencional — já saturado)
- D7.c Config/retention.php declara 4 entities desde W23 (am_assets 3650d/10y fiscal, am_asset_transactions 1825d/5y CTN, am_maintenance_logs 1825d, am_warranties 2555d/7y).
- D1 Multi-tenant: Assets/Allocation/Maintenance entities com `business_id` direto desde W18.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1 (cross-tenant SQLite skip documentado)
- ADR 0155 Module Grade v3 (D9.a 9/9 spans + D9.c 8/8 checks)
- ADR 0159 Wave 25 polish (level `none` mantido — scaffold UltimatePOS sem uso de cliente, saturação serve baseline arquitetural)

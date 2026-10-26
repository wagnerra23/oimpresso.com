---
id: requisitos-asset-management-spec
module: AssetManagement
version: "1.0"
last_updated: "2026-07-02"
owner: wagner
status: ativo
anchor_format: "v1"
na_justified:
  D5: "Módulo legacy UltimatePOS de gestão de ativos (impressoras, máquinas, equipamentos). Cross-business interno — sem cliente externo pagante específico reportando dor. ADR 0121 §modular especializado legacy + ADR 0105 §cliente como sinal qualificado: módulo dormente sem sinal qualificado de cliente real ativo."
  D6.c: "Scaffold UltimatePOS v6 legacy — controllers usam DataTables server-side (yajra) com SQL determinístico simples, sem paginate Eloquent complexo nem eager-load com N+1 risk. Volume típico <2k assets/business — perf adequada sem otimização avançada."
  D9.b: "AssetManagement é módulo CRUD síncrono — sem jobs assíncronos Horizon. Operações de allocate/revoke/maintenance são single-shot via Controller. failed_jobs N/A por design."
related_adrs: [0011-alinhamento-padrao-jana, 0093-multi-tenant-isolation-tier-0, 0105-cliente-como-sinal-guiar-sem-mandar, 0121-oimpresso-modular-especializado-por-vertical, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0155-module-grade-v3-sub-dimensoes-gate-ci, 0156-module-grade-v3-errata-otel-helper-na-justified]
---
<!-- schema-allowlist: US ativas sob "## Tabela de US" + backlog sob "## Backlog (feature wish...)"; SPEC legacy UltimatePOS usa heading "## Tabela de US" em vez do canônico "## US ativas". -->

# SPEC — Modules/AssetManagement

> Catálogo append-only de User Stories aprovadas pro módulo de gestão de ativos físicos (notebooks, impressoras, servidores, móveis, veículos). Núcleo herdado UltimatePOS v6 ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)) — preserva schema legacy (`assets`, `asset_transactions`, `asset_warranties`, `asset_maintenances`) com isolamento multi-tenant manual via `where('business_id', ...)` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

## Estado

- ✅ Em produção (legacy UltimatePOS, 7 controllers, 4 Entities)
- 🟡 Sem `BusinessScope` global — isolamento Tier 0 via filtro manual em Controllers
- 🟡 Frontend Blade only — sem MWART/Inertia ainda
- ⏸️ Backlog feature-wish: Depreciação automática (ADR 0105 — sem cliente sinalizando)

## Tabela de US

> US append-only em formato heading (anchor SPEC↔código ADR 0273, gramática v1). Owner [W] salvo indicado. Todas em produção legacy UltimatePOS — paths verificados existentes em `origin/main`.

### US-ASSET-001 · Cadastro de ativo (asset registry) `✅ done`

> status: done

`Asset::create` com `business_id` + `asset_code` auto-gerado sequencial per-biz via `setAndGetReferenceCount` quando vazio (sem constraint de unicidade no schema/request).

**Implementado em:** `Modules/AssetManagement/Http/Controllers/AssetController.php` · `AssetController@store` · `Modules/AssetManagement/Entities/Asset.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-002 · Alocação de ativo a colaborador (`transaction_type=allocate`) `✅ done`

> status: done

Vincula `receiver` (colaborador) + `quantity` reservada via `AssetTransaction` (schema real usa coluna `receiver`, não `user_id`).

**Implementado em:** `Modules/AssetManagement/Http/Controllers/AssetAllocationController.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-003 · Revogação/devolução de alocação (`transaction_type=revoke`) `✅ done`

> status: done

Restaura quantidade disponível.

**Implementado em:** `Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-004 · Log de manutenções `✅ done`

> status: done

`AssetMaintenance` com `status` + `priority` + `details` + `assigned_to` (schema real da migration `2022_03_26_062215_create_asset_maintenances_table.php` — **sem** colunas `maintenance_date`/`completion_date`/`cost`; o texto legado que as citava foi corrigido em 2026-07-02).

**Implementado em:** `Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php` · `Modules/AssetManagement/Entities/AssetMaintenance.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-005 · Garantia/warranty (período + custo adicional) `✅ done`

> status: done

`AssetWarranty` com `start_date`/`end_date` + `additional_cost`/`additional_note`, accessor `is_in_warranty` em `Asset` (schema real da migration warranty — **não há** coluna `supplier`/fornecedor; texto legado corrigido em 2026-07-02).

**Implementado em:** `Modules/AssetManagement/Entities/AssetWarranty.php` · `Modules/AssetManagement/Entities/Asset.php` · `Asset::is_in_warranty` · verificado@dad0b11 (2026-07-02)

### US-ASSET-006 · Notificações de manutenção (mail) `✅ done`

> status: done

**Implementado em:** `Modules/AssetManagement/Notifications/AssetAssignedForMaintenance.php` · `Modules/AssetManagement/Notifications/AssetSentForMaintenance.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-007 · Settings per-business (prefix, notification) `✅ done`

> status: done

Coluna `asset_settings` em `business` table.

**Implementado em:** `Modules/AssetManagement/Http/Controllers/AssetSettingsController.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-008 · Multi-tenant isolation test biz=1 vs biz=99 (Tier 0) `✅ done`

> status: done

Owner [Claude] — Wave I-W 2026-05-16.

**Implementado em:** `Modules/AssetManagement/Tests/Feature/MultiTenantIsolationTest.php` · `Modules/AssetManagement/Tests/Feature/CrossTenantAssetTest.php` · verificado@dad0b11 (2026-07-02)

### US-ASSET-009 · Depreciação gerencial linear por categoria — **P2**

> owner: — · priority: p2 · status: todo · type: story

Como dono, quero ver quanto cada bem já perdeu de valor e quanto vale hoje, calculado de forma linear pelo prazo da categoria (editável no bem), para saber o valor do meu patrimônio sem esperar o contador.

**Implementado em:** _pendente_

- **Decisão [W] 2026-10-26:** opção A, linear por categoria com prazo padrão editável no bem. Mostrar como valor **GERENCIAL**, rotulado assim, nunca como contábil. Promove o wish US-ASSET-W01 (só a parte linear; SAC e taxa fiscal ficam fora).
- Até esta data o painel devolvia `null` para o valor residual ("depreciação nunca é calculada", RESÍDUO 6 em `AssetController::dashboard`).
- Regra do protótipo do Patrimônio (`prototipo-ui/cowork/Wagner/patrimonio-data.jsx`, `depreciacao()`): meses desde a compra ÷ (prazo em anos × 12), limitado a 100%; residual = valor × quantidade × (1 − fração). Mexe em valor exibido: regra mestre (dupla prova).

### US-ASSET-010 · Manutenção preventiva com próxima data por bem — **P2**

> owner: — · priority: p2 · status: todo · type: story

Como responsável pelo patrimônio, quero cadastrar a cada quantos dias um bem precisa de manutenção e ver os que estão vencendo, para não descobrir a falha só quando o bem para.

**Implementado em:** _pendente_

- Origem: lacunas de 2026-10-25 (P2 "depreciação e manutenção preventiva"). Não depende da US-ASSET-009.
- Hoje só existe o registro de manutenção feita (US-ASSET-004) e o aviso por e-mail dela (US-ASSET-006).

## Backlog (feature wish — sem sinal qualificado ADR 0105)

- ~~🔒 US-ASSET-W01: Depreciação automática linear/SAC (depreciation_rate + book_value)~~ → parte linear ativa como US-ASSET-009 (decisão [W] 2026-10-26)
- 🔒 US-ASSET-W02: Transferência entre filiais (transfer between BusinessLocations)
- 🔒 US-ASSET-W03: Disposal/baixa contábil com motivo + data
- 🔒 US-ASSET-W04: QR code físico no ativo + scan mobile pra check-in/out
- 🔒 US-ASSET-W05: Migração Blade → Inertia/React (skill `mwart-process`)

## Schema canônico

- `assets` — `business_id`, `name`, `asset_code`, `quantity`, `unit_price`, `is_allocatable`, `purchase_type`, `location_id` (relação Eloquent → BusinessLocation, sem FK física na migration)
- `asset_transactions` — `business_id`, `asset_id`, `receiver`, `quantity`, `transaction_type` (allocate/revoke), `transaction_datetime`
- `asset_warranties` — `asset_id`, `start_date`, `end_date`, `additional_cost`, `additional_note`
- `asset_maintenances` — `business_id`, `asset_id`, `maitenance_id`, `status`, `priority`, `details`, `maintenance_note`, `created_by`, `assigned_to`

## Refs

- [Modules/AssetManagement/Entities/Asset.php](../../../Modules/AssetManagement/Entities/Asset.php)
- [Modules/AssetManagement/Http/Controllers/](../../../Modules/AssetManagement/Http/Controllers/)
- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1 nunca biz=cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
- [BRIEFING.md](BRIEFING.md)

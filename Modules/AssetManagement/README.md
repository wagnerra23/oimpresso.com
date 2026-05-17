# Modules/AssetManagement — Gestão de Ativos

> Wave 27 polish 2026-05-17 — README "como cliente usa" (D5 customer journey).
> Módulo UltimatePOS herdado, instrumentado D9.a OTel + D7.b LogsActivity + D7.c retention LGPD.

## Missão

Cadastro + alocação + manutenção de **bens / ativos físicos** (impressoras gráficas, máquinas costura, equipamentos oficina) da empresa cliente. Inclui controle de garantia + histórico de manutenção + alocação a funcionários/projetos.

## Cenários canônicos (como cliente usa)

### Cenário A — Cadastrar ativo novo (ex: impressora Epson L1300)

1. Operador acessa `/assetmanagement/assets` (botão `+ Novo Asset`)
2. Preenche `asset_code` (auto-gerado se vazio — prefix configurável), `name`, `serial_no`, `model`, `purchase_date`, `unit_price`, `depreciation` (% ao ano)
3. Marca `is_allocatable=1` se ativo pode ser alocado a usuário (impressora vs prédio)
4. (Opcional) Adiciona garantias na sub-tabela `warranties` (start_date + months → end_date calculado)
5. Submit → `AssetService::criar()` valida + persiste em transação atômica
   - Span OTel: `assetmanagement.asset.criar`
   - Audit log: `activity_log` (LogsActivity) com PII redactada

### Cenário B — Alocar ativo a funcionário (ex: notebook Maria/RH)

1. Operador acessa `/assetmanagement/asset-allocations` (botão `+ Alocar`)
2. Seleciona asset disponível (verifica `quantity - allocated_qty + revoked_qty`)
3. Preenche `receiver` (user_id), `transaction_datetime`, `reason`, `allocated_upto` (data limite)
4. Submit → `AssetAllocationService::criar()` cria `asset_transactions` type=`allocate`
   - Span OTel: `assetmanagement.allocation.criar`

### Cenário C — Abrir ordem de manutenção (ex: impressora travou)

1. Operador acessa `/assetmanagement/asset-maintenances` (botão `+ OS Manutenção`)
2. Seleciona asset, define `priority` (low/medium/high/urgent), `status=pending`, `maintenance_note`
3. Submit → `AssetMaintenanceService::criar()` cria OS + dispara notification
   - Span OTel: `assetmanagement.maintenance.criar`
   - Notifica via `assetUtil->sendAssetSentForMaintenanceNotification($id)`

### Cenário D — Verificar garantias ativas (relatório executivo)

1. Cliente quer saber quantos ativos têm garantia vigente
2. API/Controller chama `AssetWarrantyService::contagemAtivas($assetId, $businessId)` (Wave 27)
3. Service retorna count com span `assetmanagement.warranty.count_active` instrumentado
4. Útil pra widget dashboard: "73 garantias ativas / 12 expirando em 30d / 4 expiradas"

## Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)

- **`am_assets`**, **`am_asset_transactions`**, **`am_maintenance_logs`** têm `business_id` direto + HasBusinessScope global scope
- **`am_warranties`** scope via parent Asset (chain JOIN — não tem business_id próprio)
- Job assíncrono SEMPRE recebe `$businessId` no constructor — Service NUNCA chama `session()`

## D7 — Retenção LGPD + Audit (Wave 23/25)

- `Config/retention.php` declara 4 categorias com bases legais:
  - `am_assets`: 3650d / 10 anos (fiscal CC Art. 206)
  - `am_asset_transactions`: 1825d / 5 anos (CTN Art. 195)
  - `am_maintenance_logs`: 1825d / 5 anos
  - `am_warranties`: 2555d / 7 anos
- Estratégia: `anonymize` (preserva audit trail, mascara PII)
- Entities têm `LogsActivity` (Spatie Activitylog) — mudanças críticas auditadas em `activity_log`

## D9 — Observabilidade OtelHelper canônico

| Service | Métodos instrumentados | Spans |
|---|---|---|
| `AssetService` | criar/atualizar/remover | 3 |
| `AssetAllocationService` | criar/atualizar/remover | 3 |
| `AssetMaintenanceService` | criar/atualizar/remover | 3 |
| `AssetWarrantyService` | adicionar/revogar/ativas/contagemAtivas/contagemExpiradas | 5 (W27 +2) |

Total: **14 spans** com `business_id` + `module='AssetManagement'` Tier 0 propagado.

Zero-cost overhead quando `otel.enabled=false` (default Hostinger — só CT 100 com `OTEL_EXPORTER_OTLP_ENDPOINT` configurado emite).

## Health check

```bash
php artisan assetmanagement:health           # output tabela 8 checks
php artisan assetmanagement:health --json    # output JSON parseable
php artisan assetmanagement:health --alert   # exit code != 0 se FAIL
php artisan assetmanagement:health --detail  # expande JSON após tabela
```

8 checks: schema_canon · catalog_global · assets_orphan · allocations_consistency · maintenances_open_per_business · warranties_expired · audit_trail_present · `retention_config_present` (Wave 25 NEW)

## ADRs centrais

- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1
- [ADR 0155](../../memory/decisions/0155-module-grade-rubrica-v3.md) Module Grade v3 (D9.a/c)
- [ADR 0159](../../memory/decisions/0159-wave-25-polish-modulos-saturated.md) Wave 25 polish

## SCOPE / não fazer aqui

Ver [SCOPE.md](SCOPE.md). Em resumo:
- ❌ Conhecimento canônico (ADRs, sessions) → `Modules/KB`
- ❌ Tasks Jira-style → `Modules/ProjectMgmt`
- ❌ MCP server admin → `Modules/TeamMcp`
- ❌ Lançamento contábil de depreciação → `Modules/Accounting` (downstream consumer)

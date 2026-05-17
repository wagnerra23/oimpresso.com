# CHANGELOG — Modules/Spreadsheet

Mudanças observáveis. Append-only por release/wave. Módulo legado UltimatePOS — manutenção bug-fix + governance only.

## Wave 26 — 2026-05-17 (polish 74 → ≥85 +11pp · D1+D4+D6+D7+D3)

### D1 Entities + cross-tenant guard preservado
- `Tests/Feature/Wave26SpreadsheetSaturationTest.php` (~22 cenários, ~9 MySQL-skipped):
  - Entities (Spreadsheet + SpreadsheetShare) usam `LogsActivity` Spatie + `getActivitylogOptions` canon (`logAll + logOnlyDirty + dontSubmitEmptyLogs`).
  - Table custom canon (`sheet_spreadsheets` + `sheet_spreadsheet_shares`); `sheet_data` cast array; relação `shares()` hasMany via `sheet_spreadsheet_id`; `$guarded = [id]` mass assignment safe.
  - Schema `sheet_spreadsheets.business_id` confirmado existir (ADR 0093 Tier 0); cross-tenant manual via `where('business_id', ...)` no Controller (biz=1 vs biz=99 isolation preservado).

### D4 Service contract preservado
- 6 métodos canon (`createSpreadsheet`, `updateSpreadsheet`, `deleteSpreadsheet`, `resolveNotifyableUsers`, `listForUser`, `getForUser`).
- ≥6 chamadas `OtelHelper::spanBiz` (1 por método público crítico).
- `bizId` Tier 0 obrigatório nos 3 métodos write (CRUD); `listForUser` retorna LengthAwarePaginator; `getForUser` fail-secure nullable retorno.

### D6 SpreadsheetController DI canon
- Confirmado: injeta `SpreadsheetService` via DI; ACL canon (`superadmin || hasThePermissionInSubscription + access.spreadsheet`).

### D7 LGPD preservado
- `Config/retention.php` declara ≥2 entities canon (`sheet_spreadsheets` 1825d + `sheet_spreadsheet_shares` 1825d — janela fiscal Brasil 5y).

### D3 docs
- CHANGELOG (este entry) + BRIEFING.md Wave 26.

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL · ADR 0101 Tests biz=1 · ADR 0155 Module Grade v3 D4+D7 saturated · ADR 0159 Wave polish

## Wave 25 — 2026-05-16 (polish D7 LGPD declaração estável)

### Confirmação saturação D7 LGPD (sem alterações de código)
- `Spreadsheet` Model: `LogsActivity` Spatie já presente (`logAll()->logOnlyDirty()->dontSubmitEmptyLogs()`) — Wave 17.
- `SpreadsheetShare` Model: `LogsActivity` Spatie já presente — Wave 17.
- `Config/retention.php` declara 2 entities canônicas (`sheet_spreadsheets` 1825d / `sheet_spreadsheet_shares` 1825d — janela fiscal Brasil 5y).
- Strategy default `anonymize` + PiiRedactor best-effort pass sobre células texto (UGC opaco — limitação técnica documentada).
- **Status D7.b/c/a:** SATURATED — Modulo bucket `functional_horizontal` polish v3 ≥85 confirmado sem regressão.

### Pest local validado
- `Modules/Spreadsheet/Tests/Feature/ObservabilityTest` + `SpreadsheetServiceContractTest` mantém cobertura D9 OTel + Service contract.

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D7 saturated)
- ADR 0159 Wave 25 polish (level `none` — scaffold sem cliente, saturação serve baseline)

## Wave 18 — 2026-05-16 (governance saturation)

### D4 — Service layer canônica (ADR 0155 D4)
- `SpreadsheetService` ganha 2 métodos:
  - `listForUser(bizId, userId, folderId, perPage)`: extrai critério "minhas + compartilhadas comigo" (antes inlined em Controller)
  - `getForUser(id, bizId, userId)`: 1 ponto de truth pra ACL multi-tenant + share (antes espalhado em controllers)
- Ambos instrumentados `OtelHelper::spanBiz('spreadsheet.list_for_user' | 'spreadsheet.get_for_user')` — zero-cost se otel.enabled=false

### D8 — Observability contract test
- Novo Pest `ObservabilityTest.php`:
  - Verifica `SpreadsheetService` usa `App\Util\OtelHelper` canônico (não SDK OTel direto)
  - Garante 6 métodos público críticos instrumentados (regression detection se método novo for adicionado sem span)
  - Assinatura `bizId`/`userId` obrigatória nos métodos novos D4 (multi-tenant Tier 0 enforcement via reflection)

### Mantido
- `LogsActivity` em `Spreadsheet` + `SpreadsheetShare` (D7 audit trail)
- `Config/retention.php` (Wave 17) — 5 anos default conservador (planilha pode ser evidência operacional/contábil)
- Multi-tenant manual via `where('business_id', ...)` no Controller — Spreadsheet pre-data convenção `HasBusinessScope` global; back-compat preservada

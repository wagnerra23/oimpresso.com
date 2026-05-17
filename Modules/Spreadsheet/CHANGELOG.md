# CHANGELOG — Modules/Spreadsheet

Mudanças observáveis. Append-only por release/wave. Módulo legado UltimatePOS — manutenção bug-fix + governance only.

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

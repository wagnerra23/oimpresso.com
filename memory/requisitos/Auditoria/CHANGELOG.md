# Modules/Auditoria — CHANGELOG

## [Wave 27] - 2026-05-17 (polish final target 95)

### Test (D1+D2+D9 SATURATION)
- `Tests/Feature/Wave27SaturationTest.php` (NOVO) — **23 cenários organizados
  em 5 blocos**:
  - Bloco A (5) D1 cross-tenant via AuditEntryService — biz=99 NÃO retorna
    entries biz=1, biz=1 retorna suas próprias, find() lança 404 cross-tenant,
    normalizeFilters whitelist hardcoded (sem business_id injection)
  - Bloco B (5) D2 expand Reversibility — whitelist NÃO contém infra classes
    (User/Permission/Role), reasons citam autoridade legal (Portaria/SEFAZ/
    Asaas/NFSe/pagamento), cstats SEFAZ-firmes 100/101/135 bloqueia vs
    200/300/999 permite, TituloBaixa origem discrimina, OS nfse_emitida
  - Bloco C (5) D2 expand PiiLeak — telefone BR (47) 99876-5432, texto sem
    PII inalterado, input vazio sem fatal, input longo 1000 chars,
    redact SEMPRE antes de Activity::create/log->save (source code proof)
  - Bloco D (5) D9 spans completos — `auditoria.revert.execute` + `entry.list`
    + `entry.find` (zero-cost otel.enabled=false), attributes nunca exportam
    reason raw (PII Tier 0)
  - Bloco E (3) meta-saturation — acumulado ≥30 tests, whitelist count=5
    intocável regressão guard, todos Services com OtelHelper

### Acumulado Auditoria suite (Waves M + 18 + 23 + 25 + 27)
- AuditEntryReversibility (10) + PiiLeakActivityLog (8 base + 6 W25) + Wave18Sat
  + Wave27Sat (23) + outros = **≥45 cenários cross-tenant + PII + reversibility**.

### Spans OTel (D9 saturated)
- RevertService::revert (`auditoria.revert.execute`)
- AuditEntryService::list (`auditoria.entry.list`)
- AuditEntryService::find (`auditoria.entry.find`)
- Atributos canônicos: module + activity_id + subject_type + subject_id +
  restored_attrs_count + has_reason (NUNCA reason raw).

## [Wave 25] - 2026-05-16

### Added (D8 — FormRequest +1)
- `Http/Requests/ExportAuditEntriesRequest.php` — CSV/JSON dump da grade filtrada
  com 4 defesas Tier 0: cap hard limit max 10.000, `include_properties` via
  PiiRedactor, motivo obrigatório (audit log), whitelist format csv|json,
  `business_id` prohibited.

### Test (D1 — Pest +5 Wave 25 + PII expandido +6)
- `Tests/Feature/Wave18SaturationTest.php` — +5 testes ExportAuditEntriesRequest
  (rules/cap/prohibited/coerce/messages).
- `Tests/Feature/PiiLeakActivityLogEnforceTest.php` — +6 testes Wave 25:
  placeholder mode preserva legibilidade, CNPJ formatado BR, idempotência
  redact, RevertService nunca persiste raw, AuditNote casts sanity,
  ExportRequest include_properties default false.

### Docs
- `Pages/Auditoria/Index.charter.md` — seção Wave 25 (Export flow UX +
  Bulk action panel + 5 edge cases catalogados).
- `CHANGELOG.md` (este arquivo).

## [Wave 18 RETRY] - 2026-05-16

### Added (D8 — FormRequests)
- `Modules/Auditoria/Http/Requests/UpdateAuditNoteRequest.php` — edita nota recente. Authorize valida ownership ou `auditoria.note.write`.
- `Modules/Auditoria/Http/Requests/BulkRevertActivityRequest.php` — undo em lote (≤50 ids + reason min:10). Proteção hard contra revert massivo.

### Test (D1 — Pest saturado)
- `Tests/Feature/Wave18SaturationTest.php` — +4 testes Wave 18 RETRY (UpdateAuditNoteRequest + BulkRevertActivityRequest rules/messages).

### Docs
- `BRIEFING.md` (novo) — estado consolidado Wave 18 RETRY.
- `CHANGELOG.md` (este arquivo).

### Governance
- `module.json` atualizado com `governance.fsm_n_a: true` (Auditoria opera sobre activity_log existente — não tem state machine própria).

## [Wave M] - 2026-05-16

### Added
- `AuditEntryService` thin (extraído de `AuditoriaController::index/show`).
- `AuditNote` entity (`auditoria_audit_notes` table).
- 3 FormRequests: RevertActivityRequest, FilterAuditEntriesRequest, StoreAuditNoteRequest.
- `Inertia::defer` DEFAULT em activities/activity (Wave 18 D6.a).

### Preserved
- `RevertService` permanece intocado (compliance crítica).
- Whitelist UNREVERTIBLE de 5 categorias (ADR 0127 §3).

### Tests
- `MultiTenantIsolationTest`, `Wave18SaturationTest`, `AuditNoteLogsActivityTest`,
  `RevertServiceOtelSpanTest`, `RevertServicePiiRedactionTest`.

# Modules/Auditoria — CHANGELOG

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

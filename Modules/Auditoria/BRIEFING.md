# Auditoria — BRIEFING (estado consolidado)

> **Última atualização:** 2026-05-16 (Wave 18 RETRY)
> **Wave 18 RETRY score (target 97):** D1=30/30 · D2=20/20 · D6=10/10 · D8=8/8 (saturado)

## O que é

Camada de governança transversal — UI rica `/auditoria` + undo sobre `activity_log` existente (Spatie Activitylog). Distingue `User` vs `IA` (causer_kind) e mantém **whitelist UNREVERTIBLE de 5 categorias** (LGPD-sensitive ops). Per ADR 0127.

## Capacidades atuais (Wave 18 RETRY)

- **Listing/Filter activity_log** — `AuditEntryService::list/find/normalizeFilters` com whitelist
- **Revert single entry** — `RevertService` (US-AUDIT-008..010, intocado Wave M)
- **Revert bulk** — `BulkRevertActivityRequest` valida até 50 ids + reason (Wave 18 RETRY)
- **Audit notes** — `AuditNote` entity (auditoria_audit_notes) com `scopeForBusiness`
- **Inertia::defer DEFAULT** — `activities` + `activity` (Wave 18 D6.a) — 1st paint shell rápido
- **OTel spans** — `auditoria.entries.*` + `auditoria.revert.*` via `App\Util\OtelHelper`

## FormRequests (D8 Wave 18 RETRY = 5 saturado)

| FormRequest | Endpoint | Uso |
|---|---|---|
| RevertActivityRequest | POST /auditoria/{id}/revert | Undo single (reason min:10) |
| FilterAuditEntriesRequest | GET /auditoria | Filtros (causer_kind / event whitelist) |
| StoreAuditNoteRequest | POST /auditoria/{id}/notes | Cria nota interna (min:3 max:5000) |
| **UpdateAuditNoteRequest** (W18 RETRY) | PUT /auditoria/notes/{id} | Edita nota recente |
| **BulkRevertActivityRequest** (W18 RETRY) | POST /auditoria/revert-bulk | Undo lote (≤50 ids + reason) |

## Whitelist UNREVERTIBLE (ADR 0127 §3)

5 categorias **NUNCA** podem ser revertidas:
1. NFe emitida e autorizada (CONFAZ SINIEF 07/2005)
2. Marcação de ponto (Portaria MTP 671/2021 — append-only)
3. Mudança de business owner (security boundary)
4. Cancelamento financeiro com refund Asaas/Inter processado
5. Backup ou restore de DB (DDL impacta cross-tenant)

## Anti-patterns proibidos (Tier 0)

- ⛔ `RevertService` **intocado** desde US-AUDIT-008 (compliance crítica — Wave M reforça)
- ⛔ Bulk revert >50 ids (rate limit hard pra evitar undo massivo)
- ⛔ Revert sem `revert_reason` (audit trail dura)
- ⛔ Bypass whitelist UNREVERTIBLE — Service valida por entry

## Tests (Pest Feature)

- `MultiTenantIsolationTest` — biz=1 vs biz=99 real
- `Wave18SaturationTest` — saturação D1 + D2 + D6 + D8 (AuditNote + AuditEntryService + Inertia::defer + 5 FormRequests)
- `AuditNoteLogsActivityTest` — meta-audit (LogsActivity em audit_notes)
- `AuditoriaModuleTest` — module boot
- `RevertServiceOtelSpanTest` — OTel instrumentation
- `RevertServicePiiRedactionTest` — PII redaction no revert
- `SmokeRoutesTest` — rotas registradas

## Referências

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 — Constituição v2
- ADR 0101 — Tests business_id=1
- ADR 0127 — Modules/Auditoria UI + undo (mãe)
- `memory/requisitos/Auditoria/SPEC.md` — US-AUDIT-007..010

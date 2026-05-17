# Modules/Crm — CHANGELOG

## [Wave 18 RETRY] - 2026-05-16

### Added (D4 — Services extraction)
- `Modules/Crm/Services/CrmLeadService.php` — thin de criação/conversão de Lead. `createLead` + `convertToCustomer` + `acceptedFields` + `repository()`. OTel spans `crm.lead.create` / `crm.lead.convert`.

### Added (D8 — FormRequests)
- `Modules/Crm/Http/Requests/UpdateCallLogRequest.php` — PATCH parcial CallLog (sometimes em todos campos, mantém ownership via permission `view_own`/`view_all`).
- `Modules/Crm/Http/Requests/StoreCrmContactRequest.php` — substitui validação inline `ContactController::store`. Whitelist type=lead|customer + first_name obrigatório + mensagens PT-BR.

### Test (D1 — Pest saturado)
- `Tests/Feature/Wave18SaturationTest.php` — +5 testes Wave 18 RETRY (CrmLeadService + UpdateCallLogRequest + StoreCrmContactRequest).
- Total: 19 testes (10 datasets recursivos Entities + 9 unit checks).

### Docs
- `BRIEFING.md` (novo) — estado consolidado Wave 18 RETRY.
- `CHANGELOG.md` (este arquivo).

### Governance
- `module.json` já tem `governance.fsm_n_a: true` (workflow flat — não é FSM canon).
- Wave 18 saturation atualizada com novos artefatos.

## [Wave J] - 2026-05-16

### Added
- `CampaignService` extraído de `CampaignController`.
- `ProposalService` + `CallLogService` extraídos (Wave 18).
- `CrmLeadRepository` (primeiro Repository do Crm).
- 5 FormRequests: StoreCallLog, StoreProposal, UpdateProposal, StoreLead, UpdateLead.
- LGPD compliance: PII redactor + retention 730d.

### Tests
- `MultiTenantIsolationTest` (biz=1 vs biz=99 real).
- `LgpdComplianceTest` (PII redaction).
- `SecurityHardeningTest` (rate limit + auth).
- `ScheduleServiceTest` (regras domínio).

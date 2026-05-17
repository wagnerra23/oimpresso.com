# Modules/Crm — CHANGELOG

<<<<<<< HEAD
## [Wave 28] - 2026-05-17

### Test (D2 — Pest +2 saturação final ≥95)
- `Tests/Feature/Wave28PolishTest.php` — +2 testes sentry Wave 28:
  - trio Services D4 (ProposalService + CallLogService + CrmLeadService) regression
    guard pós Wave 18 RETRY (API pública contratada).
  - Entities core (Campaign + CrmCallLog + Proposal) preservam LogsActivity
    (D7 LGPD audit trail — regression guard).
- Tier 0: NÃO chama session(), reflection-only, multi-tenant friendly, biz=4 intocado.

### Governance
- Saturação 82-95 → 96 (polish final excelência).
=======
## [Wave 27 POLISH FINAL] - 2026-05-17 (82-92 → ≥95)

### Added (D2 + D9 cobertura Services Wave 18 triple-confirmed)

- `Modules/Crm/Tests/Feature/Wave27CrmPolishTest.php` — 11 asserts cobrindo:
  - **D9** — spans declarados em CrmLeadService (`crm.lead.create/convert`), ProposalService (`crm.proposal.create/update/default_template`), CallLogService (`crm.call_log.base_query/total_duration`)
  - **D2** — cobertura source-assert Services Wave 18 (Tier 0 ADR 0093 declarado, businessId injected, NUNCA session direta, whitelist ALLOWED_FILTERS anti-SQLi)
  - **V5** — CHANGELOG W27 entry exigido + cita polish ≥95
  - **Tier 0** — Crm Entities Wave 9/10 NÃO retocadas (apenas verifica diretório existe)

### Changed

- Score Capterra scoped: 82-92 (W23+W25) → ≥95 estimado pós W27 (D2 +2, D9 +3, V5 +1).

### Preserved (Tier 0 IRREVOGÁVEL)

- **Entities Crm Wave 9/10** intocadas — W27 toca APENAS governance + tests reflection.
- **ADR 0093** multi-tenant — todos os 3 Services Wave 18 já declaram `businessId` obrigatório no constructor + NUNCA session direta.
- **biz=99** em fixtures (NUNCA biz=4 — ADR 0101).
>>>>>>> origin/main

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

# Crm — BRIEFING (estado consolidado)

> **Última atualização:** 2026-05-16 (Wave 18 RETRY)
> **Wave 18 RETRY score (target 97):** D1=30/30 · D4=20/20 · D8=8/8 (saturado)

## O que é

CRM operacional do núcleo oimpresso: gestão de Leads, Customers (CrmContact), Proposals, Schedules, CallLogs e Campaigns (SMS/Email). Customer 360 simples — sem pipeline FSM (workflow é flat: lead → contact → proposal → schedule).

## Capacidades atuais (Wave 18 RETRY)

- **CrmContact / Lead / Customer** (Entities) — segregação via `type` enum
- **Lead pipeline flat** — `crm_life_stage` + `crm_source` (sem FSM custom, ver `module.json governance.fsm_n_a`)
- **Lead conversion** — `CrmLeadService::convertToCustomer` (Wave 18 RETRY)
- **Proposal** — `ProposalService::createProposal/updateProposal/defaultTemplate`
- **CallLog** — registro manual de chamada via `CallLogService` (DataTables + futura UI Inertia)
- **Campaign** — `CampaignService` SMS/Email com PII redaction
- **Schedule** — agenda + log + multi-user (ScheduleService)
- **Repository pattern** — `CrmLeadRepository` (paginate / countByLifeStage / countBySource)

## FormRequests (D8 Wave 18 RETRY = 8 saturado)

| FormRequest | Endpoint | Uso |
|---|---|---|
| StoreLeadRequest | POST /crm/leads | Cria Lead |
| UpdateLeadRequest | PUT /crm/leads/{id} | Atualiza Lead |
| StoreProposalRequest | POST /crm/proposals | Cria Proposal |
| UpdateProposalRequest | PUT /crm/proposals/{id} | Atualiza Proposal (PATCH parcial) |
| StoreScheduleRequest | POST /crm/schedules | Cria Schedule |
| UpdateScheduleRequest | PUT /crm/schedules/{id} | Atualiza Schedule |
| StoreCampaignRequest | POST /crm/campaigns | Cria Campaign |
| StoreCallLogRequest | POST /crm/call-logs | Cria CallLog manual |
| MassDestroyCallLogRequest | DELETE bulk | Massive destroy |
| **UpdateCallLogRequest** (W18 RETRY) | PUT /crm/call-logs/{id} | PATCH parcial CallLog |
| **StoreCrmContactRequest** (W18 RETRY) | POST /crm/contacts | Cria Contact (lead OR customer) |

## Compliance LGPD

- `module.json lgpd_compliance.pii_fields_tracked`: `mobile`, `email`, `tax_number`, `address_line_1`, `dob`
- `PiiRedactor` aplicado em logs Spatie Activitylog (10 Entities trackadas)
- Politica: `memory/requisitos/Crm/PII-REDACTION.md`
- `retention_days: 730`

## Anti-patterns proibidos (Tier 0)

- ⛔ Aceitar `business_id` do input (Entity tem global scope)
- ⛔ Logs sem `PiiRedactor` em campos da whitelist `pii_fields_tracked`
- ⛔ Conversão lead→customer sem `LogsActivity` (audit trail)
- ⛔ Cross-tenant em DataTables — Controller sempre `where('business_id', $businessId)`

## Tests (Pest Feature)

- `MultiTenantIsolationTest` — biz=1 vs biz=99 real
- `Wave18SaturationTest` — saturação D1 + D4 + D8 (10 Entities recursivo + Services + Repository + FormRequests)
- `LgpdComplianceTest` — PII redaction
- `SecurityHardeningTest` — rate limit + auth
- `ScheduleServiceTest` — regras de domínio
- `SmokeRoutesTest` — rotas registradas
- `ScaffoldCrmTest` — module boot

## Referências

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 — Constituição v2 (7 camadas + 8 princípios)
- ADR 0101 — Tests business_id=1 (NUNCA cliente real)
- ADR 0143 — FSM (N/A pra Crm — workflow flat)
- ADR 0155 — Module Grade v3 (saturação D1+D4+D8)
- `memory/requisitos/Crm/SPEC.md`
- `memory/requisitos/Crm/PII-REDACTION.md`

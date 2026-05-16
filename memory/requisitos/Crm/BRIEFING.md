# BRIEFING — Modules/Crm

> **Última atualização:** 2026-05-16 (Wave Massive — D2.b + D4.a)
> **Owner:** Wagner | **Status produção:** ✅ usado por biz=4 (ROTA LIVRE — Larissa)
> **Nota Module Grade v1:** 42/100 (Médio) → meta pós-wave 60+/100

## O que é

CRM operacional do oimpresso — gestão de **leads**, **contatos** (PJ/PF), **campanhas** (email/SMS), **propostas comerciais** e **schedules/follow-ups**. Extensão do contact base UltimatePOS (`App\Contact`) via `Modules\Crm\Entities\CrmContact` (table inheritance — mesmo `id`, mesma `business_id`).

## Cliente piloto em produção

**ROTA LIVRE** (`business_id=4` — Larissa, vestuário Termas do Gravatal/SC) usa o CRM ativamente:
- **Contatos** — base de clientes vestuário (mistura PF e PJ)
- **Leads** — captação via `convertToCustomer` quando vira venda
- **Schedules** — follow-ups manuais por Larissa

⚠️ **Tier 0 IRREVOGÁVEL:** biz=4 é cliente real em prod. Tests SEMPRE biz=1 (Wagner WR2) ou biz=99 (fictício) — NUNCA biz=4 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

## Capacidades canônicas

| Capacidade | Controller | Status |
|---|---|---|
| Leads (CRUD + kanban) | `LeadController` | ✅ ativo |
| Contatos PJ/PF | `CrmContact` (extends `App\Contact`) | ✅ ativo |
| Schedules / follow-ups | `ScheduleController` + `ScheduleLogController` | ✅ ativo |
| Campanhas email/SMS | `CampaignController` | 🟡 parcial (depende provider) |
| Propostas comerciais | `ProposalController` + `ProposalTemplateController` | ✅ ativo |
| Booking client-facing | `ContactBookingController` | 🟡 parcial |
| Marketplace import leads | `CrmMarketplaceController` | 🟡 parcial |
| Reports (follow-ups by user/contact) | `ReportController` | ✅ ativo |
| Dashboard CRM | `CrmDashboardController` | ✅ ativo |
| Call logs | `CallLogController` | 🟡 parcial |

## Stack técnica

- **21 Controllers** em `Modules/Crm/Http/Controllers/`
- **11 Entities** em `Modules/Crm/Entities/` (Campaign, CrmCallLog, CrmContact, CrmMarketplace, Leaduser, Proposal, ProposalTemplate, Schedule, ScheduleLog, ScheduleUser, CrmContactPersonCommission)
- **1 Service thin** em `Modules/Crm/Services/` — `LeadAssignmentService` (Wave Massive D4.a)
- **4 tests Pest** em `Tests/Feature/`: InstallControllerTest, MultiTenantIsolationTest, SmokeRoutesTest, ScaffoldCrmTest
- **Routes:** `Modules/Crm/Routes/web.php` (follow-ups, leads, campaigns, proposals resources) + `api.php`
- **Inertia React (Pages/Cliente):** Index, Create, Edit, Show, Ledger, Map, Import — todos com `*.charter.md` ao lado

## Multi-tenant (ADR 0093)

- `crm_schedules` — `business_id` NOT NULL direto + index
- `crm_campaigns` — `business_id` NOT NULL direto + index
- `crm_lead_users` (pivot) — sem `business_id` direto; herda via `contacts.business_id` FK ON DELETE CASCADE
- `crm_contacts` (extends contacts) — `business_id` via tabela base UPOS
- **Isolamento testado** em `MultiTenantIsolationTest.php` (Wave A) — Schedule + Campaign + Leaduser pivot

## Diferenciais (vs Bling/Tiny/Conta Azul)

1. **Lead kanban por life_stage** — categorias customizáveis por business (ADR 0011 padrão Jana)
2. **Convert lead → customer** preservando histórico (`crm_contacts.converted_by`, `converted_on`)
3. **Schedules com `OnlyOwnLeads` scope** — RBAC fino (Spatie `crm.access_all_leads` vs `crm.access_own_leads`)
4. **Pages/Cliente Cockpit V2** (Inertia React 19 + Tailwind 4) — drawer pattern canônico

## Gaps catalogados (Module Grade v1 — antes desta wave)

- **D2 Tests (11/20)** — faltava scaffold canônico → Wave Massive criou `ScaffoldCrmTest.php` (6 cenários)
- **D3 Charter (5/15)** — 7 charters Pages/Cliente já existem (Wave anterior)
- **D4 Architecture (3/20)** — controllers fat → Wave Massive criou `LeadAssignmentService.php` (thin DI no `LeadController`)

## Refs

- [ADR 0011 — padrão Jana](../../decisions/0011-alinhamento-padrao-jana.md)
- [ADR 0024 — InstallController 1-clique](../../decisions/0024-instalacao-1-clique-modulos.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 — Tests biz=1 nunca biz=cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- SPEC: [`SPEC.md`](SPEC.md) · RUNBOOKs cliente: `RUNBOOK-cliente-*.md` (7 telas)

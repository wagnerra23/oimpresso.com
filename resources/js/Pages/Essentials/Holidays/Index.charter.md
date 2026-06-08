---
page: /hrm/holiday
component: resources/js/Pages/Essentials/Holidays/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-17
parent_module: Essentials
related_adrs: [0093, 0094, 0101, 0104]
tier: B
charter_version: 1
---

# Page Charter — /hrm/holiday (Feriados do business)

> Migração Blade T1 Wave D — `Modules/Essentials/Resources/views/holiday/index.blade.php` (DataTable jQuery + Bootstrap modal) → React/Inertia com tabela filtrável + Dialog inline. Visível a todos, editável apenas por admin.

---

## Mission

Cadastro de **feriados do business** opcionalmente escopados por `business_location` (matriz/filial). Importante pra cálculo de RH (folha de pagamento, afastamentos, expediente) e UX (calendário compartilhado). Admin gerencia; todos visualizam.

---

## Goals (faz)

- Tabela ordenada por `start_date` DESC com colunas: Nome, Período (start→end + badge dias), Localidade, Nota, Ações
- Filtros (reativos): localidade (Select), data início, data fim, botão **Limpar filtros**
- Permissão: `can_manage` derivado de `ModuleUtil::is_admin($user, $businessId)` — só admin vê botões Editar/Excluir e botão Novo
- Form Dialog: `name` (req), `start_date` (req), `end_date` (req), `location_id` (opcional — Select com `BusinessLocation::forDropdown`), `note` (Textarea opcional)
- Empty state graceful (ícone `CalendarDays` + mensagem)
- Scope DB enforced: `business_id` + filtra por `auth()->user()->permitted_locations()` (não-admins veem só feriados de suas localidades + globais NULL)

---

## Non-Goals (NÃO faz)

- ❌ NÃO importa feriados nacionais BR automaticamente (out-of-scope T1 — TODO Sprint futuro: ANBIMA API ou hardcoded)
- ❌ NÃO calcula efeito em folha automaticamente (que módulo Payroll faz)
- ❌ NÃO renderiza calendário visual mês inteiro (decisão UX: tabela é melhor pra gestão)
- ❌ NÃO permite recorrência anual ("todo 25 dezembro") — usuário cria entry por ano
- ❌ NÃO bloqueia agendamento de venda/repair no dia (apenas registro informativo)

---

## UX targets

- Filtros reativos via `router.get` com `preserveState + preserveScroll + replace`
- Defer eager-load `holidays` (com `location:id,name`) + `locations` dropdown ([RUNBOOK-inertia-defer-pattern.md](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md))
- `filtros` + `can_manage` props eager (UI state + bool — defer não vale a pena)
- Mobile responsive (max-w-6xl + tabela overflow-x-auto)
- Toast Sonner em CRUD

---

## Backend contract

- `EssentialsHolidayController@index` retorna `{ holidays: Holiday[] (defer), locations: LocationOption[] (defer), filtros: Filters, can_manage: bool }`
- `Holiday` shape: `{ id, name, start_date, end_date, days, location_id, location_name, note }`
- POST `/hrm/holiday` (admin-only — `authorizeAdmin`)
- PUT/DELETE `/hrm/holiday/{id}` (admin-only)
- Multi-tenant Tier 0: `EssentialsHoliday` Entity tem `HasBusinessScope` ([Wave 18 SATURATION](../../../../Modules/Essentials/Tests/Feature/Wave18SaturationTest.php))

---

## Métricas de sucesso

- ✅ Admin biz=1 NÃO enxerga feriado biz=99 (cross-tenant Pest)
- ✅ Não-admin biz=1 NÃO vê botão Novo/Editar/Excluir (`can_manage=false`)
- ✅ Filtros `location_id` + `start_date` + `end_date` filtram via SQL (não no front)
- ✅ Smoke route `/hrm/holiday` retorna 200 autenticado biz=1
- ✅ Charter + RUNBOOK presentes (gate MWART)

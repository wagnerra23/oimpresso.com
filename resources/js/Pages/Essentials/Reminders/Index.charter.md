---
page: /essentials/reminder
component: resources/js/Pages/Essentials/Reminders/Index.tsx
related_us: [US-ESS-008]
owner: wagner
status: live
last_validated: "2026-05-17"
parent_module: Essentials
related_adrs: [93, 94, 101, 104]
tier: B
charter_version: 1
---

# Page Charter — /essentials/reminder (Lembretes pessoais)

> Migração Blade T1 Wave D — `Modules/Essentials/Resources/views/reminder/index.blade.php` (FullCalendar + jQuery + Bootstrap modals) → React/Inertia com listagem cronológica + Dialog inline. Cada usuário só vê os seus lembretes.

---

## Mission

Permitir que cada usuário cadastre **avisos pessoais** (data + hora + repetição) sem poluir agenda de terceiros. Substitui o calendário FullCalendar legado por listagem ordenada cronologicamente — padrão consistente com outras telas migradas (Todo, Holidays).

---

## Goals (faz)

- Lista cronológica (ASC por `date` + `time`) de lembretes do usuário logado
- Quick actions: **Edit** (Dialog inline) / **Delete** (AlertDialog confirmação) / **Novo lembrete**
- Form campos: `name` (obrigatório), `date` (date picker HTML5), `time` (time picker HTML5), `end_time` (opcional), `repeat` (Select: `one_time` / `every_day` / `every_week` / `every_month`)
- Badge visual de repetição em cada linha (ícone `Repeat`)
- Empty state graceful quando lista vazia (ícone `Bell` + mensagem)
- Scope DB enforced no Controller: `business_id` + `user_id` (cada user só vê os seus)

---

## Non-Goals (NÃO faz)

- ❌ NÃO mostra lembretes de outros usuários (privacidade por design — diferente de Todo compartilhado)
- ❌ NÃO renderiza calendário full-month grid (decisão UX: listagem é mais prática diário)
- ❌ NÃO envia notificação por email/whatsapp (Sprint futuro — hoje apenas registro)
- ❌ NÃO sincroniza com Google Calendar / Outlook (out-of-scope T1 migration)
- ❌ NÃO permite anexos / links (form mínimo)
- ❌ NÃO mostra lembretes históricos por padrão (todos ordem ASC; usuário rola se quiser)

---

## UX targets

- Render <300ms (lista local, sem paginate — usuário típico tem <50 lembretes)
- Form Dialog abre instantâneo (state local)
- Validação inline em erros (`form.errors.<field>`)
- Toast Sonner pra feedback success/error (canon)
- Mobile responsive (max-w-4xl + grid 3-col colapsa)

---

## Backend contract

- `ReminderController@index` retorna `{ reminders: Reminder[], repeats: Option[] }`
- `Reminder` shape: `{ id, name, date, time, end_time, repeat }`
- POST `/essentials/reminder` (StoreReminderRequest)
- PUT `/essentials/reminder/{id}` (UpdateReminderRequest)
- DELETE `/essentials/reminder/{id}`
- Multi-tenant Tier 0: `Reminder` Entity tem `HasBusinessScope` ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

---

## Métricas de sucesso

- ✅ User biz=1 NÃO enxerga lembretes user biz=99 (cross-tenant Pest)
- ✅ User A biz=1 NÃO enxerga lembretes user B biz=1 (user_id scope Pest)
- ✅ Form valida `name` required + `date` required + `time` required (StoreReminderRequest)
- ✅ Smoke route `/essentials/reminder` retorna 200 autenticado biz=1
- ✅ Charter + RUNBOOK presentes (gate MWART)

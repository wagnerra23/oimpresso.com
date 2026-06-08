# RUNBOOK — Blade T1 Migration Wave D (Reminders + Holidays + Knowledge)

> **Migração Blade → Inertia/React** das 3 telas index do módulo Essentials que ainda renderizavam via FullCalendar/DataTable/Bootstrap modal jQuery. Coexistência opt-in: rota canônica agora aponta pra Inertia; Blade legado preservado em `Resources/views/` como referência.

**Status:** ✅ live (3 Pages + 3 Charters + 3 Controllers Inertia + Pest smoke)
**Sprint origem:** Wave D Blade T1
**Charter gate:** ADR 0104 (MWART canon — único caminho)
**Multi-tenant Tier 0:** ADR 0093 (HasBusinessScope em Reminder + EssentialsHoliday + KnowledgeBase)
**Tests biz:** ADR 0101 (biz=1 nunca cliente real)

---

## Telas migradas

| Rota | Blade legado | Component Inertia | Controller |
|---|---|---|---|
| `/essentials/reminder` | `reminder/index.blade.php` | `Essentials/Reminders/Index.tsx` | `ReminderController@index` |
| `/hrm/holiday` | `holiday/index.blade.php` | `Essentials/Holidays/Index.tsx` | `EssentialsHolidayController@index` |
| `/essentials/knowledge-base` | `knowledge_base/index.blade.php` | `Essentials/Knowledge/Index.tsx` | `KnowledgeBaseController@index` |

---

## F1 SNAPSHOT (Discovery)

Blade legado dependia de:
- **FullCalendar 3.x + Bootstrap modal jQuery** (Reminders) — UX calendário mensal, eventos clicáveis
- **DataTable jQuery server-side** (Holidays) — paginação remota + filtros via URL params injetados
- **Cards Bootstrap collapse** (Knowledge) — accordion `data-toggle="collapse"`

Decisões UX na migração (documentadas em cada charter):
- Reminders: calendário → **lista cronológica** (mais prática diário)
- Holidays: DataTable server-side → **tabela filtrada client-state** (volume baixo: ~50 entries/ano)
- Knowledge: accordion Bootstrap → **collapse local `useState`** (mesma UX, sem jQuery)

---

## F2 BACKEND BASELINE

Controllers reescritos com:
- `Inertia\Response` return type explícito
- Multi-tenant scope via `business_id` + (quando aplicável) `user_id` / `permitted_locations`
- `Inertia::defer()` em props caras (eager-load + map shape) — [RUNBOOK-inertia-defer-pattern.md](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
- FormRequest validators preservados (`StoreReminderRequest`, `StoreHolidayRequest`, `StoreKnowledgeBaseRequest`)

---

## F3 FRONTEND

Padrão canônico aplicado:
- **AppShellV2** layout via `Component.layout` static
- **Lucide icons** (`Bell`, `CalendarDays`, `BookOpen`)
- **shadcn/ui**: `Card`, `Dialog`, `AlertDialog`, `Select`, `Input`, `Label`, `Badge`, `Button`, `Textarea`
- **sonner toast** pra feedback CRUD
- **Inertia useForm + router** pra mutations (preserveScroll)
- **`@docvault` header** em cada Page (tela / module / status / rules / tests)

Charters criados ao lado de cada Page (`*.charter.md`) — gate MWART satisfeito.

---

## F4 QA Pest

Test smoke: `Modules/Essentials/Tests/Feature/EssentialsBladeT1InertiaSmokeTest.php`

Cobertura (6 cenários):
1. Rota `/essentials/reminder` retorna 200 autenticado biz=1
2. Rota `/hrm/holiday` retorna 200 autenticado biz=1
3. Rota `/essentials/knowledge-base` retorna 200 autenticado biz=1
4. ReminderController.index escreve `Essentials/Reminders/Index` (component path)
5. EssentialsHolidayController.index escreve `Essentials/Holidays/Index` + `can_manage` boolean
6. KnowledgeBaseController.index escreve `Essentials/Knowledge/Index` + cross-tenant scope (biz=1 vs biz=99 reflection)

Skip SQLite condicional (compatível com `Modules/Essentials/Tests/Feature/SmokeRoutesEssentialsTest.php` pattern).

---

## F5 — Coexistência (NÃO cutover)

- Blade legado preservado em `Resources/views/{reminder,holiday,knowledge_base}/` como referência histórica
- Rota canônica resolve Controller → Inertia
- Sem alias /legacy ou flag de fallback — coexistência conceitual via charter `status: live`
- Cutover real do Blade-files-delete fica pra futuro (auditoria de rotas legadas batch)

---

## Tier 0 Checklist

- [x] Charter + RUNBOOK criados ANTES de Edit/Write em `.tsx` (gate MWART)
- [x] Multi-tenant `business_id` scope explícito nos 3 Controllers
- [x] Pest smoke biz=1 (nunca biz=4 ROTA LIVRE)
- [x] PT-BR em comentários + docs + UI strings
- [x] `@docvault` header em cada Page
- [x] Sem `withoutGlobalScopes` (não há SUPERADMIN caso aqui)
- [x] PII real NÃO presente em commit / log

---

## Referências

- ADR 0093 — multi-tenant isolation Tier 0 IRREVOGÁVEL
- ADR 0101 — tests biz=1 nunca cliente real
- ADR 0104 — processo MWART canônico único caminho
- [Wave 18 SATURATION test](../../../Modules/Essentials/Tests/Feature/Wave18SaturationTest.php) — multi-tenant `EssentialsHoliday`
- [HasBusinessScopeAdoptionTest](../../../Modules/Essentials/Tests/Feature/HasBusinessScopeAdoptionTest.php) — Reminder + KnowledgeBase
- [RUNBOOK-inertia-defer-pattern.md](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 21 anti-padrões evitados
